<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreCheckpointStage,RecordStore};
RecordStore::resetMemory();
for($i=0;$i<2601;$i++){
    RecordStore::put('asset',sprintf('r277-deleted-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted'
    ]);
}
$inventory=[
    'database_snapshot'=>'r277-db-immutable-descriptor',
    'object_inventory'=>'r277-provider-objects',
    'keyring_version'=>'test-v1',
    'policy_snapshot'=>'r277-policy',
    'manifest_snapshot'=>'r277-manifest',
    'tombstone_snapshot'=>'r277-tombstones'
];
$s=RestoreCheckpointStage::begin(11,$inventory);
$loops=0;
while(($s['status']??'')==='planning_unfenced'&&$loops++<100){
    $s=RestoreCheckpointStage::advance($s['id'],11);
}
ok(($s['status']??null)==='inventory_complete_unfenced'
   &&$s['visited_rows']===2601&&$s['segments']===19,
   'R277 >2600 asset rows and all nine record families are durably checkpointed');
$v=RestoreCheckpointStage::verifyComplete($s['id'],11);
ok(($v['status']??null)==='verified_unfenced'
    &&$v['segments']===19&&$v['visited_rows']===2601
    &&$v['chain_hash']===$s['chain_hash'],
    'R277 complete incremental source snapshot manifest hashes and counters verify');
ok(RecordStore::get('restore_gate','current')===null
    &&RecordStore::all('restore')===[],
    'R277 read-only checkpoint stage does not unlock serving or perform a restore');
$segments=RecordStore::all('restore_checkpoint_segment',0,null,100);
ok(count($segments)===19,'R277 every bounded page has a persisted restore checkpoint segment');
$same=RestoreCheckpointStage::advance($s['id'],11);
ok($same['version']===$s['version'],'R277 completed checkpoint advances idempotently');
$stage2=RestoreCheckpointStage::begin(11,$inventory);
$first=RestoreCheckpointStage::advance($stage2['id'],11);
$segId=hash('sha256',$stage2['id'].'|asset|');
$before=RecordStore::get('restore_checkpoint_segment',$segId);
$reset=$first;$reset['family_index']=0;$reset['after_id']='';
$reset['segments']=0;$reset['visited_rows']=0;$reset['chain_hash']=str_repeat('0',64);
RecordStore::put('restore_checkpoint',$reset['id'],$reset,(int)$first['version']);
$replayed=RestoreCheckpointStage::advance($stage2['id'],11);
ok($replayed['segments']===1
   &&RecordStore::get('restore_checkpoint_segment',$segId)['version']===$before['version'],
   'R277 interrupted checkpoint replay reuses the original durable page segment');
$src=RecordStore::get('asset','r277-deleted-00001');
$src['status']='quarantined';
RecordStore::put('asset',$src['id'],$src,(int)$src['version']);
err(fn()=>RestoreCheckpointStage::verifyComplete($s['id'],11),
    'restore_checkpoint_drift',
    'R277 changed restored source asset invalidates earlier complete checkpoint');
$state=RestoreCheckpointStage::begin(11,$inventory);
while(($state['status']??'')==='planning_unfenced')$state=RestoreCheckpointStage::advance($state['id'],11);
$corrupt=$state;$corrupt['segments']++;
RecordStore::put('restore_checkpoint',$state['id'],$corrupt,(int)$state['version']);
err(fn()=>RestoreCheckpointStage::verifyComplete($state['id'],11),
    'restore_checkpoint_drift',
    'R277 false checkpoint segment count cannot pass source verification');
echo "REVIEW ROUND 277 RESTORE RESUMABLE CHECKPOINT DRY RUN: PASS\n";
