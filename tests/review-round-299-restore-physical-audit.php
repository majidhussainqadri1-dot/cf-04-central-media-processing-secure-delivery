<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreCheckpointStage,RestorePhysicalAuditStage,
    RecordStore,ProviderRegistry,Utils};
RecordStore::resetMemory();
$store=ProviderRegistry::get('source-private');
function r299put(string $key,string $bytes):void {
    global $store;
    $s=Utils::tempStream();Utils::writeAll($s,$bytes);rewind($s);
    try{$store->putStream($key,$s);}finally{fclose($s);}
}
$objects=[];
for($i=0;$i<13;$i++){
    $id=sprintf('r299-source-%03d',$i);
    $key=hash('sha256',$id);$bytes='r299-c2-private-content-'.$i;
    r299put($key,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'owner_domain'=>'file17','status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $objects[]=[$key,$bytes];
}
for($i=0;$i<2;$i++){
    $id=sprintf('r299-derivative-%03d',$i);$key=hash('sha256',$id);
    $bytes='r299-private-derivative-'.$i;
    r299put($key,$bytes);
    RecordStore::put('derivative',$id,[
        'actor_id'=>11,'asset_id'=>'r299-source-000','status'=>'validated',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $objects[]=[$key,$bytes];
}
$inventory=['database_snapshot'=>'db-source-299','object_inventory'=>'objects-299',
    'keyring_version'=>'test-v1','policy_snapshot'=>'policy-299',
    'manifest_snapshot'=>'manifest-299','tombstone_snapshot'=>'tomb-299'];
$checkpoint=RestoreCheckpointStage::begin(11,$inventory);
while($checkpoint['status']==='planning_unfenced')
    $checkpoint=RestoreCheckpointStage::advance($checkpoint['id'],11);
$stage=RestorePhysicalAuditStage::begin($checkpoint['id'],11);
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>RestorePhysicalAuditStage::advance($stage['id'],11),'capability_denied',
    'R299 unprivileged actor cannot read the private physical inventory');
unset($GLOBALS['scm_test_denied_caps']);
$stage=RestorePhysicalAuditStage::advance($stage['id'],11);
ok($stage['segments']===1&&$stage['objects']===10&&
    $stage['status']==='scanning_unfenced',
    'R299 first physical page verifies exactly ten encrypted sources');
$key=$objects[10][0];
$store->delete($key);
err(fn()=>RestorePhysicalAuditStage::advance($stage['id'],11),'object_not_found',
    'R299 missing source stops resumable audit before next page');
ok(RecordStore::get('restore_physical_audit',$stage['id'])['segments']===1,
    'R299 missing source preserves last durable checkpoint');
r299put($key,$objects[10][1]);
while(($stage['status']??null)==='scanning_unfenced')
    $stage=RestorePhysicalAuditStage::advance($stage['id'],11);
$result=RestorePhysicalAuditStage::verifyComplete($stage['id'],11);
ok($result['status']==='physical_verified_unfenced'&&
    $result['objects']===15&&$result['segments']===3&&
    $result['delivery_authorized']===false,
    'R299 real encrypted source and derivative bytes independently rehashed');
ok(RecordStore::get('restore_gate','current')===null&&
    RecordStore::all('restore')===[],
    'R299 physical audit cannot perform restoration or unlock media serving');
$key=$objects[0][0];
$store->delete($key);r299put($key,'r299-corrupted');
err(fn()=>RestorePhysicalAuditStage::verifyComplete($stage['id'],11),
    'restore_physical_integrity_failed',
    'R299 corruption after completing audit invalidates physical proof');
$store->delete($key);r299put($key,$objects[0][1]);
$state=RecordStore::get('restore_physical_audit',$stage['id']);
$state['objects']++;
RecordStore::put('restore_physical_audit',$stage['id'],$state,(int)$state['version']);
err(fn()=>RestorePhysicalAuditStage::verifyComplete($stage['id'],11),
    'restore_physical_journal_drift',
    'R299 forged physical audit counters fail closed on fresh verification');
echo "REVIEW ROUND 299 RESTORE PHYSICAL SOURCE AUDIT: PASS\n";
