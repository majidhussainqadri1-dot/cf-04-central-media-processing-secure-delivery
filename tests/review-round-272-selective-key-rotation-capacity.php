<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Keyring,KeyRotationService,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$keyId='test-v1';
for($i=0;$i<2600;$i++){
    $id=sprintf('r272-current-%05d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready',
        'sha256'=>hash('sha256','r272-current-plain-'.$i),
        'size'=>5,'object_key'=>hash('sha256','r272-current-key-'.$i),
        'storage'=>['provider_id'=>'source-private','key_id'=>$keyId]
    ]);
}
$provider=ProviderRegistry::get('source-private');
$old=hash('sha256','r272-one-stale-physical-object');
$bytes='r272-real-stale-ciphertext-plain';
Keyring::setTestKeys([
    'test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)
],'test-v2');
$stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
try{$physical=$provider->putStream($old,$stream);}finally{fclose($stream);}
ok(($physical['key_id']??null)==='test-v2',
    'R272 source plaintext was encrypted under the previous key');
Keyring::setTestKeys([
    'test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)
],'test-v1');
$stale='r272-single-old';
RecordStore::put('asset',$stale,[
    'actor_id'=>11,'status'=>'ready',
    'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes),
    'object_key'=>$old,'storage'=>['provider_id'=>'source-private','key_id'=>'test-v2']
]);
$result=KeyRotationService::rotateAll(11);
$new=RecordStore::get('asset',$stale);
ok(($result['rotated']??null)===1&&($result['failed']??null)===0
    &&($result['groups']??null)===1,
    'R272 2,600 already-current unrelated assets no longer prevent one needed key rotation');
ok(($new['storage']['key_id']??null)==='test-v1'
    &&($new['object_key']??null)!==$old
    &&$provider->exists((string)$new['object_key']),
    'R272 the stale ciphertext was re-encrypted to active key and mapped');
$again=KeyRotationService::rotateAll(11);
ok(($again['rotated']??null)===0&&($again['failed']??null)===0,
    'R272 no-op bulk rotation of more than 2,000 already-current assets is allowed');
echo "REVIEW ROUND 272 SELECTIVE KEY ROTATION CAPACITY: PASS\n";
