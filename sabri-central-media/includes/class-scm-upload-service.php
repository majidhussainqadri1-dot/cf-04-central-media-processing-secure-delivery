<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class UploadService {
    private const DEFAULT_MEMORY_ASSEMBLY_LIMIT=67108864;
    public static function create(int $actor,array $meta,array $policy,string $idempotencyKey): array {
        Utils::requireRuntime();
        if($actor<1) throw new Error('upload_login_required','Authenticated actor required.',401);
        $policy=Policy::validate($policy,true);
        $meta=Validator::uploadMetadata($meta,$policy);
        $fingerprint=hash('sha256',Utils::canonicalJson([$actor,$meta,Policy::fingerprint($policy)]));
        $claim=Idempotency::claim('upload-create',$idempotencyKey,$fingerprint);
        if($claim['replay']) return self::replay((array)$claim['record'],'upload');
        try {
            RateLimiter::consume('upload:'.$actor,30,60);
            $decision=Auth::domainDecision($policy['domain'],'authorize_upload',['actor_id'=>$actor,'metadata'=>$meta,'policy'=>$policy,'operation'=>'create']);
            $id=Utils::id('upl');
            $session=['id'=>$id,'actor_id'=>$actor,'actor'=>$actor,'meta'=>$meta,'policy'=>$policy,'policy_hash'=>Policy::fingerprint($policy),'owner_object_version'=>(int)$decision['object_version'],'parts'=>[],'received'=>0,'status'=>'uploading','expires_at'=>Utils::now()+3600];
            $session=RecordStore::put('upload',$id,$session);
            Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,'upload',$id);
            Audit::record('upload_created',['upload_id'=>$id,'actor_id'=>$actor,'domain'=>$policy['domain']]);
            return self::public($session);
        } catch(Error $error){ Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,$error->errorCode); throw $error; }
        catch(\Throwable $error){ Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,'unexpected'); throw new Error('upload_create_failed','Upload session could not be created.',500); }
    }
    public static function putPart(string $id,int $actor,int $number,string $bytes,string $sha256): array {
        Utils::requireRuntime();
        $session=self::session($id);
        self::assertActor($session,$actor);
        if($session['status']!=='uploading'||(int)$session['expires_at']<Utils::now()) throw new Error('upload_not_active','Upload is not active.',409);
        if($number<1||$number>(int)$session['policy']['max_upload_parts']) throw new Error('upload_part_invalid','Invalid upload part number.',400);
        $length=strlen($bytes);
        if($length<1 || $length>(int)$session['policy']['max_part_size_bytes']) throw new Error('upload_part_size_invalid','Upload part violates the policy size ceiling.',413);
        $sha256=strtolower(trim($sha256));
        if(preg_match('/^[a-f0-9]{64}$/',$sha256)!==1 || !hash_equals($sha256,hash('sha256',$bytes))) throw new Error('upload_part_checksum_invalid','Part checksum mismatch.',422);
        $old=$session['parts'][$number]??null;
        if($old && !hash_equals((string)$old['sha256'],$sha256)) throw new Error('upload_part_overlap','Part number already contains different bytes.',409);
        if(!$old){
            $session['parts'][$number]=PartStore::put($id,$number,$bytes,$sha256);
            $session['received']=(int)$session['received']+$length;
        }
        if((int)$session['received']>(int)$session['meta']['size']) throw new Error('upload_size_overflow','Received bytes exceed declared size.',413);
        $session=RecordStore::put('upload',$id,$session,(int)$session['version']);
        Audit::record('upload_part_received',['upload_id'=>$id,'actor_id'=>$actor,'part'=>$number,'size'=>$length]);
        return ['upload_id'=>$id,'part'=>$number,'received'=>(int)$session['received']];
    }
    public static function complete(string $id,int $actor,string $idempotencyKey): array {
        Utils::requireRuntime();
        $session=self::session($id);
        self::assertActor($session,$actor);
        $fingerprint=hash('sha256',Utils::canonicalJson([$id,$actor,(string)$session['policy_hash'],(int)$session['version']]));
        $claim=Idempotency::claim('upload-complete',$idempotencyKey,$fingerprint);
        if($claim['replay']) return self::replay((array)$claim['record'],'upload');
        try {
            if(in_array($session['status'],['quarantined','ready'],true)){
                Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'upload',$id);
                return self::public($session);
            }
            if($session['status']!=='uploading'||(int)$session['expires_at']<Utils::now()) throw new Error('upload_not_active','Upload is not active.',409);
            if((int)$session['received']!==(int)$session['meta']['size']) throw new Error('upload_incomplete','Uploaded byte count is incomplete.',409);
            ksort($session['parts'],SORT_NUMERIC);
            $expectedPart=1;
            foreach($session['parts'] as $partNumber=>$part){
                if((int)$partNumber!==$expectedPart++) throw new Error('upload_parts_noncontiguous','Upload parts are not contiguous.',409);
                if((int)($part['size']??0)<1 || (int)$part['size']>(int)$session['policy']['max_part_size_bytes']) throw new Error('upload_part_manifest_invalid','Upload part manifest is invalid.',500);
            }
            $limit=defined('SCM_MAX_IN_MEMORY_ASSEMBLY_BYTES')?(int)SCM_MAX_IN_MEMORY_ASSEMBLY_BYTES:self::DEFAULT_MEMORY_ASSEMBLY_LIMIT;
            $limit=max(1048576,min(self::DEFAULT_MEMORY_ASSEMBLY_LIMIT,$limit));
            if((int)$session['meta']['size']>$limit) throw new Error('streaming_assembly_provider_required','This object requires an approved streaming assembly and scanning provider.',503,['declared_size'=>(int)$session['meta']['size'],'safe_local_limit'=>$limit]);
            $bytes='';
            foreach($session['parts'] as $part) $bytes.=PartStore::get($part);
            if(strlen($bytes)!==(int)$session['meta']['size']) throw new Error('upload_incomplete','Uploaded byte count is incomplete.',409);
            $contentHash=hash('sha256',$bytes);
            if($session['meta']['sha256']!==''&&!hash_equals((string)$session['meta']['sha256'],$contentHash)) throw new Error('upload_checksum_invalid','Completed upload checksum mismatch.',422);
            $temp=self::temp($bytes);
            if(!Validator::magicMatches($temp,(string)$session['meta']['mime'])) throw new Error('upload_magic_mismatch','File signature does not match MIME type.',415);
            $decision=Auth::domainDecision($session['policy']['domain'],'authorize_upload',['actor_id'=>$actor,'metadata'=>$session['meta'],'policy'=>$session['policy'],'operation'=>'complete','object_version'=>$session['owner_object_version']]);
            if((int)$decision['object_version']!==(int)$session['owner_object_version']) throw new Error('domain_object_version_stale','Owning domain object changed during upload.',409);
            $objectKey=hash('sha256',$session['policy']['domain'].'|'.$session['policy']['purpose'].'|'.$contentHash);
            $store=ProviderRegistry::store();
            $stored=$store->put($objectKey,$bytes,['upload_id'=>$id,'privacy_class'=>$session['policy']['privacy_class'],'content_sha256'=>$contentHash]);
            if(!hash_equals($contentHash,(string)($stored['sha256']??'')) || (int)($stored['size']??-1)!==strlen($bytes)) { $store->delete($objectKey); throw new Error('object_store_receipt_invalid','Storage provider returned an invalid integrity receipt.',500); }
            $asset=['actor_id'=>$actor,'status'=>'quarantined','asset_id'=>$id,'owner_domain'=>$session['policy']['domain'],'owner_object'=>(string)($session['policy']['owner_object']??$id),'object_version'=>$session['owner_object_version'],'object_key'=>$objectKey,'content_sha256'=>$contentHash,'size'=>strlen($bytes),'privacy_class'=>$session['policy']['privacy_class'],'mime'=>$session['meta']['mime'],'required_scans'=>$session['policy']['required_scans'],'scan_status'=>'pending','created_at'=>Utils::now()];
            try {
                $asset=RecordStore::put('asset',$id,$asset);
                $session['status']='quarantined';
                $session['object']=['sha256'=>$contentHash,'size'=>strlen($bytes)];
                $session['scan_status']='pending';
                $session['parts']=[];
                $session=RecordStore::put('upload',$id,$session,(int)$session['version']);
            } catch(Error $error){ $store->delete($objectKey); RecordStore::delete('asset',$id); throw $error; }
            $cleanupPending=[];
            foreach($sessionParts=self::sessionPartsBeforeCleanup($id) as $part){ try{PartStore::delete($part);}catch(Error $error){$cleanupPending[]=(string)($part['object_key']??'');} }
            if($cleanupPending){ $session['cleanup_pending_hashes']=array_map([Utils::class,'hashReference'],$cleanupPending);$session=RecordStore::put('upload',$id,$session,(int)$session['version']);Audit::record('upload_part_cleanup_deferred',['upload_id'=>$id,'count'=>count($cleanupPending)]); }
            Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'upload',$id);
            Audit::record('upload_quarantined',['upload_id'=>$id,'actor_id'=>$actor,'asset_id'=>$asset['asset_id'],'content_sha256'=>$contentHash]);
            return self::public($session);
        } catch(Error $error){ Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$error->errorCode); throw $error; }
        catch(\Throwable $error){ Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'unexpected'); throw new Error('upload_completion_failed','Upload completion failed.',500); }
    }
    private static array $cleanupParts=[];
    private static function sessionPartsBeforeCleanup(string $id): array { $parts=self::$cleanupParts[$id]??[];unset(self::$cleanupParts[$id]);return $parts; }
    private static function temp(string $bytes): string { $path=tempnam(sys_get_temp_dir(),'scm');if($path===false||file_put_contents($path,$bytes,LOCK_EX)===false)throw new Error('upload_temp_failed','Upload validation file could not be created.',500);register_shutdown_function(static fn()=>@unlink($path));return $path; }
    private static function session(string $id): array { $session=RecordStore::get('upload',$id);if(!$session)throw new Error('upload_not_found','Upload not found.',404);return $session; }
    private static function assertActor(array $session,int $actor): void { if($actor<1||(int)$session['actor']!==$actor)throw new Error('upload_actor_denied','Upload actor mismatch.',403); }
    private static function replay(array $claim,string $expectedType): array { if(($claim['result_type']??'')!==$expectedType||empty($claim['result_id']))throw new Error('idempotency_result_invalid','Stored idempotency result is invalid.',500);$row=RecordStore::get($expectedType,(string)$claim['result_id']);if(!$row)throw new Error('idempotency_result_missing','Stored idempotency result is unavailable.',409);return self::public($row); }
    private static function public(array $session): array { unset($session['parts'],$session['cleanup_pending_hashes']);return Utils::redact($session); }
}
