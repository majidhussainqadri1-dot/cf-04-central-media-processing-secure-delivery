<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,RecordStore};
RecordStore::resetMemory();
$id='r281-forged-current-asset';
RecordStore::put('asset',$id,[
    'actor_id'=>11,'status'=>'ready',
    'object_key'=>hash('sha256','r281-physical-object'),
    'sha256'=>'malformed-current-key-digest','size'=>12,
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1']
]);
$stage=KeyRotationInventoryStage::begin(11);
err(fn()=>KeyRotationInventoryStage::advance($stage['id'],11),
    'key_rotation_identity_invalid',
    'R281 allegedly current-key record with corrupt SHA cannot silently bypass staged discovery');
$persisted=RecordStore::get('key_rotation_inventory',$stage['id']);
ok(($persisted['segments']??null)===0&&($persisted['after_id']??null)===''
    &&RecordStore::all('key_rotation_inventory_segment')===[],
    'R281 incomplete invalid-current scan cannot advance or checkpoint');
$asset=RecordStore::get('asset',$id);
$asset['sha256']=hash('sha256','r281-real-plaintext');
RecordStore::put('asset',$id,$asset,(int)$asset['version']);
while(($persisted['status']??null)==='planning')$persisted=KeyRotationInventoryStage::advance($stage['id'],11);
$verified=KeyRotationInventoryStage::verifyComplete($stage['id'],11);
ok($verified['stale_references']===0&&$verified['visited_rows']===1
    &&($verified['status']??null)==='verified_unfenced',
    'R281 repaired, valid current-key asset may safely be omitted from stale candidate work');
echo "REVIEW ROUND 281 CURRENT KEY IDENTITY FAIL-CLOSED: PASS\n";
