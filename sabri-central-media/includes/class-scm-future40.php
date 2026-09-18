<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * CF-04 Future-40 source layer.
 *
 * This layer implements fail-closed contracts, orchestration, audit evidence and
 * deterministic policy for the forty approved future capabilities. Operations
 * that require specialist media engines/providers are delegated through
 * FutureAdapterRegistry and never silently simulated in production.
 */
final class Future40Registry {
    public const REQUIREMENTS=[
        'CF04-FUT-001'=>'C2PA/content-credentials provenance envelope',
        'CF04-FUT-002'=>'synthetic-media declaration and transformation history',
        'CF04-FUT-003'=>'versioned perceptual fingerprint registry',
        'CF04-FUT-004'=>'privacy-safe near-duplicate detection',
        'CF04-FUT-005'=>'same-envelope storage deduplication decision',
        'CF04-FUT-006'=>'content disarm and safe reconstruction',
        'CF04-FUT-007'=>'threat-intelligence driven automatic re-scan scheduling',
        'CF04-FUT-008'=>'format/codec emergency kill switch',
        'CF04-FUT-009'=>'content-aware encoding plan',
        'CF04-FUT-010'=>'objective derivative quality scoring',
        'CF04-FUT-011'=>'codec capability negotiation',
        'CF04-FUT-012'=>'color/HDR/ICC processing policy and lineage',
        'CF04-FUT-013'=>'audio technical quality-control evidence',
        'CF04-FUT-014'=>'smart poster/storyboard derivative plan',
        'CF04-FUT-015'=>'chapter/timecode manifest',
        'CF04-FUT-016'=>'rich caption tracks including SDH/forced tracks',
        'CF04-FUT-017'=>'audio-description track contract',
        'CF04-FUT-018'=>'sign-language synchronized track contract',
        'CF04-FUT-019'=>'OCR confidence/bounding-box coordinate map',
        'CF04-FUT-020'=>'machine-readable accessibility manifest',
        'CF04-FUT-021'=>'sensitive-data signal detection',
        'CF04-FUT-022'=>'governed redaction derivative workflow',
        'CF04-FUT-023'=>'approved multi-CDN health-aware failover routing',
        'CF04-FUT-024'=>'origin-shield/cache-protection policy',
        'CF04-FUT-025'=>'edge-authorization profile without canonical-owner transfer',
        'CF04-FUT-026'=>'network-adaptive upload profile',
        'CF04-FUT-027'=>'encrypted bounded offline-package grant',
        'CF04-FUT-028'=>'cross-session/device resumable-download state with re-authorization',
        'CF04-FUT-029'=>'regional residency pinning policy',
        'CF04-FUT-030'=>'WORM/object-lock evidence contract',
        'CF04-FUT-031'=>'per-asset envelope-encryption key reference',
        'CF04-FUT-032'=>'crypto-agility migration policy',
        'CF04-FUT-033'=>'multi-region disaster-recovery replication plan',
        'CF04-FUT-034'=>'policy-aware intelligent storage-tier optimizer',
        'CF04-FUT-035'=>'pre-processing storage/transcode/egress cost estimate',
        'CF04-FUT-036'=>'approved-provider health/cost/capability auto-routing',
        'CF04-FUT-037'=>'privacy-minimal QoE telemetry',
        'CF04-FUT-038'=>'staging-only chaos/fault-injection exercise contract',
        'CF04-FUT-039'=>'signed portable migration/export bundle',
        'CF04-FUT-040'=>'versioned media infrastructure SDK/contract kit',
    ];

    public static function manifest(): array {
        return [
            'generation'=>'future40-2026-09-16',
            'requirements'=>self::REQUIREMENTS,
            'count'=>count(self::REQUIREMENTS),
            'runtime_default'=>'disabled',
            'external_engines'=>'adapter-gated/fail-closed',
            'canonical_domain_ownership'=>'unchanged',
            'staging_live_acceptance'=>'separate',
        ];
    }

    public static function asset(string $assetId,bool $ready=false): array {
        $asset=RecordStore::get('asset',Utils::text($assetId,96));
        if(!$asset||in_array(($asset['status']??''),['deleted','rejected'],true))throw new Error('asset_not_available','Asset is unavailable.',404);
        if($ready&&($asset['status']??'')!=='ready')throw new Error('asset_not_ready','Asset is not ready for this future capability.',409);
        return $asset;
    }

    public static function sameEnvelope(array $a,array $b): bool {
        foreach(['owner_domain','privacy_class','policy_hash'] as $key)if(($a[$key]??null)!==($b[$key]??null))return false;
        return (string)($a['rights']['policy_hash']??'')===(string)($b['rights']['policy_hash']??'');
    }
}

final class FutureAdapterRegistry {
    private static array $adapters=[];
    public static function reset(): void {self::$adapters=[];}
    public static function register(string $capability,callable $adapter): void {
        $capability=Utils::key($capability,64);if($capability==='')throw new Error('future_adapter_invalid','Future adapter capability is required.',500);
        if(isset(self::$adapters[$capability])&&self::$adapters[$capability]!==$adapter)throw new Error('future_adapter_conflict','Future adapter is already registered.',409,['capability'=>$capability]);
        self::$adapters[$capability]=$adapter;
    }
    public static function has(string $capability): bool {return isset(self::$adapters[Utils::key($capability,64)]);}
    public static function call(string $capability,array $context): array {
        $capability=Utils::key($capability,64);$adapter=self::$adapters[$capability]??null;
        if(!is_callable($adapter))throw new Error('future_adapter_unavailable','Approved future capability adapter is unavailable.',503,['capability'=>$capability]);
        try{$result=$adapter(Utils::redact($context));}catch(Error $e){throw $e;}catch(\Throwable $e){throw new Error('future_adapter_failed','Future capability adapter failed.',503,['capability'=>$capability,'exception'=>get_class($e)]);}
        if(!is_array($result)||($result['ok']??false)!==true)throw new Error('future_adapter_failed','Future capability adapter did not return approved success evidence.',503,['capability'=>$capability]);
        return Utils::redact($result);
    }
    public static function registerWordPressAdapters(): void {
        if(!function_exists('apply_filters'))return;
        foreach(array_keys(Future40Registry::REQUIREMENTS) as $id){
            $cap=strtolower(str_replace('cf04-fut-','future_',strtolower($id)));
            $adapter=apply_filters('scm_'.$cap.'_adapter',null);
            if(is_callable($adapter))self::register($cap,$adapter);
        }
    }
}

final class ProvenanceCredentialService {
    private const SYNTHETIC=['original','ai_generated','ai_edited','composited','unknown'];
    public static function record(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);
        Utils::requireFields($input,['origin','synthetic_state','tool','tool_version'],'provenance_incomplete');
        $synthetic=Utils::key((string)$input['synthetic_state'],32);if(!in_array($synthetic,self::SYNTHETIC,true))throw new Error('synthetic_state_invalid','Synthetic-media state is invalid.',400);
        $parents=array_values(array_unique(array_map(fn($v)=>Utils::text((string)$v,96),(array)($input['parent_asset_ids']??[]))));
        foreach($parents as $parentId){$parent=Future40Registry::asset($parentId);if(!Future40Registry::sameEnvelope($asset,$parent))throw new Error('provenance_boundary_denied','Cross-policy provenance parent requires owning-domain migration.',403);}
        $payload=['asset_id'=>$assetId,'asset_sha256'=>$asset['sha256'],'owner_domain'=>$asset['owner_domain'],'owner_object_hash'=>Utils::hashReference((string)$asset['owner_object']),'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'origin'=>Utils::key((string)$input['origin'],48),'synthetic_state'=>$synthetic,'tool'=>Utils::text((string)$input['tool'],96),'tool_version'=>Utils::text((string)$input['tool_version'],64),'parent_asset_ids'=>$parents,'transformations'=>array_values(array_map(fn($v)=>Utils::key((string)$v,64),(array)($input['transformations']??[]))),'created_at'=>Utils::now()];
        $credential=FutureAdapterRegistry::call('future_001',['operation'=>'content_credentials','payload'=>$payload]);
        $id=hash('sha256',$assetId.'|'.Utils::canonicalJson($payload));
        $row=RecordStore::put('provenance_credential',$id,['actor_id'=>$actor,'status'=>'active','payload'=>$payload,'credential'=>$credential,'credential_hash'=>hash('sha256',Utils::canonicalJson($credential))]);
        Audit::record('future40_provenance_recorded',['asset_id'=>$assetId,'synthetic_state'=>$synthetic,'credential_id'=>$id]);
        return $row;
    }
}

final class PerceptualMediaService {
    public static function fingerprint(string $assetId,int $actor,string $algorithm,string $version): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);
        $algorithm=Utils::key($algorithm,48);$version=Utils::text($version,32);if($algorithm===''||$version==='')throw new Error('perceptual_profile_invalid','Perceptual fingerprint profile is required.',400);
        $result=FutureAdapterRegistry::call('future_003',['asset'=>['id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'media_class'=>$asset['media_class']],'algorithm'=>$algorithm,'version'=>$version]);
        $value=Utils::text((string)($result['fingerprint']??''),512);if($value==='')throw new Error('perceptual_fingerprint_invalid','Adapter omitted perceptual fingerprint.',503);
        $id=hash('sha256',$assetId.'|'.$algorithm.'|'.$version);
        return RecordStore::put('perceptual_fingerprint',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'algorithm'=>$algorithm,'algorithm_version'=>$version,'fingerprint'=>$value,'privacy_class'=>$asset['privacy_class'],'owner_domain'=>$asset['owner_domain'],'policy_hash'=>$asset['policy_hash'],'created_at'=>Utils::now()]);
    }
    public static function nearDuplicates(string $assetId,int $actor,float $threshold=0.9): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'audit_sabri_media');$asset=Future40Registry::asset($assetId,true);$threshold=max(0.5,min(1.0,$threshold));
        $source=array_values(array_filter(RecordStore::all('perceptual_fingerprint',0,'active',100000),fn($r)=>($r['asset_id']??'')===$assetId));
        if($source===[])throw new Error('perceptual_fingerprint_missing','Perceptual fingerprint is required first.',409);
        $matches=[];
        foreach(RecordStore::all('perceptual_fingerprint',0,'active',100000) as $row){
            if(($row['asset_id']??'')===$assetId||($row['algorithm']??'')!==$source[0]['algorithm']||($row['algorithm_version']??'')!==$source[0]['algorithm_version'])continue;
            $other=RecordStore::get('asset',(string)$row['asset_id']);if(!$other||!Future40Registry::sameEnvelope($asset,$other))continue;
            $score=FutureAdapterRegistry::call('future_004',['left'=>$source[0]['fingerprint'],'right'=>$row['fingerprint'],'threshold'=>$threshold]);
            $similarity=(float)($score['similarity']??0.0);if($similarity>=$threshold)$matches[]=['asset_id'=>$other['id'],'similarity'=>$similarity];
        }
        usort($matches,fn($a,$b)=>$b['similarity']<=>$a['similarity']);return $matches;
    }
    public static function dedupeDecision(string $assetId,string $candidateId,int $actor): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$asset=Future40Registry::asset($assetId,true);$candidate=Future40Registry::asset($candidateId,true);
        $assetRetention=(string)($asset['policy']['retention']['class']??'');$candidateRetention=(string)($candidate['policy']['retention']['class']??'');$allowed=$asset['sha256']===$candidate['sha256']&&Future40Registry::sameEnvelope($asset,$candidate)&&$assetRetention!==''&&hash_equals($assetRetention,$candidateRetention);
        $row=['actor_id'=>$actor,'status'=>$allowed?'eligible':'denied','asset_id'=>$assetId,'candidate_asset_id'=>$candidateId,'reason'=>$allowed?'same_binary_same_policy_envelope':'boundary_or_binary_mismatch','created_at'=>Utils::now()];
        RecordStore::put('dedupe_decision',hash('sha256',$assetId.'|'.$candidateId),$row);return $row;
    }
}

final class ContentSafetyUpgradeService {
    public static function disarm(string $assetId,int $actor,string $profile): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);$profile=Utils::key($profile,64);if($profile==='')throw new Error('cdr_profile_invalid','CDR profile is required.',400);
        $result=FutureAdapterRegistry::call('future_006',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'profile'=>$profile]);
        $safeHash=Utils::text((string)($result['safe_sha256']??''),64);if(!preg_match('/^[a-f0-9]{64}$/',$safeHash))throw new Error('cdr_output_invalid','Safe reconstruction hash is invalid.',503);
        $row=RecordStore::put('cdr_result',hash('sha256',$assetId.'|'.$profile),['actor_id'=>$actor,'status'=>'validated','asset_id'=>$assetId,'source_sha256'=>$asset['sha256'],'safe_sha256'=>$safeHash,'removed_features'=>(array)($result['removed_features']??[]),'profile'=>$profile,'created_at'=>Utils::now()]);
        Audit::record('future40_cdr_completed',['asset_id'=>$assetId,'profile'=>$profile]);return $row;
    }
    public static function scheduleRescan(string $reason,array $filters=[],int $limit=1000): array {
        $reason=Utils::key($reason,96);if($reason==='')throw new Error('rescan_reason_required','Re-scan reason is required.',400);$limit=max(1,min(5000,$limit));$queued=0;$scanned=0;$offset=0;$pageSize=500;
        while($queued<$limit&&$scanned<1000000){
            $page=RecordStore::list('asset',0,null,$pageSize,$offset);if($page===[])break;$offset+=count($page);$scanned+=count($page);
            foreach($page as $asset){if($queued>=$limit)break;if(in_array(($asset['status']??''),['deleted','rejected'],true))continue;if(isset($filters['mime'])&&$asset['mime']!==$filters['mime'])continue;if(isset($filters['media_class'])&&$asset['media_class']!==$filters['media_class'])continue;
                $id=hash('sha256',$asset['id'].'|'.$reason);if(RecordStore::get('future_rescan',$id))continue;try{RecordStore::put('future_rescan',$id,['actor_id'=>0,'status'=>'queued','asset_id'=>$asset['id'],'reason'=>$reason,'requested_at'=>Utils::now()],0);$queued++;}catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}}
            if(count($page)<$pageSize)break;
        }
        $truncated=$scanned>=1000000&&RecordStore::list('asset',0,null,1,$offset)!==[];Audit::record('future40_rescan_scheduled',['reason'=>$reason,'queued'=>$queued,'scanned'=>$scanned,'scan_truncated'=>$truncated]);return ['queued'=>$queued,'reason'=>$reason,'scanned'=>$scanned,'scan_truncated'=>$truncated];
    }
    public static function killSwitch(string $format,int $actor,bool $enabled,string $reason): array {
        Auth::assertActor($actor,'media_manage_providers');$format=strtolower(Utils::text($format,96));$reason=Utils::text($reason,255);if($format===''||$reason==='')throw new Error('kill_switch_invalid','Format and reason are required.',400);
        $id=hash('sha256',$format);$existing=RecordStore::get('format_kill_switch',$id);$row=RecordStore::put('format_kill_switch',$id,['actor_id'=>$actor,'status'=>$enabled?'blocked':'released','format'=>$format,'reason'=>$reason,'updated_by'=>$actor,'created_at'=>$existing?($existing['created_at']??Utils::now()):Utils::now()],$existing?(int)$existing['version']:0);
        Audit::record('future40_format_kill_switch',['format'=>$format,'enabled'=>$enabled,'reason'=>$reason]);return $row;
    }
    public static function assertAllowed(string $format): void {$row=RecordStore::get('format_kill_switch',hash('sha256',strtolower(Utils::text($format,96))));if($row&&($row['status']??'')==='blocked')throw new Error('format_emergency_blocked','Format is disabled by emergency security policy.',503,['format'=>$format]);}
}

final class MediaOptimizationService {
    public static function encodingPlan(string $assetId,int $actor,array $constraints=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);ContentSafetyUpgradeService::assertAllowed((string)$asset['mime']);
        $result=FutureAdapterRegistry::call('future_009',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'media_class'=>$asset['media_class'],'size'=>$asset['size'],'constraints'=>$constraints]);
        $ladder=(array)($result['renditions']??[]);if($ladder===[])throw new Error('encoding_plan_invalid','Encoding adapter returned no renditions.',503);
        return RecordStore::put('encoding_plan',hash('sha256',$assetId.'|'.Utils::canonicalJson($constraints)),['actor_id'=>$actor,'status'=>'approved','asset_id'=>$assetId,'renditions'=>$ladder,'complexity'=>(float)($result['complexity']??0.0),'constraints'=>Utils::redact($constraints),'created_at'=>Utils::now()]);
    }
    public static function qualityScore(string $assetId,string $derivativeId,int $actor,array $thresholds): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);$derivative=RecordStore::get('derivative',$derivativeId);if(!$derivative||($derivative['asset_id']??'')!==$assetId)throw new Error('derivative_not_found','Derivative not found.',404);
        $result=FutureAdapterRegistry::call('future_010',['asset_sha256'=>$asset['sha256'],'derivative_sha256'=>$derivative['sha256'],'kind'=>$derivative['kind'],'thresholds'=>$thresholds]);
        $passed=($result['passed']??false)===true;$row=RecordStore::put('quality_score',hash('sha256',$assetId.'|'.$derivativeId),['actor_id'=>$actor,'status'=>$passed?'passed':'failed','asset_id'=>$assetId,'derivative_id'=>$derivativeId,'metrics'=>(array)($result['metrics']??[]),'thresholds'=>$thresholds,'created_at'=>Utils::now()]);if(!$passed)throw new Error('derivative_quality_failed','Derivative failed objective quality policy.',409,['derivative_id'=>$derivativeId]);return $row;
    }
    public static function negotiateCodec(array $client,array $approved): string {
        $client=array_values(array_map(fn($v)=>Utils::key((string)$v,32),$client));$approved=array_values(array_map(fn($v)=>Utils::key((string)$v,32),$approved));foreach($approved as $codec)if(in_array($codec,$client,true))return $codec;throw new Error('codec_unavailable','No approved compatible codec is available.',406);
    }
    public static function colorPolicy(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');Future40Registry::asset($assetId,true);Utils::requireFields($input,['source_space','target_space','hdr_mode','icc_hash'],'color_policy_incomplete');$icc=Utils::text((string)$input['icc_hash'],64);if($icc!==''&&!preg_match('/^[a-f0-9]{64}$/',$icc))throw new Error('icc_hash_invalid','ICC hash is invalid.',400);
        return RecordStore::put('color_policy',hash('sha256',$assetId),['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'source_space'=>Utils::key((string)$input['source_space'],32),'target_space'=>Utils::key((string)$input['target_space'],32),'hdr_mode'=>Utils::key((string)$input['hdr_mode'],32),'icc_hash'=>$icc,'tone_map'=>Utils::key((string)($input['tone_map']??'none'),32),'created_at'=>Utils::now()]);
    }
    public static function audioQc(string $assetId,int $actor,array $thresholds=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);if(!in_array($asset['media_class'],['audio','video'],true))throw new Error('audio_qc_not_applicable','Audio QC is only applicable to audio/video assets.',400);
        $result=FutureAdapterRegistry::call('future_013',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'thresholds'=>$thresholds]);$passed=($result['passed']??false)===true;$row=RecordStore::put('audio_qc',hash('sha256',$assetId),['actor_id'=>$actor,'status'=>$passed?'passed':'failed','asset_id'=>$assetId,'metrics'=>(array)($result['metrics']??[]),'issues'=>(array)($result['issues']??[]),'created_at'=>Utils::now()]);if(!$passed)throw new Error('audio_qc_failed','Audio technical quality policy failed.',409);return $row;
    }
}

final class RichMediaMetadataService {
    private static function putTrack(string $type,string $assetId,int $actor,array $input,array $required): array {RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');$asset=Future40Registry::asset($assetId,true);Utils::requireFields($input,$required,'track_metadata_incomplete');$id=hash('sha256',$assetId.'|'.$type.'|'.Utils::canonicalJson($input));return RecordStore::put($type,$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'owner_object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'data'=>Utils::redact($input),'created_at'=>Utils::now()]);}
    public static function smartPreview(string $assetId,int $actor,array $profile): array {$asset=Future40Registry::asset($assetId,true);$result=FutureAdapterRegistry::call('future_014',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'profile'=>$profile]);return self::putTrack('smart_preview',$assetId,$actor,['poster_ref'=>(string)($result['poster_ref']??''),'storyboard_ref'=>(string)($result['storyboard_ref']??''),'selection'=>(array)($result['selection']??[])],['poster_ref','storyboard_ref']);}
    public static function chapters(string $assetId,int $actor,array $chapters): array {if($chapters===[])throw new Error('chapter_manifest_empty','At least one chapter is required.',400);$last=-1;foreach($chapters as $chapter){$start=(int)($chapter['start_ms']??-1);if($start<0||$start<=$last||Utils::text((string)($chapter['title']??''),160)==='')throw new Error('chapter_manifest_invalid','Chapter order/title is invalid.',400);$last=$start;}return self::putTrack('chapter_manifest',$assetId,$actor,['chapters'=>$chapters],['chapters']);}
    public static function captionTrack(string $assetId,int $actor,array $input): array {$kind=Utils::key((string)($input['kind']??''),32);if(!in_array($kind,['standard','sdh','forced'],true))throw new Error('caption_kind_invalid','Caption kind is invalid.',400);return self::putTrack('rich_caption',$assetId,$actor,$input,['locale','kind','content_hash','source']);}
    public static function audioDescription(string $assetId,int $actor,array $input): array {return self::putTrack('audio_description',$assetId,$actor,$input,['locale','track_ref','content_hash']);}
    public static function signLanguage(string $assetId,int $actor,array $input): array {return self::putTrack('sign_language_track',$assetId,$actor,$input,['locale','track_ref','synchronization_hash']);}
    public static function ocrMap(string $assetId,int $actor,array $pages): array {if($pages===[])throw new Error('ocr_map_empty','OCR coordinate map is empty.',400);foreach($pages as $page){if((int)($page['page']??0)<1||!isset($page['items'])||!is_array($page['items']))throw new Error('ocr_map_invalid','OCR page map is invalid.',400);foreach($page['items'] as $item){$confidence=(float)($item['confidence']??-1);$box=(array)($item['box']??[]);if($confidence<0||$confidence>1||count($box)!==4)throw new Error('ocr_map_invalid','OCR confidence/box is invalid.',400);}}return self::putTrack('ocr_coordinate_map',$assetId,$actor,['pages'=>$pages],['pages']);}
    public static function accessibilityManifest(string $assetId): array {$asset=Future40Registry::asset($assetId,true);$collect=[];foreach(['accessibility_metadata','rich_caption','audio_description','sign_language_track','ocr_coordinate_map','chapter_manifest'] as $type)$collect[$type]=array_values(array_filter(RecordStore::all($type,0,null,100000),fn($r)=>($r['asset_id']??'')===$assetId));$manifest=['asset_id'=>$assetId,'privacy_class'=>$asset['privacy_class'],'object_version'=>$asset['object_version'],'tracks'=>$collect,'complete'=>($collect['accessibility_metadata']!==[]||$collect['rich_caption']!==[]),'generated_at'=>Utils::now()];$manifest['manifest_hash']=hash('sha256',Utils::canonicalJson($manifest));return $manifest;}
}

final class SensitiveDataProtectionService {
    public static function detect(string $assetId,int $actor,array $classes=[]): array {RuntimeGuard::requireReady();Auth::assertActor($actor,'media_review_safety_signals');$asset=Future40Registry::asset($assetId,true);$result=FutureAdapterRegistry::call('future_021',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'classes'=>$classes]);$signals=(array)($result['signals']??[]);return RecordStore::put('sensitive_signal',hash('sha256',$assetId.'|'.Utils::canonicalJson($classes)),['actor_id'=>$actor,'status'=>$signals===[]?'clear':'review_required','asset_id'=>$assetId,'signals'=>Utils::redact($signals),'created_at'=>Utils::now()]);}
    public static function redact(string $assetId,int $actor,array $regions,string $reason): array {RuntimeGuard::requireReady();Auth::assertActor($actor,'media_review_safety_signals');$asset=Future40Registry::asset($assetId,true);if($regions===[]||Utils::text($reason,255)==='')throw new Error('redaction_request_invalid','Redaction regions and reason are required.',400);$decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'transform'=>'redaction','reason'=>$reason,'regions'=>$regions]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Redaction authorization is stale.',409);$result=FutureAdapterRegistry::call('future_022',['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'regions'=>$regions,'reason'=>$reason]);$sha=Utils::text((string)($result['redacted_sha256']??''),64);if(!preg_match('/^[a-f0-9]{64}$/',$sha))throw new Error('redaction_output_invalid','Redaction derivative hash is invalid.',503);$row=RecordStore::put('redaction_derivative',Utils::id('redaction'),['actor_id'=>$actor,'status'=>'approved','asset_id'=>$assetId,'source_sha256'=>$asset['sha256'],'redacted_sha256'=>$sha,'regions'=>Utils::redact($regions),'reason'=>Utils::text($reason,255),'object_version'=>$asset['object_version'],'created_at'=>Utils::now()]);Audit::record('future40_redaction_created',['asset_id'=>$assetId,'redaction_id'=>$row['id']]);return $row;}
}

final class DeliveryResilienceService {
    public static function routeCdn(string $assetId,array $candidates): array {
        $asset=Future40Registry::asset($assetId,true);
        if($asset['privacy_class']!=='C0')throw new Error('multi_cdn_public_only','Multi-CDN public routing is limited to C0 assets.',403);
        $approved=[];
        foreach($candidates as $c){
            $id=Utils::key((string)($c['id']??''),64);
            if($id===''||($c['approved']??false)!==true)continue;
            $region=strtoupper(Utils::text((string)($c['region']??''),16));
            if(ResidencyCryptoService::hasPolicy($assetId)){
                try{ResidencyCryptoService::assertRegion($assetId,$region);}
                catch(Error){continue;}
            }
            $approved[]=['id'=>$id,'healthy'=>($c['healthy']??false)===true,'priority'=>(int)($c['priority']??100),'region'=>$region];
        }
        usort($approved,fn($a,$b)=>$a['priority']<=>$b['priority']);
        foreach($approved as $c)if($c['healthy'])return ['status'=>'routed','provider'=>$c['id'],'region'=>$c['region'],'failover_candidates'=>count($approved)-1];
        throw new Error('cdn_unavailable','No healthy approved CDN is available.',503);
    }
    public static function originShield(string $assetId,array $input): array {
        $asset=Future40Registry::asset($assetId,true);
        if($asset['privacy_class']!=='C0')throw new Error('origin_shield_public_only','Shared origin shield is restricted to C0 assets.',403);
        $ttl=max(0,min(86400,(int)($input['ttl_seconds']??0)));
        return ['asset_id'=>$assetId,'enabled'=>$ttl>0,'ttl_seconds'=>$ttl,'request_collapsing'=>Utils::bool($input['request_collapsing']??true),'prewarm'=>Utils::bool($input['prewarm']??false),'cache_key_hash'=>hash('sha256',$asset['sha256'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash'])];
    }
    public static function edgeAuthorization(string $assetId,array $input): array {
        $asset=Future40Registry::asset($assetId,true);
        if($asset['privacy_class']==='C0')throw new Error('edge_auth_not_required','Public C0 assets do not require restricted edge authorization.',400);
        $ttl=max(1,min(900,(int)($input['ttl_seconds']??120)));
        return ['asset_id'=>$assetId,'mode'=>'edge_verify_origin_authoritative','ttl_seconds'=>$ttl,'claims'=>['asset_id','actor_id','purpose','privacy_class','policy_hash','rights_hash','object_version'],'canonical_owner'=>$asset['owner_domain'],'authorization_refresh_required'=>true];
    }
    public static function adaptiveUpload(array $network,array $bounds): array {
        $rtt=max(0,(int)($network['rtt_ms']??0));$down=max(0.0,(float)($network['mbps']??0));$unstable=Utils::bool($network['unstable']??false);$maxPart=max(65536,min(67108864,(int)($bounds['max_part_size_bytes']??8388608)));
        $part=$unstable||$rtt>400||$down<2?min($maxPart,1048576):($down<10?min($maxPart,4194304):min($maxPart,16777216));$parallel=$unstable?1:($down>=20?4:2);
        return ['part_size_bytes'=>$part,'parallel_parts'=>$parallel,'retry'=>'exponential-jitter','checkpoint_each_part'=>true];
    }
    public static function offlineGrant(string $assetId,int $actor,int $ttlSeconds): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);$asset=Future40Registry::asset($assetId,true);
        if($asset['privacy_class']==='C0')throw new Error('offline_package_unnecessary','Use normal public delivery for C0 assets.',400);
        RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',['asset'=>$asset,'actor_id'=>$actor,'mode'=>'offline_package','context'=>['audience_type'=>'user','territory'=>'GLOBAL']]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Offline-package authorization is stale.',409);
        $ttl=max(60,min(86400,$ttlSeconds));$id=Utils::id('offline');$kid=Keyring::activeId();
        $claims=['type'=>'offline_package','grant_id'=>$id,'asset_id'=>$assetId,'actor_id'=>$actor,'purpose'=>$asset['policy']['purpose'],'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'object_version'=>$asset['object_version']];
        $aad='offline-package|'.$id.'|'.$actor;
        $envelope=Crypto::encryptChunk(Utils::canonicalJson($claims),$aad,$kid);
        $token=Crypto::sign(['type'=>'offline_package_envelope','grant_id'=>$id,'encryption_kid'=>$kid,'envelope'=>$envelope],$ttl);
        $expires=Utils::now()+$ttl;
        RecordStore::put('offline_package_grant',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'object_version'=>$asset['object_version'],'token_hash'=>hash('sha256',$token),'encryption_kid'=>$kid,'expires_at'=>$expires,'created_at'=>Utils::now()],0);
        return ['grant_id'=>$id,'token'=>$token,'expires_at'=>$expires,'encrypted'=>true];
    }
    public static function verifyOfflineGrant(string $token,int $actor): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);$outer=Crypto::verify($token);
        Utils::requireFields($outer,['type','grant_id','encryption_kid','envelope'],'offline_grant_invalid');
        if($outer['type']!=='offline_package_envelope')throw new Error('offline_grant_invalid','Offline grant type is invalid.',403);
        $id=Utils::text((string)$outer['grant_id'],96);$record=RecordStore::get('offline_package_grant',$id);
        if(!$record||($record['status']??'')!=='active'||(int)($record['expires_at']??0)<=Utils::now())throw new Error('offline_grant_unavailable','Offline grant is unavailable or expired.',403);
        if((int)$record['actor_id']!==$actor||!hash_equals((string)$record['token_hash'],hash('sha256',$token)))throw new Error('offline_grant_binding_mismatch','Offline grant binding mismatch.',403);
        $kid=Utils::key((string)$outer['encryption_kid'],64);
        if($kid===''||!hash_equals((string)$record['encryption_kid'],$kid))throw new Error('offline_grant_binding_mismatch','Offline grant key binding mismatch.',403);
        try{
            $plain=Crypto::decryptChunk((array)$outer['envelope'],'offline-package|'.$id.'|'.$actor,$kid);
            $claims=json_decode($plain,true,32,JSON_THROW_ON_ERROR);
        }catch(Error $e){throw $e;}catch(\Throwable){throw new Error('offline_grant_invalid','Offline grant envelope is invalid.',403);}
        if(!is_array($claims))throw new Error('offline_grant_invalid','Offline grant claims are invalid.',403);
        Utils::requireFields($claims,['asset_id','actor_id','purpose','privacy_class','policy_hash','rights_hash','object_version'],'offline_grant_invalid');
        if((int)$claims['actor_id']!==$actor)throw new Error('offline_grant_binding_mismatch','Offline grant actor mismatch.',403);
        $asset=Future40Registry::asset((string)$claims['asset_id'],true);
        if(!hash_equals((string)$claims['purpose'],(string)$asset['policy']['purpose'])||!hash_equals((string)$claims['privacy_class'],(string)$asset['privacy_class'])||!hash_equals((string)$claims['policy_hash'],(string)$asset['policy_hash']))throw new Error('offline_grant_state_changed','Offline grant asset state changed.',403);
        if((int)$claims['object_version']!==(int)$asset['object_version']||!hash_equals((string)$claims['rights_hash'],(string)$asset['rights']['policy_hash'])||(int)$record['object_version']!==(int)$asset['object_version'])throw new Error('offline_grant_state_changed','Offline grant owner/rights version changed.',403);
        RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',['asset'=>$asset,'actor_id'=>$actor,'mode'=>'offline_package_consume','context'=>['audience_type'=>'user','territory'=>'GLOBAL']]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Offline grant authorization is stale.',409);
        return $claims;
    }
    public static function resumeDownload(string $assetId,int $actor,array $state): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);$asset=Future40Registry::asset($assetId,true);$offset=max(0,(int)($state['offset']??0));
        if($offset>(int)$asset['size'])throw new Error('download_offset_invalid','Download resume offset exceeds asset size.',416);
        $device=Utils::text((string)($state['device_id']??''),191);
        if($device==='')throw new Error('download_resume_device_required','A device reference is required for resumable download.',400);
        $id=hash('sha256',$actor.'|'.$assetId);$existing=RecordStore::get('download_resume',$id);
        return RecordStore::put('download_resume',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'offset'=>$offset,'device_hash'=>Utils::hashReference($device),'object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'reauthorize'=>true,'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
    }
    public static function resumeGrant(string $assetId,int $actor,string $sessionId,string $deviceId,array $context=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);$sessionId=Utils::text($sessionId,128);$deviceId=Utils::text($deviceId,191);
        if($sessionId===''||$deviceId==='')throw new Error('download_resume_context_required','Session and device identity are required.',400);
        $state=RecordStore::get('download_resume',hash('sha256',$actor.'|'.$assetId));
        if(!$state||($state['status']??'')!=='active'||(int)$state['actor_id']!==$actor||!hash_equals((string)$state['device_hash'],Utils::hashReference($deviceId)))throw new Error('download_resume_state_invalid','Resumable download state is unavailable for this device.',403);
        $asset=Future40Registry::asset($assetId,true);
        if((int)($state['object_version']??0)!==(int)$asset['object_version']||!hash_equals((string)($state['policy_hash']??''),(string)$asset['policy_hash'])||!hash_equals((string)($state['rights_hash']??''),(string)$asset['rights']['policy_hash']))throw new Error('download_resume_state_stale','Resumable download state is stale.',409);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',['asset'=>$asset,'actor_id'=>$actor,'mode'=>'resume','offset'=>(int)$state['offset'],'device_hash'=>$state['device_hash'],'session_id'=>$sessionId,'context'=>Utils::redact($context)]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Resume authorization is stale.',409);
        $audience=['type'=>'user','user_id'=>$actor];$deliveryContext=array_replace($context,['audience_type'=>'user','territory'=>$context['territory']??'GLOBAL']);
        $token=DeliveryService::issue($assetId,null,$actor,'future40-resume',$audience,$deliveryContext,'download',['allow_ranges'=>true,'max_range_bytes'=>(int)$asset['policy']['delivery']['max_range_bytes']],$sessionId,300,20);
        return ['asset_id'=>$assetId,'offset'=>(int)$state['offset'],'token'=>$token,'session_id'=>$sessionId,'device_hash'=>$state['device_hash'],'reauthorized'=>true];
    }
}

final class ResidencyCryptoService {
    private static function policy(string $assetId): ?array {return RecordStore::get('residency_policy',hash('sha256',$assetId));}
    public static function hasPolicy(string $assetId): bool {return self::policy($assetId)!==null;}
    public static function residency(string $assetId,int $actor,array $regions): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$asset=Future40Registry::asset($assetId);
        $regions=array_values(array_unique(array_filter(array_map(fn($v)=>strtoupper(Utils::text((string)$v,16)),$regions))));
        if($regions===[])throw new Error('residency_regions_required','At least one approved residency region is required.',400);
        $providerIds=[];$sourceProvider=Utils::key((string)($asset['storage']['provider_id']??''),64);if($sourceProvider!=='')$providerIds[$sourceProvider]=true;
        foreach(DerivativeService::forAsset($assetId) as $derivative){if(($derivative['status']??'')==='deleted')continue;$pid=Utils::key((string)($derivative['storage']['provider_id']??''),64);if($pid!=='')$providerIds[$pid]=true;}
        foreach(array_keys($providerIds) as $providerId){$region=strtoupper(Utils::text((string)(ProviderRegistry::metadata($providerId)['region']??''),16));if($region===''||!in_array($region,$regions,true))throw new Error('residency_current_placement_denied','Existing asset placement is outside the requested residency policy.',409,['provider'=>$providerId,'region'=>$region]);}
        $id=hash('sha256',$assetId);$existing=RecordStore::get('residency_policy',$id);
        $row=RecordStore::put('residency_policy',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'privacy_class'=>$asset['privacy_class'],'allowed_regions'=>$regions,'enforcement'=>'fail_closed','created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
        Audit::record('future40_residency_policy_set',['asset_id'=>$assetId,'regions'=>$regions,'actor_id'=>$actor]);return $row;
    }
    public static function assertRegion(string $assetId,string $region): void {
        $policy=self::policy($assetId);if(!$policy)return;$region=strtoupper(Utils::text($region,16));
        if($region===''||!in_array($region,(array)$policy['allowed_regions'],true))throw new Error('residency_region_denied','Storage/delivery region violates residency policy.',403,['region'=>$region]);
    }
    public static function assertProviderRegion(string $assetId,string $providerId): void {
        $policy=self::policy($assetId);if(!$policy)return;$providerId=Utils::key($providerId,64);
        if($providerId==='')throw new Error('residency_provider_unknown','Residency-controlled operation has no provider identity.',503);
        $region=(string)(ProviderRegistry::metadata($providerId)['region']??'');self::assertRegion($assetId,$region);
    }
    public static function assertPublicCdnAllowed(string $assetId): void {
        if(self::hasPolicy($assetId))throw new Error('residency_cdn_region_unverified','Public CDN publication is denied while CDN residency is not provider-attested.',503);
    }
    public static function isLocked(string $assetId): bool {
        $lock=RecordStore::get('object_lock',hash('sha256',$assetId));
        return (bool)($lock&&($lock['status']??'')==='locked'&&(int)($lock['locked_until']??0)>Utils::now());
    }
    public static function assertUnlocked(string $assetId,string $operation): void {
        if(self::isLocked($assetId)){$lock=RecordStore::get('object_lock',hash('sha256',$assetId));throw new Error('object_lock_active','Physical mutation is blocked by active WORM/object lock.',423,['operation'=>Utils::key($operation,64),'locked_until'=>(int)($lock['locked_until']??0)]);}
    }
    public static function objectLock(string $assetId,int $actor,int $until,string $reason): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_hold');$asset=Future40Registry::asset($assetId);$reason=Utils::text($reason,255);
        if($until<=Utils::now()||$reason==='')throw new Error('object_lock_invalid','Object-lock expiry and reason are required.',400);
        $id=hash('sha256',$assetId);$existing=RecordStore::get('object_lock',$id);
        if($existing&&($existing['status']??'')==='locked'&&(int)($existing['locked_until']??0)>Utils::now()&&$until<(int)$existing['locked_until'])throw new Error('object_lock_reduction_denied','An active compliance lock cannot be shortened.',409);
        $until=max($until,(int)($existing['locked_until']??0));$holds=LegalHoldService::active($assetId);
        $row=RecordStore::put('object_lock',$id,['actor_id'=>$actor,'status'=>'locked','asset_id'=>$assetId,'locked_until'=>$until,'reason'=>$reason,'mode'=>'compliance','retention_class'=>(string)($asset['policy']['retention']['class']??''),'legal_hold_ids'=>array_values(array_map('strval',array_column($holds,'id'))),'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
        Audit::record('future40_object_lock_set',['asset_id'=>$assetId,'actor_id'=>$actor,'locked_until'=>$until,'retention_class'=>$row['retention_class'],'legal_hold_count'=>count($row['legal_hold_ids'])]);return $row;
    }
    public static function keyEnvelope(string $assetId,int $actor,string $keyId,string $algorithm='aes-256-gcm'): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$asset=Future40Registry::asset($assetId);$keyId=Utils::key($keyId,64);$algorithm=Utils::key($algorithm,32);
        if($keyId===''||$algorithm==='')throw new Error('asset_key_envelope_invalid','Key identity/algorithm are required.',400);Keyring::key($keyId);$actualKeyId=Utils::key((string)($asset['storage']['key_id']??''),64);if($actualKeyId===''||!hash_equals($actualKeyId,$keyId))throw new Error('asset_key_envelope_mismatch','Envelope key reference must match the asset current encrypted storage key.',409);if($algorithm!=='aes-256-gcm')throw new Error('asset_key_envelope_algorithm_mismatch','Envelope algorithm must match current encrypted storage format.',409);
        $id=hash('sha256',$assetId);$existing=RecordStore::get('asset_key_envelope',$id);
        return RecordStore::put('asset_key_envelope',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'key_id'=>$keyId,'algorithm'=>$algorithm,'asset_sha256'=>$asset['sha256'],'rotatable'=>true,'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
    }
    public static function cryptoAgility(int $actor,array $policy): array {
        Auth::assertActor($actor,'media_manage_providers');Utils::requireFields($policy,['approved_algorithms','minimum_key_bits','migration_window_seconds'],'crypto_policy_incomplete');$alg=array_values(array_unique(array_map(fn($v)=>Utils::key((string)$v,32),(array)$policy['approved_algorithms'])));
        if($alg===[]||(int)$policy['minimum_key_bits']<128||(int)$policy['migration_window_seconds']<3600)throw new Error('crypto_policy_invalid','Crypto-agility policy is invalid.',400);
        $id='global';$existing=RecordStore::get('crypto_agility',$id);return RecordStore::put('crypto_agility',$id,['actor_id'=>$actor,'status'=>'active','approved_algorithms'=>$alg,'minimum_key_bits'=>(int)$policy['minimum_key_bits'],'migration_window_seconds'=>(int)$policy['migration_window_seconds'],'required_test_vector'=>true,'created_at'=>$existing?($existing['created_at']??Utils::now()):Utils::now()],$existing?(int)$existing['version']:0);
    }
}

final class DisasterCostRoutingService {
    public static function disasterPlan(string $assetId,int $actor,array $input): array {RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$asset=Future40Registry::asset($assetId,true);Utils::requireFields($input,['primary_region','secondary_region','rpo_seconds','rto_seconds'],'dr_plan_incomplete');$primary=strtoupper(Utils::text((string)$input['primary_region'],16));$secondary=strtoupper(Utils::text((string)$input['secondary_region'],16));if($primary===$secondary)throw new Error('dr_regions_invalid','Primary and secondary regions must differ.',400);ResidencyCryptoService::assertRegion($assetId,$primary);ResidencyCryptoService::assertRegion($assetId,$secondary);$id=hash('sha256',$assetId);$existing=RecordStore::get('disaster_plan',$id);return RecordStore::put('disaster_plan',$id,['actor_id'=>$actor,'status'=>'planned','asset_id'=>$assetId,'primary_region'=>$primary,'secondary_region'=>$secondary,'rpo_seconds'=>max(0,(int)$input['rpo_seconds']),'rto_seconds'=>max(0,(int)$input['rto_seconds']),'integrity_hash'=>$asset['sha256'],'failback_required'=>true,'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);}
    public static function optimizeTier(string $assetId,array $metrics): array {$asset=Future40Registry::asset($assetId,true);$access=max(0,(int)($metrics['accesses_30d']??0));$age=max(0,(int)($metrics['age_days']??0));$hold=ResidencyCryptoService::isLocked($assetId);$tier=$hold?'archive_locked':($access>100?'hot':($access>10?'warm':($age>180?'archive':'cold')));return ['asset_id'=>$assetId,'recommended_tier'=>$tier,'automatic_move'=>!str_starts_with($tier,'archive_locked'),'reason'=>['accesses_30d'=>$access,'age_days'=>$age,'hold'=>$hold]];}
    public static function costEstimate(string $assetId,array $prices,array $plan): array {$asset=Future40Registry::asset($assetId);foreach(['storage_gb_month','transcode_minute','egress_gb'] as $p)if(!isset($prices[$p])||(float)$prices[$p]<0)throw new Error('cost_price_invalid','Cost price table is incomplete.',400,['price'=>$p]);$gb=max(0.000001,(int)$asset['size']/1073741824);$minutes=max(0.0,(float)($plan['minutes']??0));$egress=max(0.0,(float)($plan['egress_gb']??$gb));$cost=$gb*(float)$prices['storage_gb_month']+$minutes*(float)$prices['transcode_minute']+$egress*(float)$prices['egress_gb'];return ['asset_id'=>$assetId,'currency'=>Utils::key((string)($prices['currency']??'usd'),8),'estimated_cost'=>round($cost,6),'components'=>['storage_gb'=>$gb,'minutes'=>$minutes,'egress_gb'=>$egress],'estimate_only'=>true];}
    public static function autoRoute(array $providers,array $requirements): array {$eligible=[];foreach($providers as $p){if(($p['approved']??false)!==true||($p['healthy']??false)!==true)continue;$caps=(array)($p['capabilities']??[]);$missing=array_diff((array)($requirements['capabilities']??[]),$caps);if($missing!==[])continue;$region=strtoupper(Utils::text((string)($p['region']??''),16));if(isset($requirements['regions'])&&!in_array($region,(array)$requirements['regions'],true))continue;$eligible[]=['id'=>Utils::key((string)($p['id']??''),64),'region'=>$region,'cost'=>(float)($p['cost_score']??PHP_FLOAT_MAX),'latency'=>(float)($p['latency_score']??PHP_FLOAT_MAX)];}if($eligible===[])throw new Error('approved_provider_unavailable','No approved provider satisfies the routing requirements.',503);usort($eligible,fn($a,$b)=>($a['cost']+$a['latency'])<=>($b['cost']+$b['latency']));return ['provider'=>$eligible[0]['id'],'region'=>$eligible[0]['region'],'eligible_count'=>count($eligible),'decision'=>'approved_health_capability_cost_route'];}
}

final class OperationsFutureService {
    public static function qoe(string $name,float $value,array $labels=[]): array {return PrivacyTelemetry::metric($name,$value,$labels);}
    public static function chaos(int $actor,string $scenario,array $context=[]): array {Auth::assertActor($actor,'media_manage_providers');if(!(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true)){if(!function_exists('apply_filters')||apply_filters('scm_environment','production')!=='staging')throw new Error('chaos_staging_only','Chaos/fault injection is permitted only in approved staging/test.',403);} $scenario=Utils::key($scenario,64);$allowed=['scanner_down','object_store_timeout','corrupt_derivative','key_unavailable','cdn_purge_failure','queue_saturation','region_outage'];if(!in_array($scenario,$allowed,true))throw new Error('chaos_scenario_invalid','Chaos scenario is not approved.',400);$id=Utils::id('chaos');$row=RecordStore::put('chaos_exercise',$id,['actor_id'=>$actor,'status'=>'scheduled','scenario'=>$scenario,'context'=>Utils::redact($context),'rollback_required'=>true,'created_at'=>Utils::now()]);Audit::record('future40_chaos_scheduled',['scenario'=>$scenario,'exercise_id'=>$id]);return $row;}
    public static function migrationBundle(array $assetIds,int $actor,string $target): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$target=Utils::key($target,64);if($target===''||$assetIds===[])throw new Error('migration_bundle_invalid','Target and assets are required.',400);$items=[];
        foreach(array_values(array_unique(array_map('strval',$assetIds))) as $assetId){$asset=RecordStore::get('asset',$assetId);$tombstone=RecordStore::get('tombstone',$assetId);if(!$asset&&!$tombstone)throw new Error('migration_asset_unavailable','Migration bundle asset/tombstone is unavailable.',404,['asset_id'=>$assetId]);$items[]=['asset_id'=>$assetId,'sha256'=>(string)($asset['sha256']??''),'privacy_class'=>(string)($asset['privacy_class']??''),'policy_hash'=>(string)($asset['policy_hash']??($tombstone['policy_hash']??'')),'rights_hash'=>(string)($asset['rights']['policy_hash']??($tombstone['rights_hash']??'')),'status'=>(string)($asset['status']??($tombstone['status']??'deleted')),'object_version'=>(int)($asset['object_version']??($tombstone['object_version']??0)),'tombstone_hash'=>$tombstone?hash('sha256',Utils::canonicalJson($tombstone)):null,'tombstone_state'=>$tombstone?['status'=>$tombstone['status']??'deleted','deleted_at'=>$tombstone['deleted_at']??0,'backup_expiry_at'=>$tombstone['backup_expiry_at']??0]:null];}
        $kid=Keyring::activeId();$bundle=['format'=>'SCM-MIGRATION-1','target'=>$target,'signing_kid'=>$kid,'created_at'=>Utils::now(),'items'=>$items];$payload=Utils::canonicalJson($bundle);$bundle['signature']=hash_hmac('sha256',$payload,Keyring::hashKey($kid));$bundle['bundle_hash']=hash('sha256',$payload);$id=Utils::id('migration');RecordStore::put('migration_bundle',$id,['actor_id'=>$actor,'status'=>'signed','target'=>$target,'signing_kid'=>$kid,'bundle_hash'=>$bundle['bundle_hash'],'signature'=>$bundle['signature'],'item_count'=>count($items),'created_at'=>Utils::now()]);return ['bundle_id'=>$id,'bundle'=>$bundle];
    }
    public static function verifyMigrationBundle(array $bundle): bool {$signature=(string)($bundle['signature']??'');$kid=Utils::key((string)($bundle['signing_kid']??''),64);unset($bundle['signature'],$bundle['bundle_hash']);if($signature===''||$kid==='')return false;try{$key=Keyring::hashKey($kid);}catch(\Throwable){return false;}return hash_equals(hash_hmac('sha256',Utils::canonicalJson($bundle),$key),$signature);}
    public static function sdkManifest(): array {return ['sdk_contract'=>'CF04-MEDIA-SDK-1','contract_version'=>defined('SCM_CONTRACT_VERSION')?SCM_CONTRACT_VERSION:'unknown','base_routes'=>['POST /api/media/v1/uploads','GET /api/media/v1/assets/{id}','GET /media/d/{grant}'],'required_headers'=>['Idempotency-Key','X-SCM-Contract-Version'],'consumer_rules'=>['no_direct_bucket_access','no_provider_id_as_public_identity','owner_authorization_rechecked_at_action','events_are_facts_not_commands'],'future40'=>array_keys(Future40Registry::REQUIREMENTS)];}
}
