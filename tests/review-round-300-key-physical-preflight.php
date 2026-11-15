<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,KeyRotationPhysicalPreflight,
    Keyring,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$store=ProviderRegistry::get('source-private');
function r300put(string $key,string $bytes):void {
    global $store;
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
$objects=[];
for($i=0;$i<2;$i++){
    $id='r300-stale-asset-'.$i;
    $key=hash('sha256','r300-physical-object-'.$i);
    $data='r300-encrypted-content-'.$i;
    r300put($key,$data);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'owner_domain'=>'file17','status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$data),'size'=>strlen($data)
    ]);
    $objects[]=[$key,$data];
}
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
$stage=KeyRotationInventoryStage::begin(11);
while(($stage['status']??null)==='planning')
    $stage=KeyRotationInventoryStage::advance($stage['id'],11);
$segmentId=hash('sha256',$stage['id'].'|asset|');
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11),
    'capability_denied',
    'R300 stale-key physical inspection denies an unauthorized operator');
unset($GLOBALS['scm_test_denied_caps']);
$first=KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11,0,1);
ok($first['status']==='partial_unfenced'&&$first['checked']===1&&
   $first['next_index']===1&&$first['keys_rotated']===0,
   'R300 bounded first physical candidate attested and never reencrypted');
$second=KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11,1,1);
ok($second['status']==='segment_physically_verified_unfenced'&&
    $second['checked']===1&&$second['authority_to_retire_old_key']===false,
    'R300 full candidate segment physically verified without KMS or source mutation');
$key=$objects[1][0];$bytes=$objects[1][1];
$store->delete($key);
r300put($key,$bytes);
err(fn()=>KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11,1,1),
    'key_rotation_physical_key_mismatch',
    'R300 same plaintext with different actual key id cannot spoof stale-key readiness');
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v1');
$store->delete($key);r300put($key,$bytes);
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
ok(KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11,1,1)
    ['status']==='segment_physically_verified_unfenced',
    'R300 authentic original ciphertext can be rechecked after safe source recovery');
$store->delete($key);
err(fn()=>KeyRotationPhysicalPreflight::inspectSegment($stage['id'],$segmentId,11,1,1),
    'object_not_found',
    'R300 physically missing source cannot be marked verified');
r300put($key,$bytes);
ok(RecordStore::all('key_rotation')===[]&&
    RecordStore::all('key_rotation_group')===[]&&
    RecordStore::get('asset','r300-stale-asset-0')['storage']['key_id']==='test-v1',
    'R300 preflight never commits new encryption envelopes or removes original mappings');
echo "REVIEW ROUND 300 STAGED KEY CIPHERTEXT SOURCE PREFLIGHT: PASS\n";
