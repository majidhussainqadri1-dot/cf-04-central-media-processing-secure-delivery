<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($GLOBALS['r161_fail']??false))
        throw new \RuntimeException('Test callback failure');
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RevocationDispatchService,RightsRevocationService,Utils};
RecordStore::resetMemory();
$r=rights(['view']);$r['expires_at']=Utils::now()-10;
$asset=RecordStore::put('asset','r161-test',[
    'actor_id'=>11,'asset_id'=>'r161-test','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r161-owner','object_version'=>1,
    'policy_hash'=>hash('sha256','r161-policy'),'rights'=>$r,
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r161-missing'),
]);
$GLOBALS['r161_fail']=true;
$failed=false;
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){$failed=true;}
ok($failed,'Round 161 pending notification remains durable after callback failure');
$notice=RecordStore::all('revocation_dispatch')[0];
ok($notice['status']==='pending','Round 161 outbox begins pending');
$changed=RecordStore::get('asset',$asset['id']);
$changed['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$changed,(int)$changed['version']);
$GLOBALS['r161_fail']=false;
$out=RevocationDispatchService::reconcile();
$row=RecordStore::get('revocation_dispatch',$notice['id']);
ok($out['superseded']===1&&$out['dispatched']===0&&$out['failed']===0
    &&$row['status']==='superseded'&&$row['superseded_reason']==='rights_changed',
    'Round 161 obsolete rights become superseded without dispatch');
ok(RevocationDispatchService::reconcile()['checked']===0,
    'Round 161 superseded rows do not recur in the pending scanner');
$latest=RecordStore::get('asset',$asset['id']);
RecordStore::put('asset',$asset['id'],$asset,(int)$latest['version']);
RightsRevocationService::invalidate($asset['id'],'rights_expired');
$row=RecordStore::get('revocation_dispatch',$notice['id']);
ok($row['status']==='dispatched'&&$row['supersession_count']===1
    &&count($row['supersession_history'])===1,
    'Round 161 restored original rights reactivate with preserved evidence');
echo "REVIEW ROUND 161 OUTBOX SUPERSESSION: PASS\n";
