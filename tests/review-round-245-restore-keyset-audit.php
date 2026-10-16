<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RestoreService};

RecordStore::resetMemory();
for($i=0;$i<2600;$i++){
    $id=sprintf('r245-asset-%05d',$i);
    RecordStore::put('asset',$id,['actor_id'=>11,'status'=>'deleted']);
    RecordStore::put('tombstone',sprintf('r245-tomb-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','asset_id'=>$id
    ]);
}
$lateAsset='r245-asset-02600';
RecordStore::put('asset',$lateAsset,[
    'actor_id'=>11,'status'=>'ready',
    'rights'=>rights(['view'],false)
]);
RecordStore::put('tombstone','r245-tomb-02600',[
    'actor_id'=>11,'status'=>'deleted','asset_id'=>$lateAsset
]);
$inventory=[
    'database_snapshot'=>'r245-db',
    'object_inventory'=>'r245-objects',
    'keyring_version'=>'test-v1',
    'policy_snapshot'=>'r245-policy',
    'manifest_snapshot'=>'r245-manifests',
    'tombstone_snapshot'=>'r245-tombstones',
];
$restore=RestoreService::start(11,$inventory);
$blocked=RestoreService::reconcile($restore['id']);
ok($blocked['status']==='blocked'&&($blocked['checks']['tombstones']??true)===false,
    'R245 mismatched tombstone after ten 250-row pages blocks restored serving');
err(fn()=>RestoreService::authorizeServe($restore['id']),
    'restore_gate_blocked','R245 failed restored tombstone blocks serve authorization');
$changed=RecordStore::get('asset',$lateAsset);
$changed['status']='deleted';
RecordStore::put('asset',$lateAsset,$changed,(int)$changed['version']);
$reconciled=RestoreService::reconcile($restore['id']);
ok($reconciled['status']==='reconciled'
    &&count(array_filter($reconciled['checks'],fn($value)=>$value!==true))===0,
    'R245 complete tombstone, rights, manifest and integrity scans pass after source repair');
RestoreService::authorizeServe($restore['id']);
ok((RecordStore::get('restore_gate','current')['status']??null)==='serve_authorized',
    'R245 gate is released only after a complete verified restore');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(str_contains((string)$source,'private static function restoreRecords(string $type)')
    &&str_contains((string)$source,'RecordStore::scan($type,250,$after)')
    &&!str_contains((string)$source,"RecordStore::all('tombstone',0,null,100000)")
    &&!str_contains((string)$source,"RecordStore::all('asset',0,null,100000) as $a"),
    'R245 no global 100k inventory cap remains in restore verification');
echo "REVIEW ROUND 245 RESTORE KEYSET AUDIT: PASS\n";
