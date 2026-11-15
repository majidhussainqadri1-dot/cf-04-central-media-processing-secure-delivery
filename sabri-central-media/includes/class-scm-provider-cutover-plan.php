<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/**
 * Read-only mapping reconciliation for a fully shadow-copied provider exit.
 * This plan NEVER performs a native owner write, switch, purge or key revoke.
 */
final class ProviderExitCutoverManifestStage {
    private static function authorize(int $actor):void {
        Auth::capability('media_manage_providers');
        Auth::assertActor($actor,'manage_options');
    }

    private static function stage(string $inventoryId,int $actor):array {
        $proof=ProviderExitInventoryStage::verifyComplete($inventoryId,$actor);
        ProviderExitShadowCopyStage::verifyComplete($inventoryId,$actor);
        $stage=RecordStore::get('provider_exit_inventory',$inventoryId);
        if(!$stage||($stage['status']??null)!=='inventory_complete_unfenced'||
           ($stage['chain_hash']??null)!==$proof['chain_hash']||
           (int)($stage['eligible_items']??0)<1)
            throw new Error('provider_cutover_inventory_invalid',
                'Cutover preparation needs a fully verified nonempty shadow inventory.',409);
        return $stage;
    }

    public static function begin(string $inventoryId,int $actor):array {
        self::authorize($actor);
        $stage=self::stage($inventoryId,$actor);
        $id=Utils::id('pcut');
        return RecordStore::put('provider_cutover_plan',$id,[
            'actor_id'=>$actor,'inventory_id'=>$inventoryId,
            'inventory_chain'=>$stage['chain_hash'],
            'source_provider'=>$stage['source_provider'],
            'target_provider'=>$stage['target_provider'],
            'status'=>'planning_unfenced','after_segment_id'=>'',
            'segments'=>0,'candidates'=>0,
            'chain_hash'=>str_repeat('0',64),'created_at'=>Utils::now()
        ],0);
    }

    private static function load(string $id,int $actor):array {
        self::authorize($actor);
        $plan=RecordStore::get('provider_cutover_plan',$id);
        if(!$plan||($plan['actor_id']??null)!==$actor||
           !is_string($plan['inventory_id']??null)||
           !is_string($plan['after_segment_id']??null)||
           strlen($plan['after_segment_id'])>96||
           Utils::text($plan['after_segment_id'],96)!==$plan['after_segment_id']||
           !is_int($plan['segments']??null)||$plan['segments']<0||
           !is_int($plan['candidates']??null)||$plan['candidates']<0||
           !is_string($plan['chain_hash']??null)||
           !preg_match('/^[a-f0-9]{64}$/D',$plan['chain_hash']))
            throw new Error('provider_cutover_plan_invalid',
                'Cutover preparation state is unavailable or malformed.',409);
        return $plan;
    }

    /**
     * Bind one complete copied object to its canonical native owner and
     * current WordPress record CAS generation. Do not infer owner consent.
     */
    private static function mapping(array $item,array $copy,array $stage):array {
        $type=$item['type']??null;$id=$item['id']??null;
        $assetId=$item['asset_id']??null;
        if(!in_array($type,['asset','derivative'],true)||
           !is_string($id)||$id===''||strlen($id)>96||
           !is_string($assetId)||$assetId===''||strlen($assetId)>96||
           ($item['status']??null)!=='planned_unfenced')
            throw new Error('provider_cutover_identity_invalid',
                'Staged owner and media identity are malformed.',409);
        $key=$item['object_key']??null;$target=$copy['target_key']??null;
        $sha=$item['sha256']??null;
        if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)||
           !is_string($target)||!preg_match('/^[a-f0-9]{64}$/D',$target)||
           !is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
           ($copy['type']??null)!==$type||($copy['id']??null)!==$id||
           ($copy['source_key']??null)!==$key||
           ($copy['sha256']??null)!==$sha||
           ($copy['size']??null)!==($item['size']??null)||
           ($copy['status']??null)!=='copied_unfenced')
            throw new Error('provider_cutover_shadow_invalid',
                'Saved shadow copy does not match the authoritative source.',409);
        $size=Utils::integer($item['size']??null,
            'provider_cutover_identity_invalid',0,1073741824);
        $record=RecordStore::get($type,$id);
        $asset=$type==='asset'?$record:RecordStore::get('asset',$assetId);
        if(!$record||!$asset||
           ($record['status']??null)==='deleted'||
           ($asset['status']??null)==='deleted'||
           ($record['object_key']??null)!==$key||
           ($record['storage']['provider_id']??null)!==$stage['source_provider']||
           ($record['sha256']??null)!==$sha||
           ($record['size']??null)!==$size||
           ($type==='derivative'&&($record['asset_id']??null)!==$assetId)||
           ($type==='asset'&&$id!==$assetId))
            throw new Error('provider_cutover_mapping_stale',
                'Native media source no longer equals the copied inventory.',409);
        $owner=$asset['owner_domain']??null;
        if(!is_string($owner)||$owner===''||Utils::key($owner,64)!==$owner)
            throw new Error('provider_cutover_owner_missing',
                'A canonical native domain owner must authorize cutover.',409);
        // A mere string in owner_domain is not evidence that the native owner
        // integration actually exists. Never produce a complete unfenced
        // manifest for an unknown, disabled, or unregistered owner module.
        // Presence is NOT consent or an executable write fence.
        if(!DomainRegistry::has($owner))
            throw new Error('provider_cutover_owner_unregistered',
                'Canonical native owner domain is not registered for integration.',409);
        $generation=Utils::integer($asset['object_version']??null,
            'provider_cutover_owner_version_invalid',1,PHP_INT_MAX);
        $cas=Utils::integer($record['version']??null,
            'provider_cutover_source_version_invalid',1,PHP_INT_MAX);
        LegalHoldService::assertNoHold($assetId,'provider_exit');
        ResidencyCryptoService::assertProviderRegion($assetId,
            (string)$stage['target_provider']);
        return ['type'=>$type,'id'=>$id,'asset_id'=>$assetId,
            'native_domain'=>$owner,'owner_generation'=>$generation,
            'record_cas'=>$cas,'source_provider'=>$stage['source_provider'],
            'source_key'=>$key,'target_provider'=>$stage['target_provider'],
            'target_key'=>$target,'sha256'=>$sha,'size'=>$size,
            'status'=>'unfenced_candidate'];
    }

    private static function segment(string $planId,array $inventorySegment,
        array $stage,bool $allowCreate):array {
        $inventoryId=(string)$stage['id'];
        $segmentId=(string)$inventorySegment['id'];
        $items=$inventorySegment['items']??null;
        if(($inventorySegment['inventory_id']??null)!==$inventoryId||
           !is_array($items)||!array_is_list($items)||count($items)>250)
            throw new Error('provider_cutover_segment_invalid',
                'Source inventory segment is malformed.',409);
        $shadow=RecordStore::get('provider_exit_shadow_segment',
            hash('sha256',$inventoryId.'|'.$segmentId));
        if(!$shadow||($shadow['status']??null)!=='copied_unfenced'||
           ($shadow['inventory_id']??null)!==$inventoryId||
           ($shadow['segment_id']??null)!==$segmentId||
           !is_array($shadow['copies']??null)||
           !array_is_list($shadow['copies'])||
           count($shadow['copies'])!==count($items))
            throw new Error('provider_cutover_shadow_invalid',
                'Copied source and target inventory must be complete.',409);
        $mappings=[];
        foreach($items as $index=>$item)
            $mappings[]=self::mapping($item,$shadow['copies'][$index],$stage);
        $hash=hash('sha256',Utils::canonicalJson($mappings));
        $id=hash('sha256','cutover-plan|'.$planId.'|'.$segmentId);
        $saved=RecordStore::get('provider_cutover_segment',$id);
        if(!$saved){
            if(!$allowCreate)
                throw new Error('provider_cutover_manifest_missing',
                    'A required cutover mapping page is missing.',409);
            $saved=RecordStore::put('provider_cutover_segment',$id,[
                'actor_id'=>0,'plan_id'=>$planId,
                'inventory_segment_id'=>$segmentId,
                'status'=>'prepared_unfenced','mappings'=>$mappings,
                'mapping_hash'=>$hash,'created_at'=>Utils::now()
            ],0);
        }
        if(($saved['plan_id']??null)!==$planId||
           ($saved['inventory_segment_id']??null)!==$segmentId||
           ($saved['status']??null)!=='prepared_unfenced'||
           ($saved['mapping_hash']??null)!==$hash||
           !is_array($saved['mappings']??null)||
           Utils::canonicalJson($saved['mappings'])!==Utils::canonicalJson($mappings))
            throw new Error('provider_cutover_manifest_drift',
                'Saved cross-owner mapping no longer matches the source.',409);
        return ['segment_id'=>$segmentId,'id'=>$id,
            'mapping_hash'=>$hash,'candidates'=>count($mappings)];
    }

    /** Advance at most one staged inventory segment per call. */
    public static function advance(string $planId,int $actor):array {
        $plan=self::load($planId,$actor);
        if(($plan['status']??null)==='verified_unfenced'){
            self::verifyComplete($planId,$actor);
            return $plan;
        }
        if(($plan['status']??null)!=='planning_unfenced')
            throw new Error('provider_cutover_plan_invalid',
                'Cutover manifest cannot advance in the present state.',409);
        $stage=self::stage((string)$plan['inventory_id'],$actor);
        if(($plan['inventory_chain']??null)!==$stage['chain_hash']||
           ($plan['source_provider']??null)!==$stage['source_provider']||
           ($plan['target_provider']??null)!==$stage['target_provider'])
            throw new Error('provider_cutover_inventory_drift',
                'Original provider exit scope changed after cutover planning.',409);
        $cursor=$plan['after_segment_id'];
        $page=RecordStore::scan('provider_exit_inventory_segment',250,$cursor);
        foreach($page as $seg){
            $next=(string)$seg['id'];
            if($next===''||$next<=$cursor)
                throw new Error('provider_cutover_catalog_invalid',
                    'Inventory segment cursor is noncanonical.',409);
            $cursor=$next;
            if(($seg['inventory_id']??null)!==$plan['inventory_id'])continue;
            $proof=self::segment($planId,$seg,$stage,true);
            $plan['segments']++;$plan['candidates']+=$proof['candidates'];
            $plan['chain_hash']=hash('sha256',$plan['chain_hash'].'|'.
                $proof['id'].'|'.$proof['mapping_hash']);
            $plan['after_segment_id']=$cursor;
            $plan['updated_at']=Utils::now();
            return RecordStore::put('provider_cutover_plan',$planId,
                $plan,(int)$plan['version']);
        }
        $plan['after_segment_id']=$cursor;
        if(count($page)<250){
            if($plan['segments']!==$stage['segments']||
               $plan['candidates']!==$stage['eligible_items'])
                throw new Error('provider_cutover_manifest_incomplete',
                    'A provider exit mapping segment is unaccounted for.',409);
            self::verifyComplete($planId,$actor);
            $plan['status']='verified_unfenced';
            $plan['verified_at']=Utils::now();
        }
        $plan['updated_at']=Utils::now();
        return RecordStore::put('provider_cutover_plan',$planId,
            $plan,(int)$plan['version']);
    }

    /** Full fresh source/target, owner-version and mapping-chain recheck. */
    public static function verifyComplete(string $planId,int $actor):array {
        $plan=self::load($planId,$actor);
        if(!in_array(($plan['status']??null),
            ['planning_unfenced','verified_unfenced'],true))
            throw new Error('provider_cutover_plan_invalid',
                'Cutover manifest cannot be verified in this state.',409);
        $stage=self::stage((string)$plan['inventory_id'],$actor);
        if(($plan['inventory_chain']??null)!==$stage['chain_hash']||
           ($plan['source_provider']??null)!==$stage['source_provider']||
           ($plan['target_provider']??null)!==$stage['target_provider'])
            throw new Error('provider_cutover_inventory_drift',
                'Cross-owner source/target provider identity changed.',409);
        $chain=str_repeat('0',64);$segments=0;$candidates=0;$cursor='';
        do{
            $page=RecordStore::scan('provider_exit_inventory_segment',250,$cursor);
            foreach($page as $seg){
                if(($seg['inventory_id']??null)!==$plan['inventory_id'])continue;
                $entry=self::segment($planId,$seg,$stage,false);
                $chain=hash('sha256',$chain.'|'.$entry['id'].'|'.$entry['mapping_hash']);
                $segments++;$candidates+=$entry['candidates'];
            }
            if(count($page)<250)break;
            $cursor=(string)$page[count($page)-1]['id'];
        }while(true);
        StageSegmentCatalogGuard::assertExactCount('provider_cutover_segment',
            'plan_id',$planId,$segments,'provider_cutover_manifest_drift');
        if($segments!==$stage['segments']||
           $candidates!==$stage['eligible_items']||
           $segments!==$plan['segments']||
           $candidates!==$plan['candidates']||
           ($plan['chain_hash']??null)!==$chain)
            throw new Error('provider_cutover_manifest_drift',
                'Saved provider mapping proof differs from fully rechecked media.',409);
        return ['plan_id'=>$planId,'status'=>'verified_unfenced',
            'segments'=>$segments,'candidates'=>$candidates,
            'owner_write_fenced'=>false,'authorization_to_switch'=>false,
            'source_purged'=>false,'credentials_revoked'=>false];
    }
}


/**
 * A source-only, side-effect-free approval preflight against native owner
 * callback contracts. It does not acquire a write fence, switch any provider,
 * commit owner mappings or authorize physical purge.
 */
final class ProviderExitOwnerReadiness {
    public static function inspect(string $planId,int $actor): array {
        Auth::capability('media_manage_providers');
        Auth::assertActor($actor,'manage_options');
        // Revalidate every staged source/target byte before consulting owners.
        $verified=ProviderExitCutoverManifestStage::verifyComplete($planId,$actor);
        if(($verified['status']??null)!=='verified_unfenced'||
           ($verified['authorization_to_switch']??true)!==false)
            throw new Error('provider_owner_readiness_invalid',
                'Only fully verified, non-executable cutover plans can be inspected.',409);
        $plan=RecordStore::get('provider_cutover_plan',$planId);
        if(!$plan||($plan['status']??null)!=='verified_unfenced')
            throw new Error('provider_owner_readiness_invalid',
                'Native owner preflight requires a terminal source manifest.',409);
        $after='';$seen=0;$owners=[];
        $hash=hash_init('sha256');
        do{
            $page=RecordStore::scan('provider_cutover_segment',250,$after);
            foreach($page as $segment){
                if(($segment['plan_id']??null)!==$planId)continue;
                if(($segment['status']??null)!=='prepared_unfenced'||
                   !is_array($segment['mappings']??null)||
                   !array_is_list($segment['mappings']))
                    throw new Error('provider_owner_segment_invalid',
                        'Owner mapping journal is noncanonical.',409);
                foreach($segment['mappings'] as $mapping){
                    $owner=$mapping['native_domain']??null;
                    $asset=$mapping['asset_id']??null;
                    $recordId=$mapping['id']??null;
                    $type=$mapping['type']??null;
                    if(!is_string($owner)||$owner===''||
                       Utils::key($owner,64)!==$owner||!DomainRegistry::has($owner)||
                       !is_string($asset)||$asset===''||
                       !is_string($recordId)||$recordId===''||
                       !in_array($type,['asset','derivative'],true))
                        throw new Error('provider_owner_identity_invalid',
                            'Native owner identity is unavailable or malformed.',409);
                    $ownerVersion=Utils::integer($mapping['owner_generation']??null,
                        'provider_owner_version_invalid',1,PHP_INT_MAX);
                    $cas=Utils::integer($mapping['record_cas']??null,
                        'provider_owner_cas_invalid',1,PHP_INT_MAX);
                    $decision=DomainRegistry::decision($owner,
                        'authorize_provider_exit',[
                            'plan_id'=>$planId,'asset_id'=>$asset,
                            'record_type'=>$type,'record_id'=>$recordId,
                            'object_version'=>$ownerVersion,'record_cas'=>$cas,
                            'source_provider'=>$mapping['source_provider'],
                            'target_provider'=>$mapping['target_provider'],
                            // No raw object keys or clinical media bytes leave CF-04.
                            'source_key_sha256'=>hash('sha256',(string)$mapping['source_key']),
                            'target_key_sha256'=>hash('sha256',(string)$mapping['target_key']),
                            'phase'=>'read_only_preflight'
                        ],true);
                    if(($decision['approved_for_provider_exit']??null)!==true||
                       $decision['object_version']!==$ownerVersion||
                       Utils::integer($decision['record_cas']??null,
                           'provider_owner_cas_mismatch',1,PHP_INT_MAX)!==$cas)
                        throw new Error('provider_owner_approval_stale',
                            'Owner approval is absent or does not match current versions.',409);
                    $contract=$decision['contract_version']??null;
                    if(!is_string($contract)||$contract==='')throw new Error(
                        'provider_owner_contract_invalid','Owner contract version missing.',409);
                    hash_update($hash,Utils::canonicalJson([
                        'owner'=>$owner,'asset'=>$asset,'record_type'=>$type,
                        'record_id'=>$recordId,'owner_version'=>$ownerVersion,
                        'record_cas'=>$cas,'owner_contract'=>$contract
                    ])."\n");
                    $owners[$owner]=true;
                    if($seen===PHP_INT_MAX)throw new Error(
                        'provider_owner_count_overflow','Owner approval count overflow.',409);
                    $seen++;
                }
            }
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
        if($seen!==($verified['candidates']??null)||$seen<1)
            throw new Error('provider_owner_incomplete',
                'Approved owner references differ from the cutover manifest.',409);
        // Owner callbacks are external code. A callback or concurrent job can
        // mutate a source record while approvals are being collected. Never
        // return even an unfenced approval on a stale, post-callback inventory.
        $fresh=ProviderExitCutoverManifestStage::verifyComplete($planId,$actor);
        if(($fresh['candidates']??null)!==$seen||
           ($fresh['authorization_to_switch']??true)!==false)
            throw new Error('provider_owner_postcheck_drift',
                'Native records changed during owner decision collection.',409);
        return ['plan_id'=>$planId,'status'=>'owner_approved_unfenced',
            'approvals'=>$seen,'registered_domains'=>count($owners),
            'owner_decision_chain'=>hash_final($hash),
            'native_owner_write_fenced'=>false,
            'authorization_to_switch'=>false,
            'source_purged'=>false,'credentials_revoked'=>false];
    }
}
