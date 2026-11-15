<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,KeyRotationShadowStage,
    Keyring,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$store=ProviderRegistry::get('source-private');
function r302put(string $key,string $data):void {
    global $store;
    $s=Utils::tempStream();Utils::writeAll($s,$data);rewind($s);
    try{$store->putStream($key,$s);}finally{fclose($s);}
}
$sharedKey=hash('sha256','r302-stale-shared');
$sharedData='r302-encrypted-medical-private';
$otherKey=hash('sha256','r302-stale-other');
$otherData='r302-private-derivative-payload';
r302put($sharedKey,$sharedData);
r302put($otherKey,$otherData);
$entries=[
    ['r302-asset-0',$sharedKey,$sharedData],
    ['r302-asset-1',$sharedKey,$sharedData],
    ['r302-asset-2',$otherKey,$otherData]
];
foreach($entries as [$id,$key,$data]){
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$data),'size'=>strlen($data)
    ]);
}
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
$inv=KeyRotationInventoryStage::begin(11);
while(($inv['status']??null)==='planning')
    $inv=KeyRotationInventoryStage::advance($inv['id'],11);
$seg=hash('sha256',$inv['id'].'|asset|');
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1),
    'capability_denied',
    'R302 new ciphertext creation denies actors without media provider management');
unset($GLOBALS['scm_test_denied_caps']);
$one=KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1);
ok(($one['next_index']??null)===1&&
    ($one['status']??null)==='copying_unfenced'&&
    count($one['copies']??[])===1,
    'R302 first real source encrypted under active key into separate shadow object');
$target=$one['copies'][0]['target_key'];
ok($store->objectEncryptionKeyId($target)==='test-v2'&&
    $store->objectEncryptionKeyId($sharedKey)==='test-v1',
    'R302 new ciphertext v2 exists while original v1 remains decryptable');
$two=KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1);
ok($two['next_index']===2&&
    $two['copies'][0]['target_key']===$two['copies'][1]['target_key'],
    'R302 shared old physical aliases re-use one canonical shadow ciphertext');
$store->delete($otherKey);
err(fn()=>KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1),
    'object_not_found',
    'R302 missing subsequent source refuses to advance the durable shadow cursor');
$checkpoint=RecordStore::get('key_rotation_shadow_segment',
    hash('sha256','key-shadow|'.$inv['id'].'|'.$seg));
ok($checkpoint['next_index']===2&&$checkpoint['status']==='copying_unfenced',
    'R302 failure leaves already verified shadow items and cursor intact');
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v1');
r302put($otherKey,$otherData);
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
$done=KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1);
ok(($done['status']??null)==='copied_unfenced'&&$done['next_index']===3,
    'R302 interrupted staged re-encryption safely resumes to all aliases');
$result=KeyRotationShadowStage::verifySegment($inv['id'],$seg,11);
ok($result['status']==='shadow_reencrypted_unfenced'&&
    $result['verified_objects']===3&&$result['mappings_changed']===0&&
    $result['old_ciphertexts_deleted']===0,
    'R302 every source and new ciphertext rehashed without irreversible cutover');
$finalTarget=$done['copies'][2]['target_key'];
$store->delete($finalTarget);
err(fn()=>KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1),
    'object_not_found',
    'R302 idempotent completed replay rejects a missing final shadow ciphertext');
r302put($finalTarget,$otherData);
$repeat=KeyRotationShadowStage::copySegment($inv['id'],$seg,11,1);
ok($repeat['version']===$done['version'],
    'R302 completed shadow journal remains idempotent after verified physical recovery');
foreach($entries as [$id,$key,$data]){
    $record=RecordStore::get('asset',$id);
    ok($record['object_key']===$key&&
       $record['storage']['key_id']==='test-v1'&&$store->exists($key),
       'R302 source mapping and old key remain fully authoritative and intact');
}
ok(RecordStore::all('key_rotation')===[]&&
    RecordStore::all('key_rotation_group')===[],
    'R302 stage cannot falsely claim a live key rotation transaction was completed');
echo "REVIEW ROUND 302 STAGED SHADOW REENCRYPTION: PASS\n";
