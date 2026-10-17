<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreService,RecordStore};

RecordStore::resetMemory();
$bad='r268-malformed-tombstone';
RecordStore::put('tombstone',$bad,[
    'actor_id'=>11,'status'=>'deleted'
]);
$run=RestoreService::start(11,[
    'database_snapshot'=>'r268-db','object_inventory'=>'r268-objects',
    'keyring_version'=>'test-v1','policy_snapshot'=>'r268-policy',
    'manifest_snapshot'=>'r268-manifests','tombstone_snapshot'=>'r268-tombstones'
]);
$initial=RestoreService::reconcile($run['id']);
ok(($initial['status']??null)==='blocked'
    &&($initial['checks']['tombstones']??true)===false
    &&(RecordStore::get('restore_gate','current')['status']??null)==='blocked',
    'R268 missing asset_id in tombstone blocks restore without PHP warnings');
err(fn()=>RestoreService::authorizeServe($run['id']),
    'restore_gate_blocked',
    'R268 media serving remains forbidden while tombstone linkage is incomplete');
$repairedAsset='r268-purged-asset';
RecordStore::put('asset',$repairedAsset,[
    'actor_id'=>11,'status'=>'deleted']);
$tomb=RecordStore::get('tombstone',$bad);
$tomb['asset_id']=$repairedAsset;
RecordStore::put('tombstone',$bad,$tomb,(int)$tomb['version']);
$after=RestoreService::reconcile($run['id']);
ok(($after['checks']['tombstones']??false)===true
    &&($after['status']??null)==='reconciled',
    'R268 valid tombstone-to-deleted-asset mapping clears the failure');
echo "REVIEW ROUND 268 RESTORE TOMBSTONE ID VALIDATION: PASS\n";
