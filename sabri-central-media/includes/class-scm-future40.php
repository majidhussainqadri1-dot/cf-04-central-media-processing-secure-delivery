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
    private static function identity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null
        ];
    }
    private static function authorize(array $asset,int $actor): void {
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'provenance_credential']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Provenance authorization is stale.',409);
    }
    private static function field(mixed $value,int $max,string $code): string {
        if(!is_string($value)||strlen($value)>$max
            ||!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]*$/D',$value))
            throw new Error($code,'Provenance field must be bounded canonical text.',400);
        return $value;
    }
    public static function record(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        self::authorize($asset,$actor);
        Utils::requireFields($input,['origin','synthetic_state','tool','tool_version'],'provenance_incomplete');
        if(array_diff(array_keys($input),[
            'origin','synthetic_state','tool','tool_version','parent_asset_ids','transformations'])!==[])
            throw new Error('provenance_input_invalid','Provenance contains unapproved fields.',400);
        $synthetic=$input['synthetic_state'];
        if(!is_string($synthetic)||!in_array($synthetic,self::SYNTHETIC,true))
            throw new Error('synthetic_state_invalid','Synthetic-media state is invalid.',400);
        $origin=self::field($input['origin'],48,'provenance_input_invalid');
        $tool=self::field($input['tool'],96,'provenance_input_invalid');
        $toolVersion=self::field($input['tool_version'],64,'provenance_input_invalid');
        $rawParents=$input['parent_asset_ids']??[];
        $rawTransforms=$input['transformations']??[];
        if(!is_array($rawParents)||!array_is_list($rawParents)||count($rawParents)>32
            ||!is_array($rawTransforms)||!array_is_list($rawTransforms)||count($rawTransforms)>64)
            throw new Error('provenance_input_invalid','Provenance lineage list is malformed or unbounded.',400);
        $parents=[];$parentSnapshots=[];
        foreach($rawParents as $parent){
            $parentId=self::field($parent,96,'provenance_input_invalid');
            if($parentId===$assetId||in_array($parentId,$parents,true))
                throw new Error('provenance_input_invalid','Provenance parent identity is duplicated or cyclic.',400);
            $parentAsset=Future40Registry::asset($parentId,true);
            if(!Future40Registry::sameEnvelope($asset,$parentAsset))
                throw new Error('provenance_boundary_denied','Cross-policy provenance parent requires owning-domain migration.',403);
            self::authorize($parentAsset,$actor);
            $parents[]=$parentId;$parentSnapshots[$parentId]=self::identity($parentAsset);
        }
        $transforms=[];
        foreach($rawTransforms as $transform){
            $name=self::field($transform,64,'provenance_input_invalid');
            if(in_array($name,$transforms,true))
                throw new Error('provenance_input_invalid','Duplicate provenance transform.',400);
            $transforms[]=$name;
        }
        $payload=[
            'asset_id'=>$assetId,'asset_sha256'=>$asset['sha256'],
            'owner_domain'=>$asset['owner_domain'],
            'owner_object_hash'=>Utils::hashReference((string)$asset['owner_object']),
            'owner_object_version'=>$asset['object_version'],
            'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],
            'origin'=>$origin,'synthetic_state'=>$synthetic,'tool'=>$tool,
            'tool_version'=>$toolVersion,'parent_asset_ids'=>$parents,
            'transformations'=>$transforms,'created_at'=>Utils::now()];
        $credential=FutureAdapterRegistry::call('future_001',[
            'operation'=>'content_credentials','payload'=>$payload]);
        $format=$credential['format']??null;
        if(!is_string($format)||strlen($format)>64
            ||!preg_match('/^[a-z][a-z0-9_.:-]*$/D',$format)
            ||strlen(Utils::canonicalJson($credential))>4096)
            throw new Error('provenance_credential_invalid','Provider credential evidence is invalid.',503);
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during provenance generation.',409);
        self::authorize($latest,$actor);
        foreach($parentSnapshots as $parentId=>$snapshot){
            $current=Future40Registry::asset($parentId,true);
            if(self::identity($current)!==$snapshot)
                throw new Error('asset_state_changed','Provenance parent changed during generation.',409);
            self::authorize($current,$actor);
        }
        $id=hash('sha256',$assetId.'|'.Utils::canonicalJson($payload));
        $row=RecordStore::put('provenance_credential',$id,[
            'actor_id'=>$actor,'status'=>'pending_external_verification',
            'source_sha256'=>$asset['sha256'],'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'payload'=>$payload,'credential'=>$credential,
            'credential_hash'=>hash('sha256',Utils::canonicalJson($credential)),
            'verification_state'=>'external_pending']);
        Audit::record('future40_provenance_recorded',[
            'asset_id'=>$assetId,'synthetic_state'=>$synthetic,'credential_id'=>$id]);
        return $row;
    }
}

final class PerceptualMediaService {
    private static function identity(array $asset): array {
        return [
            $asset['id']??null,$asset['status']??null,$asset['sha256']??null,
            $asset['owner_domain']??null,$asset['owner_object']??null,
            $asset['object_version']??null,$asset['privacy_class']??null,
            $asset['policy_hash']??null,$asset['rights']['policy_hash']??null
        ];
    }
    private static function authorize(array $asset,int $actor): void {
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'perceptual_fingerprint']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Perceptual authorization is stale.',409);
    }
    private static function current(array $record,array $asset): bool {
        return ($record['status']??null)==='active'
            &&($record['asset_id']??null)===($asset['id']??null)
            &&($record['source_sha256']??null)===($asset['sha256']??null)
            &&is_int($record['owner_object_version']??null)
            &&$record['owner_object_version']===$asset['object_version']
            &&($record['owner_domain']??null)===($asset['owner_domain']??null)
            &&($record['privacy_class']??null)===($asset['privacy_class']??null)
            &&($record['policy_hash']??null)===($asset['policy_hash']??null)
            &&($record['rights_hash']??null)===($asset['rights']['policy_hash']??null);
    }
    public static function fingerprint(string $assetId,int $actor,string $algorithm,string $version): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);self::authorize($asset,$actor);
        $algorithm=Utils::key($algorithm,48);$version=Utils::text($version,32);
        if($algorithm===''||$version==='')throw new Error('perceptual_profile_invalid','Perceptual fingerprint profile is required.',400);
        $result=FutureAdapterRegistry::call('future_003',[
            'asset'=>['id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'media_class'=>$asset['media_class']],
            'algorithm'=>$algorithm,'version'=>$version
        ]);
        $raw=$result['fingerprint']??null;
        if(!is_string($raw)||trim($raw)===''||strlen($raw)>512)
            throw new Error('perceptual_fingerprint_invalid','Adapter fingerprint must be bounded text.',503);
        $value=Utils::text($raw,512);
        if($value==='')throw new Error('perceptual_fingerprint_invalid','Adapter fingerprint is empty.',503);
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during perceptual generation.',409);
        self::authorize($latest,$actor);
        $id=hash('sha256',$assetId.'|'.$algorithm.'|'.$version);
        return RecordStore::put('perceptual_fingerprint',$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_object_version'=>$asset['object_version'],
            'algorithm'=>$algorithm,'algorithm_version'=>$version,'fingerprint'=>$value,
            'privacy_class'=>$asset['privacy_class'],'owner_domain'=>$asset['owner_domain'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'created_at'=>Utils::now()
        ]);
    }
    public static function nearDuplicates(string $assetId,int $actor,float $threshold=0.9): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'audit_sabri_media');
        $asset=Future40Registry::asset($assetId,true);self::authorize($asset,$actor);
        if(!is_finite($threshold)||$threshold<0.5||$threshold>1.0)
            throw new Error('perceptual_threshold_invalid','Near-duplicate threshold must be within 0.5..1.0.',400);
        $all=RecordStore::all('perceptual_fingerprint',0,'active',100000);
        $source=array_values(array_filter($all,fn($r)=>self::current($r,$asset)));
        if($source===[])throw new Error('perceptual_fingerprint_missing','Current fingerprint is required first.',409);
        usort($source,fn($a,$b)=>(($b['updated_at']??0)<=>($a['updated_at']??0))?:strcmp((string)$a['id'],(string)$b['id']));
        $selected=$source[0];$matches=[];
        foreach($all as $row){
            if(($row['asset_id']??null)===$assetId
                ||($row['algorithm']??null)!==$selected['algorithm']
                ||($row['algorithm_version']??null)!==$selected['algorithm_version'])continue;
            $other=RecordStore::get('asset',(string)($row['asset_id']??''));
            if(!$other||($other['status']??null)!=='ready'
                ||!Future40Registry::sameEnvelope($asset,$other)||!self::current($row,$other))continue;
            try{self::authorize($other,$actor);}
            catch(Error $e){if($e->errorCode==='domain_authorization_denied')continue;throw $e;}
            $score=FutureAdapterRegistry::call('future_004',[
                'left'=>$selected['fingerprint'],'right'=>$row['fingerprint'],'threshold'=>$threshold]);
            $raw=$score['similarity']??null;
            if(!(is_int($raw)||is_float($raw)))
                throw new Error('perceptual_similarity_invalid','Similarity must be numeric.',503);
            $similarity=(float)$raw;
            if(!is_finite($similarity)||$similarity<0.0||$similarity>1.0)
                throw new Error('perceptual_similarity_invalid','Similarity must be finite and normalized.',503);
            $freshSource=Future40Registry::asset($assetId,true);
            $freshOther=RecordStore::get('asset',(string)$other['id']);
            if(self::identity($freshSource)!==self::identity($asset)
                ||!is_array($freshOther)||self::identity($freshOther)!==self::identity($other))
                throw new Error('asset_state_changed','Asset changed during perceptual comparison.',409);
            if($similarity>=$threshold)$matches[]=['asset_id'=>$other['id'],'similarity'=>$similarity];
        }
        usort($matches,fn($a,$b)=>$b['similarity']<=>$a['similarity']);return $matches;
    }
    public static function dedupeDecision(string $assetId,string $candidateId,int $actor): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');
        $asset=Future40Registry::asset($assetId,true);
        $candidate=Future40Registry::asset($candidateId,true);
        self::authorize($asset,$actor);self::authorize($candidate,$actor);
        $assetRetention=(string)($asset['policy']['retention']['class']??'');
        $candidateRetention=(string)($candidate['policy']['retention']['class']??'');
        $allowed=$asset['sha256']===$candidate['sha256']
            &&Future40Registry::sameEnvelope($asset,$candidate)
            &&$assetRetention!==''&&hash_equals($assetRetention,$candidateRetention);
        $freshAsset=Future40Registry::asset($assetId,true);
        $freshCandidate=Future40Registry::asset($candidateId,true);
        if(self::identity($freshAsset)!==self::identity($asset)
            ||self::identity($freshCandidate)!==self::identity($candidate))
            throw new Error('asset_state_changed','Asset changed before dedupe decision.',409);
        $row=['actor_id'=>$actor,'status'=>$allowed?'eligible':'denied',
            'asset_id'=>$assetId,'candidate_asset_id'=>$candidateId,
            'source_sha256'=>$asset['sha256'],'candidate_sha256'=>$candidate['sha256'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'reason'=>$allowed?'same_binary_same_policy_envelope':'boundary_or_binary_mismatch',
            'created_at'=>Utils::now()];
        RecordStore::put('dedupe_decision',hash('sha256',$assetId.'|'.$candidateId),$row);
        return $row;
    }
}

final class ContentSafetyUpgradeService {
    private static function identity(array $asset): array {
        return [$asset['id']??null,$asset['status']??null,$asset['sha256']??null,
            $asset['owner_domain']??null,$asset['owner_object']??null,
            $asset['object_version']??null,$asset['privacy_class']??null,
            $asset['policy_hash']??null,$asset['rights']['policy_hash']??null];
    }
    public static function disarm(string $assetId,int $actor,string $profile): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        $profile=Utils::key($profile,64);
        if($profile==='')throw new Error('cdr_profile_invalid','CDR profile is required.',400);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'content_disarm']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','CDR owner authorization is stale.',409);
        $result=FutureAdapterRegistry::call('future_006',[
            'asset_id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'profile'=>$profile]);
        $hash=$result['safe_sha256']??null;
        if(!is_string($hash)||!preg_match('/^[a-fA-F0-9]{64}$/D',$hash))
            throw new Error('cdr_output_invalid','Safe reconstruction hash must be exact SHA-256.',503);
        $removed=$result['removed_features']??[];
        if(!is_array($removed)||!array_is_list($removed)||count($removed)>64)
            throw new Error('cdr_output_invalid','Removed-feature evidence must be a bounded list.',503);
        $safe=[];
        foreach($removed as $feature){
            if(!is_string($feature)||$feature===''||strlen($feature)>64)
                throw new Error('cdr_output_invalid','Removed-feature evidence contains invalid text.',503);
            $normalized=Utils::key($feature,64);
            if($normalized===''||in_array($normalized,$safe,true))
                throw new Error('cdr_output_invalid','Removed-feature evidence contains duplicate/invalid names.',503);
            $safe[]=$normalized;
        }
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during content disarm.',409);
        $decision=DomainRegistry::decision((string)$latest['owner_domain'],'authorize_reprocess',
            ['asset'=>$latest,'actor_id'=>$actor,'transform'=>'content_disarm']);
        if((int)$decision['object_version']!==(int)$latest['object_version'])
            throw new Error('domain_object_version_stale','CDR owner authorization changed.',409);
        $id=hash('sha256',$assetId.'|'.$profile.'|'.$asset['sha256'].'|'.$asset['object_version'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']);
        $row=RecordStore::put('cdr_result',$id,[
            'actor_id'=>$actor,'status'=>'validated','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'safe_sha256'=>strtolower($hash),'removed_features'=>$safe,
            'profile'=>$profile,'created_at'=>Utils::now()]);
        Audit::record('future40_cdr_completed',['asset_id'=>$assetId,'profile'=>$profile]);return $row;
    }
    public static function scheduleRescan(string $reason,array $filters=[],int $limit=1000,int $actor=0): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        Auth::capability('media_reprocess');
        $reason=Utils::key($reason,96);
        if($reason==='')throw new Error('rescan_reason_required','Re-scan reason is required.',400);
        $limit=max(1,min(5000,$limit));$queued=0;$scanned=0;$offset=0;$pageSize=500;
        while($queued<$limit&&$scanned<1000000){
            $page=RecordStore::list('asset',0,null,$pageSize,$offset);
            if($page===[])break;
            $offset+=count($page);$scanned+=count($page);
            foreach($page as $asset){
                if($queued>=$limit)break;
                if(in_array(($asset['status']??''),['deleted','rejected'],true))continue;
                if(isset($filters['mime'])&&($asset['mime']??null)!==$filters['mime'])continue;
                if(isset($filters['media_class'])&&($asset['media_class']??null)!==$filters['media_class'])continue;
                $sha=(string)($asset['sha256']??'');$policy=(string)($asset['policy_hash']??'');
                $rights=(string)($asset['rights']['policy_hash']??'');$version=$asset['object_version']??null;
                if(!preg_match('/^[a-f0-9]{64}$/D',$sha)||!is_int($version)||$version<1||$policy===''||$rights==='')
                    throw new Error('rescan_asset_invalid','Re-scan source identity is incomplete.',503);
                $id=hash('sha256',$asset['id'].'|'.$reason.'|'.$sha.'|'.$version.'|'.$policy.'|'.$rights);
                if(RecordStore::get('future_rescan',$id))continue;
                try{
                    RecordStore::put('future_rescan',$id,[
                        'actor_id'=>$actor,'status'=>'queued','asset_id'=>$asset['id'],
                        'source_sha256'=>$sha,'owner_object_version'=>$version,
                        'policy_hash'=>$policy,'rights_hash'=>$rights,
                        'reason'=>$reason,'requested_at'=>Utils::now()
                    ],0);$queued++;
                }catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}
            }
            if(count($page)<$pageSize)break;
        }
        $truncated=$scanned>=1000000&&RecordStore::list('asset',0,null,1,$offset)!==[];
        Audit::record('future40_rescan_scheduled',[
            'actor_id'=>$actor,'reason'=>$reason,'queued'=>$queued,'scanned'=>$scanned,'scan_truncated'=>$truncated]);
        return ['queued'=>$queued,'reason'=>$reason,'scanned'=>$scanned,'scan_truncated'=>$truncated];
    }
    public static function killSwitch(string $format,int $actor,bool $enabled,string $reason): array {
        Auth::assertActor($actor,'media_manage_providers');Auth::capability('media_manage_providers');
        $format=strtolower(Utils::text($format,96));$reason=Utils::text($reason,255);
        if($format===''||$reason==='')throw new Error('kill_switch_invalid','Format and reason are required.',400);
        $id=hash('sha256',$format);$existing=RecordStore::get('format_kill_switch',$id);
        $row=RecordStore::put('format_kill_switch',$id,[
            'actor_id'=>$actor,'status'=>$enabled?'blocked':'released','format'=>$format,
            'reason'=>$reason,'updated_by'=>$actor,
            'created_at'=>$existing?($existing['created_at']??Utils::now()):Utils::now()
        ],$existing?(int)$existing['version']:0);
        Audit::record('future40_format_kill_switch',[
            'format'=>$format,'enabled'=>$enabled,'reason'=>$reason]);return $row;
    }
    public static function assertAllowed(string $format): void {
        $row=RecordStore::get('format_kill_switch',hash('sha256',strtolower(Utils::text($format,96))));
        if($row&&($row['status']??'')==='blocked')
            throw new Error('format_emergency_blocked','Format is disabled by emergency security policy.',503,['format'=>$format]);
    }
}

final class MediaOptimizationService {
    private static function audioNumericMap(mixed $values,array $bounds,string $code): array {
        if(!is_array($values)||!$values||count($values)>count($bounds))
            throw new Error($code,'Audio QC numeric evidence must be a nonempty bounded map.',400);
        $safe=[];
        foreach($values as $name=>$value){
            if(!is_string($name)||!array_key_exists($name,$bounds)
                ||!(is_int($value)||is_float($value))||!is_finite((float)$value)
                ||$value<$bounds[$name][0]||$value>$bounds[$name][1])
                throw new Error($code,'Audio QC numeric evidence contains invalid metric or range.',400);
            $safe[$name]=(float)$value;
        }
        return $safe;
    }
    private static function audioIdentity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null
        ];
    }
    private static function authorizeAudio(array $asset,int $actor): void {
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'audio_qc']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Audio QC authorization is stale.',409);
    }
    public static function encodingPlan(string $assetId,int $actor,array $constraints=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'encoding_plan']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Encoding authorization is stale.',409);
        ContentSafetyUpgradeService::assertAllowed((string)$asset['mime']);
        $bounds=['max_bitrate'=>100000000,'min_bitrate'=>100000000,
            'max_width'=>16384,'max_height'=>16384,'max_renditions'=>32];
        $safeConstraints=[];
        foreach($constraints as $key=>$value){
            if(!is_string($key)||!isset($bounds[$key]))
                throw new Error('encoding_constraints_invalid','Unsupported encoding constraint.',400);
            $safeConstraints[$key]=Utils::integer($value,'encoding_constraints_invalid',1,$bounds[$key]);
        }
        if(isset($safeConstraints['min_bitrate'],$safeConstraints['max_bitrate'])
            &&$safeConstraints['min_bitrate']>$safeConstraints['max_bitrate'])
            throw new Error('encoding_constraints_invalid','Encoding bitrate bounds conflict.',400);
        $result=FutureAdapterRegistry::call('future_009',[
            'asset_id'=>$assetId,'sha256'=>$asset['sha256'],
            'media_class'=>$asset['media_class'],'size'=>$asset['size'],
            'constraints'=>$safeConstraints]);
        $raw=$result['renditions']??null;
        if(!is_array($raw)||!array_is_list($raw)||$raw===[]||count($raw)>32
            ||(isset($safeConstraints['max_renditions'])&&count($raw)>$safeConstraints['max_renditions']))
            throw new Error('encoding_plan_invalid','Encoding ladder shape/count is invalid.',503);
        $ladder=[];$seen=[];
        foreach($raw as $item){
            if(!is_array($item)||!is_string($item['kind']??null)
                ||array_diff(array_keys($item),['kind','bitrate','width','height','codec'])!==[])
                throw new Error('encoding_plan_invalid','Encoding rendition shape is invalid.',503);
            $kind=Utils::key($item['kind'],64);
            if($kind===''||isset($seen[$kind]))
                throw new Error('encoding_plan_invalid','Encoding rendition identity is invalid.',503);
            $bitrate=Utils::integer($item['bitrate']??null,'encoding_plan_invalid',1,100000000);
            if((isset($safeConstraints['max_bitrate'])&&$bitrate>$safeConstraints['max_bitrate'])
                ||(isset($safeConstraints['min_bitrate'])&&$bitrate<$safeConstraints['min_bitrate']))
                throw new Error('encoding_plan_invalid','Encoding bitrate violates constraints.',503);
            $entry=['kind'=>$kind,'bitrate'=>$bitrate];
            foreach(['width','height'] as $dimension){
                if(array_key_exists($dimension,$item)){
                    $entry[$dimension]=Utils::integer($item[$dimension],'encoding_plan_invalid',1,16384);
                    if(isset($safeConstraints['max_'.$dimension])
                        &&$entry[$dimension]>$safeConstraints['max_'.$dimension])
                        throw new Error('encoding_plan_invalid','Encoding dimension violates constraints.',503);
                }
            }
            if(array_key_exists('codec',$item)){
                if(!is_string($item['codec'])||Utils::key($item['codec'],32)==='')
                    throw new Error('encoding_plan_invalid','Encoding codec is invalid.',503);
                $entry['codec']=Utils::key($item['codec'],32);
            }
            $ladder[]=$entry;$seen[$kind]=true;
        }
        $complexity=$result['complexity']??null;
        if(!(is_int($complexity)||is_float($complexity))
            ||!is_finite((float)$complexity)||$complexity<0.0||$complexity>1.0)
            throw new Error('encoding_complexity_invalid','Encoding complexity must be normalized.',503);
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','privacy_class','policy_hash'] as $field)
            if(($latest[$field]??null)!==($asset[$field]??null))
                throw new Error('asset_state_changed','Asset changed during encoding planning.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Asset rights changed during encoding planning.',409);
        $decision=DomainRegistry::decision((string)$latest['owner_domain'],'authorize_reprocess',
            ['asset'=>$latest,'actor_id'=>$actor,'transform'=>'encoding_plan']);
        if((int)$decision['object_version']!==(int)$latest['object_version'])
            throw new Error('domain_object_version_stale','Encoding authorization changed.',409);
        $id=hash('sha256',$assetId.'|'.$asset['sha256'].'|'.$asset['object_version'].'|'
            .$asset['policy_hash'].'|'.$asset['rights']['policy_hash'].'|'.Utils::canonicalJson($safeConstraints));
        return RecordStore::put('encoding_plan',$id,[
            'actor_id'=>$actor,'status'=>'approved','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'renditions'=>$ladder,'complexity'=>(float)$complexity,
            'constraints'=>$safeConstraints,'created_at'=>Utils::now()]);
    }
    public static function qualityScore(string $assetId,string $derivativeId,int $actor,array $thresholds): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'quality_score']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Quality authorization is stale.',409);
        $derivative=RecordStore::get('derivative',$derivativeId);
        if(!$derivative||($derivative['asset_id']??null)!==$assetId
            ||($derivative['status']??null)!=='validated'||!empty($derivative['superseded_by'])
            ||($derivative['lineage']['source_sha256']??null)!==$asset['sha256']
            ||(int)($derivative['lineage']['processing_generation']??0)!==(int)($asset['processing_generation']??1))
            throw new Error('derivative_not_validated','Current validated derivative is required.',409);
        $limits=['vmaf_min'=>100.0,'ssim_min'=>1.0,'psnr_min'=>100.0];
        if($thresholds===[])throw new Error('quality_threshold_invalid','Quality thresholds required.',400);
        $safeThresholds=[];
        foreach($thresholds as $name=>$value){
            if(!is_string($name)||!isset($limits[$name])||!(is_int($value)||is_float($value))
                ||!is_finite((float)$value)||$value<0||$value>$limits[$name])
                throw new Error('quality_threshold_invalid','Quality threshold invalid.',400);
            $safeThresholds[$name]=(float)$value;
        }
        $result=FutureAdapterRegistry::call('future_010',[
            'asset_sha256'=>$asset['sha256'],'derivative_sha256'=>$derivative['sha256'],
            'kind'=>$derivative['kind'],'thresholds'=>$safeThresholds]);
        $raw=$result['metrics']??null;
        if(!is_array($raw)||$raw===[])throw new Error('quality_metric_invalid','Quality metrics missing.',503);
        $metricLimits=['vmaf'=>100.0,'ssim'=>1.0,'psnr'=>100.0];$metrics=[];
        foreach($raw as $name=>$value){
            if(!is_string($name)||!isset($metricLimits[$name])
                ||!(is_int($value)||is_float($value))||!is_finite((float)$value)
                ||$value<0||$value>$metricLimits[$name])
                throw new Error('quality_metric_invalid','Quality metric invalid.',503);
            $metrics[$name]=(float)$value;
        }
        $passed=($result['passed']??false)===true;
        foreach($safeThresholds as $name=>$minimum){
            $metric=substr($name,0,-4);
            if(!isset($metrics[$metric])||$metrics[$metric]<$minimum)$passed=false;
        }
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','policy_hash','privacy_class'] as $field)
            if(($latest[$field]??null)!==($asset[$field]??null))
                throw new Error('asset_state_changed','Asset changed during quality scoring.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Asset rights changed during quality scoring.',409);
        $fresh=RecordStore::get('derivative',$derivativeId);
        if(!$fresh||($fresh['version']??null)!==($derivative['version']??null)
            ||($fresh['sha256']??null)!==($derivative['sha256']??null)
            ||($fresh['status']??null)!=='validated'||!empty($fresh['superseded_by']))
            throw new Error('derivative_state_changed','Derivative changed during quality scoring.',409);
        $id=hash('sha256',$assetId.'|'.$derivativeId.'|'.$asset['sha256'].'|'
            .$asset['object_version'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']);
        $row=RecordStore::put('quality_score',$id,[
            'actor_id'=>$actor,'status'=>$passed?'passed':'failed','asset_id'=>$assetId,
            'derivative_id'=>$derivativeId,'source_sha256'=>$asset['sha256'],
            'derivative_sha256'=>$derivative['sha256'],'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'metrics'=>$metrics,'thresholds'=>$safeThresholds,'created_at'=>Utils::now()]);
        if(!$passed)throw new Error('derivative_quality_failed','Derivative failed objective quality policy.',409,['derivative_id'=>$derivativeId]);
        return $row;
    }
    public static function negotiateCodec(array $client,array $approved): string {
        $normalize=static function(array $values): array {
            if(!array_is_list($values)||count($values)>64)
                throw new Error('codec_profile_invalid','Codec list shape/count invalid.',400);
            $out=[];
            foreach($values as $value){
                if(!is_string($value)||strlen($value)<1||strlen($value)>32
                    ||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D',$value))
                    throw new Error('codec_profile_invalid','Codec identifier must be canonical text.',400);
                $codec=strtolower($value);
                if(in_array($codec,$out,true))
                    throw new Error('codec_profile_invalid','Duplicate codec identifier.',400);
                $out[]=$codec;
            }
            return $out;
        };
        $client=$normalize($client);$approved=$normalize($approved);
        if($approved===[])throw new Error('codec_profile_invalid','Approved codec list required.',400);
        foreach($approved as $codec)if(in_array($codec,$client,true))return $codec;
        throw new Error('codec_unavailable','No approved compatible codec is available.',406);
    }
    public static function colorPolicy(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>'color_policy']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Color policy authorization is stale.',409);
        Utils::requireFields($input,['source_space','target_space','hdr_mode','icc_hash'],'color_policy_incomplete');
        $spaces=['srgb','bt709','bt2020','display-p3','rec709','rec2020'];
        $modes=['sdr','hdr10','hlg','tone_map','passthrough'];
        $toneMaps=['none','approved-v1','hable','reinhard'];
        foreach(['source_space','target_space','hdr_mode'] as $field)
            if(!is_string($input[$field]))throw new Error('color_policy_invalid','Color fields must be text.',400);
        $source=Utils::key($input['source_space'],32);
        $target=Utils::key($input['target_space'],32);
        $mode=Utils::key($input['hdr_mode'],32);
        if(!in_array($source,$spaces,true)||!in_array($target,$spaces,true)
            ||!in_array($mode,$modes,true))
            throw new Error('color_policy_invalid','Color space/HDR mode is not approved.',400);
        $tone=$input['tone_map']??'none';
        if(!is_string($tone))throw new Error('color_policy_invalid','Tone-map profile must be text.',400);
        $tone=Utils::key($tone,32);
        if(!in_array($tone,$toneMaps,true)||($mode==='tone_map'&&$tone==='none'))
            throw new Error('color_policy_invalid','Tone-map profile is invalid.',400);
        $icc=$input['icc_hash'];
        if(!is_string($icc)||($icc!==''&&!preg_match('/^[a-fA-F0-9]{64}$/D',$icc)))
            throw new Error('icc_hash_invalid','ICC hash must be exact SHA-256 or empty.',400);
        $icc=strtolower($icc);
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','policy_hash','privacy_class'] as $field)
            if(($latest[$field]??null)!==($asset[$field]??null))
                throw new Error('asset_state_changed','Asset changed during color policy.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Asset rights changed during color policy.',409);
        $id=hash('sha256',$assetId.'|'.$asset['sha256'].'|'.$asset['object_version'].'|'
            .$asset['policy_hash'].'|'.$asset['rights']['policy_hash']);
        return RecordStore::put('color_policy',$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'source_space'=>$source,'target_space'=>$target,'hdr_mode'=>$mode,
            'icc_hash'=>$icc,'tone_map'=>$tone,'created_at'=>Utils::now()]);
    }
    public static function audioQc(string $assetId,int $actor,array $thresholds=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        if(!in_array($asset['media_class'],['audio','video'],true))
            throw new Error('audio_qc_not_applicable','Audio QC is only applicable to audio/video assets.',400);
        self::authorizeAudio($asset,$actor);
        $thresholdBounds=[
            'lufs_target'=>[-70,0],'lufs_min'=>[-70,0],'lufs_max'=>[-70,0],
            'peak_db_max'=>[-100,20],'true_peak_db_max'=>[-100,20],
            'clipping_ratio_max'=>[0,1],'silence_ratio_max'=>[0,1]
        ];
        $safeThresholds=self::audioNumericMap($thresholds,$thresholdBounds,'audio_qc_threshold_invalid');
        if(isset($safeThresholds['lufs_min'],$safeThresholds['lufs_max'])
            &&$safeThresholds['lufs_min']>$safeThresholds['lufs_max'])
            throw new Error('audio_qc_threshold_invalid','Audio loudness bounds conflict.',400);
        $result=FutureAdapterRegistry::call('future_013',[
            'asset_id'=>$assetId,'sha256'=>$asset['sha256'],'thresholds'=>$safeThresholds]);
        $metricBounds=['lufs'=>[-70,0],'peak_db'=>[-100,20],
            'true_peak_db'=>[-100,20],'clipping_ratio'=>[0,1],'silence_ratio'=>[0,1],
            'sample_rate_hz'=>[8000,384000]];
        $metrics=self::audioNumericMap($result['metrics']??null,$metricBounds,'audio_qc_metric_invalid');
        if(!is_bool($result['passed']??null))
            throw new Error('audio_qc_metric_invalid','Audio QC pass evidence must be boolean.',503);
        $issues=$result['issues']??[];
        if(!is_array($issues)||!array_is_list($issues)||count($issues)>64)
            throw new Error('audio_qc_issue_invalid','Audio QC issues must be a bounded list.',503);
        $safeIssues=[];
        foreach($issues as $issue){
            if(!is_string($issue)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$issue)
                ||in_array($issue,$safeIssues,true))
                throw new Error('audio_qc_issue_invalid','Audio QC issue identifier is invalid.',503);
            $safeIssues[]=$issue;
        }
        $passed=$result['passed']===true&&$safeIssues===[];
        foreach($safeThresholds as $name=>$limit){
            $metric=str_ends_with($name,'_max')?substr($name,0,-4):
                (str_ends_with($name,'_min')?substr($name,0,-4):
                (str_ends_with($name,'_target')?substr($name,0,-7):$name));
            if(!array_key_exists($metric,$metrics))
                throw new Error('audio_qc_metric_invalid','Audio QC threshold metric missing.',503);
            if(str_ends_with($name,'_max')&&$metrics[$metric]>$limit)$passed=false;
            if(str_ends_with($name,'_min')&&$metrics[$metric]<$limit)$passed=false;
            if(str_ends_with($name,'_target')&&abs($metrics[$metric]-$limit)>2.0)$passed=false;
        }
        $latest=Future40Registry::asset($assetId,true);
        if(self::audioIdentity($latest)!==self::audioIdentity($asset))
            throw new Error('asset_state_changed','Asset changed during audio quality evaluation.',409);
        self::authorizeAudio($latest,$actor);
        $id=hash('sha256',$assetId.'|'.Utils::canonicalJson(self::audioIdentity($asset))
            .'|'.Utils::canonicalJson($safeThresholds));
        $row=RecordStore::put('audio_qc',$id,[
            'actor_id'=>$actor,'status'=>$passed?'passed':'failed','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_domain'=>$asset['owner_domain'],
            'owner_object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],
            'thresholds'=>$safeThresholds,'metrics'=>$metrics,'issues'=>$safeIssues,
            'created_at'=>Utils::now()]);
        if(!$passed)throw new Error('audio_qc_failed','Audio technical quality policy failed.',409);
        return $row;
    }
}

final class RichMediaMetadataService {
    private static function identity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null,
        ];
    }
    private static function authorizeTrack(array $asset,int $actor,string $type): void {
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>$type]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Owning-domain track authorization is stale.',409);
    }
    private static function putTrack(string $type,string $assetId,int $actor,array $input,array $required,?array $expectedAsset=null): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        if($expectedAsset!==null&&self::identity($asset)!==self::identity($expectedAsset))
            throw new Error('asset_state_changed','Asset changed during track generation.',409);
        self::authorizeTrack($asset,$actor,$type);
        Utils::requireFields($input,$required,'track_metadata_incomplete');
        $allowed=match($type){
            'smart_preview'=>['poster_ref','storyboard_ref','selection'],
            'chapter_manifest'=>['chapters'],
            'rich_caption'=>['locale','kind','content_hash','source'],
            'audio_description'=>['locale','track_ref','content_hash'],
            'sign_language_track'=>['locale','track_ref','synchronization_hash'],
            'ocr_coordinate_map'=>['pages'],
            default=>[]
        };
        if($allowed===[]||array_diff(array_keys($input),$allowed)!==[])
            throw new Error('track_metadata_invalid','Track metadata contains unsupported fields.',400);
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed before track persistence.',409);
        $id=hash('sha256',$assetId.'|'.$type.'|'.Utils::canonicalJson($input));
        return RecordStore::put($type,$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'owner_object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],'data'=>Utils::redact($input),
            'created_at'=>Utils::now()
        ]);
    }
    private static function hash256(mixed $value,string $code): string {
        if(!is_string($value)||!preg_match('/^[a-fA-F0-9]{64}$/D',$value))
            throw new Error($code,'Track integrity hash must be exactly SHA-256.',400);
        return strtolower($value);
    }
    private static function locale(mixed $value): string {
        if(!is_string($value))throw new Error('track_locale_invalid','Track locale must be text.',400);
        $locale=Utils::key($value,32);
        if(!preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D',$locale))
            throw new Error('track_locale_invalid','Track locale is invalid.',400);
        return $locale;
    }
    private static function trackRef(mixed $value,string $code): string {
        if(!is_string($value)||!preg_match('/^(?:asset|derivative):[A-Za-z0-9._:-]{1,160}$/D',$value))
            throw new Error($code,'Track reference must be an internal asset or derivative reference.',400);
        return $value;
    }
    public static function smartPreview(string $assetId,int $actor,array $profile): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_reprocess');
        $asset=Future40Registry::asset($assetId,true);
        self::authorizeTrack($asset,$actor,'smart_preview');
        $result=FutureAdapterRegistry::call('future_014',
            ['asset_id'=>$assetId,'sha256'=>$asset['sha256'],'profile'=>$profile]);
        $poster=self::trackRef($result['poster_ref']??null,'smart_preview_invalid');
        $storyboard=self::trackRef($result['storyboard_ref']??null,'smart_preview_invalid');
        $selection=$result['selection']??[];
        if(!is_array($selection)||array_diff(array_keys($selection),['method'])!==[]
            ||(array_key_exists('method',$selection)&&(!is_string($selection['method'])
                ||!in_array($selection['method'],['scene-change','first-frame','frame-sampling'],true))))
            throw new Error('smart_preview_invalid','Smart-preview selection metadata is invalid.',503);
        return self::putTrack('smart_preview',$assetId,$actor,
            ['poster_ref'=>$poster,'storyboard_ref'=>$storyboard,'selection'=>$selection],
            ['poster_ref','storyboard_ref'],$asset);
    }
    public static function chapters(string $assetId,int $actor,array $chapters): array {
        if($chapters===[])throw new Error('chapter_manifest_empty','At least one chapter is required.',400);
        if(!array_is_list($chapters)||count($chapters)>10000)throw new Error('chapter_manifest_invalid','Chapter list shape/count invalid.',400);
        $last=-1;$safe=[];
        foreach($chapters as $chapter){
            if(!is_array($chapter)||!is_string($chapter['title']??null))throw new Error('chapter_manifest_invalid','Chapter title invalid.',400);
            $start=Utils::integer($chapter['start_ms']??null,'chapter_manifest_invalid',0,86400000);
            $title=Utils::text($chapter['title'],160);
            if($start<=$last||$title==='')throw new Error('chapter_manifest_invalid','Chapter order/title invalid.',400);
            $safe[]=['start_ms'=>$start,'title'=>$title];$last=$start;
        }
        return self::putTrack('chapter_manifest',$assetId,$actor,['chapters'=>$safe],['chapters']);
    }
    public static function captionTrack(string $assetId,int $actor,array $input): array {
        if(!is_string($input['kind']??null))throw new Error('caption_kind_invalid','Caption kind must be text.',400);
        $kind=Utils::key($input['kind'],32);
        if(!in_array($kind,['standard','sdh','forced'],true))
            throw new Error('caption_kind_invalid','Caption kind is invalid.',400);
        $input['kind']=$kind;$input['locale']=self::locale($input['locale']??null);
        if(!is_string($input['source']??null))throw new Error('caption_source_invalid','Caption source must be text.',400);
        $input['source']=Utils::key($input['source'],64);
        if(!in_array($input['source'],['human','machine','provider'],true))
            throw new Error('caption_source_invalid','Caption provenance source is invalid.',400);
        $input['content_hash']=self::hash256($input['content_hash']??null,'caption_hash_invalid');
        return self::putTrack('rich_caption',$assetId,$actor,$input,['locale','kind','content_hash','source']);
    }
    public static function audioDescription(string $assetId,int $actor,array $input): array {
        $input['locale']=self::locale($input['locale']??null);
        $input['track_ref']=self::trackRef($input['track_ref']??null,'track_reference_invalid');
        $input['content_hash']=self::hash256($input['content_hash']??null,'audio_description_hash_invalid');
        return self::putTrack('audio_description',$assetId,$actor,$input,['locale','track_ref','content_hash']);
    }
    public static function signLanguage(string $assetId,int $actor,array $input): array {
        $input['locale']=self::locale($input['locale']??null);
        $input['track_ref']=self::trackRef($input['track_ref']??null,'track_reference_invalid');
        $input['synchronization_hash']=self::hash256($input['synchronization_hash']??null,'sign_language_hash_invalid');
        return self::putTrack('sign_language_track',$assetId,$actor,$input,['locale','track_ref','synchronization_hash']);
    }
    public static function ocrMap(string $assetId,int $actor,array $pages): array {
        if($pages===[])throw new Error('ocr_map_empty','OCR coordinate map is empty.',400);
        if(!array_is_list($pages)||count($pages)>500)throw new Error('ocr_map_invalid','OCR page list invalid.',400);
        $last=0;$total=0;$safe=[];
        foreach($pages as $page){
            if(!is_array($page)||!is_array($page['items']??null)||!array_is_list($page['items']))
                throw new Error('ocr_map_invalid','OCR page items invalid.',400);
            $number=Utils::integer($page['page']??null,'ocr_map_invalid',1,500);
            if($number<=$last||count($page['items'])>2000)throw new Error('ocr_map_invalid','OCR page order/count invalid.',400);
            $last=$number;$total+=count($page['items']);
            if($total>10000)throw new Error('ocr_map_invalid','OCR item volume invalid.',400);
            $items=[];
            foreach($page['items'] as $item){
                if(!is_array($item)||!is_string($item['text']??null)||trim($item['text'])===''||strlen($item['text'])>1024
                    ||!(is_int($item['confidence']??null)||is_float($item['confidence']??null))
                    ||!is_array($item['box']??null)||!array_is_list($item['box'])||count($item['box'])!==4)
                    throw new Error('ocr_map_invalid','OCR item shape invalid.',400);
                $confidence=(float)$item['confidence'];
                if(!is_finite($confidence)||$confidence<0||$confidence>1)throw new Error('ocr_map_invalid','OCR confidence invalid.',400);
                $box=[];
                foreach($item['box'] as $coordinate){
                    if(!(is_int($coordinate)||is_float($coordinate)))throw new Error('ocr_map_invalid','OCR coordinate type invalid.',400);
                    $v=(float)$coordinate;
                    if(!is_finite($v)||$v<0||$v>1)throw new Error('ocr_map_invalid','OCR coordinate range invalid.',400);
                    $box[]=$v;
                }
                if($box[2]<=0||$box[3]<=0||$box[0]+$box[2]>1.00000001||$box[1]+$box[3]>1.00000001)
                    throw new Error('ocr_map_invalid','OCR box geometry invalid.',400);
                $items[]=['text'=>$item['text'],'confidence'=>$confidence,'box'=>$box];
            }
            $safe[]=['page'=>$number,'items'=>$items];
        }
        return self::putTrack('ocr_coordinate_map',$assetId,$actor,['pages'=>$safe],['pages']);
    }
    private static function currentTrack(array $row,array $asset,string $status): bool {
        return ($row['asset_id']??null)===($asset['id']??null)
            &&($row['status']??null)===$status
            &&is_int($row['owner_object_version']??null)
            &&$row['owner_object_version']===$asset['object_version']
            &&is_string($row['policy_hash']??null)
            &&hash_equals((string)$asset['policy_hash'],$row['policy_hash'])
            &&is_string($row['rights_hash']??null)
            &&hash_equals((string)$asset['rights']['policy_hash'],$row['rights_hash']);
    }
    public static function accessibilityManifest(string $assetId): array {
        RuntimeGuard::requireReady();$actor=Auth::requireUser();
        $asset=Future40Registry::asset($assetId,true);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_delivery',
            ['asset'=>$asset,'actor_id'=>$actor,'purpose'=>'accessibility_manifest']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Manifest authorization is stale.',409);
        $collect=[];
        foreach(['accessibility_metadata','rich_caption','audio_description','sign_language_track','ocr_coordinate_map','chapter_manifest'] as $type){
            $status=$type==='accessibility_metadata'?'approved':'active';
            $collect[$type]=array_values(array_filter(RecordStore::all($type,0,null,100000),
                fn($row)=>self::currentTrack($row,$asset,$status)));
        }
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during manifest projection.',409);
        $manifest=['asset_id'=>$assetId,'privacy_class'=>$asset['privacy_class'],
            'object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],'tracks'=>$collect,
            'complete'=>($collect['accessibility_metadata']!==[]||$collect['rich_caption']!==[])];
        $manifest['manifest_hash']=hash('sha256',Utils::canonicalJson($manifest));
        $manifest['generated_at']=Utils::now();
        return $manifest;
    }
}

final class SensitiveDataProtectionService {
    private static function identity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null
        ];
    }
    private static function authorize(array $asset,int $actor,string $operation): void {
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',
            ['asset'=>$asset,'actor_id'=>$actor,'transform'=>$operation]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Sensitive-data authorization is stale.',409);
    }
    private static function kind(mixed $value,string $code): string {
        if(!is_string($value)||!preg_match('/^[a-z][a-z0-9_]{0,31}$/D',$value))
            throw new Error($code,'Sensitive-data category must be a canonical bounded identifier.',400);
        return $value;
    }
    private static function region(mixed $value,string $code): array {
        if(!is_array($value)||!array_is_list($value)||count($value)!==4)
            throw new Error($code,'Region must be a four-element coordinate list.',400);
        $box=[];
        foreach($value as $coordinate){
            if(!(is_int($coordinate)||is_float($coordinate))||!is_finite((float)$coordinate)
                ||$coordinate<0||$coordinate>10000000)
                throw new Error($code,'Region coordinates must be finite bounded numbers.',400);
            $box[]=(float)$coordinate;
        }
        if($box[2]<=0||$box[3]<=0)
            throw new Error($code,'Region dimensions must be positive.',400);
        return $box;
    }
    private static function classes(array $classes): array {
        if(!array_is_list($classes)||count($classes)>32)
            throw new Error('sensitive_classes_invalid','Detection classes must be a bounded list.',400);
        $safe=[];
        foreach($classes as $class){
            $kind=self::kind($class,'sensitive_classes_invalid');
            if(in_array($kind,$safe,true))
                throw new Error('sensitive_classes_invalid','Duplicate detection class.',400);
            $safe[]=$kind;
        }
        return $safe;
    }
    private static function signals(mixed $signals): array {
        if(!is_array($signals)||!array_is_list($signals)||count($signals)>256)
            throw new Error('sensitive_signal_invalid','Detection signals must be a bounded list.',503);
        $safe=[];
        foreach($signals as $signal){
            if(!is_array($signal)||array_diff(array_keys($signal),['type','confidence','region'])!==[])
                throw new Error('sensitive_signal_invalid','Detection signal contains unapproved fields.',503);
            $kind=self::kind($signal['type']??null,'sensitive_signal_invalid');
            $confidence=$signal['confidence']??null;
            if(!(is_int($confidence)||is_float($confidence))||!is_finite((float)$confidence)
                ||$confidence<0||$confidence>1)
                throw new Error('sensitive_signal_invalid','Detection confidence is invalid.',503);
            $item=['type'=>$kind,'confidence'=>(float)$confidence];
            if(array_key_exists('region',$signal))
                $item['region']=self::region($signal['region'],'sensitive_signal_invalid');
            $safe[]=$item;
        }
        return $safe;
    }
    private static function regions(array $regions): array {
        if($regions===[]||!array_is_list($regions)||count($regions)>256)
            throw new Error('redaction_request_invalid','Redaction regions must be a nonempty bounded list.',400);
        $safe=[];
        foreach($regions as $region){
            if(!is_array($region)||array_diff(array_keys($region),['type','box','page'])!==[])
                throw new Error('redaction_request_invalid','Redaction region contains unapproved fields.',400);
            $item=['type'=>self::kind($region['type']??null,'redaction_request_invalid'),
                'box'=>self::region($region['box']??null,'redaction_request_invalid')];
            if(array_key_exists('page',$region))
                $item['page']=Utils::integer($region['page'],'redaction_request_invalid',1,10000);
            $safe[]=$item;
        }
        return $safe;
    }
    public static function detect(string $assetId,int $actor,array $classes=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_review_safety_signals');
        $asset=Future40Registry::asset($assetId,true);
        self::authorize($asset,$actor,'sensitive_detection');
        $safeClasses=self::classes($classes);
        $result=FutureAdapterRegistry::call('future_021',[
            'asset_id'=>$assetId,'sha256'=>$asset['sha256'],'mime'=>$asset['mime'],'classes'=>$safeClasses]);
        $signals=self::signals($result['signals']??null);
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during sensitive-data detection.',409);
        self::authorize($latest,$actor,'sensitive_detection');
        $id=hash('sha256',$assetId.'|'.Utils::canonicalJson(self::identity($asset)).'|'.Utils::canonicalJson($safeClasses));
        return RecordStore::put('sensitive_signal',$id,[
            'actor_id'=>$actor,'status'=>$signals===[]?'clear':'review_required','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'owner_domain'=>$asset['owner_domain'],
            'owner_object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],'classes'=>$safeClasses,
            'signals'=>$signals,'created_at'=>Utils::now()]);
    }
    public static function redact(string $assetId,int $actor,array $regions,string $reason): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_review_safety_signals');
        $asset=Future40Registry::asset($assetId,true);
        $safeRegions=self::regions($regions);
        if(trim($reason)===''||strlen($reason)>255)
            throw new Error('redaction_request_invalid','Redaction reason must be nonempty bounded text.',400);
        $safeReason=Utils::text($reason,255);
        if($safeReason==='')throw new Error('redaction_request_invalid','Redaction reason is empty.',400);
        self::authorize($asset,$actor,'redaction');
        $result=FutureAdapterRegistry::call('future_022',[
            'asset_id'=>$assetId,'sha256'=>$asset['sha256'],
            'regions'=>$safeRegions,'reason'=>$safeReason]);
        $raw=$result['redacted_sha256']??null;
        if(!is_string($raw)||!preg_match('/^[a-fA-F0-9]{64}$/D',$raw))
            throw new Error('redaction_output_invalid','Redaction derivative hash must be exactly SHA-256.',503);
        $sha=strtolower($raw);
        $latest=Future40Registry::asset($assetId,true);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during redaction.',409);
        self::authorize($latest,$actor,'redaction');
        $row=RecordStore::put('redaction_derivative',Utils::id('redaction'),[
            'actor_id'=>$actor,'status'=>'approved','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'redacted_sha256'=>$sha,
            'regions'=>$safeRegions,'reason'=>$safeReason,'object_version'=>$asset['object_version'],
            'owner_domain'=>$asset['owner_domain'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],'created_at'=>Utils::now()]);
        Audit::record('future40_redaction_created',['asset_id'=>$assetId,'redaction_id'=>$row['id']]);
        return $row;
    }
}

final class DeliveryResilienceService {
    private static function controlAsset(string $assetId,bool $public,string $purpose): array {
        RuntimeGuard::requireReady();$actor=Auth::capability('media_manage_providers');
        $asset=Future40Registry::asset($assetId,true);
        if($public){
            if($asset['privacy_class']!=='C0')
                throw new Error($purpose==='route_cdn'?'multi_cdn_public_only':'origin_shield_public_only',
                    'Public delivery optimization is restricted to C0 assets.',403);
            if(($asset['policy']['delivery']['public_cdn']??false)!==true)
                throw new Error('public_cdn_policy_denied','Public CDN is not allowed by asset policy.',403);
            RightsPolicy::assert($asset['rights'],'view',['territory'=>'GLOBAL','audience_type'=>'public']);
        }else{
            if($asset['privacy_class']==='C0')
                throw new Error('edge_auth_not_required','Public C0 assets do not require restricted edge authorization.',400);
            RightsPolicy::assert($asset['rights'],'view',['territory'=>'GLOBAL','audience_type'=>'user']);
        }
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_delivery',[
            'asset'=>$asset,'actor_id'=>$actor,'purpose'=>$purpose]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Delivery control authorization is stale.',409);
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','policy_hash','privacy_class'] as $key)
            if(($latest[$key]??null)!==($asset[$key]??null))
                throw new Error('asset_state_changed','Delivery control asset state changed.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Delivery control rights changed.',409);
        return $asset;
    }
    public static function routeCdn(string $assetId,array $candidates): array {
        $asset=self::controlAsset($assetId,true,'route_cdn');
        if(!array_is_list($candidates)||$candidates===[]||count($candidates)>32)
            throw new Error('cdn_candidates_invalid','CDN candidates must be a nonempty bounded list.',400);
        $approved=[];$seen=[];
        foreach($candidates as $c){
            if(!is_array($c)||array_diff(array_keys($c),['id','approved','healthy','priority','region'])!==[]
                ||!is_string($c['id']??null)||!preg_match('/^[a-z0-9][a-z0-9._:-]{0,63}$/D',$c['id'])
                ||!is_bool($c['approved']??null)||!is_bool($c['healthy']??null)
                ||!is_int($c['priority']??null)||$c['priority']<0||$c['priority']>1000
                ||!is_string($c['region']??null)||!preg_match('/^[A-Z]{2,16}$/D',$c['region']))
                throw new Error('cdn_candidates_invalid','CDN candidate contract is invalid.',400);
            $id=$c['id'];
            if(isset($seen[$id]))throw new Error('cdn_candidates_invalid','Duplicate CDN candidate.',400);
            $seen[$id]=true;
            if(!$c['approved'])continue;
            $region=$c['region'];
            if(ResidencyCryptoService::hasPolicy($assetId)){
                try{ResidencyCryptoService::assertRegion($assetId,$region);}
                catch(Error){continue;}
            }
            $approved[]=['id'=>$id,'healthy'=>$c['healthy'],'priority'=>$c['priority'],'region'=>$region];
        }
        usort($approved,static fn($a,$b)=>($a['priority']<=>$b['priority'])?:strcmp($a['id'],$b['id']));
        foreach($approved as $c)if($c['healthy'])
            return ['status'=>'routed','provider'=>$c['id'],'region'=>$c['region'],
                'failover_candidates'=>count($approved)-1];
        throw new Error('cdn_unavailable','No healthy approved CDN is available.',503);
    }
    public static function originShield(string $assetId,array $input): array {
        $asset=self::controlAsset($assetId,true,'origin_shield');
        if(array_diff(array_keys($input),['ttl_seconds','request_collapsing','prewarm'])!==[])
            throw new Error('origin_shield_invalid','Origin-shield policy has unsupported fields.',400);
        $ttl=$input['ttl_seconds']??0;
        $collapsing=$input['request_collapsing']??true;
        $prewarm=$input['prewarm']??false;
        if(!is_int($ttl)||$ttl<0||$ttl>86400||!is_bool($collapsing)||!is_bool($prewarm))
            throw new Error('origin_shield_invalid','Origin-shield values are invalid.',400);
        return ['asset_id'=>$assetId,'enabled'=>$ttl>0,'ttl_seconds'=>$ttl,
            'request_collapsing'=>$collapsing,'prewarm'=>$prewarm,
            'cache_key_hash'=>hash('sha256',$asset['sha256'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash'])];
    }
    public static function edgeAuthorization(string $assetId,array $input): array {
        $asset=self::controlAsset($assetId,false,'edge_authorization');
        if(array_diff(array_keys($input),['ttl_seconds'])!==[])
            throw new Error('edge_auth_profile_invalid','Edge authorization has unsupported fields.',400);
        $ttl=$input['ttl_seconds']??120;
        if(!is_int($ttl)||$ttl<1||$ttl>900)
            throw new Error('edge_auth_profile_invalid','Edge authorization TTL is invalid.',400);
        return ['asset_id'=>$assetId,'mode'=>'edge_verify_origin_authoritative',
            'ttl_seconds'=>$ttl,
            'claims'=>['asset_id','actor_id','purpose','privacy_class','policy_hash','rights_hash','object_version'],
            'canonical_owner'=>$asset['owner_domain'],'authorization_refresh_required'=>true];
    }
    public static function adaptiveUpload(array $network,array $bounds): array {
        if(array_diff(array_keys($network),['rtt_ms','mbps','unstable'])!==[]
            ||array_diff(array_keys($bounds),['max_part_size_bytes','max_parallel_parts'])!==[])
            throw new Error('network_profile_invalid','Adaptive upload contains unsupported fields.',400);
        $rtt=$network['rtt_ms']??0;
        $down=$network['mbps']??0.0;
        $unstable=$network['unstable']??false;
        $maxPart=$bounds['max_part_size_bytes']??8388608;
        $maxParallel=$bounds['max_parallel_parts']??4;
        if(!is_int($rtt)||$rtt<0||$rtt>60000
            ||!(is_int($down)||is_float($down))||!is_finite((float)$down)
            ||$down<0||$down>100000
            ||!is_bool($unstable)||!is_int($maxPart)||$maxPart<65536||$maxPart>67108864
            ||!is_int($maxParallel)||$maxParallel<1||$maxParallel>16)
            throw new Error('network_profile_invalid','Adaptive upload network or policy bounds are invalid.',400);
        $part=$unstable||$rtt>400||$down<2
            ?min($maxPart,1048576)
            :($down<10?min($maxPart,4194304):min($maxPart,16777216));
        $parallel=min($maxParallel,$unstable?1:($down>=20?4:2));
        return ['part_size_bytes'=>$part,'parallel_parts'=>$parallel,
            'retry'=>'exponential-jitter','checkpoint_each_part'=>true];
    }
    private static function offlineIdentity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null
        ];
    }
    public static function offlineGrant(string $assetId,int $actor,int $ttlSeconds): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);
        if($ttlSeconds<60||$ttlSeconds>86400)
            throw new Error('offline_grant_ttl_invalid','Offline grant TTL is outside approved bounds.',400);
        $asset=Future40Registry::asset($assetId,true);
        if($asset['privacy_class']==='C0')
            throw new Error('offline_package_unnecessary','Use normal public delivery for C0 assets.',400);
        RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',[
            'asset'=>$asset,'actor_id'=>$actor,'mode'=>'offline_package',
            'context'=>['audience_type'=>'user','territory'=>'GLOBAL']]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Offline-package authorization is stale.',409);
        $latest=Future40Registry::asset($assetId,true);
        if(self::offlineIdentity($latest)!==self::offlineIdentity($asset))
            throw new Error('asset_state_changed','Asset changed before offline grant issuance.',409);
        $id=Utils::id('offline');$kid=Keyring::activeId();
        $claims=['type'=>'offline_package','grant_id'=>$id,'asset_id'=>$assetId,
            'actor_id'=>$actor,'purpose'=>$asset['policy']['purpose'],
            'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],
            'object_version'=>$asset['object_version']];
        $aad='offline-package|'.$id.'|'.$actor;
        $envelope=Crypto::encryptChunk(Utils::canonicalJson($claims),$aad,$kid);
        $token=Crypto::sign([
            'type'=>'offline_package_envelope','grant_id'=>$id,
            'encryption_kid'=>$kid,'envelope'=>$envelope],$ttlSeconds);
        $expires=Utils::now()+$ttlSeconds;
        RecordStore::put('offline_package_grant',$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'source_sha256'=>$asset['sha256'],'object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'purpose'=>$asset['policy']['purpose'],'privacy_class'=>$asset['privacy_class'],
            'token_hash'=>hash('sha256',$token),'encryption_kid'=>$kid,
            'expires_at'=>$expires,'created_at'=>Utils::now()],0);
        return ['grant_id'=>$id,'token'=>$token,'expires_at'=>$expires,'encrypted'=>true];
    }
    public static function verifyOfflineGrant(string $token,int $actor): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);
        $outer=Crypto::verify($token);
        Utils::requireFields($outer,['type','grant_id','encryption_kid','envelope'],'offline_grant_invalid');
        if($outer['type']!=='offline_package_envelope'
            ||!is_string($outer['grant_id'])||!is_string($outer['encryption_kid'])
            ||!is_array($outer['envelope']))
            throw new Error('offline_grant_invalid','Offline grant envelope contract is invalid.',403);
        $id=Utils::text($outer['grant_id'],96);
        if($id===''||$id!==$outer['grant_id'])
            throw new Error('offline_grant_invalid','Offline grant identity is invalid.',403);
        $record=RecordStore::get('offline_package_grant',$id);
        if(!$record||($record['status']??'')!=='active'
            ||(int)($record['expires_at']??0)<=Utils::now())
            throw new Error('offline_grant_unavailable','Offline grant is unavailable or expired.',403);
        if(($record['actor_id']??null)!==$actor
            ||!is_string($record['token_hash']??null)
            ||!hash_equals($record['token_hash'],hash('sha256',$token)))
            throw new Error('offline_grant_binding_mismatch','Offline grant binding mismatch.',403);
        $kid=Utils::key($outer['encryption_kid'],64);
        if($kid===''||$kid!==$outer['encryption_kid']
            ||!hash_equals((string)$record['encryption_kid'],$kid))
            throw new Error('offline_grant_binding_mismatch','Offline grant key binding mismatch.',403);
        try{
            $plain=Crypto::decryptChunk($outer['envelope'],'offline-package|'.$id.'|'.$actor,$kid);
            $claims=json_decode($plain,true,32,JSON_THROW_ON_ERROR);
        }catch(Error $e){throw $e;}catch(\Throwable){
            throw new Error('offline_grant_invalid','Offline grant envelope is invalid.',403);
        }
        if(!is_array($claims))
            throw new Error('offline_grant_invalid','Offline grant claims are invalid.',403);
        Utils::requireFields($claims,[
            'type','grant_id','asset_id','actor_id','purpose','privacy_class',
            'policy_hash','rights_hash','object_version'],'offline_grant_invalid');
        if(($claims['type']??null)!=='offline_package'
            ||($claims['grant_id']??null)!==$id
            ||($claims['actor_id']??null)!==$actor
            ||!is_string($claims['asset_id']??null)
            ||($record['asset_id']??null)!==$claims['asset_id'])
            throw new Error('offline_grant_binding_mismatch','Offline grant claims do not match the record.',403);
        $asset=Future40Registry::asset($claims['asset_id'],true);
        foreach(['purpose','privacy_class','policy_hash','rights_hash'] as $field){
            $expected=$field==='purpose'?$asset['policy']['purpose']:
                ($field==='rights_hash'?$asset['rights']['policy_hash']:$asset[$field]);
            if(!is_string($claims[$field]??null)
                ||!is_string($record[$field]??null)
                ||!hash_equals((string)$expected,$claims[$field])
                ||!hash_equals((string)$expected,$record[$field]))
                throw new Error('offline_grant_state_changed','Offline grant state has changed.',403);
        }
        if(($claims['object_version']??null)!==$asset['object_version']
            ||($record['object_version']??null)!==$asset['object_version']
            ||($record['source_sha256']??null)!==$asset['sha256'])
            throw new Error('offline_grant_state_changed','Offline grant source version changed.',403);
        RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',[
            'asset'=>$asset,'actor_id'=>$actor,'mode'=>'offline_package_consume',
            'context'=>['audience_type'=>'user','territory'=>'GLOBAL']]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Offline grant authorization is stale.',409);
        $latest=Future40Registry::asset($claims['asset_id'],true);
        if(self::offlineIdentity($latest)!==self::offlineIdentity($asset))
            throw new Error('offline_grant_state_changed','Offline grant changed during consume authorization.',403);
        return $claims;
    }
    private static function resumeId(string $assetId,int $actor,string $deviceHash): string {
        return hash('sha256',$actor.'|'.$assetId.'|'.$deviceHash);
    }
    private static function resumeDevice(mixed $value): string {
        if(!is_string($value)||trim($value)===''||strlen($value)>191)
            throw new Error('download_resume_device_required','A bounded device reference is required.',400);
        $device=Utils::text($value,191);
        if($device==='')throw new Error('download_resume_device_required','Device reference is empty.',400);
        return Utils::hashReference($device);
    }
    public static function resumeDownload(string $assetId,int $actor,array $state): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);
        if(array_diff(array_keys($state),['offset','device_id'])!==[])
            throw new Error('download_resume_state_invalid','Download checkpoint contains unsupported fields.',400);
        $asset=Future40Registry::asset($assetId,true);
        $offset=Utils::integer($state['offset']??null,'download_offset_invalid',0,(int)$asset['size']);
        $deviceHash=self::resumeDevice($state['device_id']??null);
        RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_download',[
            'asset'=>$asset,'actor_id'=>$actor,'mode'=>'resume_checkpoint',
            'offset'=>$offset,'device_hash'=>$deviceHash]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Download checkpoint authorization is stale.',409);
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','policy_hash','privacy_class'] as $field)
            if(($latest[$field]??null)!==($asset[$field]??null))
                throw new Error('asset_state_changed','Asset changed before download checkpoint.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Rights changed before download checkpoint.',409);
        $id=self::resumeId($assetId,$actor,$deviceHash);
        $existing=RecordStore::get('download_resume',$id);
        return RecordStore::put('download_resume',$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'offset'=>$offset,'device_hash'=>$deviceHash,
            'object_version'=>$asset['object_version'],'policy_hash'=>$asset['policy_hash'],
            'rights_hash'=>$asset['rights']['policy_hash'],'reauthorize'=>true,
            'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
    }
    public static function resumeGrant(string $assetId,int $actor,string $sessionId,string $deviceId,array $context=[]): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor);
        if(trim($sessionId)===''||strlen($sessionId)>128)
            throw new Error('download_resume_context_required','Session identity is invalid.',400);
        $deviceHash=self::resumeDevice($deviceId);
        $sessionId=Utils::text($sessionId,128);
        if($sessionId==='')throw new Error('download_resume_context_required','Session identity is empty.',400);
        $state=RecordStore::get('download_resume',self::resumeId($assetId,$actor,$deviceHash));
        if(!$state||($state['status']??'')!=='active'
            ||($state['actor_id']??null)!==$actor
            ||($state['asset_id']??null)!==$assetId
            ||!is_string($state['device_hash']??null)
            ||!hash_equals($state['device_hash'],$deviceHash))
            throw new Error('download_resume_state_invalid','Download state is unavailable for this device.',403);
        $asset=Future40Registry::asset($assetId,true);
        if((int)($state['object_version']??0)!==(int)$asset['object_version']
            ||!hash_equals((string)($state['policy_hash']??''),(string)$asset['policy_hash'])
            ||!hash_equals((string)($state['rights_hash']??''),(string)$asset['rights']['policy_hash']))
            throw new Error('download_resume_state_stale','Resumable download state is stale.',409);
        $offset=$state['offset']??null;
        if(!is_int($offset)||$offset<0||$offset>(int)$asset['size'])
            throw new Error('download_offset_invalid','Stored download offset is invalid.',416);
        RightsPolicy::assert($asset['rights'],'download',[
            'territory'=>$context['territory']??'GLOBAL','audience_type'=>'user']);
        $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_download',[
            'asset'=>$asset,'actor_id'=>$actor,'mode'=>'resume','offset'=>$offset,
            'device_hash'=>$deviceHash,'session_id'=>$sessionId,'context'=>Utils::redact($context)]);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Resume authorization is stale.',409);
        $audience=['type'=>'user','user_id'=>$actor];
        $deliveryContext=array_replace($context,[
            'audience_type'=>'user','territory'=>$context['territory']??'GLOBAL']);
        $token=DeliveryService::issue($assetId,null,$actor,'future40-resume',$audience,
            $deliveryContext,'download',
            ['allow_ranges'=>true,'max_range_bytes'=>(int)$asset['policy']['delivery']['max_range_bytes']],
            $sessionId,300,20);
        return ['asset_id'=>$assetId,'offset'=>$offset,'token'=>$token,
            'session_id'=>$sessionId,'device_hash'=>$deviceHash,'reauthorized'=>true];
    }

}

final class ResidencyCryptoService {
    private static function policy(string $assetId): ?array {
        return RecordStore::get('residency_policy',hash('sha256',$assetId));
    }
    public static function hasPolicy(string $assetId): bool {
        return self::policy($assetId)!==null;
    }
    private static function regionName(mixed $value): string {
        if(!is_string($value)||strlen($value)>16
            ||!preg_match('/^[A-Z0-9-]{2,16}$/D',$value))
            throw new Error('residency_regions_invalid','Residency region must be canonical bounded text.',400);
        return $value;
    }
    private static function identity(array $asset): array {
        return [
            'id'=>$asset['id']??null,'status'=>$asset['status']??null,
            'sha256'=>$asset['sha256']??null,'owner_domain'=>$asset['owner_domain']??null,
            'owner_object'=>$asset['owner_object']??null,'object_version'=>$asset['object_version']??null,
            'privacy_class'=>$asset['privacy_class']??null,'policy_hash'=>$asset['policy_hash']??null,
            'rights_hash'=>$asset['rights']['policy_hash']??null
        ];
    }
    public static function residency(string $assetId,int $actor,array $regions): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');
        $asset=Future40Registry::asset($assetId);
        if(!array_is_list($regions)||$regions===[]||count($regions)>32)
            throw new Error('residency_regions_invalid','Residency regions must be a nonempty bounded list.',400);
        $safe=[];
        foreach($regions as $region){
            $name=self::regionName($region);
            if(in_array($name,$safe,true))
                throw new Error('residency_regions_invalid','Duplicate residency region.',400);
            $safe[]=$name;
        }
        $decision=DomainRegistry::decision((string)$asset['owner_domain'],'authorize_reprocess',[
            'asset'=>$asset,'actor_id'=>$actor,'transform'=>'residency_policy']);
        if((int)$decision['object_version']!==(int)$asset['object_version'])
            throw new Error('domain_object_version_stale','Residency authorization is stale.',409);
        $providerIds=[];
        $sourceProvider=$asset['storage']['provider_id']??'';
        if($sourceProvider!==''){
            if(!is_string($sourceProvider))throw new Error('residency_provider_unknown','Source provider identity invalid.',503);
            $providerIds[$sourceProvider]=true;
        }
        foreach(DerivativeService::forAsset($assetId) as $derivative){
            if(($derivative['status']??'')==='deleted')continue;
            $pid=$derivative['storage']['provider_id']??'';
            if($pid!==''){
                if(!is_string($pid))throw new Error('residency_provider_unknown','Derivative provider identity invalid.',503);
                $providerIds[$pid]=true;
            }
        }
        foreach(array_keys($providerIds) as $providerId){
            $metadata=ProviderRegistry::metadata($providerId);
            $region=strtoupper((string)($metadata['region']??''));
            if($region===''||!in_array($region,$safe,true))
                throw new Error('residency_current_placement_denied',
                    'Existing asset placement is outside requested residency policy.',409,
                    ['provider'=>$providerId,'region'=>$region]);
        }
        $latest=Future40Registry::asset($assetId);
        if(self::identity($latest)!==self::identity($asset))
            throw new Error('asset_state_changed','Asset changed during residency planning.',409);
        $id=hash('sha256',$assetId);$existing=self::policy($assetId);
        $row=RecordStore::put('residency_policy',$id,[
            'actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,
            'asset_sha256'=>$asset['sha256'],'owner_domain'=>$asset['owner_domain'],
            'owner_object_version'=>$asset['object_version'],
            'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],
            'privacy_class'=>$asset['privacy_class'],'allowed_regions'=>$safe,
            'enforcement'=>'fail_closed','created_at'=>$existing['created_at']??Utils::now(),
            'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
        Audit::record('future40_residency_policy_set',[
            'asset_id'=>$assetId,'regions'=>$safe,'actor_id'=>$actor]);
        return $row;
    }
    public static function assertRegion(string $assetId,string $region): void {
        $policy=self::policy($assetId);
        if(!$policy)return;
        if(($policy['asset_id']??null)!==$assetId||($policy['status']??null)!=='active'
            ||($policy['enforcement']??null)!=='fail_closed')
            throw new Error('residency_policy_invalid','Residency policy identity or state invalid.',409);
        $allowed=$policy['allowed_regions']??null;
        if(!is_array($allowed)||!array_is_list($allowed)||$allowed===[]
            ||count($allowed)>32)
            throw new Error('residency_policy_invalid','Residency policy region list invalid.',409);
        foreach($allowed as $item){
            try{self::regionName($item);}catch(Error){
                throw new Error('residency_policy_invalid','Persisted residency region invalid.',409);
            }
        }
        if(count(array_unique($allowed))!==count($allowed))
            throw new Error('residency_policy_invalid','Duplicate persisted residency region.',409);
        $asset=Future40Registry::asset($assetId);
        foreach(['asset_sha256'=>'sha256','owner_domain'=>'owner_domain',
            'owner_object_version'=>'object_version','policy_hash'=>'policy_hash',
            'privacy_class'=>'privacy_class'] as $stored=>$current)
            if(($policy[$stored]??null)!==($asset[$current]??null))
                throw new Error('residency_policy_stale','Residency policy source changed.',409);
        if(($policy['rights_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('residency_policy_stale','Residency policy rights changed.',409);
        $region=self::regionName($region);
        if(!in_array($region,$allowed,true))
            throw new Error('residency_region_denied','Storage/delivery region violates residency policy.',403,
                ['region'=>$region]);
    }
    public static function assertProviderRegion(string $assetId,string $providerId): void {
        if(!self::hasPolicy($assetId))return;
        if($providerId===''||strlen($providerId)>64
            ||!preg_match('/^[a-z0-9][a-z0-9._:-]{0,63}$/D',$providerId))
            throw new Error('residency_provider_unknown','Residency provider identity invalid.',503);
        $region=strtoupper((string)(ProviderRegistry::metadata($providerId)['region']??''));
        self::assertRegion($assetId,$region);
    }
    public static function assertPublicCdnAllowed(string $assetId): void {
        if(self::hasPolicy($assetId))throw new Error('residency_cdn_region_unverified','Public CDN publication is denied while CDN residency is not provider-attested.',503);
    }
    public static function lockUntil(string $assetId): ?int {
        $lock=RecordStore::get('object_lock',hash('sha256',$assetId));
        if($lock===null)return null;
        if(($lock['asset_id']??null)!==$assetId||($lock['status']??null)!=='locked')
            throw new Error('object_lock_record_invalid','Persisted object-lock identity or state is invalid.',409);
        return Utils::integer($lock['locked_until']??null,'object_lock_record_invalid',1,PHP_INT_MAX);
    }
    public static function isLocked(string $assetId): bool {
        $until=self::lockUntil($assetId);
        return $until!==null&&$until>Utils::now();
    }
    public static function assertUnlocked(string $assetId,string $operation): void {
        $until=self::lockUntil($assetId);
        if($until!==null&&$until>Utils::now())
            throw new Error('object_lock_active','Physical mutation is blocked by active WORM/object lock.',423,['operation'=>Utils::key($operation,64),'locked_until'=>$until]);
    }
    public static function objectLock(string $assetId,int $actor,int $until,string $reason): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_hold');$asset=Future40Registry::asset($assetId);$reason=Utils::text($reason,255);
        if($until<=Utils::now()||$reason==='')throw new Error('object_lock_invalid','Object-lock expiry and reason are required.',400);
        $id=hash('sha256',$assetId);$existing=RecordStore::get('object_lock',$id);
        $previousUntil=self::lockUntil($assetId);
        if($previousUntil!==null&&$previousUntil>Utils::now()&&$until<$previousUntil)throw new Error('object_lock_reduction_denied','An active compliance lock cannot be shortened.',409);
        $until=max($until,$previousUntil??0);$holds=LegalHoldService::active($assetId);
        $row=RecordStore::put('object_lock',$id,['actor_id'=>$actor,'status'=>'locked','asset_id'=>$assetId,'locked_until'=>$until,'reason'=>$reason,'mode'=>'compliance','retention_class'=>(string)($asset['policy']['retention']['class']??''),'legal_hold_ids'=>array_values(array_map('strval',array_column($holds,'id'))),'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
        Audit::record('future40_object_lock_set',['asset_id'=>$assetId,'actor_id'=>$actor,'locked_until'=>$until,'retention_class'=>$row['retention_class'],'legal_hold_count'=>count($row['legal_hold_ids'])]);return $row;
    }
    public static function keyEnvelope(string $assetId,int $actor,string $keyId,string $algorithm='aes-256-gcm'): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');$asset=Future40Registry::asset($assetId);$keyId=Utils::key($keyId,64);$algorithm=Utils::key($algorithm,32);
        if($keyId===''||$algorithm==='')throw new Error('asset_key_envelope_invalid','Key identity/algorithm are required.',400);Keyring::key($keyId);$actualKeyId=Utils::key((string)($asset['storage']['key_id']??''),64);if($actualKeyId===''||!hash_equals($actualKeyId,$keyId))throw new Error('asset_key_envelope_mismatch','Envelope key reference must match the asset current encrypted storage key.',409);if($algorithm!=='aes-256-gcm')throw new Error('asset_key_envelope_algorithm_mismatch','Envelope algorithm must match current encrypted storage format.',409);
        $id=hash('sha256',$assetId);$existing=RecordStore::get('asset_key_envelope',$id);
        return RecordStore::put('asset_key_envelope',$id,['actor_id'=>$actor,'status'=>'active','asset_id'=>$assetId,'key_id'=>$keyId,'algorithm'=>$algorithm,'asset_sha256'=>$asset['sha256'],'rotatable'=>true,'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
    }
    private static function cryptoAlgorithms(mixed $values,string $code): array {
        if(!is_array($values)||!array_is_list($values)||$values===[]||count($values)>16)
            throw new Error($code,'Crypto algorithms must be a bounded canonical list.',400);
        $alg=[];
        foreach($values as $value){
            if(!is_string($value)||strlen($value)>32
                ||!preg_match('/^[a-z][a-z0-9-]{0,31}$/D',$value)
                ||in_array($value,$alg,true))
                throw new Error($code,'Crypto algorithm identity is invalid or duplicated.',400);
            $alg[]=$value;
        }
        if(!in_array('aes-256-gcm',$alg,true))
            throw new Error($code,'Current storage encryption algorithm must remain approved.',400);
        return $alg;
    }
    private static function cryptoPolicyAuditContext(array $row): array {
        return [
            'actor_id'=>$row['actor_id'],'policy_version'=>$row['version'],
            'approved_algorithms'=>$row['approved_algorithms'],
            'minimum_key_bits'=>$row['minimum_key_bits'],
            'migration_window_seconds'=>$row['migration_window_seconds']
        ];
    }
    private static function cryptoPolicyAuditKey(array $row): string {
        return hash('sha256',Utils::canonicalJson([
            'context'=>self::cryptoPolicyAuditContext($row),
            'created_at'=>$row['created_at']
        ]));
    }
    private static function reconcileCryptoPolicyAudit(array $row): void {
        if(!array_key_exists('audit_key',$row))return; // Pre-round-184 legacy record.
        if(!is_string($row['audit_key'])||!preg_match('/^[a-f0-9]{64}$/D',$row['audit_key'])
            ||!hash_equals(self::cryptoPolicyAuditKey($row),$row['audit_key']))
            throw new Error('crypto_policy_record_invalid','Stored crypto audit binding is invalid.',409);
        Audit::recordOnce('future40_crypto_agility_policy_set',
            self::cryptoPolicyAuditContext($row),'crypto-agility|'.$row['audit_key']);
    }
    public static function cryptoAgility(int $actor,array $policy): array {
        RuntimeGuard::requireReady();
        Auth::assertActor($actor,'media_manage_providers');
        Utils::requireFields($policy,['approved_algorithms','minimum_key_bits','migration_window_seconds'],'crypto_policy_incomplete');
        if(array_diff(array_keys($policy),['approved_algorithms','minimum_key_bits','migration_window_seconds'])!==[])
            throw new Error('crypto_policy_invalid','Crypto policy contains unapproved fields.',400);
        $alg=self::cryptoAlgorithms($policy['approved_algorithms'],'crypto_policy_invalid');
        $bits=Utils::integer($policy['minimum_key_bits'],'crypto_policy_invalid',256,8192);
        $window=Utils::integer($policy['migration_window_seconds'],'crypto_policy_invalid',3600,31536000);
        $id='global';$existing=RecordStore::get('crypto_agility',$id);
        if($existing!==null){
            if(($existing['status']??null)!=='active'
                ||($existing['required_test_vector']??null)!==true
                ||!is_int($existing['actor_id']??null)||$existing['actor_id']<1)
                throw new Error('crypto_policy_record_invalid','Stored crypto policy evidence is invalid.',409);
            try {
                self::cryptoAlgorithms($existing['approved_algorithms']??null,'crypto_policy_record_invalid');
                Utils::integer($existing['minimum_key_bits']??null,'crypto_policy_record_invalid',128,8192);
                Utils::integer($existing['migration_window_seconds']??null,'crypto_policy_record_invalid',3600,31536000);
                $created=Utils::integer($existing['created_at']??null,'crypto_policy_record_invalid',1,PHP_INT_MAX);
                if($created>Utils::now()+300)
                    throw new Error('crypto_policy_record_invalid','Stored crypto policy timestamp is invalid.',409);
            }catch(Error $e){
                throw new Error('crypto_policy_record_invalid','Stored crypto policy evidence is invalid.',409);
            }
        }
        if($existing!==null){
            self::reconcileCryptoPolicyAudit($existing);
            if($existing['approved_algorithms']===$alg
                &&$existing['minimum_key_bits']===$bits
                &&$existing['migration_window_seconds']===$window)
                return $existing;
        }
        $row=[
            'actor_id'=>$actor,'status'=>'active','approved_algorithms'=>$alg,
            'minimum_key_bits'=>$bits,'migration_window_seconds'=>$window,
            'required_test_vector'=>true,
            'created_at'=>$existing['created_at']??Utils::now(),
            'version'=>$existing?(int)$existing['version']+1:1
        ];
        $row['audit_key']=self::cryptoPolicyAuditKey($row);
        $auditContext=self::cryptoPolicyAuditContext($row);
        $auditId='crypto-agility|'.$row['audit_key'];
        // Durable intent is recorded before any security-policy mutation.
        Audit::recordOnce('future40_crypto_agility_policy_intent',$auditContext,$auditId);
        $saved=RecordStore::put('crypto_agility',$id,$row,$existing?(int)$existing['version']:0);
        // A failed applied-event write is repaired on the next authorized call.
        self::reconcileCryptoPolicyAudit($saved);
        return $saved;
    }
}

final class DisasterCostRoutingService {
    private static function decimal(mixed $v,string $code): float {
        if(is_int($v)||is_float($v))$n=(float)$v;
        elseif(is_string($v)&&preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D',$v))$n=(float)$v;
        else throw new Error($code,'Canonical non-negative decimal required.',400);
        if(!is_finite($n)||$n<0||$n>1000000000)throw new Error($code,'Value outside supported range.',400);
        return $n;
    }
    private static function region(mixed $v): string {
        if(!is_string($v))throw new Error('dr_regions_invalid','Region must be a string.',400);
        $region=strtoupper(Utils::text($v,16));
        if(!preg_match('/^[A-Z0-9-]{2,16}$/D',$region))throw new Error('dr_regions_invalid','Region identity is invalid.',400);
        return $region;
    }
    private static function drRegion(mixed $value): string {
        if(!is_string($value)||strlen($value)>16
            ||!preg_match('/^[A-Z0-9-]{2,16}$/D',$value))
            throw new Error('dr_regions_invalid','DR region must be canonical uppercase text.',400);
        return $value;
    }
    private static function drAuditContext(array $row): array {
        return [
            'actor_id'=>$row['actor_id'],'asset_id'=>$row['asset_id'],
            'plan_version'=>$row['version'],'primary_region'=>$row['primary_region'],
            'secondary_region'=>$row['secondary_region'],
            'rpo_seconds'=>$row['rpo_seconds'],'rto_seconds'=>$row['rto_seconds'],
            'integrity_hash'=>$row['integrity_hash']
        ];
    }
    private static function drAuditKey(array $row): string {
        return hash('sha256',Utils::canonicalJson([
            'context'=>self::drAuditContext($row),'created_at'=>$row['created_at']
        ]));
    }
    private static function reconcileDrAudit(array $row): void {
        if(!array_key_exists('audit_key',$row))return; // Historical plan without binding.
        if(!is_string($row['audit_key'])||!preg_match('/^[a-f0-9]{64}$/D',$row['audit_key'])
            ||!hash_equals(self::drAuditKey($row),$row['audit_key']))
            throw new Error('dr_plan_record_invalid','Stored DR audit binding is invalid.',409);
        Audit::recordOnce('future40_disaster_plan_set',self::drAuditContext($row),
            'disaster-plan|'.$row['audit_key']);
    }
    public static function disasterPlan(string $assetId,int $actor,array $input): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');
        $asset=Future40Registry::asset($assetId,true);
        Utils::requireFields($input,['primary_region','secondary_region','rpo_seconds','rto_seconds'],'dr_plan_incomplete');
        if(array_diff(array_keys($input),['primary_region','secondary_region','rpo_seconds','rto_seconds'])!==[])
            throw new Error('dr_plan_invalid','DR plan contains unapproved fields.',400);
        $primary=self::drRegion($input['primary_region']);
        $secondary=self::drRegion($input['secondary_region']);
        $rpo=Utils::integer($input['rpo_seconds'],'dr_plan_numeric_invalid',0,31536000);
        $rto=Utils::integer($input['rto_seconds'],'dr_plan_numeric_invalid',0,31536000);
        if($primary===$secondary)throw new Error('dr_regions_invalid','Regions must differ.',400);
        $sha=$asset['sha256']??null;
        if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha))
            throw new Error('dr_asset_invalid','DR source integrity hash is invalid.',409);
        $version=Utils::integer($asset['object_version']??null,'dr_asset_invalid',1);
        $decision=DomainRegistry::decision((string)($asset['owner_domain']??''),
            'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'transform'=>'disaster_plan']);
        if($decision['object_version']!==$version)
            throw new Error('domain_object_version_stale','DR authorization is stale.',409);
        ResidencyCryptoService::assertRegion($assetId,$primary);
        ResidencyCryptoService::assertRegion($assetId,$secondary);
        $latest=Future40Registry::asset($assetId,true);
        foreach(['sha256','owner_domain','owner_object','object_version','policy_hash'] as $field)
            if(($latest[$field]??null)!==($asset[$field]??null))
                throw new Error('asset_state_changed','Asset changed during DR planning.',409);
        if(($latest['rights']['policy_hash']??null)!==($asset['rights']['policy_hash']??null))
            throw new Error('asset_state_changed','Asset rights changed during DR planning.',409);
        $id=hash('sha256',$assetId);$existing=RecordStore::get('disaster_plan',$id);
        if($existing!==null){
            try {
                if(($existing['asset_id']??null)!==$assetId
                    ||($existing['status']??null)!=='planned'
                    ||($existing['failback_required']??null)!==true
                    ||($existing['integrity_hash']??null)!==$sha
                    ||!is_int($existing['actor_id']??null)||$existing['actor_id']<1)
                    throw new Error('dr_plan_record_invalid','Stored DR plan identity is invalid.',409);
                self::drRegion($existing['primary_region']??null);
                self::drRegion($existing['secondary_region']??null);
                if($existing['primary_region']===$existing['secondary_region'])
                    throw new Error('dr_plan_record_invalid','Stored DR regions are identical.',409);
                Utils::integer($existing['rpo_seconds']??null,'dr_plan_record_invalid',0,31536000);
                Utils::integer($existing['rto_seconds']??null,'dr_plan_record_invalid',0,31536000);
                $created=Utils::integer($existing['created_at']??null,'dr_plan_record_invalid',1);
                if($created>Utils::now()+300)
                    throw new Error('dr_plan_record_invalid','Stored DR creation time is invalid.',409);
                Utils::integer($existing['version']??null,'dr_plan_record_invalid',1);
            }catch(Error $e){
                throw new Error('dr_plan_record_invalid','Stored DR plan evidence is invalid.',409);
            }
            self::reconcileDrAudit($existing);
            if($existing['primary_region']===$primary
                &&$existing['secondary_region']===$secondary
                &&$existing['rpo_seconds']===$rpo&&$existing['rto_seconds']===$rto)
                return $existing;
        }
        $row=['actor_id'=>$actor,'status'=>'planned','asset_id'=>$assetId,
            'primary_region'=>$primary,'secondary_region'=>$secondary,
            'rpo_seconds'=>$rpo,'rto_seconds'=>$rto,'integrity_hash'=>$sha,
            'failback_required'=>true,'created_at'=>$existing['created_at']??Utils::now(),
            'version'=>$existing?(int)$existing['version']+1:1];
        $row['audit_key']=self::drAuditKey($row);
        $context=self::drAuditContext($row);
        Audit::recordOnce('future40_disaster_plan_intent',$context,'disaster-plan|'.$row['audit_key']);
        $saved=RecordStore::put('disaster_plan',$id,$row,$existing?(int)$existing['version']:0);
        self::reconcileDrAudit($saved);
        return $saved;
    }
    public static function optimizeTier(string $assetId,array $metrics): array {
        $asset=Future40Registry::asset($assetId,true);
        $access=Utils::integer(array_key_exists('accesses_30d',$metrics)?$metrics['accesses_30d']:0,'storage_tier_metric_invalid',0,PHP_INT_MAX);
        $age=Utils::integer(array_key_exists('age_days',$metrics)?$metrics['age_days']:0,'storage_tier_metric_invalid',0,PHP_INT_MAX);
        $cost=self::decimal(array_key_exists('cost_score',$metrics)?$metrics['cost_score']:0.0,'storage_tier_metric_invalid');
        $rights=$asset['rights']??[];
        if(!is_array($rights))throw new Error('storage_tier_rights_invalid','Rights envelope is invalid.',409);
        $rightsExpiry=Utils::integer(array_key_exists('expires_at',$rights)?$rights['expires_at']:0,'storage_tier_rights_invalid',0,PHP_INT_MAX);
        $objectLock=ResidencyCryptoService::isLocked($assetId);$legalHold=LegalHoldService::active($assetId)!==[];
        $retentionClass=Utils::key((string)($asset['policy']['retention']['class']??''),64);
        $privacyClass=Utils::key((string)($asset['privacy_class']??''),16);
        $rightsActive=$rightsExpiry===0||$rightsExpiry>Utils::now();
        $tier=($objectLock||$legalHold)?'archive_locked':(!$rightsActive?'rights_restricted':($access>100?'hot':($access>10?'warm':($age>180&&$cost>0?'archive':'cold'))));
        return ['asset_id'=>$assetId,'recommended_tier'=>$tier,'automatic_move'=>false,'transition_requires_owner_authorization'=>true,'reason'=>['accesses_30d'=>$access,'age_days'=>$age,'cost_score'=>$cost,'object_lock'=>$objectLock,'legal_hold'=>$legalHold,'retention_class'=>$retentionClass,'privacy_class'=>$privacyClass,'rights_active'=>$rightsActive,'rights_expires_at'=>$rightsExpiry,'residency_policy'=>ResidencyCryptoService::hasPolicy($assetId)]];
    }
    public static function costEstimate(string $assetId,array $prices,array $plan): array {
        $asset=Future40Registry::asset($assetId);
        $size=Utils::integer($asset['size']??null,'cost_asset_size_invalid',0,1073741824);
        $safe=[];
        foreach(['storage_gb_month','transcode_minute','egress_gb'] as $p){
            if(!array_key_exists($p,$prices))throw new Error('cost_price_invalid','Price table incomplete.',400);
            $safe[$p]=self::decimal($prices[$p],'cost_price_invalid');
        }
        $gb=max(0.000001,$size/1073741824);
        $minutes=self::decimal(array_key_exists('minutes',$plan)?$plan['minutes']:0,'cost_plan_invalid');
        $egress=self::decimal(array_key_exists('egress_gb',$plan)?$plan['egress_gb']:$gb,'cost_plan_invalid');
        $currency=array_key_exists('currency',$prices)?$prices['currency']:'USD';
        if(!is_string($currency)||!preg_match('/^[A-Za-z]{3}$/D',$currency))throw new Error('cost_currency_invalid','Currency code invalid.',400);
        $currency=Utils::key($currency,8);
        $cost=$gb*$safe['storage_gb_month']+$minutes*$safe['transcode_minute']+$egress*$safe['egress_gb'];
        if(!is_finite($cost)||$cost>1000000000)throw new Error('cost_estimate_invalid','Estimate exceeds ceiling.',400);
        return ['asset_id'=>$assetId,'currency'=>$currency,'estimated_cost'=>round($cost,6),'components'=>['storage_gb'=>$gb,'minutes'=>$minutes,'egress_gb'=>$egress],'estimate_only'=>true];
    }
    private static function routeCapability(mixed $value,string $code): string {
        if(!is_string($value)||strlen($value)>64
            ||!preg_match('/^[a-z0-9][a-z0-9._:-]{0,63}$/D',$value))
            throw new Error($code,'Routing capability must be canonical bounded text.',400);
        return $value;
    }
    public static function autoRoute(array $providers,array $requirements): array {
        if(!array_is_list($providers)||count($providers)>256)
            throw new Error('route_providers_invalid','Provider candidates must be a bounded list.',400);
        if(array_diff(array_keys($requirements),['capabilities','regions'])!==[])
            throw new Error('route_requirements_invalid','Unknown routing requirement.',400);
        $capsInput=$requirements['capabilities']??[];
        $regionsInput=$requirements['regions']??[];
        if(!is_array($capsInput)||!array_is_list($capsInput)||count($capsInput)>32
            ||!is_array($regionsInput)||!array_is_list($regionsInput)||count($regionsInput)>32)
            throw new Error('route_requirements_invalid','Routing requirements must be bounded lists.',400);
        $requiredCaps=[];
        foreach($capsInput as $value){
            $cap=self::routeCapability($value,'route_requirements_invalid');
            if(in_array($cap,$requiredCaps,true))
                throw new Error('route_requirements_invalid','Duplicate required capability.',400);
            $requiredCaps[]=$cap;
        }
        $requiredRegions=[];
        foreach($regionsInput as $value){
            try{$region=self::drRegion($value);}catch(Error){
                throw new Error('route_requirements_invalid','Required region must be canonical.',400);
            }
            if(in_array($region,$requiredRegions,true))
                throw new Error('route_requirements_invalid','Duplicate required region.',400);
            $requiredRegions[]=$region;
        }
        $eligible=[];$seen=[];
        foreach($providers as $p){
            if(!is_array($p)||($p['approved']??false)!==true||($p['healthy']??false)!==true)continue;
            if(!is_string($p['id']??null)||!is_string($p['region']??null)
                ||!is_array($p['capabilities']??null))continue;
            try {
                $id=self::routeCapability($p['id'],'route_provider_invalid');
                $region=self::drRegion($p['region']);
                $cost=self::decimal($p['cost_score']??null,'route_score_invalid');
                $latency=self::decimal($p['latency_score']??null,'route_score_invalid');
                if(!array_is_list($p['capabilities'])||count($p['capabilities'])>32)
                    continue;
                $caps=[];
                foreach($p['capabilities'] as $v){
                    $cap=self::routeCapability($v,'route_provider_invalid');
                    if(in_array($cap,$caps,true))throw new Error('route_provider_invalid','Duplicate provider capability.',400);
                    $caps[]=$cap;
                }
            }catch(\Throwable){continue;}
            if(isset($seen[$id]))
                throw new Error('route_provider_conflict','Approved healthy provider identity is duplicated.',409);
            $seen[$id]=true;
            if(array_diff($requiredCaps,$caps)!==[])continue;
            if($requiredRegions!==[]&&!in_array($region,$requiredRegions,true))continue;
            $eligible[]=['id'=>$id,'region'=>$region,'cost'=>$cost,'latency'=>$latency];
        }
        if($eligible===[])throw new Error('approved_provider_unavailable','No approved provider satisfies requirements.',503);
        usort($eligible,fn($a,$b)=>(($a['cost']+$a['latency'])<=>($b['cost']+$b['latency']))
            ?: strcmp($a['id'],$b['id']) ?: strcmp($a['region'],$b['region']));
        return ['provider'=>$eligible[0]['id'],'region'=>$eligible[0]['region'],
            'eligible_count'=>count($eligible),'decision'=>'approved_health_capability_cost_route'];
    }

}

final class OperationsFutureService {
    public static function qoe(string $name,float $value,array $labels=[]): array {return PrivacyTelemetry::metric($name,$value,$labels);}
    public static function chaos(int $actor,string $scenario,array $context=[]): array {Auth::assertActor($actor,'media_manage_providers');if(!(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true)){if(!function_exists('apply_filters')||apply_filters('scm_environment','production')!=='staging')throw new Error('chaos_staging_only','Chaos/fault injection is permitted only in approved staging/test.',403);} $scenario=Utils::key($scenario,64);$allowed=['scanner_down','object_store_timeout','corrupt_derivative','key_unavailable','cdn_purge_failure','queue_saturation','region_outage'];if(!in_array($scenario,$allowed,true))throw new Error('chaos_scenario_invalid','Chaos scenario is not approved.',400);$id=Utils::id('chaos');$row=RecordStore::put('chaos_exercise',$id,['actor_id'=>$actor,'status'=>'scheduled','scenario'=>$scenario,'context'=>Utils::redact($context),'rollback_required'=>true,'created_at'=>Utils::now()]);Audit::record('future40_chaos_scheduled',['scenario'=>$scenario,'exercise_id'=>$id]);return $row;}
    public static function migrationBundle(array $assetIds,int $actor,string $target): array {
        RuntimeGuard::requireReady();Auth::assertActor($actor,'media_manage_providers');
        $target=Utils::key($target,64);
        if($target===''||$assetIds===[]||count($assetIds)>10000)throw new Error('migration_bundle_invalid','Target and bounded asset set required.',400);
        $ids=[];
        foreach($assetIds as $value){
            if(!is_string($value)||$value===''||strlen($value)>96||Utils::text($value,96)!==$value)throw new Error('migration_bundle_invalid','Asset identity must be canonical.',400);
            $ids[$value]=true;
        }
        $items=[];
        foreach(array_keys($ids) as $assetId){
            $asset=RecordStore::get('asset',$assetId);$tombstone=RecordStore::get('tombstone',$assetId);
            if(!$asset&&!$tombstone)throw new Error('migration_asset_unavailable','Asset/tombstone unavailable.',404,['asset_id'=>$assetId]);
            if(($asset['status']??'')==='deleted'&&!$tombstone)throw new Error('migration_tombstone_missing','Deleted asset has no tombstone.',409);
            $version=Utils::integer($asset['object_version']??($tombstone['object_version']??null),'migration_asset_identity_invalid',1,PHP_INT_MAX);
            $sha=$asset['sha256']??'';
            if($asset&&($asset['status']??'')!=='deleted'&&(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)))throw new Error('migration_asset_identity_invalid','Active asset digest is invalid.',409);
            $policy=$asset['policy_hash']??($tombstone['policy_hash']??'');
            $rights=$asset['rights']['policy_hash']??($tombstone['rights_hash']??'');
            if(!is_string($policy)||!preg_match('/^[a-f0-9]{64}$/D',$policy)||!is_string($rights)||!preg_match('/^[a-f0-9]{64}$/D',$rights))
                throw new Error('migration_asset_identity_invalid','Policy/rights identity is invalid.',409);
            $state=null;
            if($tombstone){
                $deletedAt=Utils::integer($tombstone['deleted_at']??null,'migration_tombstone_invalid',0,PHP_INT_MAX);
                $backupAt=Utils::integer($tombstone['backup_expiry_at']??null,'migration_tombstone_invalid',0,PHP_INT_MAX);
                $state=['status'=>$tombstone['status']??'deleted','deleted_at'=>$deletedAt,'backup_expiry_at'=>$backupAt];
            }
            $items[]=['asset_id'=>$assetId,'sha256'=>is_string($sha)?$sha:'','privacy_class'=>(string)($asset['privacy_class']??''),'policy_hash'=>$policy,'rights_hash'=>$rights,'status'=>(string)($asset['status']??($tombstone['status']??'deleted')),'object_version'=>$version,'tombstone_hash'=>$tombstone?hash('sha256',Utils::canonicalJson($tombstone)):null,'tombstone_state'=>$state];
        }
        $kid=Keyring::activeId();$bundle=['format'=>'SCM-MIGRATION-1','target'=>$target,'signing_kid'=>$kid,'created_at'=>Utils::now(),'items'=>$items];
        $payload=Utils::canonicalJson($bundle);$bundle['signature']=hash_hmac('sha256',$payload,Keyring::hashKey($kid));$bundle['bundle_hash']=hash('sha256',$payload);
        $id=Utils::id('migration');
        RecordStore::put('migration_bundle',$id,['actor_id'=>$actor,'status'=>'signed','target'=>$target,'signing_kid'=>$kid,'bundle_hash'=>$bundle['bundle_hash'],'signature'=>$bundle['signature'],'item_count'=>count($items),'created_at'=>Utils::now()]);
        return ['bundle_id'=>$id,'bundle'=>$bundle];
    }
    public static function verifyMigrationBundle(array $bundle): bool {
        $signature=$bundle['signature']??null;$bundleHash=$bundle['bundle_hash']??null;$kid=$bundle['signing_kid']??null;
        if(!is_string($signature)||!preg_match('/^[a-f0-9]{64}$/D',$signature)||!is_string($bundleHash)||!preg_match('/^[a-f0-9]{64}$/D',$bundleHash)||!is_string($kid)||$kid===''||Utils::key($kid,64)!==$kid)return false;
        if(($bundle['format']??null)!=='SCM-MIGRATION-1'||!is_string($bundle['target']??null)||$bundle['target']===''||!is_array($bundle['items']??null)||count($bundle['items'])<1||count($bundle['items'])>10000)return false;
        try{Utils::integer($bundle['created_at']??null,'migration_bundle_invalid',1,PHP_INT_MAX);$key=Keyring::hashKey($kid);}catch(\Throwable){return false;}
        unset($bundle['signature'],$bundle['bundle_hash']);
        $payload=Utils::canonicalJson($bundle);
        return hash_equals(hash('sha256',$payload),$bundleHash)&&hash_equals(hash_hmac('sha256',$payload,$key),$signature);
    }
    public static function sdkManifest(): array {return ['sdk_contract'=>'CF04-MEDIA-SDK-1','contract_version'=>defined('SCM_CONTRACT_VERSION')?SCM_CONTRACT_VERSION:'unknown','base_routes'=>['POST /api/media/v1/uploads','GET /api/media/v1/assets/{id}','GET /media/d/{grant}'],'required_headers'=>['X-SCM-Contract-Version'],'idempotency_header'=>'Idempotency-Key','idempotent_routes'=>['POST /api/media/v1/uploads','POST /api/media/v1/uploads/{id}/complete'],'consumer_rules'=>['no_direct_bucket_access','no_provider_id_as_public_identity','owner_authorization_rechecked_at_action','events_are_facts_not_commands'],'future40'=>array_keys(Future40Registry::REQUIREMENTS)];}
}
