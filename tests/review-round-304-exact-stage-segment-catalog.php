<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,KeyRotationInventoryStage,
    RestoreCheckpointStage,RecordStore};
RecordStore::resetMemory();

// A normal empty inventory is a valid unfenced planning witness.
$provider=ProviderExitInventoryStage::begin('source-private','target-private',11);
while($provider['status']==='planning')
    $provider=ProviderExitInventoryStage::advance($provider['id'],11);
ok(ProviderExitInventoryStage::verifyComplete($provider['id'],11)
    ['segments']===$provider['segments'],
    'R304 baseline provider catalogue exactly matches its source pages');
$extra=hash('sha256','r304-forged-provider-segment');
RecordStore::put('provider_exit_inventory_segment',$extra,[
    'actor_id'=>11,'inventory_id'=>$provider['id'],
    'record_family'=>'asset','status'=>'planned_unfenced','items'=>[]
]);
err(fn()=>ProviderExitInventoryStage::verifyComplete($provider['id'],11),
    'provider_exit_inventory_drift',
    'R304 injected extra provider segment invalidates a previously verified inventory');
RecordStore::delete('provider_exit_inventory_segment',$extra);
ok(ProviderExitInventoryStage::verifyComplete($provider['id'],11)
    ['segments']===$provider['segments'],
    'R304 removing the foreign segment restores exact source inventory proof');

$key=KeyRotationInventoryStage::begin(11);
while($key['status']==='planning')
    $key=KeyRotationInventoryStage::advance($key['id'],11);
ok(KeyRotationInventoryStage::verifyComplete($key['id'],11)
    ['segments']===$key['segments'],
    'R304 baseline stale-key candidate segment count is exact');
$extra=hash('sha256','r304-forged-key-segment');
RecordStore::put('key_rotation_inventory_segment',$extra,[
    'actor_id'=>11,'inventory_id'=>$key['id'],
    'record_family'=>'asset','status'=>'candidate_unfenced','items'=>[]
]);
err(fn()=>KeyRotationInventoryStage::verifyComplete($key['id'],11),
    'key_rotation_inventory_drift',
    'R304 extra key candidate segment fails before shadow ciphertext creation');
RecordStore::delete('key_rotation_inventory_segment',$extra);
ok(KeyRotationInventoryStage::verifyComplete($key['id'],11)
    ['segments']===$key['segments'],
    'R304 candidate catalogue recovers only after foreign segment removal');

$inventory=[
    'database_snapshot'=>'r304-source-db',
    'object_inventory'=>'r304-objects',
    'keyring_version'=>'test-v1',
    'policy_snapshot'=>'r304-policy',
    'manifest_snapshot'=>'r304-manifest',
    'tombstone_snapshot'=>'r304-tombstones'
];
$restore=RestoreCheckpointStage::begin(11,$inventory);
while($restore['status']==='planning_unfenced')
    $restore=RestoreCheckpointStage::advance($restore['id'],11);
ok(RestoreCheckpointStage::verifyComplete($restore['id'],11)
    ['segments']===$restore['segments'],
    'R304 baseline nine-family restore segment catalogue is exact');
$extra=hash('sha256','r304-forged-restore-segment');
RecordStore::put('restore_checkpoint_segment',$extra,[
    'actor_id'=>11,'checkpoint_id'=>$restore['id'],
    'record_family'=>'asset','status'=>'snapshot_unfenced'
]);
err(fn()=>RestoreCheckpointStage::verifyComplete($restore['id'],11),
    'restore_checkpoint_drift',
    'R304 extra restore checkpoint invalidates any full source reconciliation');
RecordStore::delete('restore_checkpoint_segment',$extra);
ok(RestoreCheckpointStage::verifyComplete($restore['id'],11)
    ['segments']===$restore['segments'],
    'R304 clean restore journal can again be verified after extra segment removal');
ok(RecordStore::get('restore_gate','current')===null&&
   RecordStore::all('key_rotation_shadow_run')===[],
    'R304 catalogue integrity audits do not serve media or re-encrypt content');
echo "REVIEW ROUND 304 EXACT STAGE SEGMENT CATALOG: PASS\n";
