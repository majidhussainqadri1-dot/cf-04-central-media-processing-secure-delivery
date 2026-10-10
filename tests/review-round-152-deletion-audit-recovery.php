<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore};
function r152Asset(string $id): array {
    return RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','owner_object'=>'r152-object-'.$id,
        'object_version'=>1,'policy_hash'=>hash('sha256','policy-'.$id),
        'rights'=>['policy_hash'=>hash('sha256','rights-'.$id)],
        'storage'=>['provider_id'=>'source-private'],'object_key'=>'missing-'.$id,
    ]);
}
function r152AuditCount(string $event,string $deletionId): int {
    return count(array_filter(RecordStore::all('audit',0,null,100000),
        static fn(array $a): bool => ($a['event_key']??null)===$event
            &&($a['payload']['deletion_id']??null)===$deletionId));
}
RecordStore::resetMemory();
$asset=r152Asset('r152-audit');
Audit::failNextForTest('deletion_requested');
err(fn()=>DeletionService::request($asset['id'],11,'user-request'),
    'audit_write_failed','Round 152 injected request audit failure');
$pendingAsset=RecordStore::get('asset',$asset['id']);
$deletionId=$pendingAsset['deletion_id'];
ok(RecordStore::get('deletion',$deletionId)!==null
    &&r152AuditCount('deletion_requested',$deletionId)===0,
    'Round 152 durable request survives missing initial audit');
$deletion=DeletionService::request($asset['id'],11,'user-request');
ok($deletion['id']===$deletionId&&r152AuditCount('deletion_requested',$deletionId)===1,
    'Round 152 idempotent retry heals request audit');
DeletionService::request($asset['id'],11,'user-request');
ok(r152AuditCount('deletion_requested',$deletionId)===1,
    'Round 152 request audit remains exactly once');
Audit::failNextForTest('deletion_completed');
err(fn()=>DeletionService::process($deletionId),'audit_write_failed',
    'Round 152 injected post-completion audit failure');
ok(RecordStore::get('deletion',$deletionId)['status']==='completed'
    &&r152AuditCount('deletion_completed',$deletionId)===0,
    'Round 152 terminal deletion is durable despite audit outage');
$completed=DeletionService::process($deletionId);
ok($completed['status']==='completed'
    &&r152AuditCount('deletion_completed',$deletionId)===1,
    'Round 152 completed retry heals terminal audit');
DeletionService::process($deletionId);
ok(r152AuditCount('deletion_completed',$deletionId)===1
    &&Audit::verifyChain(),
    'Round 152 audit deduplication preserves verified hash chain');
$out=DeletionService::reconcile();
ok($out['completed']===1&&$out['failed']===0,
    'Round 152 reconciliation verifies completed audit evidence');

RecordStore::resetMemory();
$asset=r152Asset('r152-legacy-crash');
$d=DeletionService::request($asset['id'],11,'user-request');
$ledgerId=hash('sha256',$d['id'].'|backup-expiry');
RecordStore::put('backup_expiry',$ledgerId,[
    'actor_id'=>0,'asset_id'=>$asset['id'],'deletion_id'=>$d['id'],
    'status'=>'awaiting_backup_expiry','backup_expiry_at'=>$d['backup_expiry_at'],
]);
$steps=array_fill_keys(array_keys($d['steps']),'complete');
$steps['tombstone']='pending';
$d=RecordStore::put('deletion',$d['id'],array_replace($d,[
    'status'=>'pending_tombstone','steps'=>$steps,'attempts'=>1,
    'next_attempt_at'=>time()-1,
]),(int)$d['version']);
$asset=RecordStore::get('asset',$asset['id']);
$asset['status']='deleted';$asset['deleted_at']=time();
unset($asset['storage'],$asset['object_key']);
RecordStore::put('asset',$asset['id'],$asset,(int)$asset['version']);
$recovered=DeletionService::process($d['id']);
ok($recovered['status']==='completed'
    &&RecordStore::get('tombstone',$asset['id'])['status']==='deleted'
    &&r152AuditCount('deletion_completed',$d['id'])===1,
    'Round 152 recovers legacy crash after asset delete before tombstone');
$out=DeletionService::reconcile();
ok($out['completed']===1&&$out['failed']===0,
    'Round 152 recovered deletion reconciles as completed');

RecordStore::resetMemory();
$asset=r152Asset('r152-missing-ledger');
$d=DeletionService::request($asset['id'],11,'user-request');
$steps=array_fill_keys(array_keys($d['steps']),'complete');
$steps['tombstone']='pending';
$d=RecordStore::put('deletion',$d['id'],array_replace($d,[
    'status'=>'pending_tombstone','steps'=>$steps,'next_attempt_at'=>time()-1,
]),(int)$d['version']);
$asset=RecordStore::get('asset',$asset['id']);
$asset['status']='deleted';$asset['deleted_at']=time();
unset($asset['storage'],$asset['object_key']);
RecordStore::put('asset',$asset['id'],$asset,(int)$asset['version']);
err(fn()=>DeletionService::process($d['id']),'deletion_evidence_invalid',
    'Round 152 terminal recovery fails closed without matching backup ledger');
ok(RecordStore::get('deletion',$d['id'])['status']==='pending_tombstone',
    'Round 152 unverified terminal record is not accepted');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
$stage=substr($source,strpos($source,"if(\$d['steps']['tombstone']!=='complete')"));
ok(strpos($stage,"RecordStore::put('tombstone'")<strpos($stage,"RecordStore::put('asset'"),
    'Round 152 terminal stage persists tombstone before marking asset deleted');
echo "REVIEW ROUND 152 DELETION AUDIT RECOVERY: PASS\n";
