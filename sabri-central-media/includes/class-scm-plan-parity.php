<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * Source-level controls added for the current rewritten Central Master Plan
 * and CF-04 plan. These controls do not authorize staging or production use.
 */
final class PlanParityRegistry {
    public const DATA_CLASSES=['C0','C1','C2','C3','C4','C5'];
    public static function manifest(): array {
        return [
            'plan_generation'=>'2026-09-current',
            'data_classes'=>self::DATA_CLASSES,
            'requirements'=>[
                'CF04-CEN-01'=>'typed owner/data class/rights/purpose/retention/revocation policy before processing',
                'CF04-CEN-02'=>'fail-closed quarantine, scan and MIME/content verification',
                'CF04-CEN-03'=>'idempotent versioned checksum-linked derivatives with stale-state blocking',
                'CF04-CEN-04'=>'audience/object/purpose/expiry-scoped grants and privacy/rights-aware CDN keys',
                'CF04-CEN-05'=>'C0-only public CDN/index; C1-C5 require non-public owner-authorized delivery',
                'CF04-CEN-06'=>'caption/transcript/alt-text provenance, human correction and domain review',
                'CF04-CEN-07'=>'low-bandwidth/audio/text alternatives, resumable transfer and explicit degraded state',
                'CF04-CEN-08'=>'rights/delete/correction propagation to grants, derivatives, CDN and downstream projections',
                'CF04-CEN-09'=>'provider failure is explicit degraded state; no false success or silent substitution',
                'CF04-CEN-10'=>'privacy-minimal operational media metrics only',
            ],
            'native_journeys'=>['CF04-NJ-01','CF04-NJ-02','CF04-NJ-03','CF04-NJ-04','CF04-NJ-05','CF04-NJ-06'],
            'runtime_default'=>'disabled',
            'external_acceptance'=>'pending',
        ];
    }
}

final class AccessibilityMetadataService {
    private const KINDS=['caption','transcript','alt_text'];
    private const SOURCES=['human','machine','provider'];
    private const HIGH_RISK=['safety','legal','medical'];

    public static function record(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady(['streaming']);
        Auth::assertActor($actor,'media_reprocess');
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset||in_array(($asset['status']??''),['deleted','rejected'],true))throw new Error('asset_not_available','Asset unavailable for accessibility metadata.',404);
        Utils::requireFields($input,['kind','locale','source_type','source_version','content_hash','risk_class','human_reviewed'],'accessibility_metadata_incomplete');
        $kind=Utils::key((string)$input['kind'],32);
        $locale=Utils::text((string)$input['locale'],32);
        $source=Utils::key((string)$input['source_type'],32);
        $sourceVersion=Utils::text((string)$input['source_version'],64);
        $risk=Utils::key((string)$input['risk_class'],32);
        $hash=strtolower(Utils::text((string)$input['content_hash'],64));
        $contentRef=Utils::text((string)($input['content_ref']??''),191);
        $human=Utils::bool($input['human_reviewed']);
        $reviewer=(int)($input['reviewer_id']??0);
        if(!in_array($kind,self::KINDS,true)||$locale===''||!in_array($source,self::SOURCES,true)||$sourceVersion===''||!preg_match('/^[a-f0-9]{64}$/',$hash))throw new Error('accessibility_metadata_invalid','Accessibility metadata provenance is invalid.',400);
        if(in_array($risk,self::HIGH_RISK,true)&&(!$human||$reviewer<1))throw new Error('qualified_review_required','High-risk accessibility metadata requires recorded human review.',403);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_accessibility_metadata',[
            'asset'=>$asset,'actor_id'=>$actor,'kind'=>$kind,'locale'=>$locale,'source_type'=>$source,
            'source_version'=>$sourceVersion,'risk_class'=>$risk,'human_reviewed'=>$human,'reviewer_id'=>$reviewer,
            'content_hash'=>$hash,'content_ref_hash'=>$contentRef===''?'':Utils::hashReference($contentRef),
        ]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Accessibility approval is stale.',409);
        $id=hash('sha256',$assetId.'|'.$kind.'|'.strtolower($locale));
        $existing=RecordStore::get('accessibility_metadata',$id);
        $row=[
            'actor_id'=>$actor,'accessibility_id'=>$id,'asset_id'=>$assetId,'kind'=>$kind,'locale'=>$locale,
            'source_type'=>$source,'source_version'=>$sourceVersion,'content_hash'=>$hash,
            'content_ref_hash'=>$contentRef===''?'':Utils::hashReference($contentRef),'risk_class'=>$risk,
            'human_reviewed'=>$human,'reviewer_id'=>$reviewer,'owner_object_version'=>(int)$asset['object_version'],
            'policy_hash'=>(string)$asset['policy_hash'],'rights_hash'=>(string)$asset['rights']['policy_hash'],
            'accessibility_version'=>$existing?((int)($existing['accessibility_version']??0)+1):1,
            'status'=>'approved','created_at'=>$existing?($existing['created_at']??Utils::now()):Utils::now(),'updated_at'=>Utils::now(),
        ];
        $row=RecordStore::put('accessibility_metadata',$id,$row,$existing?(int)$existing['version']:0);
        Audit::record('accessibility_metadata_updated',['asset_id'=>$assetId,'kind'=>$kind,'locale'=>$locale,'risk_class'=>$risk,'human_reviewed'=>$human,'reviewer_id'=>$reviewer]);
        if(function_exists('do_action'))do_action('scm.media.accessibility.updated',Utils::redact($row));
        return $row;
    }

    public static function forAsset(string $assetId): array {
        return array_values(array_filter(RecordStore::all('accessibility_metadata',0,null,100000),fn($row)=>($row['asset_id']??'')===$assetId));
    }
}

final class RenditionSelector {
    private const MODES=['default','low_bandwidth','audio_only','text_first'];
    public static function select(string $assetId,string $mode='default'): array {
        $mode=Utils::key($mode,32);
        if(!in_array($mode,self::MODES,true))throw new Error('rendition_mode_invalid','Unsupported rendition mode.',400);
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        if(($asset['status']??'')!=='ready'||empty($asset['active_manifest_id']))return ['status'=>'degraded','reason'=>'asset_not_ready','mode'=>$mode,'asset_id'=>$assetId,'rendition'=>null];
        $manifest=RecordStore::get('manifest',(string)$asset['active_manifest_id']);
        if(!$manifest||($manifest['status']??'')!=='active'||(int)($manifest['processing_generation']??0)!==(int)($asset['processing_generation']??0))return ['status'=>'degraded','reason'=>'active_manifest_unavailable','mode'=>$mode,'asset_id'=>$assetId,'rendition'=>null];
        $preferences=match($mode){
            'low_bandwidth'=>['video-low','audio-low','audio-aac','thumbnail','text'],
            'audio_only'=>['audio-low','audio-aac','audio-opus','audio-mp3'],
            'text_first'=>['transcript-ref','text','caption-ref','ocr'],
            default=>['hls-manifest','dash-manifest','video-h264','audio-aac','preview','thumbnail','text'],
        };
        $items=(array)($manifest['derivatives']??[]);
        foreach($preferences as $kind){
            foreach($items as $item){
                if(($item['kind']??'')!==$kind)continue;
                $derivative=RecordStore::get('derivative',(string)($item['derivative_id']??''));
                if(!$derivative||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))continue;
                return ['status'=>'ready','reason'=>'preferred_rendition_available','mode'=>$mode,'asset_id'=>$assetId,'rendition'=>[
                    'derivative_id'=>$derivative['id'],'kind'=>$derivative['kind'],'mime'=>$derivative['mime'],'size'=>$derivative['size'],'sha256'=>$derivative['sha256']
                ]];
            }
        }
        return ['status'=>'degraded','reason'=>'preferred_rendition_unavailable','mode'=>$mode,'asset_id'=>$assetId,'rendition'=>null];
    }
}

final class DegradedStateService {
    public static function record(string $component,string $code,array $context=[]): array {
        $component=Utils::key($component,64);$code=Utils::key($code,96);
        if($component===''||$code==='')throw new Error('degraded_state_invalid','Component and degraded-state code are required.',400);
        $id=hash('sha256',$component.'|'.$code);
        $existing=RecordStore::get('degraded_state',$id);
        $row=['actor_id'=>0,'component'=>$component,'code'=>$code,'context'=>Utils::redact($context),'status'=>'degraded','started_at'=>$existing?($existing['started_at']??Utils::now()):Utils::now(),'updated_at'=>Utils::now()];
        $row=RecordStore::put('degraded_state',$id,$row,$existing?(int)$existing['version']:0);
        Observability::alert('warning','provider_or_dependency_degraded',['component'=>$component,'code'=>$code]);
        if(function_exists('do_action'))do_action('scm.provider.degraded',$row);
        return $row;
    }
    public static function clear(string $component,string $code): array {
        $id=hash('sha256',Utils::key($component,64).'|'.Utils::key($code,96));$row=RecordStore::get('degraded_state',$id);
        if(!$row)throw new Error('degraded_state_not_found','Degraded state not found.',404);
        $row['status']='recovered';$row['recovered_at']=Utils::now();$row['updated_at']=Utils::now();
        return RecordStore::put('degraded_state',$id,$row,(int)$row['version']);
    }
}

final class PrivacyTelemetry {
    private const ALLOWED_LABELS=['domain','media_class','privacy_class','provider','status','reason','operation','queue','result'];
    private const FORBIDDEN=['user_id','actor_id','asset_id','owner_object','filename','email','phone','ip','query','message','clinical','content'];
    public static function metric(string $name,float $value,array $labels=[]): array {
        foreach($labels as $key=>$unused){
            $key=Utils::key((string)$key,48);
            if($key===''||in_array($key,self::FORBIDDEN,true)||!in_array($key,self::ALLOWED_LABELS,true))throw new Error('privacy_metric_label_denied','Media telemetry label is not privacy-minimal.',400,['label'=>$key]);
        }
        return Observability::metric($name,$value,$labels);
    }
}


final class RevocationDispatchService {
    private static function identity(array $asset,string $reason,?string $projectionId): array {
        $assetId=(string)($asset['id']??'');
        $domain=(string)($asset['owner_domain']??'');
        $object=(string)($asset['owner_object']??'');
        $version=$asset['object_version']??null;
        if($assetId===''||$domain===''||$object===''||!is_int($version)||$version<1
            ||$reason===''||($projectionId!==null&&!preg_match('/^[a-f0-9]{64}$/D',$projectionId)))
            throw new Error('revocation_identity_invalid','Revocation dispatch identity is invalid.',500);
        $fields=[
            'asset_id'=>$assetId,'owner_domain'=>$domain,
            'owner_object_hash'=>Utils::hashReference($object),'object_version'=>$version,
            'reason'=>$reason,'rights_fingerprint'=>hash('sha256',Utils::canonicalJson((array)($asset['rights']??[]))),
            'projection_revocation_id'=>$projectionId??'',
        ];
        $digest=hash('sha256',Utils::canonicalJson($fields));
        return $fields+['id'=>hash('sha256','nonterminal-revocation|'.$digest),'event_id'=>'scm-revoked-'.substr($digest,0,48)];
    }
    private static function dispatch(array $row): array {
        $id=$row['id']??null;
        $fields=[];
        foreach(['asset_id','owner_domain','owner_object_hash','object_version','reason','rights_fingerprint','projection_revocation_id'] as $key){
            if(!array_key_exists($key,$row))throw new Error('revocation_dispatch_invalid','Revocation dispatch field is missing.',500);
            $fields[$key]=$row[$key];
        }
        $digest=hash('sha256',Utils::canonicalJson($fields));
        if(($row['record_type']??null)!=='revocation_dispatch'
            ||!is_string($id)||$id!==hash('sha256','nonterminal-revocation|'.$digest)
            ||($row['event_id']??null)!=='scm-revoked-'.substr($digest,0,48)
            ||($row['actor_id']??null)!==0
            ||!is_int($row['version']??null)||$row['version']<1
            ||!is_int($row['created_at']??null)||$row['created_at']<1
            ||!is_int($row['object_version'])||$row['object_version']<1
            ||!is_string($row['reason'])||$row['reason']===''||Utils::key($row['reason'],64)!==$row['reason']
            ||!is_string($row['owner_domain'])||$row['owner_domain']===''
            ||!is_string($row['asset_id'])||$row['asset_id']===''
            ||!is_string($row['owner_object_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$row['owner_object_hash'])
            ||!is_string($row['rights_fingerprint'])||!preg_match('/^[a-f0-9]{64}$/D',$row['rights_fingerprint'])
            ||!is_string($row['projection_revocation_id'])
            ||($row['projection_revocation_id']!==''&&!preg_match('/^[a-f0-9]{64}$/D',$row['projection_revocation_id']))
            ||(array_key_exists('policy_hash',$row)
                &&(!is_string($row['policy_hash'])||!preg_match('/^[a-f0-9]{64}$/D',$row['policy_hash'])))
            ||!in_array($row['status']??null,['pending','dispatched','superseded'],true)
            ||($row['status']==='pending'&&(array_key_exists('dispatched_at',$row)
                ||array_key_exists('superseded_at',$row)||array_key_exists('superseded_reason',$row)))
            ||($row['status']==='dispatched'&&(!is_int($row['dispatched_at']??null)
                ||$row['dispatched_at']<1||array_key_exists('superseded_at',$row)
                ||array_key_exists('superseded_reason',$row)))
            ||($row['status']==='superseded'&&(array_key_exists('dispatched_at',$row)
                ||!is_int($row['superseded_at']??null)||$row['superseded_at']<1
                ||!in_array($row['superseded_reason']??null,
                    ['asset_missing','owner_changed','rights_changed','asset_terminal','policy_changed','policy_unbound'],true)))
            ||(isset($row['supersession_history'])&&(!is_array($row['supersession_history'])
                ||!array_is_list($row['supersession_history'])||count($row['supersession_history'])>32)))
            throw new Error('revocation_dispatch_invalid','Stored revocation dispatch evidence is invalid.',500);
        if($row['projection_revocation_id']!==''){
            $projection=RecordStore::get('projection_revocation',$row['projection_revocation_id']);
            $expectedProjectionId=hash('sha256',Utils::canonicalJson([
                'asset_id'=>$row['asset_id'],'reason'=>$row['reason'],
                'rights_fingerprint'=>$row['rights_fingerprint'],
                'object_version'=>$row['object_version'],
            ]));
            if($row['projection_revocation_id']!==$expectedProjectionId
                ||!is_array($projection)
                ||($projection['record_type']??null)!=='projection_revocation'
                ||($projection['id']??null)!==$expectedProjectionId
                ||($projection['actor_id']??null)!==0
                ||!is_int($projection['version']??null)||$projection['version']<1
                ||!is_int($projection['created_at']??null)||$projection['created_at']<1
                ||!is_int($projection['effective_at']??null)||$projection['effective_at']<1
                ||($projection['status']??null)!=='propagation_pending_consumers'
                ||($projection['asset_id']??null)!==$row['asset_id']
                ||($projection['asset_ref_hash']??null)!==Utils::hashReference($row['asset_id'])
                ||($projection['reason']??null)!==$row['reason']
                ||($projection['object_version']??null)!==$row['object_version']
                ||($projection['owner_domain']??null)!==$row['owner_domain']
                ||($projection['owner_object_hash']??null)!==$row['owner_object_hash']
                ||($projection['rights_fingerprint']??null)!==$row['rights_fingerprint']
                ||($projection['cdn_status']??null)!=='purged_or_not_published'
                ||($projection['derivative_status']??null)!=='revoked'
                ||($projection['index_status']??null)!=='pending_owner_consumer'
                ||($projection['backup_status']??null)!=='retention_policy_applies')
                throw new Error('revocation_projection_invalid','Revocation projection evidence is invalid.',500);
        }
        // A completed local hook is historical evidence, not a live-state assertion.
        // Superseded records remain durable and are never retried by the cron scanner.
        if($row['status']==='dispatched'||$row['status']==='superseded')return $row;
        $asset=RecordStore::get('asset',$row['asset_id']);
        $stale=null;
        if(!is_array($asset))$stale='asset_missing';
        elseif(($asset['owner_domain']??null)!==$row['owner_domain']
            ||($asset['object_version']??null)!==$row['object_version']
            ||!hash_equals($row['owner_object_hash'],Utils::hashReference((string)($asset['owner_object']??''))))
            $stale='owner_changed';
        elseif(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))
            $stale='asset_terminal';
        elseif(!hash_equals($row['rights_fingerprint'],
            hash('sha256',Utils::canonicalJson((array)($asset['rights']??[])))))
            $stale='rights_changed';
        elseif(!array_key_exists('policy_hash',$row))$stale='policy_unbound';
        elseif(!hash_equals($row['policy_hash'],(string)($asset['policy_hash']??'')))
            $stale='policy_changed';
        if($stale!==null){
            $row['status']='superseded';$row['superseded_at']=Utils::now();
            $row['superseded_reason']=$stale;
            $history=$row['supersession_history']??[];
            $history[]=['at'=>$row['superseded_at'],'reason'=>$stale,'prior_version'=>(int)$row['version']];
            $row['supersession_history']=array_slice($history,-32);
            $row['supersession_count']=(int)($row['supersession_count']??0)+1;
            return RecordStore::put('revocation_dispatch',$id,$row,(int)$row['version']);
        }
        if(!function_exists('do_action'))
            throw new Error('revocation_dispatch_unavailable','Owner revocation hook is unavailable.',503);
        $event=['event_id'=>$row['event_id'],'asset_id'=>$row['asset_id'],
            'owner_domain'=>$row['owner_domain'],'owner_object'=>$asset['owner_object'],
            'object_version'=>$row['object_version'],'reason'=>$row['reason']];
        if($row['projection_revocation_id']!=='')$event['projection_revocation_id']=$row['projection_revocation_id'];
        do_action('scm.media.revoked',$event);
        $row['status']='dispatched';$row['dispatched_at']=Utils::now();
        return RecordStore::put('revocation_dispatch',$id,$row,(int)$row['version']);
    }
    public static function notify(array $asset,string $reason,?string $projectionId=null): array {
        $reason=Utils::key($reason,64);
        $fields=self::identity($asset,$reason,$projectionId);
        $id=$fields['id'];$row=RecordStore::get('revocation_dispatch',$id);
        if($row===null){
            try{$row=RecordStore::put('revocation_dispatch',$id,$fields+[
                'actor_id'=>0,'status'=>'pending','created_at'=>Utils::now(),
                'policy_hash'=>(string)($asset['policy_hash']??''),
            ],0);}
            catch(Error $e){
                if($e->errorCode!=='record_version_conflict')throw $e;
                $row=RecordStore::get('revocation_dispatch',$id);
                if($row===null)throw $e;
            }
        }
        if(($row['status']??null)==='superseded'){
            // Validate stored evidence before any reactivation. The caller's
            // snapshot must still agree with the current authoritative asset.
            self::dispatch($row);
            $live=RecordStore::get('asset',$fields['asset_id']);
            if(is_array($live)
                &&($live['owner_domain']??null)===$fields['owner_domain']
                &&($live['object_version']??null)===$fields['object_version']
                &&hash_equals($fields['owner_object_hash'],
                    Utils::hashReference((string)($live['owner_object']??'')))
                &&hash_equals($fields['rights_fingerprint'],
                    hash('sha256',Utils::canonicalJson((array)($live['rights']??[]))))
                &&!in_array(($live['status']??''),['deleted','deletion_pending','rejected'],true)
                &&($asset['policy_hash']??null)===($live['policy_hash']??null)
                &&is_string($live['policy_hash']??null)
                &&preg_match('/^[a-f0-9]{64}$/D',$live['policy_hash'])){
                $row['status']='pending';unset($row['superseded_at'],$row['superseded_reason']);
                $row['policy_hash']=$live['policy_hash'];
                $row=RecordStore::put('revocation_dispatch',$id,$row,(int)$row['version']);
            }
        }
        return self::dispatch($row);
    }
    public static function reconcile(int $limit=500): array {
        $limit=max(1,min(2000,$limit));
        $rows=RecordStore::all('revocation_dispatch',0,'pending',100000);
        usort($rows,static fn(array $a,array $b): int=>strcmp((string)$a['id'],(string)$b['id']));
        $cursor=RecordStore::get('cron_cursor','revocation-dispatch');
        $after=(string)($cursor['last_id']??'');
        $ordered=array_merge(
            array_values(array_filter($rows,static fn(array $r): bool=>strcmp((string)$r['id'],$after)>0)),
            array_values(array_filter($rows,static fn(array $r): bool=>strcmp((string)$r['id'],$after)<=0))
        );
        $result=['checked'=>0,'dispatched'=>0,'superseded'=>0,'failed'=>0];
        foreach(array_slice($ordered,0,$limit) as $row){
            $result['checked']++;$after=(string)$row['id'];
            try{
                $out=self::dispatch($row);
                if($out['status']==='dispatched')$result['dispatched']++;
                elseif($out['status']==='superseded')$result['superseded']++;
                else throw new Error('revocation_dispatch_invalid','Recovery did not reach a terminal local state.',500);
            }
            catch(\Throwable $e){
                $result['failed']++;
                try{DegradedStateService::record('revocation-dispatch',$e instanceof Error?$e->errorCode:'unexpected',
                    ['event_ref'=>Utils::hashReference((string)($row['event_id']??''))]);}catch(\Throwable){}
            }
        }
        if($result['checked']>0){
            RecordStore::put('cron_cursor','revocation-dispatch',[
                'actor_id'=>0,'status'=>'active','last_id'=>$after,'updated_at'=>Utils::now(),
            ],$cursor?(int)$cursor['version']:0);
        }
        return $result;
    }
}

final class RightsRevocationService {
    private static function expiry(array $asset): ?int {
        try{return Utils::integer($asset['rights']['expires_at']??null,'rights_integer_invalid',0);}
        catch(Error){return null;}
    }

    private static function rightsFingerprint(array $asset): string {
        return hash('sha256',Utils::canonicalJson((array)($asset['rights']??[])));
    }

    private static function assertCurrent(string $assetId,array $snapshot,string $phase): array {
        $current=RecordStore::get('asset',$assetId);
        if(!is_array($current)||($current['id']??null)!==$assetId)
            throw new Error('revocation_asset_stale','Revocation asset disappeared during reconciliation.',409,['phase'=>$phase]);
        if(in_array(($current['status']??''),['deleted','deletion_pending','rejected'],true))
            throw new Error('revocation_asset_terminal','Revocation asset became terminal.',409,['phase'=>$phase]);
        foreach(['owner_domain','owner_object','object_version'] as $key)
            if(($current[$key]??null)!==($snapshot[$key]??null))
                throw new Error('revocation_owner_stale','Revocation owner identity changed.',409,['phase'=>$phase]);
        if(($current['policy_hash']??null)!==($snapshot['policy_hash']??null))
            throw new Error('revocation_policy_stale','Revocation policy changed.',409,['phase'=>$phase]);
        if(!hash_equals(self::rightsFingerprint($snapshot),self::rightsFingerprint($current)))
            throw new Error('revocation_rights_stale','Revocation rights changed.',409,['phase'=>$phase]);
        return $current;
    }

    private static function alreadyReconciled(array $asset): bool {
        return in_array(($asset['rights_reconciled_reason']??''),['rights_expired','rights_invalid'],true)
            &&hash_equals((string)($asset['rights_reconciled_hash']??''),self::rightsFingerprint($asset));
    }

    public static function reconcileExpired(int $now=0,int $limit=500): array {
        $now=$now>0?$now:Utils::now();$limit=max(1,min(2000,$limit));
        $result=['checked'=>0,'revoked'=>0,'failed'=>0];
        // Only actionable assets enter the bounded batch; rotate a durable key
        // so one repeatedly failing rights record cannot starve later records.
        $eligible=[];
        foreach(RecordStore::all('asset',0,null,1000000) as $asset){
            if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true)
                ||self::alreadyReconciled($asset))continue;
            $expires=self::expiry($asset);
            if($expires===0||($expires!==null&&$expires>$now))continue;
            $assetId=(string)($asset['id']??'');
            $sortKey=sprintf('%019d',max(0,$expires??0)).':'.$assetId;
            $eligible[]=['key'=>$sortKey,'asset'=>$asset,'reason'=>$expires===null?'rights_invalid':'rights_expired'];
        }
        usort($eligible,static fn(array $a,array $b): int=>strcmp($a['key'],$b['key']));
        $cursor=RecordStore::get('cron_cursor','rights-reconciliation');
        $after=(string)($cursor['last_key']??'');
        $ordered=array_merge(
            array_values(array_filter($eligible,static fn(array $r): bool=>strcmp($r['key'],$after)>0)),
            array_values(array_filter($eligible,static fn(array $r): bool=>strcmp($r['key'],$after)<=0))
        );
        foreach(array_slice($ordered,0,$limit) as $entry){
            $asset=$entry['asset'];$result['checked']++;$after=$entry['key'];
            try{self::invalidate((string)$asset['id'],$entry['reason'],$now);$result['revoked']++;}
            catch(\Throwable $exception){
                $result['failed']++;
                try{DegradedStateService::record('rights-reconciliation',
                    $exception instanceof Error?$exception->errorCode:'unexpected',
                    ['asset_ref'=>Utils::hashReference((string)($asset['id']??''))]);}
                catch(\Throwable){}
            }
        }
        if($result['checked']>0){
            RecordStore::put('cron_cursor','rights-reconciliation',[
                'actor_id'=>0,'status'=>'active','last_key'=>$after,'updated_at'=>Utils::now(),
            ],$cursor?(int)$cursor['version']:0);
        }
        return $result;
    }

    public static function invalidate(string $assetId,string $reason,int $effectiveAt=0): array {
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        $reason=Utils::key($reason,64);
        if($reason==='')throw new Error('revocation_reason_required','Revocation reason is required.',400);
        $fingerprint=self::rightsFingerprint($asset);
        $id=hash('sha256',Utils::canonicalJson([
            'asset_id'=>$assetId,'reason'=>$reason,'rights_fingerprint'=>$fingerprint,
            'object_version'=>$asset['object_version']??null,
        ]));
        $existing=RecordStore::get('projection_revocation',$id);
        if($existing!==null){
            if(($existing['record_type']??null)!=='projection_revocation'
                ||($existing['id']??null)!==$id
                ||($existing['actor_id']??null)!==0
                ||!is_int($existing['version']??null)||$existing['version']<1
                ||!is_int($existing['created_at']??null)||$existing['created_at']<1
                ||!is_int($existing['effective_at']??null)||$existing['effective_at']<1
                ||($existing['status']??null)!=='propagation_pending_consumers'
                ||($existing['asset_ref_hash']??null)!==Utils::hashReference($assetId)
                ||($existing['asset_id']??null)!==$assetId||($existing['reason']??null)!==$reason
                ||($existing['rights_fingerprint']??null)!==$fingerprint
                ||($existing['object_version']??null)!==($asset['object_version']??null)
                ||($existing['owner_domain']??null)!==($asset['owner_domain']??null)
                ||($existing['owner_object_hash']??null)!==Utils::hashReference((string)$asset['owner_object'])
                ||($existing['cdn_status']??null)!=='purged_or_not_published'
                ||($existing['derivative_status']??null)!=='revoked'
                ||($existing['index_status']??null)!=='pending_owner_consumer'
                ||($existing['backup_status']??null)!=='retention_policy_applies')
                throw new Error('revocation_projection_invalid','Stored projection conflicts with revocation evidence.',500);
        }
        self::assertCurrent($assetId,$asset,'before_derivative_expiry');
        $propagation=DeletionService::expireDerivatives($assetId,$reason,false);
        $current=self::assertCurrent($assetId,$asset,'after_derivative_expiry');
        if($existing!==null){
            $row=$existing;
        }else{
            $row=RecordStore::put('projection_revocation',$id,[
                'actor_id'=>0,'asset_id'=>$assetId,'asset_ref_hash'=>Utils::hashReference($assetId),
                'owner_domain'=>$asset['owner_domain'],
                'owner_object_hash'=>Utils::hashReference((string)$asset['owner_object']),
                'object_version'=>$asset['object_version'],'rights_fingerprint'=>$fingerprint,
                'reason'=>$reason,'status'=>'propagation_pending_consumers',
                'cdn_status'=>'purged_or_not_published','derivative_status'=>'revoked',
                'index_status'=>'pending_owner_consumer','backup_status'=>'retention_policy_applies',
                'effective_at'=>$effectiveAt>0?$effectiveAt:Utils::now(),'created_at'=>Utils::now(),
            ],0);
        }
        Audit::recordOnce('media_rights_revocation_propagated',[
            'actor_id'=>0,'asset_id'=>$assetId,'reason'=>$reason,'projection_revocation_id'=>$row['id'],
        ],$id);
        $notice=RevocationDispatchService::notify($current,$reason,$id);
        if(($notice['status']??null)!=='dispatched')
            throw new Error('revocation_dispatch_superseded','Revocation notice was superseded before dispatch.',409);
        // Synchronous hooks may mutate or delete the asset; never reconcile their new state.
        $fresh=self::assertCurrent($assetId,$asset,'after_revocation_dispatch');
        if(in_array($reason,['rights_expired','rights_invalid'],true)){
            $fresh['rights_reconciled_reason']=$reason;
            $fresh['rights_reconciled_hash']=$fingerprint;
            $fresh['rights_reconciled_at']=$row['effective_at'];
            RecordStore::put('asset',$assetId,$fresh,(int)$fresh['version']);
        }
        return ['propagation'=>$propagation,'projection'=>$row];
    }
}
