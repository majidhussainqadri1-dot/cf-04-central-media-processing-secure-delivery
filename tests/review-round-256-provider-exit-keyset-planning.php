<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{ProviderExitService,RecordStore,Error};

RecordStore::resetMemory();
$source='source-private';$target='target-private';
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r256-deleted-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','storage'=>['provider_id'=>$source],
        'object_key'=>hash('sha256','historical-'.$i)
    ]);
}
$id='r256-late-live';
$asset=RecordStore::put('asset',$id,[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
    'storage'=>['provider_id'=>$source,'key_id'=>'test-v1'],
    'object_key'=>hash('sha256','r256-old-object'),
    'sha256'=>hash('sha256','r256-source-bytes'),'size'=>16
]);
$stream=new ReflectionMethod(ProviderExitService::class,'inventoryRows');
$visited=0;$lastId='';
foreach($stream->invoke(null,'asset') as $row){$visited++;$lastId=$row['id'];}
ok($visited===2601&&$lastId===$id,
    'R256 keyset planner traverses every source row after ten pages');
$plan=ProviderExitService::plan($source,$target,11);
ok(count($plan['items'])===1&&$plan['items'][0]['id']===$id,
    'R256 late eligible File 17 source object enters the provider-exit manifest');
$plans=count(RecordStore::scan('provider_exit',250));
for($i=0;$i<2000;$i++){
    RecordStore::put('asset',sprintf('r256-extra-%05d',$i),[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
        'storage'=>['provider_id'=>$source,'key_id'=>'test-v1'],
        'object_key'=>hash('sha256','r256-extra-object-'.$i),
        'sha256'=>hash('sha256','r256-extra-bytes-'.$i),'size'=>16
    ]);
}
err(fn()=>ProviderExitService::plan($source,$target,11),
    'provider_exit_plan_capacity_exceeded',
    'R256 oversized single-record provider-exit plan fails closed before commit');
ok(count(RecordStore::scan('provider_exit',250))===$plans,
    'R256 failed oversized plan creates no partial provider-exit ledger row');
err(fn()=>iterator_to_array($stream->invoke(null,'unsupported')),
    'provider_exit_inventory_type_invalid',
    'R256 arbitrary record types cannot be enumerated through provider-exit planner');
$code=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
$start=strpos((string)$code,'final class ProviderExitService');
$end=strpos((string)$code,'final class KeyRotationService');
$provider=substr((string)$code,$start,$end-$start);
ok(str_contains($provider,"RecordStore::scan(\$type,250,\$after)")
    &&str_contains($provider,'provider_exit_plan_capacity_exceeded')
    &&!str_contains($provider,'RecordStore::all('),
    'R256 no global capped materialization or silently truncated provider-exit inventory');
echo "REVIEW ROUND 256 PROVIDER-EXIT BOUNDED KEYSET PLANNING: PASS\n";
