<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','r155-asset',[
    'actor_id'=>11,'asset_id'=>'r155-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r155-object','object_version'=>1,
    'policy_hash'=>hash('sha256','r155-policy'),
    'rights'=>['policy_hash'=>hash('sha256','r155-rights')],
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r155-missing'),
]);
$d=DeletionService::request($asset['id'],11,'user-request');
$completed=DeletionService::process($d['id']);
$noticeId=hash('sha256',$d['id'].'|completion-revocation');
$notice=RecordStore::get('revocation_notice',$noticeId);
$events=static fn(): array => array_values(array_filter($GLOBALS['scm_actions'],
    static fn(array $a): bool => $a['tag']==='scm.media.revoked'
        &&($a['args'][0]['event_id']??null)==='scm-revoked-'.$d['id']));
ok($completed['status']==='completed'&&$notice['status']==='dispatched'
    &&is_int($notice['dispatched_at']??null)
    &&!array_key_exists('delivered_at',$notice)
    &&count($events())===1,
    'Round 155 local WordPress hook is recorded as dispatched, not externally delivered');
DeletionService::process($d['id']);
DeletionService::reconcile();
ok(count($events())===1,'Round 155 completed replay does not duplicate dispatched event');
$originalTime=$notice['dispatched_at'];
$legacy=$notice;
$legacy['status']='delivered';
$legacy['delivered_at']=$originalTime;
unset($legacy['dispatched_at']);
$legacy=RecordStore::put('revocation_notice',$noticeId,$legacy,(int)$notice['version']);
DeletionService::process($d['id']);
$migrated=RecordStore::get('revocation_notice',$noticeId);
ok($migrated['status']==='dispatched'
    &&$migrated['dispatched_at']===$originalTime
    &&!array_key_exists('delivered_at',$migrated)
    &&count($events())===1,
    'Round 155 legacy false-delivery marker migrates without redispatch');
$corrupt=RecordStore::put('revocation_notice',$noticeId,
    array_replace($migrated,['status'=>'dispatched','dispatched_at'=>'bad']),
    (int)$migrated['version']);
err(fn()=>DeletionService::process($d['id']),'revocation_notice_invalid',
    'Round 155 malformed dispatch evidence fails closed');
ok(Audit::verifyChain(),'Round 155 audit chain remains valid');
echo "REVIEW ROUND 155 REVOCATION DISPATCH TRUTH: PASS\n";
