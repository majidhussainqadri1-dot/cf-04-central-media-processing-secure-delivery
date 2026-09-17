#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 81 was fully completed before corrections began.
# Defect ledger:
# - ProviderExitService::plan snapshots source-provider inventory, but switch() could
#   activate the target provider after switching only that snapshot. A new asset or
#   derivative written to the source provider after planning could remain behind and
#   become an untracked split-brain object. Re-scan source-provider authoritative
#   records immediately before provider activation and fail closed on inventory drift.

p=ROOT/'sabri-central-media/includes/class-scm-operations.php'
s=p.read_text()
old="""        try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified','Provider exit item is not verified.',409,['id'=>$item['id']]);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing','Migrated record missing.',500,['id'=>$item['id']]);$provider=(string)($record['storage']['provider_id']??'');$key=(string)($record['object_key']??'');if($provider===(string)$plan['target_provider']&&$key===(string)$item['target_key']){$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));continue;}if($provider!==(string)$plan['source_provider']||$key!==(string)$item['object_key'])throw new Error('provider_exit_mapping_stale','Source mapping changed during provider exit.',409,['id'=>$item['id']]);$record['previous_storage']=$record['storage']??null;$record['previous_object_key']=$record['object_key']??null;$record['storage']=$item['target_storage'];$record['object_key']=$item['target_key'];$record['provider_migrated_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));}ProviderRegistry::activate((string)$plan['target_provider']);$plan['status']='switched';$plan['switched_at']=Utils::now();return self::save(self::counted($plan));}
"""
new="""        try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified','Provider exit item is not verified.',409,['id'=>$item['id']]);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing','Migrated record missing.',500,['id'=>$item['id']]);$provider=(string)($record['storage']['provider_id']??'');$key=(string)($record['object_key']??'');if($provider===(string)$plan['target_provider']&&$key===(string)$item['target_key']){$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));continue;}if($provider!==(string)$plan['source_provider']||$key!==(string)$item['object_key'])throw new Error('provider_exit_mapping_stale','Source mapping changed during provider exit.',409,['id'=>$item['id']]);$record['previous_storage']=$record['storage']??null;$record['previous_object_key']=$record['object_key']??null;$record['storage']=$item['target_storage'];$record['object_key']=$item['target_key'];$record['provider_migrated_at']=Utils::now();RecordStore::put($type,(string)$record['id'],$record,(int)$record['version']);$plan['items'][$index]['status']='switched';$plan=self::save(self::counted($plan));}self::assertSourceDrained($plan);ProviderRegistry::activate((string)$plan['target_provider']);$plan['status']='switched';$plan['switched_at']=Utils::now();return self::save(self::counted($plan));}
"""
if old not in s: raise SystemExit('round 81 provider switch target missing')
s=s.replace(old,new,1)
anchor="""    private static function load(string $id,array $states): array {$plan=RecordStore::get('provider_exit',$id);if(!$plan)throw new Error('provider_exit_not_found','Provider exit plan not found.',404);if(!in_array(($plan['status']??''),$states,true))throw new Error('provider_exit_state_invalid','Provider exit is not in an allowed state.',409,['status'=>$plan['status']??'unknown']);return $plan;}
"""
helper="""    private static function assertSourceDrained(array $plan): void {
        $source=(string)$plan['source_provider'];$remaining=[];
        foreach(RecordStore::all('asset',0,null,100000) as $record)if(($record['status']??'')!=='deleted'&&($record['storage']['provider_id']??'')===$source)$remaining[]=['type'=>'asset','id'=>$record['id']??''];
        foreach(RecordStore::all('derivative',0,null,200000) as $record)if(($record['status']??'')!=='deleted'&&($record['storage']['provider_id']??'')===$source)$remaining[]=['type'=>'derivative','id'=>$record['id']??''];
        if($remaining!==[])throw new Error('provider_exit_inventory_drift','Source-provider inventory changed during provider exit; re-plan before activation.',409,['remaining_count'=>count($remaining),'sample'=>array_slice($remaining,0,10)]);
    }
"""
if anchor not in s: raise SystemExit('round 81 provider helper anchor missing')
p.write_text(s.replace(anchor,helper+anchor,1))

t=ROOT/'tests/review-round-81-provider-exit-drift.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r81($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 81 FAIL: $m\n");exit(1);}echo "ROUND 81 PASS: $m\n";}
r81(str_contains($s,'self::assertSourceDrained($plan);ProviderRegistry::activate'),'source inventory is rechecked immediately before target-provider activation');
r81(str_contains($s,"provider_exit_inventory_drift"),'inventory drift has an explicit fail-closed error');
r81(substr_count($s,"RecordStore::all('asset',0,null,100000)")>=2&&substr_count($s,"RecordStore::all('derivative',0,null,200000)")>=2,'drift check independently covers authoritative source assets and derivatives');
echo "REVIEW ROUND 81 PROVIDER EXIT DRIFT: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchorq='php "$ROOT/tests/review-round-80-audit-lock-name.php"\n'
if 'review-round-81-provider-exit-drift.php' not in x:
    if anchorq not in x: raise SystemExit('round 81 quality anchor missing')
    q.write_text(x.replace(anchorq,anchorq+'php "$ROOT/tests/review-round-81-provider-exit-drift.php"\n',1))
