<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,Keyring,RecordStore};
RecordStore::resetMemory();
for($i=0;$i<267;$i++){
    $id=sprintf('r280-asset-%05d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>$i<260?'test-v2':'test-v1'],
        'object_key'=>hash('sha256','r280-object-'.$i),
        'sha256'=>hash('sha256','r280-plain-'.$i),'size'=>11
    ]);
}
RecordStore::put('derivative','r280-derivative',[
    'actor_id'=>11,'status'=>'validated','asset_id'=>'r280-asset-00000',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v2'],
    'object_key'=>hash('sha256','r280-derivative-object'),
    'sha256'=>hash('sha256','r280-derivative-plain'),'size'=>18
]);
$s=KeyRotationInventoryStage::begin(11);
while(($s['status']??null)==='planning')$s=KeyRotationInventoryStage::advance($s['id'],11);
$v=KeyRotationInventoryStage::verifyComplete($s['id'],11);
ok(($v['status']??null)==='verified_unfenced'
    &&$v['visited_rows']===268&&$v['stale_references']===261
    &&$v['segments']===3&&$v['chain_hash']===$s['chain_hash'],
    'R280 full source/journal verification covers all stale asset+derivative aliases');
$segId=hash('sha256',$s['id'].'|asset|');
$seg=RecordStore::get('key_rotation_inventory_segment',$segId);
$seg['items'][0]['old_key_id']='test-v1';
RecordStore::put('key_rotation_inventory_segment',$segId,$seg,(int)$seg['version']);
err(fn()=>KeyRotationInventoryStage::verifyComplete($s['id'],11),
    'key_rotation_inventory_drift',
    'R280 a forged staged key identity is rejected even if original source-page SHA is unchanged');
$s2=KeyRotationInventoryStage::begin(11);
while(($s2['status']??null)==='planning')$s2=KeyRotationInventoryStage::advance($s2['id'],11);
$asset=RecordStore::get('asset','r280-asset-00000');
$asset['storage']['key_id']='test-v1';
RecordStore::put('asset',$asset['id'],$asset,(int)$asset['version']);
err(fn()=>KeyRotationInventoryStage::verifyComplete($s2['id'],11),
    'key_rotation_inventory_drift',
    'R280 changed authoritative key mapping invalidates a completed candidate inventory');
$s3=KeyRotationInventoryStage::begin(11);
while(($s3['status']??null)==='planning')$s3=KeyRotationInventoryStage::advance($s3['id'],11);
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v2');
err(fn()=>KeyRotationInventoryStage::verifyComplete($s3['id'],11),
    'key_rotation_inventory_key_changed',
    'R280 a completed stage cannot claim verification after active encryption epoch changes');
ok(RecordStore::all('key_rotation')===[],
    'R280 source discovery cannot write encryption outputs or claim a completed rotation');
echo "REVIEW ROUND 280 KEY CANDIDATE COMPLETE VERIFICATION: PASS\n";
