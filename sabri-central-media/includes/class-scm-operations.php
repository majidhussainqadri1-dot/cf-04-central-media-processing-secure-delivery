<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class ProviderExitService {
    public static function plan(string $sourceProvider,string $targetProvider,int $actor): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
    $sourceProvider=Utils::key($sourceProvider,64);$targetProvider=Utils::key($targetProvider,64);
    if($sourceProvider===''||$targetProvider===''||$sourceProvider===$targetProvider)throw new Error('provider_exit_identity_invalid','Distinct source and target providers are required.',400);
    $source=ProviderRegistry::get($sourceProvider);$target=ProviderRegistry::get($targetProvider);
    foreach(['copy','delete','streaming'] as $capability)if(($source->capabilities()[$capability]??false)!==true||($target->capabilities()[$capability]??false)!==true)throw new Error('provider_exit_capability_missing','Provider exit capability missing.',503,['capability'=>$capability]);
    $id=Utils::id('pex');$items=[];
    foreach(RecordStore::all('asset',0,null,100000) as $asset){
        if(($asset['status']??'')==='deleted'||($asset['storage']['provider_id']??'')!==$sourceProvider)continue;
        LegalHoldService::assertNoHold((string)$asset['id'],'provider_exit');
        $items[]=['type'=>'asset','id'=>$asset['id'],'object_key'=>$asset['object_key'],'sha256'=>$asset['sha256'],'size'=>$asset['size'],'status'=>'pending'];
        foreach(DerivativeService::forAsset((string)$asset['id']) as $derivative)if(($derivative['status']??'')!=='deleted'&&($derivative['storage']['provider_id']??'')===$sourceProvider)$items[]=['type'=>'derivative','id'=>$derivative['id'],'asset_id'=>$asset['id'],'object_key'=>$derivative['object_key'],'sha256'=>$derivative['sha256'],'size'=>$derivative['size'],'status'=>'pending'];
    }
    if($items===[])throw new Error('provider_exit_inventory_empty','No eligible source-provider objects were found.',409);
    $record=['actor_id'=>$actor,'exit_id'=>$id,'source_provider'=>$sourceProvider,'target_provider'=>$targetProvider,'status'=>'planned','items'=>$items,'copied'=>0,'verified'=>0,'switched'=>0,'purged'=>0,'created_at'=>Utils::now()];
    return RecordStore::put('provider_exit',$id,$record);
}
    public static function copy(string $exitId): array {
        $plan=self::load($exitId,['planned','copying','copy_failed','verified']);if(($plan['status']??'')==='verified')return $plan;$source=ProviderRegistry::get((string)$plan['source_provider']);$target=ProviderRegistry::get((string)$plan['target_provider']);$plan['status']='copying';$plan=self::save($plan);
        try{foreach($plan['items'] as $index=>$item){if(in_array(($item['status']??''),['verified','switched','purged'],true))continue;$targetKey=hash('sha256','migration|'.$plan['target_provider'].'|'.$item['object_key'].'|'.$item['sha256']);$stored=$source->copyTo((string)$item['object_key'],$target,$targetKey);$stream=$target->openStream($targetKey);try{$stats=Utils::streamHash($stream);}finally{fclose($stream);}if(!hash_equals((string)$item['sha256'],$stats['sha256'])||(int)$item['size']!==(int)$stats['size'])throw new Error('provider_exit_integrity_failed','Copied object integrity failed.',500,['id'=>$item['id']]);$plan['items'][$index]['target_key']=$targetKey;$plan['items'][$index]['target_storage']=$stored+['provider_id'=>$plan['target_provider']];$plan['items'][$index]['status']='verified';$plan=self::counted(self::save($plan));$plan=self::save($plan);} $plan['status']='verified';$plan['verified_at']=Utils::now();return self::save(self::counted($plan));}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='copy_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save(self::counted($plan));}catch(\Throwable){}throw $e;}
    }
    public static function shadowVerify(string $exitId): array {
        $plan=self::load($exitId,['verified','shadow_verifying','shadow_failed','shadow_verified']);if(($plan['status']??'')==='shadow_verified')return $plan;$target=ProviderRegistry::get((string)$plan['target_provider']);$plan['status']='shadow_verifying';$plan=self::save($plan);
        try{foreach($plan['items'] as $item){if(empty($item['target_key']))throw new Error('provider_exit_target_missing','Target mapping missing.',500,['id'=>$item['id']]);$stream=$target->openStream((string)$item['target_key']);try{$stats=Utils::streamHash($stream);}finally{fclose($stream);}if(!hash_equals((string)$item['sha256'],$stats['sha256'])||(int)$item['size']!==(int)$stats['size'])throw new Error('provider_shadow_mismatch','Shadow delivery integrity mismatch.',500,['id'=>$item['id']]);}$plan['status']='shadow_verified';$plan['shadow_verified_at']=Utils::now();return self::save($plan);}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='shadow_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save($plan);}catch(\Throwable){}throw $e;}
    }
    public static function switch(string $exitId): array {
        $plan=self::load($exitId,['shadow_verified','switching','switch_failed','switched']);if(($plan['status']??'')==='switched')return $plan;$plan['status']='switching';$plan=self::save($plan);
        try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified','Provider exit item is not verified.',409,['id'=>$item['id']]);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing','Migrated record missing.',500,['id'=>$item['id']]);$provider=(string)($record['storage']['provider_id']??'');$key=(string)($record['object_key']??'');if($provider===(string)$plan['target_provider']&&$key===(string)$item['target_key']){$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));continue;}if($provider!==(string)$plan['source_provider']||$key!==(string)$item['object_key'])throw new Error('provider_exit_mapping_stale','Source mapping changed during provider exit.',409,['id'=>$item['id']]);$record['previous_storage']=$record['storage']??null;$record['previous_object_key']=$record['object_key']??null;$record['storage']=$item['target_storage'];$record['object_key']=$item['target_key'];$record['provider_migrated_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));}ProviderRegistry::activate((string)$plan['target_provider']);$plan['status']='switched';$plan['switched_at']=Utils::now();return self::save(self::counted($plan));}
        catch(\Throwable $e){$plan=RecordStore::get('provider_exit',$exitId)??$plan;$plan['status']='switch_failed';$plan['last_error']=$e instanceof Error?$e->errorCode:'unexpected';try{self::save(self::counted($plan));}catch(\Throwable){}throw $e;}
    }
    public static function rollback(string $exitId,int $actor,string $reason): array {
        Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');$reason=Utils::text($reason,500);if($reason==='')throw new Error('provider_exit_reason_required','Provider rollback reason required.',400);$plan=self::load($exitId,['switching','switch_failed','switched','purge_pending']);foreach($plan['items'] as $item)if(($item['status']??'')==='purged')throw new Error('provider_exit_rollback_unavailable','Rollback is unsafe after source purge has begun.',409);
        $target=ProviderRegistry::get((string)$plan['target_provider']);foreach($plan['items'] as $index=>$item){if(($item['status']??'')!=='switched')continue;$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record||empty($record['previous_object_key'])||empty($record['previous_storage']))throw new Error('provider_exit_rollback_missing','Previous provider mapping missing.',500,['id'=>$item['id']]);$currentProvider=(string)($record['storage']['provider_id']??'');$currentKey=(string)($record['object_key']??'');if($currentProvider===(string)$plan['target_provider']&&$currentKey===(string)$item['target_key']){$record['object_key']=$record['previous_object_key'];$record['storage']=$record['previous_storage'];$record['provider_rollback_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);}elseif($currentProvider!==(string)$plan['source_provider']||$currentKey!==(string)$item['object_key'])throw new Error('provider_exit_rollback_mapping_stale','Provider mapping changed during rollback.',409,['id'=>$item['id']]);if($target->exists((string)$item['target_key'])&&!$target->delete((string)$item['target_key']))throw new Error('provider_exit_rollback_cleanup_failed','Rollback target cleanup failed.',503,['id'=>$item['id']]);$plan['items'][$index]['status']='verified';$plan=self::save(self::counted($plan));}ProviderRegistry::activate((string)$plan['source_provider']);$plan['status']='rolled_back';$plan['rollback_by']=$actor;$plan['rollback_reason']=$reason;$plan['rolled_back_at']=Utils::now();return self::save(self::counted($plan));
    }
    public static function purgeSource(string $exitId): array {
        $plan=self::load($exitId,['switched','purge_pending']);$source=ProviderRegistry::get((string)$plan['source_provider']);$plan['status']='purge_pending';$plan=self::save($plan);
        foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='purged')continue;if(($item['status']??'')!=='switched')throw new Error('provider_exit_item_not_switched','Provider exit item was not switched.',409,['id'=>$item['id']]);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record||($record['storage']['provider_id']??'')!==(string)$plan['target_provider']||($record['object_key']??'')!==(string)$item['target_key'])throw new Error('provider_exit_target_mapping_invalid','Target mapping is not authoritative.',409,['id'=>$item['id']]);if($source->exists((string)$item['object_key'])&&!$source->delete((string)$item['object_key']))throw new Error('provider_source_purge_failed','Source provider purge failed.',503,['id'=>$item['id']]);$plan['items'][$index]['status']='purged';$plan=self::save(self::counted($plan));}
        $plan['status']='completed';$plan['credentials_revocation_required']=true;$plan['completed_at']=Utils::now();return self::save(self::counted($plan));
    }
    private static function load(string $id,array $states): array {$plan=RecordStore::get('provider_exit',$id);if(!$plan)throw new Error('provider_exit_not_found','Provider exit plan not found.',404);if(!in_array(($plan['status']??''),$states,true))throw new Error('provider_exit_state_invalid','Provider exit is not in an allowed state.',409,['status'=>$plan['status']??'unknown']);return $plan;}
    private static function save(array $plan): array {return RecordStore::put('provider_exit',(string)$plan['id'],$plan,(int)$plan['version']);}
    private static function counted(array $plan): array {$statuses=array_column((array)$plan['items'],'status');$plan['copied']=count(array_filter($statuses,fn($s)=>in_array($s,['verified','switched','purged'],true)));$plan['verified']=$plan['copied'];$plan['switched']=count(array_filter($statuses,fn($s)=>in_array($s,['switched','purged'],true)));$plan['purged']=count(array_filter($statuses,fn($s)=>$s==='purged'));return $plan;}
}

final class KeyRotationService {
    public static function rotateAll(int $actor): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');Keyring::assertReady();
    $active=Keyring::activeId();$runId=Utils::id('krot');$result=['rotation_id'=>$runId,'active_key_id'=>$active,'rotated'=>0,'failed'=>0,'groups'=>0,'orphan_cleanup_pending'=>0];
    $groups=[];
    foreach(RecordStore::all('asset',0,null,100000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key'])||($record['storage']['key_id']??'')===$active)continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'asset','record'=>$record];}
    foreach(RecordStore::all('derivative',0,null,200000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key'])||($record['storage']['key_id']??'')===$active)continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'derivative','record'=>$record];}
    RecordStore::put('key_rotation',$runId,['actor_id'=>$actor,'rotation_id'=>$runId,'active_key_id'=>$active,'status'=>'running','groups_total'=>count($groups),'result'=>$result,'created_at'=>Utils::now()]);
    foreach($groups as $groupKey=>$group){
        $first=$group[0]['record'];$providerId=Utils::key((string)($first['storage']['provider_id']??ProviderRegistry::activeId()),64);$oldKey=(string)$first['object_key'];$newKey='';
        try{
            $provider=ProviderRegistry::get($providerId);$source=$provider->openStream($oldKey);$newKey=hash('sha256','rekey|'.$oldKey.'|'.$active.'|'.$first['sha256']);
            try{$stored=$provider->putStream($newKey,$source,['scope'=>'key-rotation','source_key_hash'=>hash('sha256',$oldKey),'rotation_id'=>$runId]);}finally{fclose($source);}
            if(!hash_equals((string)$first['sha256'],(string)$stored['sha256'])||(int)$first['size']!==(int)$stored['size'])throw new Error('key_rotation_integrity_failed','Re-encrypted object integrity failed.',500);
            $stored['provider_id']=$providerId;$updated=0;
            foreach($group as $entry){
                $record=RecordStore::get($entry['type'],(string)$entry['record']['id']);
                if(!$record)throw new Error('key_rotation_record_missing','Key-rotation record disappeared.',409);
                if(($record['object_key']??'')===$newKey&&($record['storage']['key_id']??'')===$active){$updated++;continue;}
                if(($record['object_key']??'')!==$oldKey)throw new Error('key_rotation_mapping_stale','Object mapping changed during key rotation.',409);
                $previousKeyId=$record['storage']['key_id']??null;$record['object_key']=$newKey;$record['storage']=$stored;$record['previous_key_id']=$previousKeyId;$record['rotated_at']=Utils::now();$record['rotation_id']=$runId;
                RecordStore::put($entry['type'],(string)$record['id'],$record,(int)$record['version']);$updated++;$result['rotated']++;
            }
            if($updated!==count($group))throw new Error('key_rotation_partial_mapping','Not all mappings were rotated.',500);
            if($provider->exists($oldKey)&&!$provider->delete($oldKey))throw new Error('key_rotation_cleanup_failed','Old encrypted object could not be deleted.',503);
            $result['groups']++;
            RecordStore::put('key_rotation_group',hash('sha256',$runId.'|'.$groupKey),['actor_id'=>$actor,'rotation_id'=>$runId,'group_hash'=>hash('sha256',$groupKey),'status'=>'completed','records'=>$updated,'completed_at'=>Utils::now()]);
        }catch(\Throwable $exception){
            $result['failed']++;
            if($newKey!==''){$mapped=false;foreach($group as $entry){$fresh=RecordStore::get($entry['type'],(string)$entry['record']['id']);if(($fresh['object_key']??'')===$newKey){$mapped=true;break;}}if(!$mapped){try{ProviderRegistry::get($providerId)->delete($newKey);}catch(\Throwable){$result['orphan_cleanup_pending']++;}}}
            RecordStore::put('key_rotation_group',hash('sha256',$runId.'|'.$groupKey),['actor_id'=>$actor,'rotation_id'=>$runId,'group_hash'=>hash('sha256',$groupKey),'status'=>'failed','error'=>$exception instanceof Error?$exception->errorCode:'unexpected','created_at'=>Utils::now()]);
            Observability::alert('critical','key_rotation_failed',['rotation_id'=>$runId,'object_key_hash'=>hash('sha256',$oldKey),'exception'=>get_class($exception)]);
        }
    }
    $run=RecordStore::get('key_rotation',$runId);if($run){$run['status']=$result['failed']===0?'completed':'completed_with_failures';$run['result']=$result;$run['completed_at']=Utils::now();RecordStore::put('key_rotation',$runId,$run,(int)$run['version']);}
    Audit::record('key_rotation_completed',$result+['actor_id'=>$actor]);return $result;
}
}

final class CostService {
    public static function record(string $assetId,string $provider,string $purpose,array $units,array $rates): array {
    $asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    $provider=Utils::key($provider,64);$purpose=Utils::key($purpose,64);
    if($provider===''||$purpose===''||$units===[])throw new Error('cost_identity_invalid','Cost provider, purpose and units are required.',400);
    $safeUnits=[];$safeRates=[];$cost=0.0;
    foreach($units as $name=>$value){
        $key=Utils::key((string)$name,64);$unit=(float)$value;
        if($key===''||isset($safeUnits[$key]))throw new Error('cost_unit_duplicate','Cost unit names must be unique after normalization.',400,['unit'=>$key]);
        $rateKey=array_key_exists($name,$rates)?$name:$key;$rate=(float)($rates[$rateKey]??0);
        if(!is_finite($unit)||!is_finite($rate)||$unit<0||$rate<0)throw new Error('cost_value_invalid','Cost units and rates must be finite and non-negative.',400);
        $safeUnits[$key]=$unit;$safeRates[$key]=$rate;$cost+=$unit*$rate;
    }
    if(!is_finite($cost)||$cost>1_000_000_000)throw new Error('cost_value_invalid','Calculated cost is invalid or exceeds the safety ceiling.',400);
    $id=Utils::id('cost');$row=['actor_id'=>0,'cost_id'=>$id,'asset_id'=>$assetId,'owner_domain'=>$asset['owner_domain'],'purpose'=>$purpose,'provider'=>$provider,'units'=>$safeUnits,'rates'=>$safeRates,'cost'=>round($cost,8),'currency'=>'USD','status'=>'recorded','created_at'=>Utils::now()];
    $row=RecordStore::put('cost',$id,$row);self::checkBudget($asset['owner_domain'],$row);return $row;
}
    public static function setBudget(string $domain,string $period,float $limit,int $actor): array {
    Auth::capability('media_manage_providers');Auth::assertActor($actor,'manage_options');
    $domain=Utils::key($domain,64);$period=trim($period);
    $date=\DateTimeImmutable::createFromFormat('!Y-m',$period,new \DateTimeZone('UTC'));
    $validPeriod=$date!==false&&$date->format('Y-m')===$period;
    if($domain===''||!$validPeriod||!is_finite($limit)||$limit<=0||$limit>1_000_000_000)throw new Error('budget_invalid','Budget domain, valid month and bounded positive limit are required.',400);
    $id=hash('sha256',$domain.'|'.$period);$existing=RecordStore::get('budget',$id);
    return RecordStore::put('budget',$id,['actor_id'=>$actor,'domain'=>$domain,'period'=>$period,'limit'=>round($limit,8),'status'=>'active','created_at'=>$existing['created_at']??Utils::now(),'updated_at'=>Utils::now()],$existing?(int)$existing['version']:0);
}
    private static function checkBudget(string $domain,array $row): void {
    $period=gmdate('Y-m');$budget=RecordStore::get('budget',hash('sha256',$domain.'|'.$period));if(!$budget||($budget['status']??'')!=='active')return;
    $spent=0.0;foreach(RecordStore::all('cost',0,null,500000) as $cost)if(($cost['owner_domain']??'')===$domain&&gmdate('Y-m',(int)$cost['created_at'])===$period)$spent+=(float)$cost['cost'];
    if($spent>(float)$budget['limit']){
        Audit::record('budget_threshold_exceeded',['domain'=>$domain,'spent'=>$spent,'limit'=>$budget['limit'],'cost_id'=>$row['id']]);
        if(function_exists('do_action'))do_action('scm.budget.threshold',['domain'=>$domain,'spent'=>$spent,'limit'=>$budget['limit']]);
    }
}
    public static function reconcile(string $provider,array $invoice): array {
    $provider=Utils::key($provider,64);$reported=(float)($invoice['total']??-1);$tolerance=(float)($invoice['tolerance']??0.01);
    if($provider===''||!is_finite($reported)||!is_finite($tolerance)||$reported<0||$tolerance<0||$tolerance>1_000_000)throw new Error('invoice_invalid','Invoice provider, total and tolerance are invalid.',400);
    $period=trim((string)($invoice['period']??''));
    if($period!==''){$date=\DateTimeImmutable::createFromFormat('!Y-m',$period,new \DateTimeZone('UTC'));if($date===false||$date->format('Y-m')!==$period)throw new Error('invoice_period_invalid','Invoice period is invalid.',400);}
    $calculated=0.0;foreach(RecordStore::all('cost',0,null,500000) as $cost)if(($cost['provider']??'')===$provider&&($period===''||gmdate('Y-m',(int)$cost['created_at'])===$period))$calculated+=(float)$cost['cost'];
    $delta=abs($calculated-$reported);$result=['actor_id'=>0,'provider'=>$provider,'period'=>$period,'calculated'=>round($calculated,8),'reported'=>round($reported,8),'tolerance'=>$tolerance,'delta'=>round($delta,8),'status'=>$delta<=$tolerance?'reconciled':'mismatch','created_at'=>Utils::now()];
    return RecordStore::put('invoice_reconciliation',Utils::id('inv'),$result);
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
        $repair=RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);Idempotency::complete('repair',$idempotencyKey,$fingerprint,'repair',$repairId);return $repair;
    }catch(\Throwable $exception){
        $fresh=RecordStore::get('asset',(string)$asset['id'])??$asset;unset($fresh['reprocess_context']);$fresh['job_graph']=[];
        if($oldManifest){$fresh['active_manifest_id']=$oldManifest;$fresh['status']='ready';$fresh['processing_status']='completed';}
        RecordStore::put('asset',(string)$fresh['id'],$fresh,(int)$fresh['version']);
        $repair=RecordStore::get('repair',$repairId)??$repair;$repair['status']='failed';$repair['error']=$exception instanceof Error?$exception->errorCode:'unexpected';$repair['last_valid_preserved']=$oldManifest!==null;
        RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);Idempotency::fail('repair',$idempotencyKey,$fingerprint,$repair['error']);throw $exception;
    }
}
    public static function rollback(string $repairId,int $actor): array {
    Auth::capability('media_reprocess');Auth::assertActor($actor,'manage_options');
    $repair=RecordStore::get('repair',$repairId);if(!$repair||empty($repair['previous_manifest_id'])||!in_array(($repair['status']??''),['completed','failed'],true))throw new Error('repair_rollback_unavailable','Repair rollback unavailable.',409);
    $asset=RecordStore::get('asset',(string)$repair['asset_id']);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    LegalHoldService::assertNoHold((string)$asset['id'],'reprocess');
    $previous=RecordStore::get('manifest',(string)$repair['previous_manifest_id']);if(!$previous||($previous['asset_id']??'')!==$asset['id'])throw new Error('repair_rollback_manifest_missing','Previous manifest is unavailable.',409);
    $current=!empty($asset['active_manifest_id'])?RecordStore::get('manifest',(string)$asset['active_manifest_id']):null;
    if($current&&($current['status']??'')==='active'){$current['status']='rolled_back';$current['rolled_back_to']=$previous['id'];RecordStore::put('manifest',(string)$current['id'],$current,(int)$current['version']);}
    $previous['status']='active';unset($previous['superseded_by']);RecordStore::put('manifest',(string)$previous['id'],$previous,(int)$previous['version']);
    $asset['active_manifest_id']=$previous['id'];$asset['manifest_version']=max((int)$asset['manifest_version'],(int)$previous['manifest_version']);$asset['status']='ready';$asset['processing_status']='completed';unset($asset['reprocess_context']);
    RecordStore::put('asset',(string)$asset['id'],$asset,(int)$asset['version']);
    $repair['status']='rolled_back';$repair['rolled_back_by']=$actor;$repair['rolled_back_at']=Utils::now();
    return RecordStore::put('repair',$repairId,$repair,(int)$repair['version']);
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
    RecordStore::put('restore_gate','current',['actor_id'=>$actor,'restore_id'=>$id,'inventory_hash'=>$hash,'status'=>'rebuilding','updated_at'=>Utils::now()],$gate?(int)$gate['version']:0);
    Audit::record('restore_started',['restore_id'=>$id,'actor_id'=>$actor,'inventory_hash'=>$hash]);
    return $row;
}
    public static function reconcile(string $restoreId): array {
    $restore=RecordStore::get('restore',$restoreId);if(!$restore)throw new Error('restore_not_found','Restore not found.',404);
    $gate=RecordStore::get('restore_gate','current');if(!$gate||($gate['restore_id']??'')!==$restoreId)throw new Error('restore_gate_mismatch','Restore is not the active reconciliation gate.',409);
    $checks=['schema'=>Schema::ready(),'keyring'=>self::check(fn()=>Keyring::assertReady()),'provider'=>self::check(fn()=>ProviderRegistry::store()),'tombstones'=>self::tombstones(),'rights'=>self::rights(),'manifests'=>self::manifests(),'integrity'=>self::integrity()];
    $restore['checks']=$checks;$restore['status']=in_array(false,$checks,true)?'blocked':'reconciled';$restore['reconciled_at']=Utils::now();
    $restore=RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);
    $gate['status']=$restore['status'];$gate['checks']=$checks;$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);
    return $restore;
}
    private static function check(callable $f): bool {try{$f();return true;}catch(\Throwable){return false;}}
    private static function tombstones(): bool {foreach(RecordStore::all('tombstone',0,null,100000) as $t){$a=RecordStore::get('asset',(string)$t['asset_id']);if($a&&($a['status']??'')!=='deleted')return false;}return true;}
    private static function rights(): bool {foreach(RecordStore::all('asset',0,null,100000) as $a){if(($a['status']??'')==='deleted')continue;try{RightsPolicy::assert($a['rights'],'view',['territory'=>'GLOBAL','audience_type'=>'private']);}catch(Error $e){if($e->errorCode==='rights_operation_denied')continue;return false;}}return true;}
    private static function manifests(): bool {foreach(RecordStore::all('asset',0,null,100000) as $a){if(($a['status']??'')==='ready'&&empty($a['active_manifest_id']))return false;}return true;}
    private static function integrity(): bool {foreach(RecordStore::all('asset',0,null,100000) as $a){if(($a['status']??'')==='deleted')continue;try{IntegrityService::sample((string)$a['id']);}catch(\Throwable){return false;}}return true;}
    public static function authorizeServe(string $restoreId): void {
    Auth::capability('media_manage_providers');
    $restore=RecordStore::get('restore',$restoreId);$gate=RecordStore::get('restore_gate','current');
    if(!$restore||!$gate||($gate['restore_id']??'')!==$restoreId||($restore['status']??'')!=='reconciled'||($gate['status']??'')!=='reconciled')throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    $restore['status']='serve_authorized';$restore['serve_authorized_at']=Utils::now();RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);
    $gate['status']='serve_authorized';$gate['serve_authorized_at']=Utils::now();$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);
    Audit::record('restore_serve_authorized',['restore_id'=>$restoreId,'actor_id'=>Auth::currentUser()]);
}
    public static function assertServeAllowed(): void {
        $gate=RecordStore::get('restore_gate','current');
        if($gate&&!in_array(($gate['status']??''),['serve_authorized','cancelled','failed'],true))throw new Error('restore_gate_blocked','Media serving is blocked during restore reconciliation.',503,['restore_id'=>$gate['restore_id']??'']);
    }

}

final class Observability {
    public static function metric(string $name,float $value,array $labels=[]): array {
    $name=Utils::key($name,96);if($name===''||!is_finite($value))throw new Error('metric_invalid','Metric name and finite value are required.',400);
    $safe=[];foreach($labels as $key=>$value){$key=Utils::key((string)$key,48);if($key===''||isset($safe[$key]))throw new Error('metric_label_invalid','Metric labels must be uniquely named.',400);$safe[$key]=Utils::text((string)$value,96);}
    return RecordStore::put('metric',Utils::id('met'),['actor_id'=>0,'metric'=>$name,'value'=>$value,'labels'=>$safe,'status'=>'recorded','created_at'=>Utils::now()]);
}
    public static function trace(string $operation,string $traceId,string $spanId,array $context=[]): array {
    $operation=Utils::key($operation,96);$traceId=Utils::text($traceId,96);$spanId=Utils::text($spanId,96);
    if($operation===''||$traceId===''||$spanId===''||!preg_match('/^[A-Za-z0-9._:-]{8,96}$/',$traceId)||!preg_match('/^[A-Za-z0-9._:-]{4,96}$/',$spanId))throw new Error('trace_identity_invalid','Trace operation and bounded trace/span identifiers are required.',400);
    return RecordStore::put('trace',Utils::id('trc'),['actor_id'=>Auth::currentUser(),'operation'=>$operation,'trace_id'=>$traceId,'span_id'=>$spanId,'context'=>Utils::redact($context),'status'=>'recorded','created_at'=>Utils::now()]);
}
    public static function health(): array {
    $providers=ProviderRegistry::health();$jobs=RecordStore::all('job',0,null,200000);
    $queued=count(array_filter($jobs,fn($job)=>in_array(($job['status']??''),['queued','retry','leased'],true)));$oldest=0;
    foreach($jobs as $job)if(in_array(($job['status']??''),['queued','retry'],true))$oldest=max($oldest,Utils::now()-(int)$job['created_at']);
    $dead=count(array_filter($jobs,fn($job)=>($job['status']??'')==='dead_letter'));
    $pendingDeletion=count(array_filter(RecordStore::all('deletion',0,null,100000),fn($deletion)=>!in_array(($deletion['status']??''),['completed','cancelled'],true)));
    $gate=RecordStore::get('restore_gate','current');$restoreBlocking=$gate&&!in_array(($gate['status']??''),['serve_authorized','cancelled','failed'],true);
    $status=RuntimeGuard::enabled()&&Schema::ready()&&$providers!==[]&&!in_array(false,array_map(fn($provider)=>($provider['healthy']??false)===true,$providers),true)&&!$restoreBlocking?'ready':'disabled_or_degraded';
    return ['status'=>$status,'runtime_enabled'=>RuntimeGuard::enabled(),'schema_ready'=>Schema::ready(),'providers'=>$providers,'queue_depth'=>$queued,'oldest_queue_age_seconds'=>$oldest,'dead_letters'=>$dead,'pending_deletions'=>$pendingDeletion,'restore_gate'=>$gate?['restore_id'=>$gate['restore_id']??'','status'=>$gate['status']??'unknown']:null,'audit_chain'=>Audit::verifyChain(),'manifest'=>IntegrationRegistry::manifest(),'runbooks'=>['provider-outage'=>'docs/runbooks/PROVIDER-OUTAGE.md','scanner-outage'=>'docs/runbooks/SCANNER-OUTAGE.md','deletion-pending'=>'docs/runbooks/DELETION-RECONCILIATION.md','restore'=>'docs/runbooks/RESTORE.md']];
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
    $record=RecordStore::put('webhook',$id,['actor_id'=>0,'provider'=>$provider,'event_id'=>$eventId,'body_hash'=>$bodyHash,'status'=>'accepted','created_at'=>Utils::now()]);
    return ['replay'=>false,'record'=>$record];
}
}
