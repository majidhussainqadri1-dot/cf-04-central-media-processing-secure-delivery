<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreService,RecordStore};

RecordStore::resetMemory();
$inventory=[
 'database_snapshot'=>'r265-db',
 'object_inventory'=>'r265-objects',
 'keyring_version'=>'test-v1',
 'policy_snapshot'=>'r265-policies',
 'manifest_snapshot'=>'r265-manifests',
 'tombstone_snapshot'=>'r265-tombstones',
];
$run=RestoreService::start(11,$inventory);
$first=RestoreService::reconcile($run['id']);
ok(($first['status']??null)==='reconciled',
    'R265 initial empty restore inventory reconciles safely');
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r265-deleted-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted']);
}
$late='r265-late-active-asset';
RecordStore::put('asset',$late,['actor_id'=>11,'status'=>'ready']);
RecordStore::put('tombstone','r265-late-tombstone',[
    'actor_id'=>11,'status'=>'deleted','asset_id'=>$late
]);
err(fn()=>RestoreService::authorizeServe($run['id']),
    'restore_gate_blocked',
    'R265 later tombstone mismatch denies serve despite previously reconciled gate');
ok((RecordStore::get('restore_gate','current')['status']??null)==='blocked',
    'R265 new data corruption persists a blocking restore gate');
ok((RecordStore::get('restore',$run['id'])['status']??null)==='blocked',
    'R265 no stale restore record reaches serve-authorized state');
$changed=RecordStore::get('asset',$late);$changed['status']='deleted';
RecordStore::put('asset',$late,$changed,(int)$changed['version']);
$reconciled=RestoreService::reconcile($run['id']);
ok(($reconciled['status']??null)==='reconciled',
    'R265 remediated inventory may be reconciled again');
RestoreService::authorizeServe($run['id']);
ok((RecordStore::get('restore_gate','current')['status']??null)==='serve_authorized',
    'R265 serving is enabled only following fresh full-inventory revalidation');
echo "REVIEW ROUND 265 RESTORE FRESH SERVE-AUTHORIZATION: PASS\n";
