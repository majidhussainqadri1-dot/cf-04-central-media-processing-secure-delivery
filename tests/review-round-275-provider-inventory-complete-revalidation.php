<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderRegistry,RecordStore};
RecordStore::resetMemory();
for($i=0;$i<525;$i++){
    $id=sprintf('r275-asset-%04d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>hash('sha256','r275-object-'.$i),
        'sha256'=>hash('sha256','r275-bytes-'.$i),'size'=>12
    ]);
}
RecordStore::put('derivative','r275-file12-derivative',[
    'actor_id'=>11,'status'=>'validated','asset_id'=>'r275-asset-0000',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>hash('sha256','r275-pdf-thumb'),
    'sha256'=>hash('sha256','r275-pdf-bytes'),'size'=>22
]);
$active=ProviderRegistry::activeId();
$s=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($s['status']??null)==='planning')$s=ProviderExitInventoryStage::advance($s['id'],11);
$v=ProviderExitInventoryStage::verifyComplete($s['id'],11);
ok(($v['status']??null)==='verified_unfenced'&&$v['eligible_items']===526
   &&$v['visited_rows']===526&&$v['segments']===5
   &&$v['chain_hash']===$s['chain_hash'],
   'R275 complete asset and derivative segments match original source identities');
ok(ProviderRegistry::activeId()===$active&&RecordStore::all('provider_exit')===[],
   'R275 verification does not activate provider or create executable migration');
$firstId=hash('sha256',$s['id'].'|asset|');
$seg=RecordStore::get('provider_exit_inventory_segment',$firstId);
$seg['items'][0]['sha256']=hash('sha256','r275-forged-manifest');
RecordStore::put('provider_exit_inventory_segment',$firstId,$seg,(int)$seg['version']);
err(fn()=>ProviderExitInventoryStage::verifyComplete($s['id'],11),
   'provider_exit_inventory_drift',
   'R275 altered staged item payload cannot be hidden by an unchanged page hash');
$second=ProviderExitInventoryStage::begin('source-private','target-private',11);
$first=ProviderExitInventoryStage::advance($second['id'],11);
$segmentId=hash('sha256',$second['id'].'|asset|');
$row=RecordStore::get('provider_exit_inventory_segment',$segmentId);
$row['items'][0]['object_key']=hash('sha256','r275-replay-bogus-key');
RecordStore::put('provider_exit_inventory_segment',$segmentId,$row,(int)$row['version']);
$rewind=$first;
$rewind['record_family']='asset';$rewind['after_id']='';$rewind['segments']=0;
$rewind['eligible_items']=0;$rewind['visited_rows']=0;
$rewind['chain_hash']=str_repeat('0',64);
RecordStore::put('provider_exit_inventory',$first['id'],$rewind,(int)$first['version']);
err(fn()=>ProviderExitInventoryStage::advance($first['id'],11),
   'provider_exit_inventory_drift',
   'R275 crash replay rejects forged manifest despite identical scanned row/page hash');
$third=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($third['status']??null)==='planning')$third=ProviderExitInventoryStage::advance($third['id'],11);
$late='r275-late-media';
RecordStore::put('asset',$late,[
    'actor_id'=>11,'status'=>'ready','storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>hash('sha256','r275-late-object'),
    'sha256'=>hash('sha256','r275-late-bytes'),'size'=>4
]);
err(fn()=>ProviderExitInventoryStage::verifyComplete($third['id'],11),
    'provider_exit_inventory_drift',
    'R275 new late File17 source object invalidates an unfenced completed inventory');
$fourth=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($fourth['status']??null)==='planning')$fourth=ProviderExitInventoryStage::advance($fourth['id'],11);
$bad=$fourth;$bad['eligible_items']++;
RecordStore::put('provider_exit_inventory',$bad['id'],$bad,(int)$fourth['version']);
err(fn()=>ProviderExitInventoryStage::verifyComplete($fourth['id'],11),
    'provider_exit_inventory_drift',
    'R275 false stage item counter cannot impersonate a verified inventory');
echo "REVIEW ROUND 275 PROVIDER INVENTORY COMPLETE REVALIDATION: PASS\n";
