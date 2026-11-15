<?php
declare(strict_types=1);
define('SCM_TEST_REQUIRE_SIGNED_RESTORE',true);
define('SCM_RESTORE_ATTESTATION_KEY',str_repeat('r',64));
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreService,RecordStore,Utils};
RecordStore::resetMemory();
$inventory=[
    'database_snapshot'=>'r319-db-snapshot',
    'object_inventory'=>'r319-object-snapshot',
    'keyring_version'=>'test-v1',
    'policy_snapshot'=>'r319-policy-snapshot',
    'manifest_snapshot'=>'r319-manifest-snapshot',
    'tombstone_snapshot'=>'r319-tombstones'
];
$run=RestoreService::start(11,$inventory);
$first=RestoreService::reconcile($run['id']);
ok($first['status']==='reconciled'&&
   preg_match('/^[a-f0-9]{64}$/D',$first['source_fingerprint'])===1,
   'R319 initial empty restore inventory is stable and blocked pending authorization');
$GLOBALS['r319_mutate']=true;
add_filter('scm_restore_execution_proof',static function($value,array $restore):array {
    $now=Utils::now();
    $attestation=[
        'restore_id'=>$restore['restore_id'],
        'inventory_hash'=>$restore['inventory_hash'],
        'source_fingerprint'=>$restore['source_fingerprint'],
        'database_snapshot_sha256'=>hash('sha256','r319-real-db'),
        'object_snapshot_sha256'=>hash('sha256','r319-real-object'),
        'key_snapshot_sha256'=>hash('sha256','r319-real-key'),
        'issued_at'=>$now,'expires_at'=>$now+300,'restored_at'=>$now-10,
        'executed'=>true,'rights_reapplied'=>true,
        'tombstones_verified'=>true,'owner_reconciliation'=>'complete'
    ];
    if($GLOBALS['r319_mutate']){
        $GLOBALS['r319_mutate']=false;
        RecordStore::put('asset','r319-touched-during-callback',[
            'actor_id'=>11,'status'=>'deleted',
            'reason'=>'external_restore_hook_mutation'
        ]);
    }
    $attestation['signature']=hash_hmac('sha256',
        Utils::canonicalJson($attestation),SCM_RESTORE_ATTESTATION_KEY);
    return $attestation;
});
err(fn()=>RestoreService::authorizeServe($run['id']),
    'restore_attestation_source_drift',
    'R319 signed external callback cannot mutate inventory and still open serving');
ok(RecordStore::get('restore_gate','current')['status']==='reconciled'&&
   RecordStore::get('restore',$run['id'])['status']==='reconciled',
   'R319 post-attestation drift preserves a non-serving restore gate');
$reconciled=RestoreService::reconcile($run['id']);
ok($reconciled['status']==='reconciled'&&
   $reconciled['source_fingerprint']!==$first['source_fingerprint'],
   'R319 changed source requires a fresh independent inventory fingerprint');
RestoreService::authorizeServe($run['id']);
ok(RecordStore::get('restore_gate','current')['status']==='serve_authorized',
   'R319 new stable inventory and matching signed proof may authorize test fixture serving');
echo "REVIEW ROUND 319 RESTORE POST-ATTESTATION SOURCE DRIFT: PASS\n";
