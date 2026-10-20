<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreService,RecordStore};

RecordStore::resetMemory();
$rights=new ReflectionMethod(RestoreService::class,'rights');
$bad='r267-missing-rights';
RecordStore::put('asset',$bad,['actor_id'=>11,'status'=>'quarantined']);
ok($rights->invoke(null)===false,
    'R267 missing rights policy returns fail-closed false, not unhandled PHP warning/TypeError');
$run=RestoreService::start(11,[
    'database_snapshot'=>'r267-db','object_inventory'=>'r267-objects',
    'keyring_version'=>'test-v1','policy_snapshot'=>'r267-policy',
    'manifest_snapshot'=>'r267-manifests','tombstone_snapshot'=>'r267-tombstones'
]);
$blocked=RestoreService::reconcile($run['id']);
ok(($blocked['status']??null)==='blocked'
    &&($blocked['checks']['rights']??true)===false
    &&(RecordStore::get('restore_gate','current')['status']??null)==='blocked',
    'R267 malformed rights persist blocked restore/serve state as an auditable result');
err(fn()=>RestoreService::authorizeServe($run['id']),
    'restore_gate_blocked',
    'R267 serve gate cannot open with a missing rights-policy record');
$asset=RecordStore::get('asset',$bad);
$asset['rights']=rights(['view'],false);
RecordStore::put('asset',$bad,$asset,(int)$asset['version']);
ok($rights->invoke(null)===true,
    'R267 a repaired canonical rights envelope now passes rights reconciliation');
echo "REVIEW ROUND 267 RESTORE RIGHTS MALFORMED FAIL-CLOSED: PASS\n";
