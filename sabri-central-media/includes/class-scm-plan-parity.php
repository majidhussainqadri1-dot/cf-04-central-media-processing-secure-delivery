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
                'CF04-CEN-05'=>'C2-C5 never public CDN/index; encrypted owner-authorized access',
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

final class RightsRevocationService {
    public static function reconcileExpired(int $now=0,int $limit=500): array {
        $now=$now>0?$now:Utils::now();$limit=max(1,min(2000,$limit));$result=['checked'=>0,'revoked'=>0,'failed'=>0];
        foreach(RecordStore::all('asset',0,null,$limit) as $asset){
            if($result['checked']>=$limit)break;$result['checked']++;
            if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))continue;
            $expires=(int)($asset['rights']['expires_at']??0);
            if($expires<1||$expires>$now)continue;
            try{self::invalidate((string)$asset['id'],'rights_expired',$now);$result['revoked']++;}
            catch(\Throwable $exception){$result['failed']++;DegradedStateService::record('rights-reconciliation',$exception instanceof Error?$exception->errorCode:'unexpected',['asset_ref'=>Utils::hashReference((string)$asset['id'])]);}
        }
        return $result;
    }

    public static function invalidate(string $assetId,string $reason,int $effectiveAt=0): array {
        $asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        $reason=Utils::key($reason,64);if($reason==='')throw new Error('revocation_reason_required','Revocation reason is required.',400);
        $propagation=DeletionService::expireDerivatives($assetId,$reason);
        $id=hash('sha256',$assetId.'|'.$reason.'|'.($effectiveAt?:Utils::now()));
        $row=RecordStore::put('projection_revocation',$id,[
            'actor_id'=>0,'asset_id'=>$assetId,'asset_ref_hash'=>Utils::hashReference($assetId),'owner_domain'=>$asset['owner_domain'],
            'owner_object_hash'=>Utils::hashReference((string)$asset['owner_object']),'object_version'=>$asset['object_version'],
            'reason'=>$reason,'status'=>'propagation_pending_consumers','cdn_status'=>'purged_or_not_published','derivative_status'=>'revoked',
            'index_status'=>'pending_owner_consumer','backup_status'=>'retention_policy_applies','effective_at'=>$effectiveAt?:Utils::now(),'created_at'=>Utils::now(),
        ]);
        Audit::record('media_rights_revocation_propagated',['asset_id'=>$assetId,'reason'=>$reason,'projection_revocation_id'=>$row['id']]);
        if(function_exists('do_action'))do_action('scm.media.revoked',['asset_id'=>$assetId,'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],'object_version'=>$asset['object_version'],'reason'=>$reason,'projection_revocation_id'=>$row['id']]);
        return ['propagation'=>$propagation,'projection'=>$row];
    }
}
