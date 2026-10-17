<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','r153-asset',[
    'actor_id'=>11,'asset_id'=>'r153-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r153-object','object_version'=>1,
    'policy_hash'=>hash('sha256','r153-policy'),
    'rights'=>['policy_hash'=>hash('sha256','r153-rights')],
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r153-missing'),
]);
$d=DeletionService::request($asset['id'],11,'user-request');
$d=DeletionService::process($d['id']);
$tomb=RecordStore::get('tombstone',$asset['id']);
ok($d['status']==='completed'&&$tomb['deletion_id']===$d['id']
    &&$tomb['owner_object']==='r153-object',
    'Round 153 terminal tombstone is bound to exact deletion and owner');
$GLOBALS['scm_user_id']=0;
$reconciled=DeletionService::reconcile();
ok($reconciled['completed']===1&&$reconciled['failed']===0
    &&DeletionService::process($d['id'])['status']==='completed'
    &&Audit::verifyChain(),
    'Round 153 background reconciliation uses stable original audit actor');
$GLOBALS['scm_user_id']=11;
foreach([
    ['owner_object'=>'other'],
    ['deletion_id'=>'wrong'],
    ['rights_hash'=>hash('sha256','other')],
    ['object_version'=>2],
] as $index=>$tamper){
    $original=RecordStore::get('tombstone',$asset['id']);
    $modified=RecordStore::put('tombstone',$asset['id'],array_replace($original,$tamper),(int)$original['version']);
    err(fn()=>DeletionService::process($d['id']),'deletion_evidence_invalid',
        'Round 153 tampered terminal identity '.$index.' fails closed');
    RecordStore::put('tombstone',$asset['id'],array_replace($tomb,['version'=>$modified['version']]),(int)$modified['version']);
}
$old=RecordStore::get('tombstone',$asset['id']);
unset($old['deletion_id']);
RecordStore::put('tombstone',$asset['id'],$old,(int)$old['version']);
ok(DeletionService::process($d['id'])['status']==='completed',
    'Round 153 historical tombstone without deletion_id remains readable when full owner identity matches');
echo "REVIEW ROUND 153 AUDIT AND TOMBSTONE BINDING: PASS\n";
