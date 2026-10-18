<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

/** Fail-closed streaming witness for provider cutover and ciphertext deletion. */
final class StorageReferenceWitness {
    public static function first(string $providerId,?string $objectKey=null): ?array {
        if($providerId===''||Utils::key($providerId,64)!==$providerId
            ||($objectKey!==null&&!preg_match('/^[a-f0-9]{64}$/D',$objectKey)))
            throw new Error('storage_reference_query_invalid','Storage reference query is noncanonical.',500);
        foreach(['asset','derivative'] as $type){
            $after='';
            do{
                $page=RecordStore::scan($type,250,$after);
                if($page===[])break;
                foreach($page as $record){
                    $after=(string)$record['id'];
                    if(($record['status']??'')==='deleted'
                        ||($record['storage']['provider_id']??null)!==$providerId
                        ||($objectKey!==null&&($record['object_key']??null)!==$objectKey))
                        continue;
                    return ['type'=>$type,'id'=>$after];
                }
            }while(count($page)===250);
        }
        return null;
    }
}

final class ProviderExitService {
    public static function plan(string $sourceProvider,string $targetProvider,int $actor): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
    $sourceProvider=Utils::key($sourceProvider,64);$targetProvider=Utils::key($targetProvider,64);
    if($sourceProvider===''||$targetProvider===''||$sourceProvider===$targetProvider)throw new Error('provider_exit_identity_invalid','Distinct source and target providers are required.',400);
    $source=ProviderRegistry::get($sourceProvider);$target=ProviderRegistry::get($targetProvider);
    foreach(['copy','delete','streaming'] as $capability)if(($source->capabilities()[$capability]??false)!==true||($target->capabilities()[$capability]??false)!==true)throw new Error('provider_exit_capability_missing','Provider exit capability missing.',503,['capability'=>$capability]);
    $id=Utils::id('pex');$items=[];
    foreach(self::inventoryRows('asset') as $asset){
        if(($asset['status']??'')==='deleted'||($asset['storage']['provider_id']??'')!==$sourceProvider)continue;
        LegalHoldService::assertNoHold((string)$asset['id'],'provider_exit');ResidencyCryptoService::assertProviderRegion((string)$asset['id'],$targetProvider);self::identity($asset);
        if(count($items)>=2000)throw new Error('provider_exit_plan_capacity_exceeded','Provider-exit inventory exceeds the safe single-plan capacity; use an approved staged migration.',503);
        $items[]=['type'=>'asset','id'=>$asset['id'],'object_key'=>$asset['object_key'],'sha256'=>$asset['sha256'],'size'=>$asset['size'],'status'=>'pending'];
    }
    foreach(self::inventoryRows('derivative') as $derivative){
        if(($derivative['status']??'')==='deleted'||($derivative['storage']['provider_id']??'')!==$sourceProvider)continue;
        $assetId=Utils::text((string)($derivative['asset_id']??''),96);$parent=$assetId!==''?RecordStore::get('asset',$assetId):null;
        if(!$parent||($parent['status']??'')==='deleted')throw new Error('provider_exit_derivative_orphaned','Source-provider derivative has no active parent asset.',409,['derivative_id'=>$derivative['id']??'']);
        LegalHoldService::assertNoHold($assetId,'provider_exit');ResidencyCryptoService::assertProviderRegion($assetId,$targetProvider);self::identity($derivative);
        if(count($items)>=2000)throw new Error('provider_exit_plan_capacity_exceeded','Provider-exit inventory exceeds the safe single-plan capacity; use an approved staged migration.',503);
        $items[]=['type'=>'derivative','id'=>$derivative['id'],'asset_id'=>$assetId,'object_key'=>$derivative['object_key'],'sha256'=>$derivative['sha256'],'size'=>$derivative['size'],'status'=>'pending'];
    }
    if($items===[])throw new Error('provider_exit_inventory_empty','No eligible source-provider objects were found.',409);
    $record=['actor_id'=>$actor,'exit_id'=>$id,'source_provider'=>$sourceProvider,'target_provider'=>$targetProvider,'status'=>'planned','items'=>$items,'copied'=>0,'verified'=>0,'switched'=>0,'purged'=>0,'created_at'=>Utils::now()];
    return RecordStore::put('provider_exit',$id,$record);
}
    /** Plan inventory is streamed; no globally capped asset/derivative materialization. */
    private static function inventoryRows(string $type): \Generator {
        if(!in_array($type,['asset','derivative'],true))
            throw new Error('provider_exit_inventory_type_invalid','Provider-exit record family is invalid.',500);
        $after='';
        do {
            $page=RecordStore::scan($type,250,$after);
            foreach($page as $row)yield $row;
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
    }
    public static function copy(string $exitId): array {
        $plan=self::load($exitId,['planned','copying','copy_failed','verified']);if(($plan['status']??'')==='verified')return $plan;$source=ProviderRegistry::get((string)$plan['source_provider']);$target=ProviderRegistry::get((string)$plan['target_provider']);$plan['status']='copying';$plan=self::save($plan);
        try{foreach($plan['items'] as $index=>$item){if(in_array(($item['status']??''),['verified','switched','purged'],true))continue;$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');ResidencyCryptoService::assertProviderRegion($holdAssetId,(string)$plan['target_provider']);$identity=self::identity($item);$targetKey=hash('sha256','migration|'.$plan['target_provider'].'|'.$identity['object_key'].'|'.$identity['sha256']);$stored=$source->copyTo($identity['object_key'],$target,$targetKey);$stream=$target->openStream($targetKey);try{$stats=Utils::streamHash($stream);}finally{fclose($stream);}if(!hash_equals($identity['sha256'],$stats['sha256'])||$identity['size']!==$stats['size'])throw new Error('provider_exit_integrity_failed','Copied object integrity failed.',500,['id'=>$item['id']]);$plan['items'][$index]['target_key']=$targetKey;$plan['items'][$index]['target_storage']=$stored+['provider_id'=>$plan['target_provider']];$plan['items'][$index]['status']='verified';$plan=self::counted(self::save($plan));$plan=self::save($plan);} $plan['status']='verified';$plan['verified_at']=Utils::now();return self::save(self::counted($plan));}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='copy_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save(self::counted($plan));}catch(\Throwable){}throw $e;}
    }
    public static function shadowVerify(string $exitId): array {
        $plan=self::load($exitId,['verified','shadow_verifying','shadow_failed','shadow_verified']);if(($plan['status']??'')==='shadow_verified')return $plan;$target=ProviderRegistry::get((string)$plan['target_provider']);$plan['status']='shadow_verifying';$plan=self::save($plan);
        try{foreach($plan['items'] as $item){$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no residency parent.',409,['id'=>$item['id']??'']);ResidencyCryptoService::assertProviderRegion($holdAssetId,(string)$plan['target_provider']);$identity=self::identity($item);if(empty($item['target_key']))throw new Error('provider_exit_target_missing','Target mapping missing.',500,['id'=>$item['id']]);$stream=$target->openStream((string)$item['target_key']);try{$stats=Utils::streamHash($stream);}finally{fclose($stream);}if(!hash_equals($identity['sha256'],$stats['sha256'])||$identity['size']!==$stats['size'])throw new Error('provider_shadow_mismatch','Shadow delivery integrity mismatch.',500,['id'=>$item['id']]);}$plan['status']='shadow_verified';$plan['shadow_verified_at']=Utils::now();return self::save($plan);}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='shadow_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save($plan);}catch(\Throwable){}throw $e;}
    }
    public static function switch(string $exitId): array {
        $plan=self::load($exitId,['shadow_verified','switching','switch_failed','switched']);if(($plan['status']??'')==='switched')return $plan;$plan['status']='switching';$plan=self::save($plan);
        try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified','Provider exit item is not verified.',409,['id'=>$item['id']]);$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');ResidencyCryptoService::assertProviderRegion($holdAssetId,(string)$plan['target_provider']);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing','Migrated record missing.',500,['id'=>$item['id']]);$planned=self::identity($item);$current=self::identity($record);if($planned['sha256']!==$current['sha256']||$planned['size']!==$current['size'])throw new Error('provider_exit_mapping_stale','Content identity changed during provider exit.',409,['id'=>$item['id']]);$provider=(string)($record['storage']['provider_id']??'');$key=(string)($record['object_key']??'');if($provider===(string)$plan['target_provider']&&$key===(string)$item['target_key']){$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));continue;}if($provider!==(string)$plan['source_provider']||$key!==(string)$item['object_key'])throw new Error('provider_exit_mapping_stale','Source mapping changed during provider exit.',409,['id'=>$item['id']]);$record['previous_storage']=$record['storage']??null;$record['previous_object_key']=$record['object_key']??null;$record['storage']=$item['target_storage'];$record['object_key']=$item['target_key'];$record['provider_migrated_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));}self::assertSourceDrained($plan);ProviderRegistry::activate((string)$plan['target_provider']);$plan['status']='switched';$plan['switched_at']=Utils::now();return self::save(self::counted($plan));}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='switch_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save(self::counted($plan));}catch(\Throwable){}throw $e;}
    }
    public static function rollback(string $exitId,int $actor,string $reason): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');$reason=Utils::text($reason,500);if($reason==='')throw new Error('provider_exit_reason_required','Provider rollback reason required.',400);$plan=self::load($exitId,['switching','switch_failed','switched','purge_pending']);foreach($plan['items'] as $item)if(($item['status']??'')==='purged')throw new Error('provider_exit_rollback_unavailable','Rollback is unsafe after source purge has begun.',409);
        $target=ProviderRegistry::get((string)$plan['target_provider']);$source=ProviderRegistry::get((string)$plan['source_provider']);
        // Preflight every original before the first compensating remap.
        // A missing final source must not leave earlier items half-rolled-back.
        foreach($plan['items'] as $candidate){
            if(($candidate['status']??null)!=='switched')continue;
            $type=($candidate['type']??null)==='asset'?'asset':'derivative';
            $id=(string)($candidate['id']??'');
            $current=$id!==''?RecordStore::get($type,$id):null;
            if(!$current||empty($current['previous_object_key'])||!is_array($current['previous_storage']??null))
                throw new Error('provider_exit_rollback_missing','Previous provider mapping missing during rollback preflight.',500,['id'=>$id]);
            $provider=(string)($current['storage']['provider_id']??'');
            $key=(string)($current['object_key']??'');
            if($provider===(string)$plan['target_provider']&&$key===(string)($candidate['target_key']??''))
                self::verifyRollbackSource($source,$current);
            elseif($provider!==(string)$plan['source_provider']||$key!==(string)($candidate['object_key']??''))
                throw new Error('provider_exit_rollback_mapping_stale','Provider rollback mapping changed before preflight.',409,['id'=>$id]);
        }
        foreach($plan['items'] as $index=>$item){if(($item['status']??'')!=='switched')continue;$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit rollback item has no policy parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');ResidencyCryptoService::assertProviderRegion($holdAssetId,(string)$plan['source_provider']);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record||empty($record['previous_object_key'])||empty($record['previous_storage']))throw new Error('provider_exit_rollback_missing','Previous provider mapping missing.',500,['id'=>$item['id']]);$currentProvider=(string)($record['storage']['provider_id']??'');$currentKey=(string)($record['object_key']??'');if($currentProvider===(string)$plan['target_provider']&&$currentKey===(string)$item['target_key']){self::verifyRollbackSource($source,$record);$record['object_key']=$record['previous_object_key'];$record['storage']=$record['previous_storage'];$record['provider_rollback_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);}elseif($currentProvider!==(string)$plan['source_provider']||$currentKey!==(string)$item['object_key'])throw new Error('provider_exit_rollback_mapping_stale','Provider mapping changed during rollback.',409,['id'=>$item['id']]);if(ResidencyCryptoService::isLocked($holdAssetId)){$plan['items'][$index]['target_cleanup_deferred']='object_lock';}elseif(StorageReferenceWitness::first((string)$plan['target_provider'],(string)$item['target_key'])!==null){$plan['items'][$index]['target_cleanup_deferred']='live_reference';}elseif($target->exists((string)$item['target_key'])&&!$target->delete((string)$item['target_key'])&&$target->exists((string)$item['target_key']))throw new Error('provider_exit_rollback_cleanup_failed','Rollback target cleanup failed.',503,['id'=>$item['id']]);$plan['items'][$index]['status']='verified';$plan=self::save(self::counted($plan));}ProviderRegistry::activate((string)$plan['source_provider']);$plan['status']='rolled_back';$plan['rollback_by']=$actor;$plan['rollback_reason']=$reason;$plan['rolled_back_at']=Utils::now();return self::save(self::counted($plan));
    }
    /** Rollback is forbidden unless the original source ciphertext decrypts to the expected bytes. */
    private static function verifyRollbackSource(ObjectStore $source,array $record): void {
        $expected=self::identity($record);
        $previous=$record['previous_object_key']??null;
        if(!is_string($previous)||!preg_match('/^[a-f0-9]{64}$/D',$previous))
            throw new Error('provider_exit_rollback_source_invalid','Rollback source object identity is invalid.',409);
        $stream=$source->openStream($previous);
        try{$stats=Utils::streamHash($stream,1073741824);}finally{fclose($stream);}
        if($stats['size']!==$expected['size']||!hash_equals($expected['sha256'],$stats['sha256']))
            throw new Error('provider_exit_rollback_source_mismatch','Original source object no longer matches the recorded media bytes.',409,['id'=>$record['id']??'']);
    }
    public static function purgeSource(string $exitId): array {
        $plan=self::load($exitId,['switched','purge_pending','credentials_pending']);$source=ProviderRegistry::get((string)$plan['source_provider']);$target=ProviderRegistry::get((string)$plan['target_provider']);$plan['status']='purge_pending';$plan=self::save($plan);
        // An exit plan can go stale after cutover. Never start destructive purge
        // while any authoritative asset or derivative still points to the source.
        self::assertSourceDrained($plan);
        foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='purged')continue;if(($item['status']??'')!=='switched')throw new Error('provider_exit_item_not_switched','Provider exit item was not switched.',409,['id'=>$item['id']]);$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');ResidencyCryptoService::assertUnlocked($holdAssetId,'provider_exit_purge');$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record||($record['storage']['provider_id']??'')!==(string)$plan['target_provider']||($record['object_key']??'')!==(string)$item['target_key'])throw new Error('provider_exit_target_mapping_invalid','Target mapping is not authoritative.',409,['id'=>$item['id']]);if(StorageReferenceWitness::first((string)$plan['source_provider'],(string)$item['object_key'])!==null)throw new Error('provider_exit_source_reference_live','Source ciphertext is still referenced; physical purge is forbidden.',409,['id'=>$item['id']]);
            // Shadow delivery can go stale. Revalidate actual target bytes at the
            // destructive boundary, after source-reference and policy guards.
            $expected=self::identity($item);
            $targetStream=$target->openStream((string)$item['target_key']);
            try{$targetStats=Utils::streamHash($targetStream,1073741824);}finally{fclose($targetStream);}
            if($targetStats['size']!==$expected['size']||!hash_equals($expected['sha256'],$targetStats['sha256']))
                throw new Error('provider_exit_purge_target_integrity_failed','Target bytes changed after shadow verification; source purge denied.',409,['id'=>$item['id']]);
            if($source->exists((string)$item['object_key'])&&!$source->delete((string)$item['object_key'])&&$source->exists((string)$item['object_key']))throw new Error('provider_source_purge_failed','Source provider purge failed.',503,['id'=>$item['id']]);$plan['items'][$index]['status']='purged';$plan=self::save(self::counted($plan));}
        $plan['status']='credentials_pending';$plan['credentials_revocation_required']=true;$plan['source_purge_completed_at']=$plan['source_purge_completed_at']??Utils::now();$plan=self::save(self::counted($plan));return self::finalizeCredentials($plan);
    }
    private static function finalizeCredentials(array $plan): array {
        // A late source alias must not lose its provider credentials. This
        // check is independent of the earlier purge-phase safety witnesses.
        self::assertSourceDrained($plan);
        $source=ProviderRegistry::get((string)$plan['source_provider']);
        if(!($source instanceof CredentialRevocableObjectStore)){throw new Error('provider_credentials_revocation_unavailable','Source provider cannot prove credential revocation; provider exit remains pending.',503,['provider'=>$plan['source_provider']]);}
        $evidence=$source->revokeExitCredentials(['exit_id'=>$plan['id'],'source_provider'=>$plan['source_provider'],'target_provider'=>$plan['target_provider'],'purged'=>(int)($plan['purged']??0)]);
        if(($evidence['revoked']??false)!==true){throw new Error('provider_credentials_revocation_unconfirmed','Source-provider credential revocation was not confirmed.',503);}
        self::assertSourceDrained($plan);
        $fresh=RecordStore::get('provider_exit',(string)$plan['id'])??$plan;$fresh['credentials_revocation_required']=false;$fresh['credentials_revocation_evidence']=Utils::redact($evidence);$fresh['credentials_revoked_at']=Utils::now();$fresh['status']='completed';$fresh['completed_at']=Utils::now();return self::save(self::counted($fresh));
    }
    private static function assertSourceDrained(array $plan): void {
        // One live reference prevents cutover; a bounded-page proof of absence
        // is required before activation, regardless of global inventory size.
        $witness=StorageReferenceWitness::first((string)$plan['source_provider']);
        if($witness!==null)throw new Error('provider_exit_inventory_drift',
            'Source-provider inventory changed during provider exit; re-plan before activation.',
            409,['remaining_count_at_least'=>1,'sample'=>[$witness]]);
    }
    private static function identity(array $record): array {
        $size=Utils::integer($record['size']??null,'provider_exit_identity_invalid',0,1073741824);
        $sha=$record['sha256']??null;$key=$record['object_key']??null;
        if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))
            throw new Error('provider_exit_identity_invalid','Provider-exit content identity must be canonical.',409);
        return ['size'=>$size,'sha256'=>$sha,'object_key'=>$key];
    }
    private static function load(string $id,array $states): array {$plan=RecordStore::get('provider_exit',$id);if(!$plan)throw new Error('provider_exit_not_found','Provider exit plan not found.',404);if(!in_array(($plan['status']??''),$states,true))throw new Error('provider_exit_state_invalid','Provider exit is not in an allowed state.',409,['status'=>$plan['status']??'unknown']);return $plan;}
    private static function save(array $plan): array {return RecordStore::put('provider_exit',(string)$plan['id'],$plan,(int)$plan['version']);}
    private static function counted(array $plan): array {$statuses=array_column((array)$plan['items'],'status');$plan['copied']=count(array_filter($statuses,fn($s)=>in_array($s,['verified','switched','purged'],true)));$plan['verified']=$plan['copied'];$plan['switched']=count(array_filter($statuses,fn($s)=>in_array($s,['switched','purged'],true)));$plan['purged']=count(array_filter($statuses,fn($s)=>$s==='purged'));return $plan;}
}

/**
 * Durable, non-destructive inventory staging for large provider exits.
 * This is deliberately NOT an executable cutover plan: source writes have
 * not been fenced and no provider copy, source deletion or credential
 * retirement may be driven by these provisional inventory segments.
 */
final class ProviderExitInventoryStage {
    public static function begin(string $sourceProvider,string $targetProvider,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $sourceProvider=Utils::key($sourceProvider,64);
        $targetProvider=Utils::key($targetProvider,64);
        if($sourceProvider===''||$targetProvider===''||$sourceProvider===$targetProvider)
            throw new Error('provider_exit_identity_invalid','Distinct source and target providers required.',400);
        $source=ProviderRegistry::get($sourceProvider);$target=ProviderRegistry::get($targetProvider);
        foreach(['copy','delete','streaming'] as $capability){
            if(($source->capabilities()[$capability]??false)!==true||
                ($target->capabilities()[$capability]??false)!==true)
                throw new Error('provider_exit_capability_missing','Provider capability unavailable.',503);
        }
        $id=Utils::id('pexinv');
        return RecordStore::put('provider_exit_inventory',$id,[
            'actor_id'=>$actor,'source_provider'=>$sourceProvider,
            'target_provider'=>$targetProvider,'status'=>'planning',
            'record_family'=>'asset','after_id'=>'','page_size'=>250,
            'segments'=>0,'eligible_items'=>0,'visited_rows'=>0,
            'chain_hash'=>str_repeat('0',64),'created_at'=>Utils::now()
        ],0);
    }

    /**
     * Audit a completed unfenced inventory against current canonical rows.
     * This does not authorize copy, cutover, source purge or key revocation.
     * It is a best-effort sequential read, not an atomic owner-write fence.
     */
    public static function verifyComplete(string $inventoryId,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $stage=RecordStore::get('provider_exit_inventory',$inventoryId);
        if(!$stage)throw new Error('provider_exit_inventory_not_found','Provider inventory stage missing.',404);
        if(($stage['status']??null)!=='inventory_complete_unfenced'||
           ($stage['record_family']??null)!=='complete'||
           ($stage['after_id']??null)!==''||
           ($stage['page_size']??null)!==250)
            throw new Error('provider_exit_inventory_not_complete','Provider inventory must be complete before verification.',409);
        $source=(string)($stage['source_provider']??'');
        $target=(string)($stage['target_provider']??'');
        ProviderRegistry::get($source);ProviderRegistry::get($target);
        $chain=str_repeat('0',64);$segments=0;$eligible=0;$visited=0;
        foreach(['asset','derivative'] as $family){
            $after='';
            do {
                $page=RecordStore::scan($family,250,$after);
                $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
                $id=hash('sha256',$inventoryId.'|'.$family.'|'.$after);
                $saved=RecordStore::get('provider_exit_inventory_segment',$id);
                $pageHash=hash('sha256',Utils::canonicalJson($page));
                if(!$saved||($saved['inventory_id']??null)!==$inventoryId||
                   ($saved['record_family']??null)!==$family||
                   ($saved['status']??null)!=='planned_unfenced'||
                   ($saved['after_id']??null)!==$after||
                   ($saved['last_id']??null)!==$last||
                   ($saved['page_hash']??null)!==$pageHash||
                   ($saved['raw_rows']??null)!==count($page)||
                   !is_array($saved['items']??null))
                    throw new Error('provider_exit_inventory_drift','Completed inventory page changed or segment journal is missing.',409);
                $expected=[];
                foreach($page as $row){
                    if(($row['status']??null)==='deleted'||
                       ($row['storage']['provider_id']??null)!==$source)continue;
                    $parent=$family==='asset'?(string)$row['id']:
                        (string)($row['asset_id']??'');
                    if($parent==='')throw new Error('provider_exit_inventory_drift','Staged object has no policy parent.',409);
                    if($family==='derivative'){
                        $asset=RecordStore::get('asset',$parent);
                        if(!$asset||($asset['status']??null)==='deleted')
                            throw new Error('provider_exit_inventory_drift','Staged derivative lost its parent.',409);
                    }
                    LegalHoldService::assertNoHold($parent,'provider_exit');
                    ResidencyCryptoService::assertProviderRegion($parent,$target);
                    $expected[]=['type'=>$family,'id'=>$row['id'],'asset_id'=>$parent,
                        'object_key'=>$row['object_key']??null,
                        'sha256'=>$row['sha256']??null,'size'=>$row['size']??null,
                        'status'=>'planned_unfenced'];
                }
                if(Utils::canonicalJson($expected)!==Utils::canonicalJson($saved['items']))
                    throw new Error('provider_exit_inventory_drift','Saved source manifest entries no longer match authoritative media rows.',409);
                $chain=hash('sha256',$chain.'|'.$id.'|'.$pageHash);
                $segments++;$visited+=count($page);$eligible+=count($expected);
                if(count($page)<250)break;
                $after=$last;
            }while(true);
        }
        if(($stage['chain_hash']??null)!==$chain||
           ($stage['segments']??null)!==$segments||
           ($stage['visited_rows']??null)!==$visited||
           ($stage['eligible_items']??null)!==$eligible)
            throw new Error('provider_exit_inventory_drift','Inventory stage counters or journal hash do not match source pages.',409);
        return ['inventory_id'=>$inventoryId,'status'=>'verified_unfenced',
            'segments'=>$segments,'visited_rows'=>$visited,
            'eligible_items'=>$eligible,'chain_hash'=>$chain,
            'verified_at'=>Utils::now()];
    }

    /** Advance exactly one small, idempotently journaled source page. */
    public static function advance(string $inventoryId,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $stage=RecordStore::get('provider_exit_inventory',$inventoryId);
        if(!$stage)throw new Error('provider_exit_inventory_not_found','Provider inventory stage missing.',404);
        if(($stage['status']??null)==='inventory_complete_unfenced')return $stage;
        if(($stage['status']??null)!=='planning')
            throw new Error('provider_exit_inventory_state_invalid','Provider inventory stage cannot advance.',409);
        $family=$stage['record_family']??null;$after=$stage['after_id']??null;
        if(!in_array($family,['asset','derivative'],true)||!is_string($after)||
            strlen($after)>96||Utils::text($after,96)!==$after||
            ($stage['page_size']??null)!==250)
            throw new Error('provider_exit_inventory_cursor_invalid','Inventory cursor is invalid.',409);
        $source=(string)$stage['source_provider'];
        $target=(string)$stage['target_provider'];
        ProviderRegistry::get($source);ProviderRegistry::get($target);
        $page=RecordStore::scan($family,250,$after);
        $pageHash=hash('sha256',Utils::canonicalJson($page));
        $segmentId=hash('sha256',$inventoryId.'|'.$family.'|'.$after);
        $items=[];
        foreach($page as $record){
            if(($record['status']??null)==='deleted'||
                ($record['storage']['provider_id']??null)!==$source)continue;
            $parentId=$family==='asset'?(string)$record['id']:
                (string)($record['asset_id']??'');
            if($parentId==='')throw new Error('provider_exit_derivative_orphaned','Media policy parent missing.',409);
            if($family==='derivative'){
                $parent=RecordStore::get('asset',$parentId);
                if(!$parent||($parent['status']??'')==='deleted')
                    throw new Error('provider_exit_derivative_orphaned','Media derivative has no active parent.',409);
            }
            LegalHoldService::assertNoHold($parentId,'provider_exit');
            ResidencyCryptoService::assertProviderRegion($parentId,$target);
            $sha=$record['sha256']??null;$key=$record['object_key']??null;
            $size=Utils::integer($record['size']??null,'provider_exit_identity_invalid',0,1073741824);
            if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
                !is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key))
                throw new Error('provider_exit_identity_invalid','Noncanonical staged object identity.',409);
            $items[]=['type'=>$family,'id'=>$record['id'],'asset_id'=>$parentId,
                'object_key'=>$key,'sha256'=>$sha,'size'=>$size,'status'=>'planned_unfenced'];
        }
        $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
        $existing=RecordStore::get('provider_exit_inventory_segment',$segmentId);
        if($existing!==null){
            // The page hash alone does not protect a saved manifest whose
            // selected item keys, sizes or digests were changed separately.
            if(($existing['inventory_id']??null)!==$inventoryId||
               ($existing['status']??null)!=='planned_unfenced'||
               ($existing['page_hash']??null)!==$pageHash||
               ($existing['after_id']??null)!==$after||
               ($existing['last_id']??null)!==$last||
               ($existing['record_family']??null)!==$family||
               ($existing['raw_rows']??null)!==count($page)||
               !is_array($existing['items']??null)||
               Utils::canonicalJson($existing['items'])!==Utils::canonicalJson($items))
                throw new Error('provider_exit_inventory_drift','Uncommitted inventory segment or selected media identities changed after journaling.',409);
        }else{
            RecordStore::put('provider_exit_inventory_segment',$segmentId,[
                'actor_id'=>$actor,'inventory_id'=>$inventoryId,'record_family'=>$family,
                'after_id'=>$after,'last_id'=>$last,'page_hash'=>$pageHash,
                'raw_rows'=>count($page),'items'=>$items,
                'status'=>'planned_unfenced','created_at'=>Utils::now()
            ],0);
        }
        $stage['visited_rows']+=(int)count($page);
        $stage['eligible_items']+=count($items);
        $stage['segments']++;
        $stage['chain_hash']=hash('sha256',(string)$stage['chain_hash'].'|'.$segmentId.'|'.$pageHash);
        if(count($page)<250){
            $stage['after_id']='';
            if($family==='asset')$stage['record_family']='derivative';
            else{$stage['record_family']='complete';$stage['status']='inventory_complete_unfenced';}
        }else{$stage['after_id']=$last;}
        $stage['updated_at']=Utils::now();
        return RecordStore::put('provider_exit_inventory',$inventoryId,$stage,(int)$stage['version']);
    }
}

/**
 * Durable, bounded, read-only discovery for large key-rotation workloads.
 * Candidate records are NOT safe atomic ciphertext groups; an executable
 * worker must gather every current/stale alias and acquire owner fencing.
 */

/**
 * Resumable, bounded non-destructive provider-exit shadow copying.
 *
 * Explicitly UNFENCED: physical copy and hash validation are not a
 * cross-owner cutover transaction. No authoritative mapping, source
 * ciphertext, active provider or credential may be changed here.
 */
final class ProviderExitShadowCopyStage {
    private static function loaded(string $inventoryId,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $state=RecordStore::get('provider_exit_inventory',$inventoryId);
        if(!$state||($state['status']??null)!=='inventory_complete_unfenced')
            throw new Error('provider_exit_shadow_inventory_incomplete',
                'Only completed inventory plans may be shadow-copied.',409);
        ProviderExitInventoryStage::verifyComplete($inventoryId,$actor);
        return $state;
    }
    private static function hashObject(ObjectStore $store,string $key): array {
        $stream=$store->openStream($key);
        try{return Utils::streamHash($stream,1073741824);}
        finally{fclose($stream);}
    }
    private static function expected(array $item,array $stage): array {
        if(($item['status']??null)!=='planned_unfenced'||
            !in_array(($item['type']??null),['asset','derivative'],true)||
            !is_string($item['id']??null)||$item['id']===''||
            !is_string($item['asset_id']??null)||$item['asset_id']===''||
            !is_string($item['object_key']??null)||
            !preg_match('/^[a-f0-9]{64}$/D',$item['object_key'])||
            !is_string($item['sha256']??null)||
            !preg_match('/^[a-f0-9]{64}$/D',$item['sha256']))
            throw new Error('provider_exit_shadow_identity_invalid','Untrusted staged item identity.',409);
        $bytes=Utils::integer($item['size']??null,
            'provider_exit_shadow_identity_invalid',0,1073741824);
        $current=RecordStore::get($item['type'],$item['id']);
        if(!$current||($current['status']??null)==='deleted'||
            ($current['storage']['provider_id']??null)!==$stage['source_provider']||
            ($current['object_key']??null)!==$item['object_key']||
            ($current['sha256']??null)!==$item['sha256']||
            ($current['size']??null)!==$bytes||
            ($item['type']==='derivative'&&($current['asset_id']??null)!==$item['asset_id']))
            throw new Error('provider_exit_shadow_mapping_stale',
                'Authoritative source mapping changed during shadow copying.',409);
        LegalHoldService::assertNoHold($item['asset_id'],'provider_exit');
        ResidencyCryptoService::assertProviderRegion($item['asset_id'],(string)$stage['target_provider']);
        return ['sha256'=>$item['sha256'],'size'=>$bytes,'key'=>$item['object_key']];
    }

    /** Move at most 25 copied items forward, with a durable per-segment CAS cursor. */
    public static function copySegment(string $inventoryId,string $segmentId,int $actor,int $limit=10): array {
        if($limit<1||$limit>25)
            throw new Error('provider_exit_shadow_limit_invalid','Shadow batch size must be 1–25.',400);
        $stage=self::loaded($inventoryId,$actor);
        $segment=RecordStore::get('provider_exit_inventory_segment',$segmentId);
        if(!$segment||($segment['inventory_id']??null)!==$inventoryId||
            ($segment['status']??null)!=='planned_unfenced'||
            !is_array($segment['items']??null)||count($segment['items'])>250)
            throw new Error('provider_exit_shadow_segment_invalid','Segment is not a valid stage item journal.',409);
        $copyId=hash('sha256',$inventoryId.'|'.$segmentId);
        $row=RecordStore::get('provider_exit_shadow_segment',$copyId);
        if(!$row)$row=RecordStore::put('provider_exit_shadow_segment',$copyId,[
            'actor_id'=>$actor,'inventory_id'=>$inventoryId,'segment_id'=>$segmentId,
            'status'=>'copying_unfenced','next_index'=>0,'copies'=>[],
            'created_at'=>Utils::now()
        ],0);
        if(($row['status']??null)==='copied_unfenced'){
            if(($row['next_index']??null)!==count($segment['items'])||
               count($row['copies']??[])!==count($segment['items']))
                throw new Error('provider_exit_shadow_journal_invalid','Completed shadow journal is incomplete.',409);
            return $row;
        }
        if(($row['status']??null)!=='copying_unfenced'||
           !is_int($row['next_index']??null)||
           $row['next_index']<0||$row['next_index']>count($segment['items'])||
           !is_array($row['copies']??null)||count($row['copies'])!==$row['next_index'])
            throw new Error('provider_exit_shadow_journal_invalid','Shadow copy checkpoint is invalid.',409);
        $source=ProviderRegistry::get((string)$stage['source_provider']);
        $target=ProviderRegistry::get((string)$stage['target_provider']);
        $until=min(count($segment['items']),$row['next_index']+$limit);
        for($i=$row['next_index'];$i<$until;$i++){
            $item=$segment['items'][$i];
            $identity=self::expected($item,$stage);
            $sourceStats=self::hashObject($source,$identity['key']);
            if($sourceStats['size']!==$identity['size']||
               !hash_equals($sourceStats['sha256'],$identity['sha256']))
                throw new Error('provider_exit_shadow_source_mismatch',
                    'Source bytes differ from the staged original.',409);
            $targetKey=hash('sha256','provider-shadow|'.$inventoryId.'|'.$segmentId.
                '|'.$identity['key'].'|'.$identity['sha256']);
            $stored=$source->copyTo($identity['key'],$target,$targetKey);
            if(!is_array($stored))
                throw new Error('provider_exit_shadow_copy_invalid','Object store copy evidence is invalid.',503);
            $targetStats=self::hashObject($target,$targetKey);
            if($targetStats['size']!==$identity['size']||
               !hash_equals($targetStats['sha256'],$identity['sha256']))
                throw new Error('provider_exit_shadow_target_mismatch',
                    'Shadow target bytes do not match source identity.',409);
            $row['copies'][]=['type'=>$item['type'],'id'=>$item['id'],
                'source_key'=>$identity['key'],'target_key'=>$targetKey,
                'sha256'=>$identity['sha256'],'size'=>$identity['size'],
                'status'=>'copied_unfenced'];
            $row['next_index']=$i+1;
            $row['updated_at']=Utils::now();
            // Persist each successfully verified copy. Failed next copy resumes here.
            $row=RecordStore::put('provider_exit_shadow_segment',$copyId,
                $row,(int)$row['version']);
        }
        if($row['next_index']===count($segment['items'])){
            $row['status']='copied_unfenced';
            $row['completed_at']=Utils::now();
            $row=RecordStore::put('provider_exit_shadow_segment',$copyId,
                $row,(int)$row['version']);
        }
        return $row;
    }

    /**
     * Cooperative worker for an entire segmented inventory. One invocation
     * copies at most 25 objects and completes at most three segment journals.
     * Parent progress advances only after a segment's item journal is durable.
     * Never changes native owner references, active provider, or old ciphertext.
     */
    public static function advanceInventory(string $inventoryId,int $actor,int $limit=10): array {
        if($limit<1||$limit>25)
            throw new Error('provider_exit_shadow_limit_invalid','Shadow worker limit must be 1–25.',400);
        $stage=self::loaded($inventoryId,$actor);
        $workId=hash('sha256','shadow-run|'.$inventoryId);
        $work=RecordStore::get('provider_exit_shadow_run',$workId);
        if(!$work){
            $work=RecordStore::put('provider_exit_shadow_run',$workId,[
                'actor_id'=>$actor,'inventory_id'=>$inventoryId,
                'inventory_chain'=>$stage['chain_hash'],
                'status'=>'copying_unfenced','after_segment_id'=>'',
                'segments_done'=>0,'completed_objects'=>0,'created_at'=>Utils::now()
            ],0);
        }
        if(($work['inventory_id']??null)!==$inventoryId||
           ($work['inventory_chain']??null)!==$stage['chain_hash']||
           !is_string($work['after_segment_id']??null)||
           strlen($work['after_segment_id'])>96||
           Utils::text($work['after_segment_id'],96)!==$work['after_segment_id']||
           !is_int($work['segments_done']??null)||$work['segments_done']<0||
           !is_int($work['completed_objects']??null)||$work['completed_objects']<0)
            throw new Error('provider_exit_shadow_run_invalid','Shadow worker checkpoint does not match source inventory.',409);
        if(($work['status']??null)==='shadow_verified_unfenced'){
            // A previous verified run must still be valid if queried again.
            self::verifyComplete($inventoryId,$actor);
            return $work;
        }
        if(($work['status']??null)!=='copying_unfenced')
            throw new Error('provider_exit_shadow_run_invalid','Shadow worker status invalid.',409);

        $cursor=$work['after_segment_id'];
        $remaining=$limit;
        $completedHere=0;$pages=0;$exhausted=false;
        // Bound both the physical copies and the catalogue traversal.
        while($remaining>0&&$completedHere<3&&$pages++<4){
            $page=RecordStore::scan('provider_exit_inventory_segment',250,$cursor);
            foreach($page as $segment){
                $id=(string)($segment['id']??'');
                if($id===''||$id<=$cursor)
                    throw new Error('provider_exit_shadow_catalog_invalid','Segment scan cursor is not strictly increasing.',409);
                if(($segment['inventory_id']??null)!==$inventoryId){
                    $cursor=$id;
                    continue;
                }
                $entryCount=is_array($segment['items']??null)?count($segment['items']):-1;
                if($entryCount<0||$entryCount>250)
                    throw new Error('provider_exit_shadow_segment_invalid','Invalid staged segment payload.',409);
                $copyId=hash('sha256',$inventoryId.'|'.$id);
                $before=RecordStore::get('provider_exit_shadow_segment',$copyId);
                $previous=$before?(int)($before['next_index']??0):0;
                $result=self::copySegment($inventoryId,$id,$actor,$remaining);
                $new=(int)($result['next_index']??-1);
                if($new<$previous||$new>$entryCount)
                    throw new Error('provider_exit_shadow_journal_invalid','Shadow copy cursor regressed.',409);
                $remaining-=($new-$previous);
                if(($result['status']??null)!=='copied_unfenced'){
                    $work['after_segment_id']=$cursor;
                    $work['updated_at']=Utils::now();
                    return RecordStore::put('provider_exit_shadow_run',$workId,$work,(int)$work['version']);
                }
                // A crash after copySegment() but before this write repeats
                // this completed segment and credits it exactly once.
                $cursor=$id;
                $work['after_segment_id']=$cursor;
                $work['segments_done']++;
                $work['completed_objects']+=$entryCount;
                $work['updated_at']=Utils::now();
                $work=RecordStore::put('provider_exit_shadow_run',$workId,$work,(int)$work['version']);
                $completedHere++;
                if($remaining===0||$completedHere>=3)break;
            }
            if($remaining===0||$completedHere>=3)break;
            if(count($page)<250){$exhausted=true;break;}
            if($page===[]){$exhausted=true;break;}
        }
        if($exhausted){
            if($work['segments_done']!==$stage['segments']||
               $work['completed_objects']!==$stage['eligible_items'])
                throw new Error('provider_exit_shadow_incomplete','Shadow worker counters do not match complete source.',409);
            self::verifyComplete($inventoryId,$actor);
            $work['status']='shadow_verified_unfenced';
            $work['verified_at']=Utils::now();
            $work=RecordStore::put('provider_exit_shadow_run',$workId,$work,(int)$work['version']);
        }
        return $work;
    }

    /** Validate all source/target bytes again; still no authority to cut over. */
    public static function verifyComplete(string $inventoryId,int $actor): array {
        $stage=self::loaded($inventoryId,$actor);
        $target=ProviderRegistry::get((string)$stage['target_provider']);
        $source=ProviderRegistry::get((string)$stage['source_provider']);
        $after='';$segments=0;$objects=0;
        do{
            $page=RecordStore::scan('provider_exit_inventory_segment',250,$after);
            foreach($page as $seg){
                if(($seg['inventory_id']??null)!==$inventoryId)continue;
                $copyId=hash('sha256',$inventoryId.'|'.$seg['id']);
                $row=RecordStore::get('provider_exit_shadow_segment',$copyId);
                $items=$seg['items']??null;
                if(!is_array($items)||!$row||
                   ($row['status']??null)!=='copied_unfenced'||
                   ($row['next_index']??null)!==count($items)||
                   !is_array($row['copies']??null)||
                   count($row['copies'])!==count($items))
                    throw new Error('provider_exit_shadow_incomplete','At least one shadow segment is unverified.',409);
                foreach($items as $index=>$item){
                    $expected=self::expected($item,$stage);
                    $proof=$row['copies'][$index]??null;
                    $targetKey=hash('sha256','provider-shadow|'.$inventoryId.'|'.$seg['id'].
                        '|'.$expected['key'].'|'.$expected['sha256']);
                    if(!is_array($proof)||
                       ($proof['type']??null)!==$item['type']||
                       ($proof['id']??null)!==$item['id']||
                       ($proof['source_key']??null)!==$expected['key']||
                       ($proof['target_key']??null)!==$targetKey||
                       ($proof['sha256']??null)!==$expected['sha256']||
                       ($proof['size']??null)!==$expected['size'])
                        throw new Error('provider_exit_shadow_journal_invalid','Shadow proof is not bound to its authoritative item.',409);
                    foreach([[$source,$expected['key']],[$target,$targetKey]] as $object){
                        $stats=self::hashObject($object[0],$object[1]);
                        if($stats['size']!==$expected['size']||
                           !hash_equals($stats['sha256'],$expected['sha256']))
                            throw new Error('provider_exit_shadow_integrity_failed','Shadow verification content differs from source.',409);
                    }
                    $objects++;
                }
                $segments++;
            }
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
        if($segments!==$stage['segments']||$objects!==$stage['eligible_items'])
            throw new Error('provider_exit_shadow_incomplete','Shadow evidence counts do not match the inventory.',409);
        return ['inventory_id'=>$inventoryId,'status'=>'shadow_verified_unfenced',
            'segments'=>$segments,'verified_objects'=>$objects];
    }
}

final class KeyRotationInventoryStage {
    public static function begin(int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        Keyring::assertReady();
        $id=Utils::id('kstage');
        return RecordStore::put('key_rotation_inventory',$id,[
            'actor_id'=>$actor,'active_key_id'=>Keyring::activeId(),
            'status'=>'planning','record_family'=>'asset','after_id'=>'',
            'segments'=>0,'visited_rows'=>0,'stale_references'=>0,
            'chain_hash'=>str_repeat('0',64),'page_size'=>250,
            'created_at'=>Utils::now()
        ],0);
    }
    public static function advance(string $id,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $stage=RecordStore::get('key_rotation_inventory',$id);
        if(!$stage)throw new Error('key_rotation_inventory_not_found','Key inventory stage unavailable.',404);
        if(($stage['status']??null)==='inventory_complete_unfenced')return $stage;
        if(($stage['status']??null)!=='planning'||
            !in_array(($stage['record_family']??null),['asset','derivative'],true)||
            !is_string($stage['after_id']??null)||
            strlen($stage['after_id'])>96||
            Utils::text($stage['after_id'],96)!==$stage['after_id']||
            ($stage['page_size']??null)!==250)
            throw new Error('key_rotation_inventory_cursor_invalid','Rotation stage cursor invalid.',409);
        Keyring::assertReady();
        if(($stage['active_key_id']??null)!==Keyring::activeId())
            throw new Error('key_rotation_inventory_key_changed','Active encryption key changed during discovery.',409);
        $family=$stage['record_family'];$after=$stage['after_id'];
        $page=RecordStore::scan($family,250,$after);
        $pageHash=hash('sha256',Utils::canonicalJson($page));
        $items=[];
        foreach($page as $record){
            if(($record['status']??null)==='deleted'||empty($record['object_key']))continue;
            $objectKey=$record['object_key']??null;
            $sha=$record['sha256']??null;
            $provider=$record['storage']['provider_id']??null;
            $oldKey=$record['storage']['key_id']??null;
            $size=Utils::integer($record['size']??null,'key_rotation_identity_invalid',0,1073741824);
            if(!is_string($objectKey)||!preg_match('/^[a-f0-9]{64}$/D',$objectKey)||
               !is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
               !is_string($provider)||$provider===''||Utils::key($provider,64)!==$provider||
               !is_string($oldKey)||$oldKey===''||Utils::key($oldKey,64)!==$oldKey)
                throw new Error('key_rotation_identity_invalid','Key inventory reference is noncanonical, even when labelled already-current.',409);
            // A current-key record is skipped only after its identity is verified.
            if($oldKey===$stage['active_key_id'])continue;
            $items[]=['type'=>$family,'id'=>$record['id'],'provider_id'=>$provider,
                'object_key'=>$objectKey,'sha256'=>$sha,'size'=>$size,
                'old_key_id'=>$oldKey,'status'=>'candidate_unfenced'];
        }
        $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
        $segId=hash('sha256',$id.'|'.$family.'|'.$after);
        $seg=RecordStore::get('key_rotation_inventory_segment',$segId);
        if($seg){
            if(($seg['inventory_id']??null)!==$id||
               ($seg['record_family']??null)!==$family||
               ($seg['after_id']??null)!==$after||
               ($seg['last_id']??null)!==$last||
               ($seg['status']??null)!=='candidate_unfenced'||
               ($seg['raw_rows']??null)!==count($page)||
               ($seg['page_hash']??null)!==$pageHash||
               !is_array($seg['items']??null)||
               Utils::canonicalJson($seg['items'])!==Utils::canonicalJson($items))
                throw new Error('key_rotation_inventory_drift','Staged key candidate page or segment changed.',409);
        }else{
            RecordStore::put('key_rotation_inventory_segment',$segId,[
                'actor_id'=>$actor,'inventory_id'=>$id,'record_family'=>$family,
                'after_id'=>$after,'last_id'=>$last,'page_hash'=>$pageHash,
                'raw_rows'=>count($page),'items'=>$items,
                'status'=>'candidate_unfenced','created_at'=>Utils::now()
            ],0);
        }
        $stage['visited_rows']+=count($page);
        $stage['stale_references']+=count($items);
        $stage['segments']++;
        $stage['chain_hash']=hash('sha256',(string)$stage['chain_hash'].'|'.$segId.'|'.$pageHash);
        if(count($page)<250){
            $stage['after_id']='';
            if($family==='asset')$stage['record_family']='derivative';
            else{$stage['record_family']='complete';$stage['status']='inventory_complete_unfenced';}
        }else{$stage['after_id']=$last;}
        $stage['updated_at']=Utils::now();
        return RecordStore::put('key_rotation_inventory',$id,$stage,(int)$stage['version']);
    }

    /** Full source/journal recheck; advisory only until alias write fencing exists. */
    public static function verifyComplete(string $id,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $stage=RecordStore::get('key_rotation_inventory',$id);
        if(!$stage||($stage['status']??null)!=='inventory_complete_unfenced'||
           ($stage['record_family']??null)!=='complete'||
           ($stage['after_id']??null)!==''||
           ($stage['page_size']??null)!==250)
            throw new Error('key_rotation_inventory_not_complete','Key inventory discovery is incomplete.',409);
        Keyring::assertReady();
        if(($stage['active_key_id']??null)!==Keyring::activeId())
            throw new Error('key_rotation_inventory_key_changed','Encryption key epoch changed after staging.',409);
        $hash=str_repeat('0',64);$segments=0;$visited=0;$stale=0;
        foreach(['asset','derivative'] as $family){
            $after='';
            do{
                $page=RecordStore::scan($family,250,$after);
                $pageHash=hash('sha256',Utils::canonicalJson($page));
                $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
                $segmentId=hash('sha256',$id.'|'.$family.'|'.$after);
                $segment=RecordStore::get('key_rotation_inventory_segment',$segmentId);
                $expected=[];
                foreach($page as $asset){
                    if(($asset['status']??null)==='deleted'||empty($asset['object_key']))continue;
                    if(($asset['storage']['key_id']??null)===$stage['active_key_id'])continue;
                    $expected[]=['type'=>$family,'id'=>$asset['id'],
                        'provider_id'=>$asset['storage']['provider_id']??null,
                        'object_key'=>$asset['object_key']??null,
                        'sha256'=>$asset['sha256']??null,
                        'size'=>$asset['size']??null,
                        'old_key_id'=>$asset['storage']['key_id']??null,
                        'status'=>'candidate_unfenced'];
                }
                if(!$segment||($segment['inventory_id']??null)!==$id||
                   ($segment['record_family']??null)!==$family||
                   ($segment['status']??null)!=='candidate_unfenced'||
                   ($segment['after_id']??null)!==$after||
                   ($segment['last_id']??null)!==$last||
                   ($segment['raw_rows']??null)!==count($page)||
                   ($segment['page_hash']??null)!==$pageHash||
                   !is_array($segment['items']??null)||
                   Utils::canonicalJson($segment['items'])!==Utils::canonicalJson($expected))
                    throw new Error('key_rotation_inventory_drift','Stale-key candidate segment changed after discovery.',409);
                $hash=hash('sha256',$hash.'|'.$segmentId.'|'.$pageHash);
                $segments++;$visited+=count($page);$stale+=count($expected);
                if(count($page)<250)break;
                $after=$last;
            }while(true);
        }
        if(($stage['chain_hash']??null)!==$hash||
           ($stage['segments']??null)!==$segments||
           ($stage['visited_rows']??null)!==$visited||
           ($stage['stale_references']??null)!==$stale)
            throw new Error('key_rotation_inventory_drift','Stale-key discovery totals or chained proof changed.',409);
        return ['inventory_id'=>$id,'status'=>'verified_unfenced',
            'segments'=>$segments,'visited_rows'=>$visited,
            'stale_references'=>$stale,'chain_hash'=>$hash];
    }
}

final class KeyRotationService {
    public static function rotateAll(int $actor): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');Keyring::assertReady();
    $active=Keyring::activeId();$runId=Utils::id('krot');$result=['rotation_id'=>$runId,'active_key_id'=>$active,'rotated'=>0,'failed'=>0,'groups'=>0,'orphan_cleanup_pending'=>0,'locked_old_objects_retained'=>0,'envelopes_reconciled'=>0,'deferred_cleanup_reconciled'=>0];
    // Select only physical objects with stale/malformed key envelopes; a site
    // can have millions of already-current references without consuming the
    // 2,000-reference safety budget of an actual rotation run.
    $pending=[];
    foreach(['asset','derivative'] as $type){
        foreach(self::rotationRows($type) as $record){
            if(($record['status']??'')==='deleted'||empty($record['object_key']))continue;
            $needs=(($record['storage']['key_id']??'')!==$active);
            if(!$needs){try{self::rotationIdentity($record);}catch(\Throwable){$needs=true;}}
            if(!$needs)continue;
            $groupKey=hash('sha256',Utils::canonicalJson(['provider'=>$record['storage']['provider_id']??null,'object_key'=>$record['object_key']]));
            if(!isset($pending[$groupKey])){
                if(count($pending)>=2000)throw new Error('key_rotation_capacity_exceeded','More than 2,000 physical groups need a durable staged rotation.',503);
                $pending[$groupKey]=true;
            }
        }
    }
    // Collect every alias, including aliases claiming to be on the current
    // key, for each stale physical object. Do not split a shared ciphertext.
    $groups=[];$selected=0;
    foreach(['asset','derivative'] as $type){
        foreach(self::rotationRows($type) as $record){
            if(($record['status']??'')==='deleted'||empty($record['object_key']))continue;
            $groupKey=hash('sha256',Utils::canonicalJson(['provider'=>$record['storage']['provider_id']??null,'object_key'=>$record['object_key']]));
            if(!isset($pending[$groupKey]))continue;
            if($selected>=2000)throw new Error('key_rotation_capacity_exceeded','Selected shared ciphertext aliases exceed the safe in-memory capacity.',503);
            $groups[$groupKey][]=['type'=>$type,'record'=>$record];$selected++;
        }
    }
    $groups=array_filter($groups,static function(array $group)use($active): bool {foreach($group as $entry){if(($entry['record']['storage']['key_id']??'')!==$active)return true;try{self::rotationIdentity($entry['record']);}catch(\Throwable){return true;}}return false;});
    RecordStore::put('key_rotation',$runId,['actor_id'=>$actor,'rotation_id'=>$runId,'active_key_id'=>$active,'status'=>'running','groups_total'=>count($groups),'result'=>$result,'created_at'=>Utils::now()]);
    $result['envelopes_reconciled']=self::reconcileCurrentEnvelopes($active,$runId);$result['deferred_cleanup_reconciled']=self::reconcileDeferredCleanup(200)['completed'];
    foreach($groups as $groupKey=>$group){
        $first=$group[0]['record'];$providerId='';$oldKey='';$newKey='';$stored=[];$writeAttempted=false;
        try{
            $identity=self::rotationIdentity($first);$providerId=$identity['provider_id'];$oldKey=$identity['object_key'];
            foreach($group as $entry){$candidate=self::rotationIdentity($entry['record']);if(!hash_equals($identity['sha256'],$candidate['sha256'])||$identity['size']!==$candidate['size']||($candidate['object_key']??'')!==$oldKey||$candidate['provider_id']!==$providerId)throw new Error('key_rotation_shared_identity_mismatch','Shared storage references disagree about physical content identity.',409,['group_hash'=>hash('sha256',$groupKey)]);}
            $provider=ProviderRegistry::get($providerId);$source=$provider->openStream($oldKey);
            try{
                $sourceStats=Utils::streamHash($source,1073741824);
                if($sourceStats['size']!==$identity['size']||!hash_equals($identity['sha256'],$sourceStats['sha256']))throw new Error('key_rotation_source_identity_mismatch','Source bytes differ from the persisted content identity.',409);
                $newKey=hash('sha256','rekey|'.$oldKey.'|'.$active.'|'.$identity['sha256']);
                $writeAttempted=true;$stored=$provider->putStream($newKey,$source,['scope'=>'key-rotation','source_key_hash'=>hash('sha256',$oldKey),'rotation_id'=>$runId]);
            }finally{fclose($source);}
            if(!is_array($stored))throw new Error('key_rotation_integrity_failed','Re-encrypted storage result is invalid.',500);
            $storedSize=Utils::integer($stored['size']??null,'key_rotation_integrity_failed',0,1073741824);
            if(!is_string($stored['sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$stored['sha256'])||!hash_equals($identity['sha256'],$stored['sha256'])||$identity['size']!==$storedSize||($stored['key_id']??null)!==$active)throw new Error('key_rotation_integrity_failed','Re-encrypted object identity or active key is invalid.',500);
            $stored['provider_id']=$providerId;$updated=0;
            foreach($group as $entry){
                $record=RecordStore::get($entry['type'],(string)$entry['record']['id']);
                if(!$record)throw new Error('key_rotation_record_missing','Key-rotation record disappeared.',409);
                $fresh=self::rotationIdentity($record);$expected=self::rotationIdentity($entry['record']);
                if($fresh['provider_id']!==$expected['provider_id']||$fresh['size']!==$expected['size']||!hash_equals($fresh['sha256'],$expected['sha256']))throw new Error('key_rotation_mapping_stale','Content identity changed during key rotation.',409);
                if(($record['object_key']??'')===$newKey&&($record['storage']['key_id']??'')===$active){$updated++;continue;}
                if(($record['object_key']??'')!==$oldKey||$fresh['key_id']!==$expected['key_id'])throw new Error('key_rotation_mapping_stale','Object mapping changed during key rotation.',409);
                $previousKeyId=$record['storage']['key_id']??null;$record['object_key']=$newKey;$record['storage']=$stored;$record['previous_key_id']=$previousKeyId;$record['rotated_at']=Utils::now();$record['rotation_id']=$runId;
                $record=RecordStore::put($entry['type'],(string)$record['id'],$record,(int)$record['version']);if($entry['type']==='asset')self::syncAssetEnvelope($record,$active,$runId);$updated++;$result['rotated']++;
            }
            if($updated!==count($group))throw new Error('key_rotation_partial_mapping','Not all mappings were rotated.',500);
            $remaining=self::liveReferences($providerId,$oldKey);
            if($remaining!==[])throw new Error('key_rotation_reference_drift','A live reference still points to the old encrypted object.',409,['remaining_count_at_least'=>count($remaining)]);
            $locked=false;foreach($group as $entry){$assetId=$entry['type']==='asset'?(string)$entry['record']['id']:(string)($entry['record']['asset_id']??'');if($assetId!==''&&ResidencyCryptoService::isLocked($assetId)){$locked=true;break;}}
            if($locked){$result['locked_old_objects_retained']++;self::deferCleanup($runId,$providerId,$oldKey,$group);}elseif($provider->exists($oldKey)&&!$provider->delete($oldKey)&&$provider->exists($oldKey))throw new Error('key_rotation_cleanup_failed','Old encrypted object could not be deleted.',503);
            $result['groups']++;
            RecordStore::put('key_rotation_group',hash('sha256',$runId.'|'.$groupKey),['actor_id'=>$actor,'rotation_id'=>$runId,'group_hash'=>hash('sha256',$groupKey),'status'=>'completed','records'=>$updated,'completed_at'=>Utils::now()]);
        }catch(\Throwable $exception){
            $result['failed']++;
            if($newKey!==''&&$providerId!==''&&$writeAttempted){$mapped=self::liveReferences($providerId,$newKey)!==[];if(!$mapped){if(($stored['reused']??null)===false){try{$cleanupProvider=ProviderRegistry::get($providerId);if($cleanupProvider->exists($newKey)&&!$cleanupProvider->delete($newKey)&&$cleanupProvider->exists($newKey))$result['orphan_cleanup_pending']++;}catch(\Throwable){$result['orphan_cleanup_pending']++;}}else{$result['orphan_cleanup_pending']++;}}}
            RecordStore::put('key_rotation_group',hash('sha256',$runId.'|'.$groupKey),['actor_id'=>$actor,'rotation_id'=>$runId,'group_hash'=>hash('sha256',$groupKey),'status'=>'failed','error'=>$exception instanceof Error?$exception->errorCode:'unexpected','created_at'=>Utils::now()]);
            Observability::alert('critical','key_rotation_failed',['rotation_id'=>$runId,'object_key_hash'=>hash('sha256',$oldKey),'exception'=>get_class($exception)]);
        }
    }
    $run=RecordStore::get('key_rotation',$runId);if($run){$run['status']=$result['failed']===0?'completed':'completed_with_failures';$run['result']=$result;$run['completed_at']=Utils::now();RecordStore::put('key_rotation',$runId,$run,(int)$run['version']);}
    Audit::record('key_rotation_completed',$result+['actor_id'=>$actor]);return $result;
}
    /** Complete keyset inventory; group work remains bounded pending durable staging. */
    private static function rotationRows(string $type): \Generator {
        if(!in_array($type,['asset','derivative'],true))
            throw new Error('key_rotation_inventory_type_invalid','Key-rotation inventory type invalid.',500);
        $after='';
        do {
            $page=RecordStore::scan($type,250,$after);
            foreach($page as $row)yield $row;
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
    }
    private static function rotationIdentity(array $record): array {
        $storage=$record['storage']??null;
        if(!is_array($storage))throw new Error('key_rotation_identity_invalid','Key-rotation storage identity is missing.',409);
        $size=Utils::integer($record['size']??null,'key_rotation_identity_invalid',0,1073741824);
        $sha=$record['sha256']??null;$objectKey=$record['object_key']??null;
        $provider=$storage['provider_id']??null;$keyId=$storage['key_id']??null;
        if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha)||
           !is_string($objectKey)||!preg_match('/^[a-f0-9]{64}$/D',$objectKey)||
           !is_string($provider)||$provider===''||Utils::key($provider,64)!==$provider||
           !is_string($keyId)||$keyId===''||Utils::key($keyId,64)!==$keyId)
            throw new Error('key_rotation_identity_invalid','Key-rotation content or storage identity is noncanonical.',409);
        return ['size'=>$size,'sha256'=>$sha,'object_key'=>$objectKey,'provider_id'=>$provider,'key_id'=>$keyId];
    }
    /** Exhaust all asset-key references without the 100k global-inventory ceiling. */
    private static function reconcileCurrentEnvelopes(string $activeKeyId,string $runId): int {
        $count=0;$after='';
        do {
            $page=RecordStore::scan('asset',250,$after);
            foreach($page as $asset){
                if(($asset['status']??'')==='deleted'||empty($asset['storage']['key_id'])||(string)$asset['storage']['key_id']!==$activeKeyId)continue;
                $id=hash('sha256',(string)$asset['id']);$envelope=RecordStore::get('asset_key_envelope',$id);
                if(!$envelope)continue;
                $stale=!hash_equals((string)($envelope['key_id']??''),$activeKeyId)
                    ||!hash_equals((string)($envelope['asset_sha256']??''),(string)$asset['sha256'])
                    ||(string)($envelope['algorithm']??'')!=='aes-256-gcm';
                if(!$stale)continue;
                self::syncAssetEnvelope($asset,$activeKeyId,$runId);$count++;
            }
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
        return $count;
    }
    private static function deferCleanup(string $runId,string $providerId,string $oldKey,array $group): void {
        $assetIds=[];$notBefore=Utils::now()+3600;
        foreach($group as $entry){
            $assetId=$entry['type']==='asset'?(string)$entry['record']['id']:(string)($entry['record']['asset_id']??'');
            if($assetId==='')throw new Error('cleanup_identity_invalid','Deferred cleanup requires a policy parent.',409);
            $assetIds[$assetId]=true;
            $lockedUntil=ResidencyCryptoService::lockUntil($assetId);
            if($lockedUntil!==null)$notBefore=max($notBefore,$lockedUntil);
        }
        if($assetIds===[])throw new Error('cleanup_identity_invalid','Deferred cleanup requires asset references.',409);
        $id=hash('sha256',$providerId.'|'.$oldKey);$existing=RecordStore::get('key_rotation_cleanup',$id);
        $row=['actor_id'=>0,'status'=>'deferred_lock','provider_id'=>$providerId,'object_key'=>$oldKey,'object_key_hash'=>hash('sha256',$oldKey),'asset_ids'=>array_keys($assetIds),'not_before'=>$notBefore,'rotation_id'=>$runId,'created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()];
        RecordStore::put('key_rotation_cleanup',$id,$row,$existing?(int)$existing['version']:0);
    }
    public static function reconcileDeferredCleanup(int $limit=200): array {
        $limit=max(1,min(2000,$limit));$out=['checked'=>0,'completed'=>0,'pending'=>0,'failed'=>0];
        $cursor=RecordStore::get('cron_cursor','rotation-cleanup');
        $after=$cursor['last_id']??'';
        if($cursor!==null&&(
            ($cursor['record_type']??null)!=='cron_cursor'
            ||($cursor['id']??null)!=='rotation-cleanup'
            ||($cursor['actor_id']??null)!==0
            ||($cursor['status']??null)!=='active'
            ||!is_int($cursor['version']??null)||$cursor['version']<1
            ||!is_string($after)||strlen($after)>96
            ||Utils::text($after,96)!==$after
        ))throw new Error('rotation_cleanup_cursor_invalid','Deferred cleanup cursor is invalid.',500);
        $page=RecordStore::scan('key_rotation_cleanup',2000,$after);
        foreach($page as $snapshot){
            if($out['checked']>=$limit)break;
            $after=(string)$snapshot['id'];
            if(in_array(($snapshot['status']??null),['completed','cancelled'],true))continue;
            $out['checked']++;$id=(string)$snapshot['id'];$row=RecordStore::get('key_rotation_cleanup',$id)??$snapshot;
            try{
                if(!in_array(($row['status']??null),['deferred_lock','pending_reference','pending_retry','failed'],true))
                    throw new Error('cleanup_identity_invalid','Deferred cleanup state is invalid.',409);
                $providerId=$row['provider_id']??null;$objectKey=$row['object_key']??null;
                if(!is_string($providerId)||$providerId===''||Utils::key($providerId,64)!==$providerId||
                   !is_string($objectKey)||!preg_match('/^[a-f0-9]{64}$/D',$objectKey)||
                   ($row['object_key_hash']??null)!==hash('sha256',$objectKey)||
                   $id!==hash('sha256',$providerId.'|'.$objectKey))
                    throw new Error('cleanup_identity_invalid','Deferred cleanup provider or object identity is invalid.',409);
                $assetIds=$row['asset_ids']??null;
                if(!is_array($assetIds)||!array_is_list($assetIds)||$assetIds===[])
                    throw new Error('cleanup_identity_invalid','Deferred cleanup asset references are missing.',409);
                foreach($assetIds as $assetId){
                    if(!is_string($assetId)||!preg_match('/^[A-Za-z0-9._:-]{1,96}$/D',$assetId))
                        throw new Error('cleanup_identity_invalid','Deferred cleanup asset reference is invalid.',409);
                }
                if(count(array_unique($assetIds))!==count($assetIds))
                    throw new Error('cleanup_identity_invalid','Deferred cleanup asset references are duplicated.',409);
                $notBefore=Utils::integer($row['not_before']??null,'cleanup_identity_invalid',1,PHP_INT_MAX);
                if($notBefore>Utils::now()){$out['pending']++;continue;}
                $next=0;
                foreach($assetIds as $assetId){
                    $lockedUntil=ResidencyCryptoService::lockUntil($assetId);
                    if($lockedUntil!==null&&$lockedUntil>Utils::now())$next=max($next,$lockedUntil);
                }
                if($next>0){
                    $row['status']='deferred_lock';$row['not_before']=$next;$row['updated_at']=Utils::now();
                    RecordStore::put('key_rotation_cleanup',$id,$row,(int)$row['version']);$out['pending']++;continue;
                }
                if(self::liveReferences($providerId,$objectKey)!==[]){
                    $row['status']='pending_reference';$row['not_before']=Utils::now()+3600;$row['updated_at']=Utils::now();
                    RecordStore::put('key_rotation_cleanup',$id,$row,(int)$row['version']);$out['pending']++;continue;
                }
                $provider=ProviderRegistry::get($providerId);
                if($provider->exists($objectKey)&&!$provider->delete($objectKey)&&$provider->exists($objectKey))
                    throw new Error('key_rotation_cleanup_failed','Deferred old ciphertext cleanup failed.',503);
                $row['status']='completed';$row['completed_at']=Utils::now();$row['updated_at']=Utils::now();
                RecordStore::put('key_rotation_cleanup',$id,$row,(int)$row['version']);$out['completed']++;
            }catch(\Throwable $e){
                $row=RecordStore::get('key_rotation_cleanup',$id)??$row;
                $invalid=$e instanceof Error&&in_array($e->errorCode,['cleanup_identity_invalid','object_lock_record_invalid'],true);
                $row['status']=$invalid?'failed':'pending_retry';$row['last_error']=$e instanceof Error?$e->errorCode:'unexpected';
                $row['not_before']=$invalid?($row['not_before']??null):Utils::now()+3600;$row['updated_at']=Utils::now();
                try{RecordStore::put('key_rotation_cleanup',$id,$row,(int)$row['version']);}catch(\Throwable){}
                $out['failed']++;
            }
        }
        if(count($page)<2000&&$out['checked']<$limit)$after='';
        if($page!==[]||$cursor!==null)RecordStore::put('cron_cursor','rotation-cleanup',[
            'actor_id'=>0,'status'=>'active','last_id'=>$after,
            'updated_at'=>Utils::now(),
        ],$cursor?(int)$cursor['version']:0);
        return $out;
    }
    private static function liveReferences(string $providerId,string $objectKey): array {
        // All callers need an existence proof, not a complete reference list.
        // Never remove old ciphertext if even one live alias still uses it.
        $witness=StorageReferenceWitness::first($providerId,$objectKey);
        return $witness===null?[]:[$witness];
    }
    private static function syncAssetEnvelope(array $asset,string $activeKeyId,string $rotationId): void {
        $id=hash('sha256',(string)$asset['id']);$envelope=RecordStore::get('asset_key_envelope',$id);if(!$envelope)return;
        if(($envelope['asset_id']??'')!==$asset['id']||!hash_equals((string)($envelope['asset_sha256']??''),(string)$asset['sha256']))throw new Error('asset_key_envelope_stale','Per-asset encryption envelope does not match the rotated asset.',409);
        $envelope['key_id']=$activeKeyId;$envelope['algorithm']='aes-256-gcm';$envelope['rotation_id']=$rotationId;$envelope['rotated_at']=Utils::now();$envelope['asset_sha256']=$asset['sha256'];
        RecordStore::put('asset_key_envelope',$id,$envelope,(int)$envelope['version']);
    }
}

final class CostService {
    private static function amount(mixed $value,string $code,bool $positive=false): float {
        if(is_int($value)||is_float($value))$n=(float)$value;
        elseif(is_string($value)&&preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D',$value))$n=(float)$value;
        else throw new Error($code,'Canonical non-negative decimal required.',400);
        if(!is_finite($n)||$n<0||$n>1000000000||($positive&&$n<=0))throw new Error($code,'Amount outside supported range.',400);
        return $n;
    }
    private static function ledgerTime(mixed $v,string $code): int {return Utils::integer($v,$code,1,253402300799);}
    public static function record(string $assetId,string $provider,string $purpose,array $units,array $rates): array {
        $asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        $domain=$asset['owner_domain']??null;
        if(!is_string($domain)||$domain===''||Utils::key($domain,64)!==$domain)throw new Error('cost_identity_invalid','Owner domain is missing or noncanonical.',409);
        $provider=Utils::key($provider,64);$purpose=Utils::key($purpose,64);
        if($provider===''||$purpose===''||$units===[])throw new Error('cost_identity_invalid','Cost provider, purpose and units are required.',400);
        $safeUnits=[];$safeRates=[];$cost=0.0;
        foreach($units as $name=>$value){
            $key=Utils::key((string)$name,64);
            if($key===''||array_key_exists($key,$safeUnits))throw new Error('cost_unit_duplicate','Duplicate cost unit.',400);
            $unit=self::amount($value,'cost_value_invalid');
            $rateKey=array_key_exists($name,$rates)?$name:$key;
            if(!array_key_exists($rateKey,$rates))throw new Error('cost_rate_missing','Explicit unit rate required.',400);
            $rate=self::amount($rates[$rateKey],'cost_value_invalid');
            $safeUnits[$key]=$unit;$safeRates[$key]=$rate;$cost+=$unit*$rate;
            if(!is_finite($cost)||$cost>1000000000)throw new Error('cost_value_invalid','Cost exceeds ceiling.',400);
        }
        $cost=round($cost,8);$threshold=self::checkBudget($domain,$cost);
        $id=Utils::id('cost');$row=RecordStore::put('cost',$id,['actor_id'=>0,'cost_id'=>$id,'asset_id'=>$assetId,'owner_domain'=>$domain,'purpose'=>$purpose,'provider'=>$provider,'units'=>$safeUnits,'rates'=>$safeRates,'cost'=>$cost,'currency'=>'USD','status'=>'recorded','created_at'=>Utils::now()]);
        if($threshold!==null){$details=['domain'=>$domain,'spent'=>$threshold['spent'],'limit'=>$threshold['limit'],'cost_id'=>$row['id']];Audit::record('budget_threshold_exceeded',$details);if(function_exists('do_action'))do_action('scm.budget.threshold',$details);}
        return $row;
    }
    public static function setBudget(string $domain,string $period,float $limit,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $domain=Utils::key($domain,64);$date=\DateTimeImmutable::createFromFormat('!Y-m',$period,new \DateTimeZone('UTC'));
        $valid=$date!==false&&$date->format('Y-m')===$period;$limit=round(self::amount($limit,'budget_invalid',true),8);
        if($domain===''||!$valid||$limit<=0)throw new Error('budget_invalid','Budget domain, period or limit invalid.',400);
        $id=hash('sha256',$domain.'|'.$period);$existing=RecordStore::get('budget',$id);
        return RecordStore::put('budget',$id,['actor_id'=>$actor,'domain'=>$domain,'period'=>$period,'limit'=>$limit,'status'=>'active','created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
    }
    /** Traverse the complete immutable-ID ledger in bounded DB pages without a 500k-row cap. */
    private static function ledgerRows(): \Generator {
        $after='';
        do {
            $page=RecordStore::scan('cost',250,$after);
            foreach($page as $row)yield $row;
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
    }
    private static function checkBudget(string $domain,float $pending): ?array {
        $period=gmdate('Y-m');$budget=RecordStore::get('budget',hash('sha256',$domain.'|'.$period));
        if(!$budget||($budget['status']??'')!=='active')return null;
        $limit=self::amount($budget['limit']??null,'budget_ledger_invalid',true);$spent=$pending;
        foreach(self::ledgerRows() as $row){
            if(($row['owner_domain']??null)!==$domain)continue;
            $time=self::ledgerTime($row['created_at']??null,'budget_ledger_invalid');
            $amount=self::amount($row['cost']??null,'budget_ledger_invalid');
            if(gmdate('Y-m',$time)===$period)$spent+=$amount;
            if(!is_finite($spent)||$spent>1000000000)throw new Error('budget_ledger_invalid','Budget ledger overflow.',409);
        }
        return $spent>$limit?['spent'=>$spent,'limit'=>$limit]:null;
    }
    public static function reconcile(string $provider,array $invoice): array {
        $provider=Utils::key($provider,64);$reported=self::amount($invoice['total']??null,'invoice_invalid');
        $tolerance=self::amount(array_key_exists('tolerance',$invoice)?$invoice['tolerance']:0.01,'invoice_invalid');$period=array_key_exists('period',$invoice)?$invoice['period']:'';
        if($provider===''||!is_string($period))throw new Error('invoice_invalid','Invoice provider or period invalid.',400);
        if($period!==''){$date=\DateTimeImmutable::createFromFormat('!Y-m',$period,new \DateTimeZone('UTC'));if($date===false||$date->format('Y-m')!==$period)throw new Error('invoice_period_invalid','Invoice month invalid.',400);}
        $calculated=0.0;
        foreach(self::ledgerRows() as $row){
            if(($row['provider']??null)!==$provider)continue;
            $time=self::ledgerTime($row['created_at']??null,'invoice_ledger_invalid');
            $amount=self::amount($row['cost']??null,'invoice_ledger_invalid');
            if($period===''||gmdate('Y-m',$time)===$period)$calculated+=$amount;
            if(!is_finite($calculated)||$calculated>1000000000)throw new Error('invoice_ledger_invalid','Invoice ledger overflow.',409);
        }
        $delta=abs($calculated-$reported);
        return RecordStore::put('invoice_reconciliation',Utils::id('inv'),['actor_id'=>0,'provider'=>$provider,'period'=>$period,'calculated'=>round($calculated,8),'reported'=>$reported,'tolerance'=>$tolerance,'delta'=>round($delta,8),'status'=>$delta<=$tolerance?'reconciled':'mismatch','created_at'=>Utils::now()]);
    }
}

final class RepairService {
    public static function preview(string $assetId,string $reason,array $targetKinds,array $preset,int $actor): array {
    Auth::capability('media_reprocess');Auth::assertActor($actor,'manage_options');
    $asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    LegalHoldService::assertNoHold($assetId,'reprocess');
    $reason=Utils::text($reason,1000);if($reason==='')throw new Error('repair_reason_required','Repair reason is required.',400);
    $targets=array_values(array_filter(array_unique(array_map(fn($kind)=>Utils::key((string)$kind,64),$targetKinds))));
    if($targets===[])throw new Error('repair_target_required','At least one repair derivative kind is required.',400);
    $allowed=array_values(array_filter(array_unique(array_map('strval',(array)($asset['policy']['derivative_set']??[])))));
    $manifest=!empty($asset['active_manifest_id'])?RecordStore::get('manifest',(string)$asset['active_manifest_id']):null;
    if($manifest)foreach((array)$manifest['derivatives'] as $item)$allowed[]=Utils::key((string)($item['kind']??''),64);
    $allowed=array_values(array_unique(array_filter($allowed)));
    if(array_diff($targets,$allowed)!==[])throw new Error('repair_target_invalid','Repair target is not permitted by policy or active manifest.',400,['targets'=>array_values(array_diff($targets,$allowed))]);
    $safePreset=[];foreach($preset as $key=>$value){$key=Utils::key((string)$key,64);if($key===''||is_array($value)||is_object($value)||is_resource($value))throw new Error('repair_preset_invalid','Repair preset must contain scalar, named values.',400);$safePreset[$key]=Utils::text((string)$value,191);}
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'reason'=>$reason,'target_kinds'=>$targets,'preset'=>$safePreset]);
    if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Repair authorization is stale.',409);
    $id=Utils::id('repair');$row=['actor_id'=>$actor,'repair_id'=>$id,'asset_id'=>$assetId,'reason'=>$reason,'target_kinds'=>$targets,'preset'=>$safePreset,'owner_object_version'=>(int)$decision['object_version'],'previous_manifest_id'=>$asset['active_manifest_id']??null,'status'=>'preview','created_at'=>Utils::now()];
    return RecordStore::put('repair',$id,$row);
}
    public static function execute(string $repairId,string $idempotencyKey): array {
    $repair=RecordStore::get('repair',$repairId);if(!$repair||($repair['status']??'')!=='preview')throw new Error('repair_not_ready','Repair is not ready.',409);
    $fingerprint=hash('sha256',Utils::canonicalJson($repair));$claim=Idempotency::claim('repair',$idempotencyKey,$fingerprint);if($claim['replay'])return RecordStore::get('repair',$repairId)??$repair;
    $asset=RecordStore::get('asset',(string)$repair['asset_id']);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    LegalHoldService::assertNoHold((string)$asset['id'],'reprocess');
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_reprocess',['asset'=>$asset,'actor_id'=>(int)$repair['actor_id'],'reason'=>$repair['reason'],'target_kinds'=>$repair['target_kinds'],'preset'=>$repair['preset']]);
    if((int)$decision['object_version']!==(int)$repair['owner_object_version']||(int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Repair authorization is stale.',409);
    $oldManifest=$asset['active_manifest_id']??null;
    $asset['status']='quarantined';$asset['processing_status']='pending';$asset['job_graph']=[];$asset['reprocess_context']=['repair_id'=>$repairId,'target_kinds'=>$repair['target_kinds'],'preset'=>$repair['preset']];
    RecordStore::put('asset',(string)$asset['id'],$asset,(int)$asset['version']);
    try{
        ProcessingService::start((string)$asset['id']);$updated=ProcessingService::execute((string)$asset['id'],'repair-worker');
        if(empty($updated['active_manifest_id'])||$updated['active_manifest_id']===$oldManifest)throw new Error('repair_manifest_unchanged','Repair did not create a new manifest.',500);
        $repair['status']='completed';$repair['new_manifest_id']=$updated['active_manifest_id'];$repair['completed_at']=Utils::now();
        $repair=RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);Idempotency::complete('repair',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'repair',$repairId);return $repair;
    }catch(\Throwable $exception){
        $fresh=RecordStore::get('asset',(string)$asset['id'])??$asset;unset($fresh['reprocess_context']);$fresh['job_graph']=[];
        if($oldManifest){$fresh['active_manifest_id']=$oldManifest;$fresh['status']='ready';$fresh['processing_status']='completed';}
        RecordStore::put('asset',(string)$fresh['id'],$fresh,(int)$fresh['version']);
        $repair=RecordStore::get('repair',$repairId)??$repair;$repair['status']='failed';$repair['error']=$exception instanceof Error?$exception->errorCode:'unexpected';$repair['last_valid_preserved']=$oldManifest!==null;
        RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);Idempotency::fail('repair',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$repair['error']);throw $exception;
    }
}
    public static function rollback(string $repairId,int $actor): array {
    Auth::capability('media_reprocess');Auth::assertActor($actor,'manage_options');
    $repair=RecordStore::get('repair',$repairId);if(!$repair||empty($repair['previous_manifest_id'])||!in_array(($repair['status']??''),['completed','failed'],true))throw new Error('repair_rollback_unavailable','Repair rollback unavailable.',409);
    $asset=RecordStore::get('asset',(string)$repair['asset_id']);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    LegalHoldService::assertNoHold((string)$asset['id'],'reprocess');
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'reason'=>'rollback:'.(string)$repair['reason'],'target_kinds'=>$repair['target_kinds']??[],'preset'=>$repair['preset']??[],'rollback_to_manifest'=>$repair['previous_manifest_id']]);
    if((int)$decision['object_version']!==(int)$asset['object_version']||(int)$decision['object_version']!==(int)($repair['owner_object_version']??0))throw new Error('domain_object_version_stale','Repair rollback authorization is stale.',409);
    $previous=RecordStore::get('manifest',(string)$repair['previous_manifest_id']);if(!$previous||($previous['asset_id']??'')!==$asset['id'])throw new Error('repair_rollback_manifest_missing','Previous manifest is unavailable.',409);
    $current=!empty($asset['active_manifest_id'])?RecordStore::get('manifest',(string)$asset['active_manifest_id']):null;
    $previousOriginal=$previous;$previous['status']='active';unset($previous['superseded_by']);$previous=RecordStore::put('manifest',(string)$previous['id'],$previous,(int)$previous['version']);
    try{$asset['active_manifest_id']=$previous['id'];$asset['manifest_version']=max((int)$asset['manifest_version'],(int)$previous['manifest_version']);$asset['status']='ready';$asset['processing_status']='completed';unset($asset['reprocess_context']);$asset=RecordStore::put('asset',(string)$asset['id'],$asset,(int)$asset['version']);}
    catch(\Throwable $exception){try{$freshPrevious=RecordStore::get('manifest',(string)$previous['id']);if($freshPrevious){$restore=$previousOriginal;unset($restore['id'],$restore['version'],$restore['created_at'],$restore['updated_at']);foreach($restore as $k=>$v)$freshPrevious[$k]=$v;if(($previousOriginal['superseded_by']??null)===null)unset($freshPrevious['superseded_by']);RecordStore::put('manifest',(string)$freshPrevious['id'],$freshPrevious,(int)$freshPrevious['version']);}}catch(\Throwable){Audit::record('repair_rollback_reconciliation_required',['repair_id'=>$repairId,'asset_id'=>$asset['id'],'reason'=>'asset_switch_failed_previous_restore_failed']);}throw $exception;}
    if($current&&($current['id']??'')!==$previous['id']&&($current['status']??'')==='active'){try{$freshCurrent=RecordStore::get('manifest',(string)$current['id']);if($freshCurrent&&($freshCurrent['status']??'')==='active'){$freshCurrent['status']='rolled_back';$freshCurrent['rolled_back_to']=$previous['id'];RecordStore::put('manifest',(string)$freshCurrent['id'],$freshCurrent,(int)$freshCurrent['version']);}}catch(\Throwable){Audit::record('repair_rollback_reconciliation_required',['repair_id'=>$repairId,'asset_id'=>$asset['id'],'reason'=>'previous_activated_current_retire_failed']);}}
    $repair['status']='rolled_back';$repair['rolled_back_by']=$actor;$repair['rolled_back_at']=Utils::now();
    return RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);
}
}

/**
 * Incremental, non-destructive restore inventory checkpoint preparation.
 * This does NOT restore objects, freeze writes or open the serve gate.
 */
final class RestoreCheckpointStage {
    private const TYPES=['asset','derivative','manifest','tombstone',
        'asset_key_envelope','residency_policy','legal_hold','deletion','provider_mapping'];

    public static function begin(int $actor,array $inventory): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        Utils::requireFields($inventory,
            ['database_snapshot','object_inventory','keyring_version','policy_snapshot',
             'manifest_snapshot','tombstone_snapshot'],'restore_inventory_incomplete');
        $id=Utils::id('rststage');
        return RecordStore::put('restore_checkpoint',$id,[
            'actor_id'=>$actor,'status'=>'planning_unfenced',
            'inventory_hash'=>hash('sha256',Utils::canonicalJson($inventory)),
            'family_index'=>0,'after_id'=>'','page_size'=>250,
            'segments'=>0,'visited_rows'=>0,'chain_hash'=>str_repeat('0',64),
            'created_at'=>Utils::now()
        ],0);
    }

    public static function advance(string $id,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $state=RecordStore::get('restore_checkpoint',$id);
        if(!$state)throw new Error('restore_checkpoint_not_found','Restore checkpoint missing.',404);
        if(($state['status']??null)==='inventory_complete_unfenced')return $state;
        $index=$state['family_index']??null;
        $after=$state['after_id']??null;
        if(($state['status']??null)!=='planning_unfenced'||
           !is_int($index)||$index<0||$index>=count(self::TYPES)||
           !is_string($after)||strlen($after)>96||
           Utils::text($after,96)!==$after||($state['page_size']??null)!==250)
            throw new Error('restore_checkpoint_cursor_invalid','Restore checkpoint cursor invalid.',409);
        $family=self::TYPES[$index];
        $page=RecordStore::scan($family,250,$after);
        $pageHash=hash('sha256',Utils::canonicalJson($page));
        $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
        $segmentId=hash('sha256',$id.'|'.$family.'|'.$after);
        $existing=RecordStore::get('restore_checkpoint_segment',$segmentId);
        if($existing){
            if(($existing['checkpoint_id']??null)!==$id||
               ($existing['record_family']??null)!==$family||
               ($existing['after_id']??null)!==$after||
               ($existing['last_id']??null)!==$last||
               ($existing['page_hash']??null)!==$pageHash||
               ($existing['raw_rows']??null)!==count($page)||
               ($existing['status']??null)!=='snapshot_unfenced')
                throw new Error('restore_checkpoint_drift','Checkpointed source page has changed.',409);
        }else{
            RecordStore::put('restore_checkpoint_segment',$segmentId,[
                'actor_id'=>$actor,'checkpoint_id'=>$id,'record_family'=>$family,
                'after_id'=>$after,'last_id'=>$last,'raw_rows'=>count($page),
                'page_hash'=>$pageHash,'status'=>'snapshot_unfenced',
                'created_at'=>Utils::now()
            ],0);
        }
        $state['chain_hash']=hash('sha256',$state['chain_hash'].'|'.$segmentId.'|'.$pageHash);
        $state['segments']++;
        $state['visited_rows']+=count($page);
        if(count($page)<250){
            $index++;
            $state['family_index']=$index;
            $state['after_id']='';
            if($index===count(self::TYPES))$state['status']='inventory_complete_unfenced';
        }else{$state['after_id']=$last;}
        $state['updated_at']=Utils::now();
        return RecordStore::put('restore_checkpoint',$id,$state,(int)$state['version']);
    }

    /** Recheck all pages, counters and chained source hashes before accepting a dry run. */
    public static function verifyComplete(string $id,int $actor): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
        $state=RecordStore::get('restore_checkpoint',$id);
        if(!$state||($state['status']??null)!=='inventory_complete_unfenced'||
            ($state['family_index']??null)!==count(self::TYPES)||
            ($state['after_id']??null)!=='')
            throw new Error('restore_checkpoint_not_complete','Restore inventory dry run incomplete.',409);
        $chain=str_repeat('0',64);$segments=0;$rows=0;
        foreach(self::TYPES as $family){
            $after='';
            do {
                $page=RecordStore::scan($family,250,$after);
                $last=$page===[]?$after:(string)$page[count($page)-1]['id'];
                $segmentId=hash('sha256',$id.'|'.$family.'|'.$after);
                $saved=RecordStore::get('restore_checkpoint_segment',$segmentId);
                $pageHash=hash('sha256',Utils::canonicalJson($page));
                if(!$saved||($saved['checkpoint_id']??null)!==$id||
                   ($saved['record_family']??null)!==$family||
                   ($saved['status']??null)!=='snapshot_unfenced'||
                   ($saved['after_id']??null)!==$after||
                   ($saved['last_id']??null)!==$last||
                   ($saved['page_hash']??null)!==$pageHash||
                   ($saved['raw_rows']??null)!==count($page))
                    throw new Error('restore_checkpoint_drift','Restore source differs from persisted checkpoint.',409);
                $chain=hash('sha256',$chain.'|'.$segmentId.'|'.$pageHash);
                $rows+=count($page);$segments++;
                if(count($page)<250)break;
                $after=$last;
            }while(true);
        }
        if(($state['chain_hash']??null)!==$chain||
           ($state['segments']??null)!==$segments||
           ($state['visited_rows']??null)!==$rows)
            throw new Error('restore_checkpoint_drift','Restore checkpoint summary differs from source.',409);
        return ['checkpoint_id'=>$id,'status'=>'verified_unfenced',
            'inventory_hash'=>$state['inventory_hash'],
            'chain_hash'=>$chain,'segments'=>$segments,'visited_rows'=>$rows];
    }
}

final class RestoreService {
    public static function start(int $actor,array $inventory): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
    Utils::requireFields($inventory,['database_snapshot','object_inventory','keyring_version','policy_snapshot','manifest_snapshot','tombstone_snapshot'],'restore_inventory_incomplete');
    $gate=RecordStore::get('restore_gate','current');
    if($gate&&!in_array(($gate['status']??''),['serve_authorized','cancelled','failed'],true))throw new Error('restore_already_active','A restore reconciliation is already active.',409,['restore_id'=>$gate['restore_id']??'']);
    $id=Utils::id('rst');$hash=hash('sha256',Utils::canonicalJson($inventory));
    $row=['actor_id'=>$actor,'restore_id'=>$id,'inventory'=>Utils::redact($inventory),'inventory_hash'=>$hash,'status'=>'rebuilding','checks'=>[],'created_at'=>Utils::now()];
    $row=RecordStore::put('restore',$id,$row);
    try{RecordStore::put('restore_gate','current',['actor_id'=>$actor,'restore_id'=>$id,'inventory_hash'=>$hash,'status'=>'rebuilding','updated_at'=>Utils::now()],$gate?(int)$gate['version']:0);}catch(\Throwable $gateError){try{RecordStore::delete('restore',$id,(int)$row['version']);}catch(\Throwable){Audit::record('restore_start_reconciliation_required',['restore_id'=>$id,'reason'=>'gate_write_failed_restore_cleanup_failed']);}throw $gateError;}
    Audit::record('restore_started',['restore_id'=>$id,'actor_id'=>$actor,'inventory_hash'=>$hash]);
    return $row;
}
    public static function reconcile(string $restoreId): array {
    $restore=RecordStore::get('restore',$restoreId);if(!$restore)throw new Error('restore_not_found','Restore not found.',404);
    $gate=RecordStore::get('restore_gate','current');if(!$gate||($gate['restore_id']??'')!==$restoreId)throw new Error('restore_gate_mismatch','Restore is not the active reconciliation gate.',409);
    // A prior serve authorization cannot remain open while a new verification
    // runs. CAS the persistent gate to a blocking state before reading assets.
    $gate['status']='rebuilding';$gate['updated_at']=Utils::now();
    $gate=RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);
    $before=self::sourceFingerprint();
    $checks=['schema'=>self::checkedRestore(fn()=>Schema::ready()),
        'keyring'=>self::checkedRestore(fn()=>Keyring::assertReady()),
        'provider'=>self::checkedRestore(fn()=>ProviderRegistry::store()),
        'tombstones'=>self::checkedRestore(fn()=>self::tombstones()),
        'rights'=>self::checkedRestore(fn()=>self::rights()),
        'manifests'=>self::checkedRestore(fn()=>self::manifests()),
        'integrity'=>self::checkedRestore(fn()=>self::integrity())];
    $after=self::sourceFingerprint();
    $checks['inventory_stable']=hash_equals($before,$after);
    $restore['checks']=$checks;$restore['status']=in_array(false,$checks,true)?'blocked':'reconciled';$restore['reconciled_at']=Utils::now();$restore['source_fingerprint']=$after;
    $restore=RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);
    $gate['status']=$restore['status'];$gate['checks']=$checks;$gate['source_fingerprint']=$after;$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);
    return $restore;
}
    private static function check(callable $f): bool {try{$f();return true;}catch(\Throwable){return false;}}
    /** Safe boolean probes: malformed or missing restoration records block serving. */
    private static function checkedRestore(callable $probe): bool {
        try{return $probe()!==false;}catch(\Throwable){return false;}
    }
    /** Versioned source inventory witness. This is not a DB/object snapshot. */
    private static function sourceFingerprint(): string {
        $hash=hash_init('sha256');
        foreach(['asset','derivative','manifest','tombstone','asset_key_envelope',
            'residency_policy','legal_hold','deletion','provider_mapping'] as $type){
            hash_update($hash,$type."\0");
            foreach(self::restoreRecords($type) as $row){
                $encoded=Utils::canonicalJson($row);
                hash_update($hash,strlen($encoded).':'.$encoded);
            }
        }
        return hash_final($hash);
    }
    /** Restore verification must exhaust an immutable-ID inventory without materializing it. */
    private static function restoreRecords(string $type): \Generator {
        $after='';
        do {
            $page=RecordStore::scan($type,250,$after);
            foreach($page as $row)yield $row;
            if(count($page)<250)break;
            $after=(string)$page[count($page)-1]['id'];
        }while(true);
    }
    private static function tombstones(): bool {foreach(self::restoreRecords('tombstone') as $t){$assetId=$t['asset_id']??null;if(!is_string($assetId)||$assetId===''||strlen($assetId)>96||Utils::text($assetId,96)!==$assetId)return false;$a=RecordStore::get('asset',$assetId);if($a&&($a['status']??'')!=='deleted')return false;}return true;}
    private static function rights(): bool {foreach(self::restoreRecords('asset') as $a){if(($a['status']??'')==='deleted')continue;if(!is_array($a['rights']??null))return false;try{RightsPolicy::assert($a['rights'],'view',['territory'=>'GLOBAL','audience_type'=>'private']);}catch(Error $e){if($e->errorCode==='rights_operation_denied')continue;return false;}catch(\Throwable){return false;}}return true;}
    private static function manifests(): bool {foreach(self::restoreRecords('asset') as $a){if(($a['status']??'')!=='ready')continue;$manifestId=(string)($a['active_manifest_id']??'');if($manifestId==='')return false;$m=RecordStore::get('manifest',$manifestId);if(!$m||($m['status']??'')!=='active'||($m['asset_id']??'')!==$a['id'])return false;$items=(array)($m['derivatives']??[]);if($items===[])return false;foreach($items as $item){$id=(string)($item['derivative_id']??'');$d=$id!==''?RecordStore::get('derivative',$id):null;if(!$d||($d['asset_id']??'')!==$a['id']||($d['status']??'')!=='validated'||!empty($d['superseded_by']))return false;if(!hash_equals((string)($item['sha256']??''),(string)($d['sha256']??''))||(int)($item['size']??-1)!==(int)($d['size']??-2)||(string)($item['kind']??'')!==(string)($d['kind']??''))return false;}}return true;}
    private static function integrity(): bool {foreach(self::restoreRecords('asset') as $a){if(($a['status']??'')==='deleted')continue;try{IntegrityService::sample((string)$a['id']);}catch(\Throwable){return false;}}return true;}
    public static function authorizeServe(string $restoreId): void {
    Auth::capability('media_manage_providers');
    $restore=RecordStore::get('restore',$restoreId);$gate=RecordStore::get('restore_gate','current');
    if(!$restore||!$gate||($gate['restore_id']??'')!==$restoreId)throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    $restoreStatus=(string)($restore['status']??'');$gateStatus=(string)($gate['status']??'');
    if(!in_array($restoreStatus,['reconciled','serve_authorized'],true)||!in_array($gateStatus,['reconciled','serve_authorized'],true))throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    // The inventory may have changed after the preceding reconciliation. Refresh
    // every authoritative restore check before reopening any media serving.
    $restore=self::reconcile($restoreId);
    $gate=RecordStore::get('restore_gate','current');
    if(($restore['status']??null)!=='reconciled'||!$gate||($gate['restore_id']??null)!==$restoreId||($gate['status']??null)!=='reconciled')
        throw new Error('restore_gate_blocked','Restore inventory changed or failed revalidation before serving.',503);
    $restoreStatus=(string)$restore['status'];
    if($restoreStatus!=='serve_authorized'){$restore['status']='serve_authorized';$restore['serve_authorized_at']=Utils::now();$restore=RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);}
    $gate=RecordStore::get('restore_gate','current')??$gate;if(($gate['restore_id']??'')!==$restoreId||!in_array(($gate['status']??''),['reconciled','serve_authorized'],true))throw new Error('restore_gate_mismatch','Restore gate changed during serve authorization.',409);
    if(($gate['status']??'')!=='serve_authorized'){$gate['status']='serve_authorized';$gate['serve_authorized_at']=Utils::now();$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);}
    Audit::record('restore_serve_authorized',['restore_id'=>$restoreId,'actor_id'=>Auth::currentUser()]);
}
    public static function assertServeAllowed(): void {
        $gate=RecordStore::get('restore_gate','current');
        if($gate&&!in_array(($gate['status']??''),['serve_authorized','cancelled'],true))throw new Error('restore_gate_blocked','Media serving is blocked during restore reconciliation.',503,['restore_id'=>$gate['restore_id']??'']);
    }

}

final class Observability {
    public static function metric(string $name,float $value,array $labels=[]): array {
        if($name==='queue.depth'){
            if(!is_finite($value)||$value<0||floor($value)!==$value)
                throw new Error('metric_invalid','Queue depth must be a finite non-negative count.',400);
            if(count($labels)!==1||!array_key_exists('tenant',$labels)||
               !is_string($labels['tenant'])||
               !in_array($labels['tenant'],['file00','file10','file11','file12','file17','file21','file22','cf01','cf04'],true))
                throw new Error('metric_label_invalid','Queue depth tenant must be an approved domain bucket.',400);
            $safe=['tenant'=>$labels['tenant']];
        }else{
            [$name,$value,$safe]=PrivacyTelemetry::validate($name,$value,$labels);
        }
        return RecordStore::put('metric',Utils::id('met'),[
            'actor_id'=>0,'metric'=>$name,'value'=>$value,'labels'=>$safe,
            'status'=>'recorded','created_at'=>Utils::now()
        ]);
    }
    public static function trace(string $operation,string $traceId,string $spanId,array $context=[]): array {
    $operation=Utils::key($operation,96);$traceId=Utils::text($traceId,96);$spanId=Utils::text($spanId,96);
    if($operation===''||$traceId===''||$spanId===''||!preg_match('/^[A-Za-z0-9._:-]{8,96}$/',$traceId)||!preg_match('/^[A-Za-z0-9._:-]{4,96}$/',$spanId))throw new Error('trace_identity_invalid','Trace operation and bounded trace/span identifiers are required.',400);
    return RecordStore::put('trace',Utils::id('trc'),['actor_id'=>Auth::currentUser(),'operation'=>$operation,'trace_id'=>$traceId,'span_id'=>$spanId,'context'=>Utils::redact($context),'status'=>'recorded','created_at'=>Utils::now()]);
}
    public static function health(): array {
    $providers=ProviderRegistry::health();$queued=RecordStore::countStatuses('job',['queued','retry','leased']);$oldestRow=RecordStore::oldestByStatuses('job',['queued','retry']);$oldest=$oldestRow?max(0,Utils::now()-(int)($oldestRow['created_at']??$oldestRow['updated_at']??Utils::now())):0;$dead=RecordStore::countStatuses('job',['dead_letter']);$pendingDeletion=RecordStore::countStatuses('deletion',['completed','cancelled'],true);$pendingCdn=RecordStore::countStatuses('cdn_mapping',['purge_pending']);$degraded=RecordStore::countStatuses('degraded_state',['degraded']);
    $gate=RecordStore::get('restore_gate','current');$restoreBlocking=$gate&&!in_array(($gate['status']??''),['serve_authorized','cancelled'],true);$schemaReady=Schema::ready();$auditChain=Audit::verifyChain();$providersHealthy=$providers!==[]&&!in_array(false,array_map(fn($provider)=>($provider['healthy']??false)===true,$providers),true);
    $status=RuntimeGuard::enabled()&&$schemaReady&&$providersHealthy&&!$restoreBlocking&&$auditChain&&$dead===0&&$pendingCdn===0&&$degraded===0?'ready':'disabled_or_degraded';
    return ['status'=>$status,'runtime_enabled'=>RuntimeGuard::enabled(),'schema_ready'=>$schemaReady,'providers'=>$providers,'queue_depth'=>$queued,'oldest_queue_age_seconds'=>$oldest,'dead_letters'=>$dead,'pending_deletions'=>$pendingDeletion,'pending_cdn_purges'=>$pendingCdn,'degraded_states'=>$degraded,'restore_gate'=>$gate?['restore_id'=>$gate['restore_id']??'','status'=>$gate['status']??'unknown']:null,'audit_chain'=>$auditChain,'manifest'=>IntegrationRegistry::manifest(),'runbooks'=>['provider-outage'=>'docs/runbooks/PROVIDER-OUTAGE.md','scanner-outage'=>'docs/runbooks/SCANNER-OUTAGE.md','deletion-pending'=>'docs/runbooks/DELETION-RECONCILIATION.md','restore'=>'docs/runbooks/RESTORE.md']];
}
    public static function synthetic(): array {return ['provider'=>self::check(fn()=>ProviderRegistry::store()),'keyring'=>self::check(fn()=>Keyring::assertReady()),'audit_chain'=>Audit::verifyChain(),'schema'=>Schema::ready(),'domain_file00'=>DomainRegistry::has('file00'),'domain_file17'=>DomainRegistry::has('file17')];}
    private static function check(callable $f): bool {try{$f();return true;}catch(\Throwable){return false;}}
    public static function alert(string $severity,string $code,array $context=[]): array {
    $severity=in_array($severity,['info','warning','critical'],true)?$severity:'warning';$code=Utils::key($code,96);
    if($code==='')throw new Error('alert_code_invalid','Operator alert code is required.',400);
    $row=['actor_id'=>0,'severity'=>$severity,'code'=>$code,'context'=>Utils::redact($context),'status'=>'open','created_at'=>Utils::now()];
    $row=RecordStore::put('alert',Utils::id('alt'),$row);if(function_exists('do_action'))do_action('scm_operator_alert',$row);return $row;
}
}

final class WebhookService {
    public static function verify(string $provider,string $eventId,int $timestamp,string $body,string $signature,string $secret,int $maxSkew=300): array {
    $provider=Utils::key($provider,64);$eventId=Utils::text($eventId,191);$signature=trim($signature);
    if($provider===''||$eventId===''||$maxSkew<30||$maxSkew>3600)throw new Error('webhook_identity_invalid','Webhook provider, event and skew policy are invalid.',400);
    if(strlen($body)>1048576)throw new Error('webhook_body_too_large','Webhook body exceeds the permitted size.',413);
    if(abs(Utils::now()-$timestamp)>$maxSkew)throw new Error('webhook_timestamp_invalid','Webhook timestamp outside allowed skew.',403);
    if(strlen($secret)<32)throw new Error('webhook_secret_invalid','Webhook secret invalid.',503);
    if($signature===''||strlen($signature)>256)throw new Error('webhook_signature_invalid','Webhook signature invalid.',403);
    $bodyHash=hash('sha256',$body);$expected=Utils::b64url(hash_hmac('sha256',$provider.'|'.$eventId.'|'.$timestamp.'|'.$bodyHash,$secret,true));
    if(!hash_equals($expected,$signature))throw new Error('webhook_signature_invalid','Webhook signature invalid.',403);
    $id=hash('sha256',$provider.'|'.$eventId);$existing=RecordStore::get('webhook',$id);
    if($existing){if(!hash_equals((string)$existing['body_hash'],$bodyHash))throw new Error('webhook_replay_conflict','Webhook event identifier was reused with different content.',409);return ['replay'=>true,'record'=>$existing];}
    try{$record=RecordStore::put('webhook',$id,['actor_id'=>0,'provider'=>$provider,'event_id'=>$eventId,'body_hash'=>$bodyHash,'status'=>'accepted','created_at'=>Utils::now()],0);return ['replay'=>false,'record'=>$record];}
    catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$winner=RecordStore::get('webhook',$id);if(!$winner)throw $createError;if(!hash_equals((string)($winner['body_hash']??''),$bodyHash))throw new Error('webhook_replay_conflict','Webhook event identifier was reused with different content.',409);return ['replay'=>true,'record'=>$winner];}
}
}
