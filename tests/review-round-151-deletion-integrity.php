<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeletionService,LegalHoldService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','r151-asset',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','owner_object'=>'r151-object',
    'object_version'=>1,'policy_hash'=>hash('sha256','r151-policy'),
    'rights'=>['policy_hash'=>hash('sha256','r151-rights')],
    'storage'=>['provider_id'=>'source-private'],'object_key'=>'r151-missing-object',
]);
err(fn()=>DeletionService::request($asset['id'],11,'user-request',['backup_expiry_at'=>'1e2']),
    'deletion_backup_expiry_invalid','Round 151 noncanonical backup expiry rejected');
err(fn()=>DeletionService::request($asset['id'],11,'user-request',['backup_expiry_at'=>false]),
    'deletion_backup_expiry_invalid','Round 151 boolean backup expiry rejected');
$deletion=DeletionService::request($asset['id'],11,'user-request');
$hold=LegalHoldService::place($asset['id'],11,[
    'authority'=>'Test security','reason'=>'r151 freeze','scope'=>['deletion'],'review_at'=>time()+3600,
]);
err(fn()=>DeletionService::request($asset['id'],11,'user-request'),
    'asset_on_hold','Round 151 in-flight idempotency rechecks current legal hold');
LegalHoldService::review($hold['id'],11,'release','test completed');
$base=RecordStore::get('deletion',$deletion['id']);
foreach([
    ['deletion_id'=>'different'],['asset_id'=>'other'],['status'=>'unrecognized'],
    ['attempts'=>'1e2'],['next_attempt_at'=>false],['backup_expiry_at'=>null],
    ['steps'=>array_replace($base['steps'],['purge_cdn'=>'complete'])],
    ['steps'=>array_replace($base['steps'],['revoke_grants'=>'unknown'])],
] as $i=>$changes){
    $current=RecordStore::get('deletion',$deletion['id']);
    $invalid=RecordStore::put('deletion',$deletion['id'],array_replace($current,$changes),(int)$current['version']);
    err(fn()=>DeletionService::process($deletion['id']),'deletion_record_invalid',
        'Round 151 corrupt persisted deletion '.$i.' rejected before action');
    RecordStore::put('deletion',$deletion['id'],array_replace($base,['version'=>$invalid['version']]),(int)$invalid['version']);
}
$now=RecordStore::get('deletion',$deletion['id']);
$completed=RecordStore::put('deletion',$deletion['id'],array_replace($now,[
    'status'=>'completed','steps'=>array_fill_keys(array_keys($now['steps']),'complete'),
    'completed_at'=>time(),
]),(int)$now['version']);
err(fn()=>DeletionService::process($deletion['id']),'deletion_evidence_invalid',
    'Round 151 forged completion without asset/ledger/tombstone rejected');
$out=DeletionService::reconcile();
ok($out['completed']===0&&$out['failed']===1,
    'Round 151 reconcile never counts forged completion');
$asset=RecordStore::get('asset',$asset['id']);
$asset['status']='deleted';$asset['deletion_id']=$deletion['id'];
RecordStore::put('asset',$asset['id'],$asset,(int)$asset['version']);
RecordStore::put('tombstone',$asset['id'],[
    'actor_id'=>11,'asset_id'=>$asset['id'],'status'=>'deleted','reason'=>'user-request',
    'backup_expiry_at'=>$completed['backup_expiry_at'],
]);
RecordStore::put('backup_expiry',hash('sha256',$deletion['id'].'|backup-expiry'),[
    'actor_id'=>0,'asset_id'=>$asset['id'],'deletion_id'=>$deletion['id'],
    'status'=>'awaiting_backup_expiry','backup_expiry_at'=>$completed['backup_expiry_at'],
]);
ok(DeletionService::process($deletion['id'])['status']==='completed',
    'Round 151 consistent completed evidence accepted');
$out=DeletionService::reconcile();
ok($out['completed']===1&&$out['failed']===0,
    'Round 151 reconcile counts verified completion');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
ok(str_contains($source,"'phase'=>'backup_ledger'")&&str_contains($source,"'phase'=>'tombstone'"),
    'Round 151 terminal stages use fresh owner/hold authorization');
ok(str_contains($source,'$d=self::save($d);')&&str_contains($source,"'deletion-post-completion'"),
    'Round 151 attempts are persisted and completed state is protected on post-commit errors');
echo "REVIEW ROUND 151 DELETION INTEGRITY: PASS\n";
