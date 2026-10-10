<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($args[0]['reason']??null)==='deleted'
        &&($GLOBALS['scm_test_revocation_fail']??0)>0){
        $GLOBALS['scm_test_revocation_fail']--;
        throw new \RuntimeException('Injected downstream revocation callback failure');
    }
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','r154-asset',[
    'actor_id'=>11,'asset_id'=>'r154-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r154-object','object_version'=>1,
    'policy_hash'=>hash('sha256','r154-policy'),
    'rights'=>['policy_hash'=>hash('sha256','r154-rights')],
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r154-missing'),
]);
$d=DeletionService::request($asset['id'],11,'user-request');
$GLOBALS['scm_test_revocation_fail']=1;
$thrown=false;
try{DeletionService::process($d['id']);}
catch(\RuntimeException $e){$thrown=str_contains($e->getMessage(),'Injected downstream');}
ok($thrown,'Round 154 terminal revocation callback failure is surfaced');
$noticeId=hash('sha256',$d['id'].'|completion-revocation');
$notice=RecordStore::get('revocation_notice',$noticeId);
ok(RecordStore::get('deletion',$d['id'])['status']==='completed'
    &&$notice['status']==='pending'
    &&$notice['event_id']==='scm-revoked-'.$d['id'],
    'Round 154 durable pending outbox survives callback failure');
$completed=DeletionService::process($d['id']);
$notice=RecordStore::get('revocation_notice',$noticeId);
$events=array_values(array_filter($GLOBALS['scm_actions'],
    static fn(array $a): bool => $a['tag']==='scm.media.revoked'
        &&($a['args'][0]['event_id']??null)==='scm-revoked-'.$d['id']));
ok($completed['status']==='completed'&&$notice['status']==='delivered'
    &&count($events)===1,
    'Round 154 completed retry delivers pending owner notification');
DeletionService::process($d['id']);
$out=DeletionService::reconcile();
$events=array_values(array_filter($GLOBALS['scm_actions'],
    static fn(array $a): bool => $a['tag']==='scm.media.revoked'
        &&($a['args'][0]['event_id']??null)==='scm-revoked-'.$d['id']));
ok(count($events)===1&&$out['completed']===1&&$out['failed']===0&&Audit::verifyChain(),
    'Round 154 delivered notice is not resent on ordinary replay');
$corrupt=RecordStore::put('revocation_notice',$noticeId,array_replace($notice,['event_id'=>'wrong']),(int)$notice['version']);
err(fn()=>DeletionService::process($d['id']),'revocation_notice_invalid',
    'Round 154 corrupted completion outbox fails closed');
echo "REVIEW ROUND 154 REVOCATION OUTBOX: PASS\n";
