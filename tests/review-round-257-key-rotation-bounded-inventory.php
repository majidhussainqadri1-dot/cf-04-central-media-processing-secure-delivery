<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{KeyRotationService,RecordStore};

RecordStore::resetMemory();
$old=hash('sha256','r257-old-ciphertext');
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r257-deleted-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v2'],
        'object_key'=>$old
    ]);
}
$last='r257-late-asset';
RecordStore::put('asset',$last,[
    'actor_id'=>11,'status'=>'ready','size'=>3,
    'sha256'=>hash('sha256','r257-late-source'),
    'object_key'=>$old,'storage'=>['provider_id'=>'source-private','key_id'=>'test-v2']
]);
$source=new ReflectionMethod(KeyRotationService::class,'rotationRows');
$ids=[];foreach($source->invoke(null,'asset') as $row)$ids[]=$row['id'];
ok(count($ids)===2601&&end($ids)===$last,
    'R257 late live asset remains visible beyond ten 250-row keyset pages');
for($i=0;$i<2000;$i++){
    RecordStore::put('asset',sprintf('r257-extra-%05d',$i),[
        'actor_id'=>11,'status'=>'ready','size'=>3,
        'sha256'=>hash('sha256','r257-extra-source-'.$i),
        'object_key'=>hash('sha256','r257-extra-cipher-'.$i),
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v2']
    ]);
}
err(fn()=>KeyRotationService::rotateAll(11),
    'key_rotation_capacity_exceeded',
    'R257 oversized shared-key rotation cannot begin irreversible writes');
ok(RecordStore::scan('key_rotation',250)===[],
    'R257 oversized rotation creates no partial rotation run or physical changes');
err(fn()=>iterator_to_array($source->invoke(null,'invalid')),
    'key_rotation_inventory_type_invalid',
    'R257 arbitrary record types cannot enter key-rotation inventory');
$code=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
$start=strpos((string)$code,'final class KeyRotationService');
$end=strpos((string)$code,'final class CostService');
$block=substr((string)$code,$start,$end-$start);
ok(str_contains($block,'key_rotation_capacity_exceeded')
    &&str_contains($block,'RecordStore::scan($type,250,$after)')
    &&!str_contains($block,'RecordStore::all('),
    'R257 key-rotation grouping is bounded and does not globally materialize history');
echo "REVIEW ROUND 257 KEY ROTATION BOUNDED INVENTORY: PASS\n";
