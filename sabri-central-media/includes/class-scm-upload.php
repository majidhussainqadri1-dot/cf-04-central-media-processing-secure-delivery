<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class Idempotency {
    private static function identity(string $scope,string $key): array {
        $scope=Utils::key($scope,48);$key=Utils::text($key,128);$actor=Auth::currentUser();
        if($scope===''||$key===''||$actor<1)throw new Error('idempotency_invalid','Idempotency identity invalid.',400);
        return [$scope,$key,$actor,hash('sha256',$scope.'|'.$actor.'|'.$key)];
    }
    public static function claim(string $scope,string $key,string $fingerprint,int $ttl=86400): array {
        if(!preg_match('/^[a-f0-9]{64}$/',$fingerprint)||$ttl<1||$ttl>604800)throw new Error('idempotency_invalid','Idempotency claim invalid.',400);
        [$scope,$key,$actor,$id]=self::identity($scope,$key);$record=RecordStore::get('idempotency',$id);
        if($record){
            if((int)($record['actor_id']??0)!==$actor||!hash_equals((string)$record['fingerprint'],$fingerprint))throw new Error('idempotency_conflict','Idempotency key reused with different request.',409);
            if(($record['status']??'')==='completed')return ['replay'=>true,'record'=>$record];
            if(($record['status']??'')==='claimed'&&(int)($record['expires_at']??0)>Utils::now())throw new Error('idempotency_in_progress','Equivalent request is already in progress.',409);
        }
        $row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'claim_token'=>Utils::id('idem'),'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];
        return ['replay'=>false,'record'=>RecordStore::put('idempotency',$id,$row,$record?(int)$record['version']:0)];
    }
    public static function complete(string $scope,string $key,string $fingerprint,string $claimToken,string $resultType,string $resultId,array $result=[]): array {
        [,,$actor,$id]=self::identity($scope,$key);$record=RecordStore::get('idempotency',$id);
        if(!$record||(int)($record['actor_id']??0)!==$actor||($record['status']??'')!=='claimed'||!hash_equals((string)$record['fingerprint'],$fingerprint)||!hash_equals((string)($record['claim_token']??''),$claimToken))throw new Error('idempotency_claim_missing','Current idempotency claim identity is missing or stale.',409);
        $record['status']='completed';$record['result_type']=Utils::key($resultType,48);$record['result_id']=Utils::text($resultId,96);$record['result']=Utils::redact($result);$record['completed_at']=Utils::now();
        return RecordStore::put('idempotency',$id,$record,(int)$record['version']);
    }
    public static function fail(string $scope,string $key,string $fingerprint,string $claimToken,string $code): void {
        try{[,,$actor,$id]=self::identity($scope,$key);}catch(Error){return;}$r=RecordStore::get('idempotency',$id);
        if(!$r||(int)($r['actor_id']??0)!==$actor||($r['status']??'')!=='claimed'||!hash_equals((string)$r['fingerprint'],$fingerprint)||!hash_equals((string)($r['claim_token']??''),$claimToken))return;
        $r['status']='failed';$r['failure_code']=Utils::key($code,64);$r['failed_at']=Utils::now();RecordStore::put('idempotency',$id,$r,(int)$r['version']);
    }
}

final class QuotaService {
    public static function reserve(string $domain,int $actor,int $bytes,int $jobs,array $limits,array $context=[]): array {
        Auth::assertActor($actor);if($actor<1||$bytes<0||$jobs<0)throw new Error('quota_request_invalid','Quota request invalid.',400);
        $domain=Utils::key($domain,64);if($domain==='')throw new Error('quota_request_invalid','Quota domain invalid.',400);
        $window=(int)floor(Utils::now()/86400);$id=hash('sha256',$domain.'|'.$actor.'|'.$window);
        $storageLimit=Utils::integer($limits['storage_bytes']??1073741824,'quota_limit_invalid',1);
        $dailyLimit=Utils::integer($limits['daily_bytes']??1073741824,'quota_limit_invalid',1);
        $jobLimit=Utils::integer($limits['jobs']??100,'quota_limit_invalid',1);
        $burst=Utils::integer($limits['burst_bytes']??67108864,'quota_limit_invalid',1);
        $abuseBlock=Utils::integer($limits['abuse_block_score']??100,'quota_limit_invalid',1);
        if(array_key_exists('governed_exception_authorized',$context)
            &&!is_bool($context['governed_exception_authorized']))
            throw new Error('quota_exception_invalid','Governed burst exception must be an explicit boolean.',403);
        if($bytes>$burst&&($context['governed_exception_authorized']??false)!==true)throw new Error('quota_burst_exceeded','Upload burst exceeds policy.',429);
        for($attempt=0;$attempt<5;$attempt++){
            $r=RecordStore::get('quota',$id)??['actor_id'=>$actor,'domain'=>$domain,'window'=>$window,'status'=>'active','reserved_bytes'=>0,'used_bytes'=>0,'reserved_jobs'=>0,'used_jobs'=>0,'abuse_score'=>0,'reservations'=>[]];
            if((int)$r['reserved_bytes']+(int)$r['used_bytes']+$bytes>$dailyLimit||(int)$r['used_bytes']+$bytes>$storageLimit||(int)$r['reserved_jobs']+(int)$r['used_jobs']+$jobs>$jobLimit)throw new Error('quota_exceeded','Quota exhausted.',429,['domain'=>$domain]);
            if((int)($r['abuse_score']??0)>=$abuseBlock)throw new Error('abuse_policy_block','Abuse policy blocked reservation.',403);
            $reservation=Utils::id('qres');$r['reserved_bytes']+=$bytes;$r['reserved_jobs']+=$jobs;$r['reservations'][$reservation]=['bytes'=>$bytes,'jobs'=>$jobs,'status'=>'reserved','created_at'=>Utils::now(),'context'=>Utils::redact($context)];
            try{$r=RecordStore::put('quota',$id,$r,(int)($r['version']??0));return ['quota_id'=>$id,'reservation_id'=>$reservation,'version'=>$r['version']];}
            catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}
        }
        throw new Error('quota_contention','Quota state changed too frequently to reserve safely.',409,['domain'=>$domain]);
    }
    public static function settle(string $quotaId,string $reservationId,bool $commit,int $actualBytes=0,int $actualJobs=0): array {
        if($actualBytes<0||$actualJobs<0)throw new Error('quota_settlement_invalid','Quota settlement values are invalid.',409);
        for($attempt=0;$attempt<5;$attempt++){
            $r=RecordStore::get('quota',$quotaId);if(!$r||!isset($r['reservations'][$reservationId]))throw new Error('quota_reservation_missing','Quota reservation missing.',409);
            $res=$r['reservations'][$reservationId];if(($res['status']??'')!=='reserved')return $r;
            if($actualBytes>(int)$res['bytes']||$actualJobs>(int)$res['jobs'])throw new Error('quota_settlement_invalid','Quota settlement exceeds its reservation.',409);
            $r['reserved_bytes']=max(0,(int)$r['reserved_bytes']-(int)$res['bytes']);$r['reserved_jobs']=max(0,(int)$r['reserved_jobs']-(int)$res['jobs']);
            if($commit){$r['used_bytes']+=$actualBytes;$r['used_jobs']+=$actualJobs;$r['reservations'][$reservationId]['status']='committed';}else{$r['reservations'][$reservationId]['status']='released';}
            $r['reservations'][$reservationId]['settled_at']=Utils::now();
            try{return RecordStore::put('quota',$quotaId,$r,(int)$r['version']);}
            catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}
        }
        throw new Error('quota_contention','Quota settlement could not converge safely.',409);
    }
    public static function scoreAbuse(string $domain,int $actor,int $score,string $reason): void {
        Auth::assertActor($actor,'manage_options');$domain=Utils::key($domain,64);$id=hash('sha256',$domain.'|'.$actor.'|'.(int)floor(Utils::now()/86400));
        for($attempt=0;$attempt<5;$attempt++){
            $r=RecordStore::get('quota',$id)??['actor_id'=>$actor,'domain'=>$domain,'window'=>(int)floor(Utils::now()/86400),'status'=>'active','reserved_bytes'=>0,'used_bytes'=>0,'reserved_jobs'=>0,'used_jobs'=>0,'abuse_score'=>0,'reservations'=>[]];
            $r['abuse_score']=max(0,min(100000,(int)$r['abuse_score']+$score));$r['abuse_reasons'][]=['reason'=>Utils::key($reason,64),'score'=>$score,'at'=>Utils::now()];
            try{RecordStore::put('quota',$id,$r,(int)($r['version']??0));return;}catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}
        }
        throw new Error('quota_contention','Abuse-score update could not converge safely.',409);
    }
}

final class RateLimiter {
    public static function hit(string $bucket,string $subject,int $limit,int $windowSeconds): void {
        $bucket=Utils::key($bucket,48);$subject=Utils::text($subject,128);if($bucket===''||$subject===''||$limit<1||$limit>100000||$windowSeconds<1||$windowSeconds>86400)throw new Error('rate_limit_invalid','Rate-limit policy invalid.',500);
        $id=hash('sha256',$bucket.'|'.$subject.'|'.(int)floor(Utils::now()/$windowSeconds));
        for($attempt=0;$attempt<5;$attempt++){
            $r=RecordStore::get('rate',$id)??['actor_id'=>Auth::currentUser(),'bucket'=>$bucket,'count'=>0,'status'=>'active','expires_at'=>Utils::now()+$windowSeconds];
            if((int)$r['count']>=$limit)throw new Error('rate_limited','Rate limit exceeded.',429);$r['count']++;
            try{RecordStore::put('rate',$id,$r,(int)($r['version']??0));return;}catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}
        }
        throw new Error('rate_limited','Rate-limit state is under contention; request denied safely.',429);
    }
}

final class PartStore {
    public static function put(string $uploadId,int $part,$stream,string $expectedHash): array {
        if($part<1||!is_resource($stream)||!preg_match('/^[a-f0-9]{64}$/',$expectedHash))throw new Error('upload_part_invalid','Upload part invalid.',400);
        $hash=Utils::streamHash($stream);if(!hash_equals($expectedHash,$hash['sha256']))throw new Error('part_checksum_mismatch','Part checksum mismatch.',422);
        $id=hash('sha256',$uploadId.'|'.$part);$existing=RecordStore::get('upload_part',$id);
        if($existing&&hash_equals((string)$existing['sha256'],$expectedHash))return $existing;if($existing)throw new Error('part_conflict','Part number already contains different bytes.',409);
        $key=hash('sha256','part|'.$uploadId.'|'.$part.'|'.$expectedHash);$store=ProviderRegistry::store();$stored=$store->putStream($key,$stream,['scope'=>'upload-part','upload_id'=>$uploadId,'part'=>$part]);
        try{return RecordStore::put('upload_part',$id,['actor_id'=>Auth::currentUser(),'upload_id'=>$uploadId,'part_number'=>$part,'provider_id'=>ProviderRegistry::activeId(),'object_key'=>$stored['object_key'],'size'=>$hash['size'],'sha256'=>$expectedHash,'status'=>'stored','created_at'=>Utils::now()],0);}
        catch(\Throwable $e){$authoritative=RecordStore::get('upload_part',$id);if($authoritative&&hash_equals((string)($authoritative['sha256']??''),$expectedHash)&&($authoritative['object_key']??'')===($stored['object_key']??''))return $authoritative;if(!$authoritative||($authoritative['object_key']??'')!==($stored['object_key']??''))$store->delete((string)$stored['object_key']);throw $e;}
    }
    /** Retrieve only hashes belonging to this upload, using the existing primary-key index. */
    public static function list(string $uploadId): array {
        if($uploadId===''||strlen($uploadId)>96||Utils::text($uploadId,96)!==$uploadId)
            throw new Error('upload_part_identity_invalid','Upload identity is noncanonical.',500);
        $upload=RecordStore::get('upload',$uploadId);
        if($upload===null)throw new Error('upload_not_found','Upload session not found for part enumeration.',404);
        // Existing policy bounds this session to <=20,000 possible part ordinals.
        // Legacy synthetic/persisted sessions without a policy are checked within
        // the maximum allowed policy envelope, never against the global part table.
        $rawMax=$upload['policy']['max_upload_parts']??null;
        $maximum=$rawMax===null?20000:Utils::integer($rawMax,'upload_part_policy_invalid',1,20000);
        $rows=[];
        for($first=1;$first<=$maximum;$first+=250){
            $keys=[];$end=min($maximum,$first+249);
            for($number=$first;$number<=$end;$number++)$keys[$number]=hash('sha256',$uploadId.'|'.$number);
            $found=RecordStore::getMany('upload_part',array_values($keys));
            foreach($keys as $number=>$key){
                if(!isset($found[$key]))continue;
                $row=$found[$key];
                if(($row['record_type']??null)!=='upload_part'
                    ||($row['id']??null)!==$key
                    ||($row['upload_id']??null)!==$uploadId
                    ||($row['part_number']??null)!==$number
                    ||!is_int($row['version']??null)||$row['version']<1)
                    throw new Error('upload_part_identity_invalid','Stored upload part does not match its scoped primary key.',500);
                $rows[]=$row;
            }
        }
        return $rows;
    }
    public static function assemble(string $uploadId,int $expectedSize,string $expectedHash,int $maxParts){
        $parts=self::list($uploadId);if($parts===[]||count($parts)>$maxParts)throw new Error('upload_parts_incomplete','Upload parts incomplete.',409);$out=Utils::tempStream();$ctx=hash_init('sha256');$size=0;$expectedPart=1;
        try{foreach($parts as $part){if((int)$part['part_number']!==$expectedPart++)throw new Error('upload_part_gap','Upload part sequence has a gap.',409);$provider=ProviderRegistry::get((string)($part['provider_id']??ProviderRegistry::activeId()));$s=$provider->openStream((string)$part['object_key']);try{while(!feof($s)){$chunk=fread($s,1048576);if($chunk===false)throw new Error('stream_read_failed','Part read failed.',500);if($chunk===''&&!feof($s))throw new Error('stream_stalled','Upload part source stalled before EOF.',500);if($chunk==='')continue;$size+=strlen($chunk);if($size>$expectedSize)throw new Error('upload_size_exceeded','Assembled upload exceeds expected size.',422);hash_update($ctx,$chunk);Utils::writeAll($out,$chunk);}}finally{fclose($s);}}
            $hash=hash_final($ctx);if($size!==$expectedSize||!hash_equals($expectedHash,$hash))throw new Error('upload_integrity_failed','Assembled upload integrity failed.',422,['size'=>$size]);rewind($out);return $out;
        }catch(\Throwable $e){fclose($out);throw $e;}
    }
    public static function purge(string $uploadId): void {foreach(self::list($uploadId) as $p){$provider=ProviderRegistry::get((string)($p['provider_id']??ProviderRegistry::activeId()));$key=(string)$p['object_key'];if(!$provider->delete($key)&&$provider->exists($key))throw new Error('part_delete_failed','Upload part could not be deleted.',500,['part_id'=>$p['id']]);RecordStore::delete('upload_part',(string)$p['id'],(int)$p['version']);}}
}

final class UploadService {
    private const DEFAULT_LIMITS=['storage_bytes'=>1073741824,'daily_bytes'=>2147483648,'jobs'=>100,'burst_bytes'=>1073741824,'abuse_block_score'=>100];
    private static function effectiveLimits(array $requested): array {
        $out=self::DEFAULT_LIMITS;
        foreach($out as $key=>$maximum){
            if(array_key_exists($key,$requested))
                $out[$key]=Utils::integer($requested[$key],'quota_limit_invalid',1,$maximum);
        }
        return $out;
    }
    private static function sessionTtl(array $metadata): int {
        return Utils::integer($metadata['session_ttl_seconds']??3600,'upload_session_ttl_invalid',300,86400);
    }
    private static function issueCredential(array $upload,int $ttl): array {$uploadId=Utils::text((string)($upload['id']??$upload['upload_id']??''),96);if($uploadId==='')throw new Error('upload_identity_invalid','Upload identity unavailable.',500);if($ttl<300||$ttl>86400)throw new Error('upload_session_ttl_invalid','Upload credential TTL outside policy.',400);$credential=Crypto::sign(['type'=>'upload-credential','upload_id'=>$uploadId,'actor_id'=>(int)$upload['actor_id'],'policy_hash'=>$upload['policy_hash'],'object_version'=>(int)$upload['object_version']],$ttl);$upload['credential_hash']=hash('sha256',$credential);$upload['expires_at']=Utils::now()+$ttl;return [$upload,$credential];}
    public static function create(int $actor,array $metadata,array $rawPolicy,string $idempotencyKey,array $quotaLimits=[]): array {
        RuntimeGuard::requireReady(['streaming']);Auth::assertActor($actor);Auth::verifiedUser($actor,'media_upload_create',(string)($rawPolicy['owner_domain']??''));RateLimiter::hit('upload-create',(string)$actor,60,60);
        $policy=Policy::normalize($rawPolicy,true);self::validateMetadata($metadata,$policy);$ttl=self::sessionTtl($metadata);$ownerType=self::ownerType((string)$metadata['owner_object']);$fingerprint=hash('sha256',Utils::canonicalJson(['actor'=>$actor,'metadata'=>Utils::redact($metadata),'policy_hash'=>$policy['policy_hash']]));$claim=Idempotency::claim('upload-create',$idempotencyKey,$fingerprint);
        if($claim['replay']){
            $row=RecordStore::get('upload',(string)$claim['record']['result_id'])
                ??throw new Error('upload_replay_missing','Replayed upload missing.',500);
            if(!in_array(($row['status']??''),['uploading','paused'],true))
                throw new Error('upload_replay_state_invalid','Replayed upload is no longer resumable.',409);
            if((int)($row['actor_id']??0)!==$actor
                ||($row['owner_domain']??'')!==$policy['owner_domain']
                ||($row['owner_object']??'')!==$metadata['owner_object']
                ||($row['policy_hash']??'')!==$policy['policy_hash']
                ||(int)($row['expected_size']??0)!==Utils::integer($metadata['size'],'upload_size_invalid',1,$policy['max_size_bytes']))
                throw new Error('upload_replay_binding_invalid','Replayed upload identity or policy no longer matches its caller.',409);
            // The idempotency result does not replace live File 17/domain permission.
            self::reauthorizeActiveUpload($row,$actor,'replay');
            [$row,$credential]=self::issueCredential($row,$ttl);
            $row=RecordStore::put('upload',(string)$row['id'],$row,(int)$row['version']);
            return $row+['upload_credential'=>$credential];
        }
        $ownerContext=['actor_id'=>$actor,'owner_object'=>Utils::text((string)($metadata['owner_object']??''),191),'owner_type'=>$ownerType,'policy'=>$policy,'metadata'=>Utils::redact($metadata)];
        try{
            $decision=DomainRegistry::decision($policy['owner_domain'],'authorize_upload',$ownerContext);
            if(array_key_exists('governed_exception_authorized',$decision)
                &&!is_bool($decision['governed_exception_authorized']))
                throw new Error('quota_exception_invalid','Owner burst exception must be an explicit boolean.',403);
            if(array_key_exists('quota_limits',$decision)&&!is_array($decision['quota_limits']))
                throw new Error('quota_limit_invalid','Owner quota limits must be a structured map.',400);
            $decisionLimits=(array)($decision['quota_limits']??[]);
            $effectiveQuota=self::effectiveLimits(array_replace($quotaLimits,$decisionLimits));
            $quota=QuotaService::reserve($policy['owner_domain'],$actor,(int)$metadata['size'],1,$effectiveQuota,[
                'purpose'=>$policy['purpose'],
                'governed_exception_authorized'=>$decision['governed_exception_authorized']??false
            ]);
        }catch(\Throwable $e){
            Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected');
            throw $e;
        }
        $id=Utils::id('upl');$row=['actor_id'=>$actor,'upload_id'=>$id,'owner_domain'=>$policy['owner_domain'],'owner_object'=>Utils::text((string)$metadata['owner_object'],191),'owner_type'=>$ownerType,'object_version'=>(int)$decision['object_version'],'declared_name'=>Utils::filename((string)$metadata['name']),'declared_mime'=>strtolower((string)$metadata['mime']),'expected_size'=>(int)$metadata['size'],'expected_sha256'=>strtolower((string)$metadata['sha256']),'media_class'=>$policy['media_class'],'policy'=>$policy,'policy_hash'=>$policy['policy_hash'],'quota'=>$quota,'received_size'=>0,'received_parts'=>0,'status'=>'uploading','created_at'=>Utils::now()];[$row,$credential]=self::issueCredential($row,$ttl);
        try{$row=RecordStore::put('upload',$id,$row);Audit::record('upload_session_created',['upload_id'=>$id,'actor_id'=>$actor,'owner_domain'=>$policy['owner_domain'],'expected_size'=>$metadata['size']]);Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'upload',$id);return $row+['upload_credential'=>$credential];}
        catch(\Throwable $e){QuotaService::settle($quota['quota_id'],$quota['reservation_id'],false);Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected');throw $e;}
    }
    private static function ownerType(string $reference): string {$reference=Utils::text($reference,191);$raw=str_contains($reference,':')?explode(':',$reference,2)[0]:'object';$type=Utils::key($raw,64);if($type==='')throw new Error('owner_type_invalid','Typed owner reference is required.',400);return $type;}
    private static function validateMetadata(array $m,array $p): void {
        Utils::requireFields($m,['name','mime','size','sha256','owner_object'],'upload_metadata_incomplete');
        $size=Utils::integer($m['size'],'upload_size_invalid',1,$p['max_size_bytes']);
        if(!is_string($m['sha256'])||!preg_match('/^[a-f0-9]{64}$/i',$m['sha256']))
            throw new Error('upload_hash_invalid','Expected SHA-256 invalid.',400);
        if(!is_string($m['mime'])||!in_array(strtolower($m['mime']),$p['allowed_mime_types'],true))
            throw new Error('declared_mime_denied','Declared MIME not allowed.',415);
        $ext=strtolower(pathinfo(Utils::filename((string)$m['name']),PATHINFO_EXTENSION));
        if($p['allowed_extensions']!==[]&&!in_array($ext,$p['allowed_extensions'],true))
            throw new Error('extension_denied','File extension denied.',415);
        $object=$m['owner_object'];
        if(!is_string($object)||$object===''||strlen($object)>191
            ||Utils::text($object,191)!==$object)
            throw new Error('owner_object_invalid','Canonical owner object reference is required.',400);
    }
    private static function authenticate(array $upload,int $actor,string $credential): void {Auth::assertActor($actor);if((int)$upload['actor_id']!==$actor)throw new Error('upload_actor_denied','Upload actor mismatch.',403);$claims=Crypto::verify($credential);foreach(['type'=>'upload-credential','upload_id'=>$upload['id'],'actor_id'=>$actor,'policy_hash'=>$upload['policy_hash'],'object_version'=>(int)$upload['object_version']] as $k=>$v)if(($claims[$k]??null)!==$v)throw new Error('upload_credential_invalid','Upload credential invalid.',403,['field'=>$k]);if(!hash_equals((string)$upload['credential_hash'],hash('sha256',$credential)))throw new Error('upload_credential_invalid','Upload credential record mismatch.',403);if((int)$upload['expires_at']<=Utils::now())throw new Error('upload_expired','Upload session expired.',410);}
    private static function reauthorizeActiveUpload(array $upload,int $actor,string $phase): void {
        Auth::verifiedUser($actor,'media_upload_'.$phase,(string)$upload['owner_domain'],['upload_id'=>(string)$upload['id'],'owner_object'=>(string)$upload['owner_object']]);
        $decision=DomainRegistry::decision((string)$upload['owner_domain'],'authorize_upload',['actor_id'=>$actor,'owner_object'=>$upload['owner_object'],'owner_type'=>$upload['owner_type'],'object_version'=>(int)$upload['object_version'],'policy'=>$upload['policy'],'metadata'=>['name'=>$upload['declared_name'],'mime'=>$upload['declared_mime'],'size'=>$upload['expected_size'],'sha256'=>$upload['expected_sha256'],'owner_object'=>$upload['owner_object']],'phase'=>$phase,'upload_id'=>(string)$upload['id']]);
        if((int)$decision['object_version']!==(int)$upload['object_version'])throw new Error('domain_object_version_stale','Upload authorization is stale.',409,['phase'=>$phase]);
    }
    public static function putPart(string $uploadId,int $actor,int $part,$stream,string $sha256,string $credential): array {RuntimeGuard::requireReady(['streaming']);$upload=RecordStore::get('upload',$uploadId);if(!$upload)throw new Error('upload_not_found','Upload session not found.',404);self::authenticate($upload,$actor,$credential);if(($upload['status']??'')!=='uploading')throw new Error('upload_state_invalid','Upload is not accepting parts.',409);self::reauthorizeActiveUpload($upload,$actor,'part');if($part<1||$part>(int)$upload['policy']['max_upload_parts'])throw new Error('part_number_invalid','Part number outside policy.',400);try{$hash=Utils::streamHash($stream,(int)$upload['policy']['max_part_size_bytes']);}catch(Error $streamError){if($streamError->errorCode==='stream_size_exceeded')throw new Error('part_size_exceeded','Part exceeds policy.',413);throw $streamError;}if($hash['size']<1)throw new Error('part_size_exceeded','Part exceeds policy.',413);$existingPart=null;foreach(PartStore::list($uploadId) as $candidate)if((int)($candidate['part_number']??0)===$part){$existingPart=$candidate;break;}$projected=(int)$upload['received_size']-(int)($existingPart['size']??0)+(int)$hash['size'];if($projected>(int)$upload['expected_size'])throw new Error('upload_size_exceeded','Uploaded parts exceed expected size.',422);$stored=PartStore::put($uploadId,$part,$stream,strtolower($sha256));$parts=PartStore::list($uploadId);$upload['received_size']=array_sum(array_map(fn($p)=>(int)$p['size'],$parts));$upload['received_parts']=count($parts);$upload['progress_percent']=(float)min(100.0,round(100*$upload['received_size']/max(1,(int)$upload['expected_size']),2));$upload=RecordStore::put('upload',$uploadId,$upload,(int)$upload['version']);return ['upload_id'=>$uploadId,'part'=>$stored,'received_size'=>$upload['received_size'],'progress_percent'=>$upload['progress_percent']];}
    public static function pause(string $uploadId,int $actor,string $credential): array {RuntimeGuard::requireReady(['streaming']);$u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);if(($u['status']??'')!=='uploading')throw new Error('upload_state_invalid','Upload cannot be paused.',409);$u['status']='paused';return RecordStore::put('upload',$uploadId,$u,(int)$u['version']);}
    public static function resume(string $uploadId,int $actor,string $credential): array {RuntimeGuard::requireReady(['streaming']);$u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);if(($u['status']??'')!=='paused')throw new Error('upload_state_invalid','Upload cannot be resumed.',409);self::reauthorizeActiveUpload($u,$actor,'resume');$u['status']='uploading';return RecordStore::put('upload',$uploadId,$u,(int)$u['version']);}
    public static function abort(string $uploadId,int $actor,string $credential,string $reason): array {
        RuntimeGuard::requireReady(['streaming']);$u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);
        $state=(string)($u['status']??'');if($state==='completed')return $u;if($state==='aborted')return $u;
        if(!in_array($state,['uploading','paused','aborting'],true))throw new Error('upload_state_invalid','Upload cannot be aborted from its current state.',409);
        if($state!=='aborting'){$u['status']='aborting';$u['abort_reason']=Utils::key($reason,64);$u['abort_started_at']=$u['abort_started_at']??Utils::now();$u=RecordStore::put('upload',$uploadId,$u,(int)$u['version']);}
        PartStore::purge($uploadId);
        if(isset($u['quota']['quota_id'],$u['quota']['reservation_id']))QuotaService::settle((string)$u['quota']['quota_id'],(string)$u['quota']['reservation_id'],false);
        $fresh=RecordStore::get('upload',$uploadId)??$u;$fresh['status']='aborted';$fresh['abort_reason']=Utils::key($reason,64);$fresh['aborted_at']=$fresh['aborted_at']??Utils::now();unset($fresh['abort_started_at']);
        $fresh=RecordStore::put('upload',$uploadId,$fresh,(int)$fresh['version']);Audit::record('upload_aborted',['upload_id'=>$uploadId,'actor_id'=>$actor,'reason'=>$reason]);return $fresh;
    }
    public static function complete(string $uploadId,int $actor,string $credential,string $idempotencyKey): array {
        RuntimeGuard::requireReady(['streaming']);$u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);if(!in_array(($u['status']??''),['uploading','finalizing','completed'],true))throw new Error('upload_state_invalid','Upload cannot be completed.',409);if(($u['status']??'')!=='completed')self::reauthorizeActiveUpload($u,$actor,'complete');$fingerprint=hash('sha256',$uploadId.'|'.$u['expected_sha256'].'|'.$u['expected_size']);$claim=Idempotency::claim('upload-complete',$idempotencyKey,$fingerprint);if($claim['replay'])return RecordStore::get('asset',(string)$claim['record']['result_id'])??throw new Error('asset_replay_missing','Completed asset missing.',500);
        $existingAsset=RecordStore::get('asset',$uploadId);
        if($existingAsset){
            if(($existingAsset['source_upload_id']??'')!==$uploadId||(int)($existingAsset['actor_id']??0)!==$actor||!hash_equals((string)($existingAsset['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($existingAsset['policy_hash']??''),(string)$u['policy_hash'])){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'asset_reconciliation_mismatch');throw new Error('asset_reconciliation_mismatch','Existing asset cannot be reconciled to this upload.',409);}
            try{return self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token']);}
            catch(\Throwable $e){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected');throw $e;}
        }
        $stream=PartStore::assemble($uploadId,(int)$u['expected_size'],(string)$u['expected_sha256'],(int)$u['policy']['max_upload_parts']);$stored=null;$assetCreated=false;
        try{$inspection=Validator::inspectStream($stream,(string)$u['declared_name'],(string)$u['declared_mime'],(array)$u['policy']);rewind($stream);$duplicate=self::dedupe($u);$objectKey=hash('sha256','source|'.$uploadId.'|'.$u['expected_sha256'].'|'.$u['policy_hash'].'|'.$u['owner_domain'].'|'.$u['owner_object'].'|'.$u['object_version'].'|'.$u['policy']['rights']['policy_hash']);$stored=ProviderRegistry::store()->putStream($objectKey,$stream,['scope'=>'quarantine','privacy_class'=>$u['policy']['privacy_class'],'owner_domain'=>$u['owner_domain'],'policy_hash'=>$u['policy_hash']]);$stored['provider_id']=ProviderRegistry::activeId();
            $asset=['actor_id'=>$actor,'asset_id'=>$uploadId,'source_upload_id'=>$uploadId,'duplicate_of'=>$duplicate['id']??null,'owner_domain'=>$u['owner_domain'],'owner_object'=>$u['owner_object'],'owner_type'=>$u['owner_type'],'object_version'=>$u['object_version'],'policy'=>$u['policy'],'policy_hash'=>$u['policy_hash'],'rights'=>$u['policy']['rights'],'privacy_class'=>$u['policy']['privacy_class'],'media_class'=>$u['media_class'],'declared_name'=>$u['declared_name'],'mime'=>$inspection['mime'],'size'=>$inspection['size'],'sha256'=>$inspection['sha256'],'fingerprint'=>$inspection['fingerprint'],'storage'=>$stored,'object_key'=>$stored['object_key'],'status'=>'quarantined','scan_status'=>'pending','processing_status'=>'pending','manifest_version'=>0,'created_at'=>Utils::now()];try{$asset=RecordStore::put('asset',$uploadId,$asset,0);$assetCreated=true;}catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$concurrent=RecordStore::get('asset',$uploadId);if(!$concurrent||($concurrent['source_upload_id']??'')!==$uploadId||(int)($concurrent['actor_id']??0)!==$actor||!hash_equals((string)($concurrent['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($concurrent['policy_hash']??''),(string)$u['policy_hash']))throw new Error('asset_reconciliation_mismatch','Concurrent asset cannot be reconciled to this upload.',409);$asset=$concurrent;$assetCreated=true;}return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token']);
        }catch(\Throwable $e){if(!$assetCreated&&is_array($stored)&&isset($stored['object_key']))ProviderRegistry::store()->delete((string)$stored['object_key']);Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected');throw $e;}finally{fclose($stream);}
    }


    private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint,string $claimToken): array {
        $uploadId=(string)$upload['id'];
        if(!in_array(($upload['status']??''),['uploading','finalizing','completed'],true))throw new Error('upload_finalization_state_invalid','Upload cannot enter completion reconciliation.',409);
        if(($upload['status']??'')!=='completed'){
            $upload['status']='finalizing';$upload['finalizing_at']=$upload['finalizing_at']??Utils::now();
            $upload=RecordStore::put('upload',$uploadId,$upload,(int)$upload['version']);
        }
        if(isset($upload['quota']['quota_id'],$upload['quota']['reservation_id']))QuotaService::settle((string)$upload['quota']['quota_id'],(string)$upload['quota']['reservation_id'],true,(int)$upload['expected_size'],1);
        PartStore::purge($uploadId);
        if(($upload['status']??'')!=='completed'){
            $upload['status']='completed';$upload['completed_at']=$upload['completed_at']??Utils::now();unset($upload['finalizing_at']);
            $upload=RecordStore::put('upload',$uploadId,$upload,(int)$upload['version']);
        }
        Audit::record('asset_quarantined',['asset_id'=>$uploadId,'actor_id'=>(int)$upload['actor_id'],'privacy_class'=>$asset['privacy_class'],'sha256'=>$asset['sha256'],'duplicate_of'=>$asset['duplicate_of']??null]);
        Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,$claimToken,'asset',$uploadId);
        self::emit('scm.asset.quarantined',$asset);
        return $asset;
    }

    public static function cleanupExpired(int $now=0,int $limit=500): array {
        $now=$now>0?$now:Utils::now();$limit=max(1,min(2000,$limit));
        $result=['expired'=>0,'parts_purged'=>0,'failed'=>0,'scanned'=>0];
        $cursor=RecordStore::get('cron_cursor','upload-expiry');
        if($cursor!==null){
            $last=$cursor['last_id']??null;
            if(($cursor['record_type']??null)!=='cron_cursor'
                ||($cursor['id']??null)!=='upload-expiry'
                ||($cursor['actor_id']??null)!==0
                ||($cursor['status']??null)!=='active'
                ||!is_int($cursor['version']??null)||$cursor['version']<1
                ||!is_string($last)||strlen($last)>96
                ||Utils::text($last,96)!==$last)
                throw new Error('upload_expiry_cursor_invalid','Stored upload expiry cursor is invalid.',500);
        }
        $after=$cursor['last_id']??'';
        // Bound each maintenance invocation and persist progress, including when
        // most records are still active. Never materialize the whole upload table.
        $scanBudget=min(20000,max(1000,$limit*4));
        while($result['scanned']<$scanBudget&&$result['expired']+$result['failed']<$limit){
            $page=RecordStore::scan('upload',min(250,$scanBudget-$result['scanned']),$after);
            if($page===[]){$after='';break;}
            foreach($page as $snapshot){
                $after=(string)$snapshot['id'];$result['scanned']++;

            $state=(string)($snapshot['status']??'');if(!in_array($state,['uploading','paused','expiring'],true))continue;if($state!=='expiring'&&(int)($snapshot['expires_at']??0)>$now)continue;
            try{
                $upload=RecordStore::get('upload',(string)$snapshot['id'])??$snapshot;$state=(string)($upload['status']??'');
                if(!in_array($state,['uploading','paused','expiring'],true))continue;
                if($state!=='expiring'){
                    if((int)($upload['expires_at']??0)>$now)continue;
                    $upload['status']='expiring';$upload['expiry_started_at']=$upload['expiry_started_at']??$now;$upload['credential_hash']='';
                    try{$upload=RecordStore::put('upload',(string)$upload['id'],$upload,(int)$upload['version']);}
                    catch(Error $race){if($race->errorCode==='record_version_conflict')continue;throw $race;}
                }
                $parts=PartStore::list((string)$upload['id']);PartStore::purge((string)$upload['id']);
                if(isset($upload['quota']['quota_id'],$upload['quota']['reservation_id']))QuotaService::settle((string)$upload['quota']['quota_id'],(string)$upload['quota']['reservation_id'],false);
                $fresh=RecordStore::get('upload',(string)$upload['id'])??$upload;if(($fresh['status']??'')!=='expiring')continue;
                $fresh['status']='expired';$fresh['expired_at']=$now;unset($fresh['expiry_started_at']);$fresh=RecordStore::put('upload',(string)$fresh['id'],$fresh,(int)$fresh['version']);
                $result['expired']++;$result['parts_purged']+=count($parts);
            }catch(\Throwable $exception){$result['failed']++;try{Observability::alert('warning','upload_cleanup_failed',['upload_id'=>$snapshot['id']??'','exception'=>get_class($exception)]);}catch(\Throwable){}}
                if($result['expired']+$result['failed']>=$limit)break;
            }
        }
        if($result['scanned']>0||$cursor!==null){
            RecordStore::put('cron_cursor','upload-expiry',[
                'actor_id'=>0,'status'=>'active','last_id'=>$after,
                'last_scanned'=>$result['scanned'],'last_completed'=>$result['expired'],
                'updated_at'=>Utils::now(),
            ],$cursor?(int)$cursor['version']:0);
        }
        return $result;
    }

    private static function dedupe(array $upload): ?array {foreach(RecordStore::all('asset',0,null,100000) as $asset){if(($asset['sha256']??'')===$upload['expected_sha256']&&($asset['policy_hash']??'')===$upload['policy_hash']&&($asset['privacy_class']??'')===$upload['policy']['privacy_class']&&($asset['rights']['policy_hash']??'')===$upload['policy']['rights']['policy_hash']&&($asset['owner_domain']??'')===$upload['owner_domain']&&($asset['owner_object']??'')===$upload['owner_object']&&in_array(($asset['status']??''),['quarantined','ready'],true))return $asset;}return null;}
    private static function emit(string $event,array $payload): void {if(function_exists('do_action'))do_action($event,Utils::redact($payload));}
}
