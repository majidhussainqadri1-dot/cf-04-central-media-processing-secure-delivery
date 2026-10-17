<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($GLOBALS['r162_fail']??false))
        throw new \RuntimeException('Round 162 hook failure');
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RevocationDispatchService,RightsRevocationService,Utils};

RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
$rights=rights(['view']);$rights['expires_at']=Utils::now()-10;
$asset=RecordStore::put('asset','r162-asset',[
    'actor_id'=>11,'asset_id'=>'r162-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r162-owner','object_version'=>1,
    'policy_hash'=>hash('sha256','r162-policy'),'rights'=>$rights,
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r162-missing'),
]);
$GLOBALS['r162_fail']=true;
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){}
$projection=RecordStore::all('projection_revocation')[0];
$notice=RecordStore::all('revocation_dispatch')[0];
ok($notice['status']==='pending','Round 162 establishes pending outbox');
$changed=RecordStore::get('asset',$asset['id']);
$changed['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$changed,(int)$changed['version']);
$GLOBALS['r162_fail']=false;
$scan=RevocationDispatchService::reconcile();
$good=RecordStore::get('revocation_dispatch',$notice['id']);
ok($scan['superseded']===1&&$good['supersession_count']===1
    &&count($good['supersession_history'])===1,'Round 162 establishes valid supersession');
$mutations=[
    'missing history'=>static function(array $r): array {unset($r['supersession_history']);return $r;},
    'missing count'=>static function(array $r): array {unset($r['supersession_count']);return $r;},
    'string count'=>static function(array $r): array {$r['supersession_count']='1';return $r;},
    'negative count'=>static function(array $r): array {$r['supersession_count']=-1;return $r;},
    'count mismatch'=>static function(array $r): array {$r['supersession_count']=2;return $r;},
    'non-list history'=>static function(array $r): array {$r['supersession_history']=[4=>$r['supersession_history'][0]];return $r;},
    'scalar history entry'=>static function(array $r): array {$r['supersession_history'][0]='bad';return $r;},
    'string timestamp'=>static function(array $r): array {$r['supersession_history'][0]['at']='bad';return $r;},
    'unknown reason'=>static function(array $r): array {$r['supersession_history'][0]['reason']='forged';return $r;},
    'string prior version'=>static function(array $r): array {$r['supersession_history'][0]['prior_version']='1';return $r;},
    'missing prior version'=>static function(array $r): array {unset($r['supersession_history'][0]['prior_version']);return $r;},
    'extra entry key'=>static function(array $r): array {$r['supersession_history'][0]['untrusted']='x';return $r;},
    'active timestamp mismatch'=>static function(array $r): array {$r['superseded_at']++;return $r;},
    'active reason mismatch'=>static function(array $r): array {$r['superseded_reason']='owner_changed';return $r;},
];
foreach($mutations as $label=>$mutate){
    $current=RecordStore::get('revocation_dispatch',$notice['id']);
    $bad=RecordStore::put('revocation_dispatch',$notice['id'],$mutate($current),(int)$current['version']);
    err(fn()=>RevocationDispatchService::notify($asset,'rights_expired',$projection['id']),
        'revocation_dispatch_invalid','Round 162 rejects '.$label);
    ok(RecordStore::get('revocation_dispatch',$notice['id'])['version']===$bad['version'],
        'Round 162 rejected '.$label.' without additional outbox mutation');
    RecordStore::put('revocation_dispatch',$notice['id'],$good,(int)$bad['version']);
}
ok(RevocationDispatchService::notify($asset,'rights_expired',$projection['id'])['status']==='superseded',
    'Round 162 intact superseded evidence remains non-dispatchable while rights drift');
$current=RecordStore::get('asset',$asset['id']);
RecordStore::put('asset',$asset['id'],$asset,(int)$current['version']);
$GLOBALS['r162_fail']=true;
try{RevocationDispatchService::notify($asset,'rights_expired',$projection['id']);}
catch(\RuntimeException){}
$pending=RecordStore::get('revocation_dispatch',$notice['id']);
ok($pending['status']==='pending'&&$pending['supersession_count']===1
    &&count($pending['supersession_history'])===1,
    'Round 162 reactivation preserves validated history');
$GLOBALS['r162_fail']=false;
ok(RevocationDispatchService::reconcile()['dispatched']===1,
    'Round 162 intact pending history permits normal recovery');

RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
$asset=RecordStore::put('asset','r162-overflow',[
    'actor_id'=>11,'asset_id'=>'r162-overflow','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r162-owner','object_version'=>1,
    'policy_hash'=>hash('sha256','r162-overflow-policy'),'rights'=>$rights,
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r162-overflow-missing'),
]);
$GLOBALS['r162_fail']=true;
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){}
$projection=RecordStore::all('projection_revocation')[0];
$notice=RecordStore::all('revocation_dispatch')[0];
$current=RecordStore::get('asset',$asset['id']);$current['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$current,(int)$current['version']);
$GLOBALS['r162_fail']=false;
ok(RevocationDispatchService::reconcile()['superseded']===1,
    'Round 162 overflow baseline superseded');
for($i=0;$i<40;$i++){
    $row=RecordStore::get('revocation_dispatch',$notice['id']);
    RecordStore::put('revocation_dispatch',$notice['id'],$row,(int)$row['version']);
}
$row=RecordStore::get('revocation_dispatch',$notice['id']);
$entries=[];
for($i=0;$i<32;$i++)$entries[]=[
    'at'=>$row['superseded_at'],'reason'=>$row['superseded_reason'],
    'prior_version'=>$row['version']-32+$i,
];
$row['supersession_history']=$entries;$row['supersession_count']=PHP_INT_MAX;
$row=RecordStore::put('revocation_dispatch',$notice['id'],$row,(int)$row['version']);
$current=RecordStore::get('asset',$asset['id']);
RecordStore::put('asset',$asset['id'],$asset,(int)$current['version']);
$GLOBALS['r162_fail']=true;
try{RevocationDispatchService::notify($asset,'rights_expired',$projection['id']);}
catch(\RuntimeException){}
$pending=RecordStore::get('revocation_dispatch',$notice['id']);
ok($pending['status']==='pending'&&$pending['supersession_count']===PHP_INT_MAX,
    'Round 162 maximal valid count survives reactivation');
$current=RecordStore::get('asset',$asset['id']);$current['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$current,(int)$current['version']);
$GLOBALS['r162_fail']=false;
$out=RevocationDispatchService::reconcile();
$still=RecordStore::get('revocation_dispatch',$notice['id']);
ok($out['failed']===1&&$out['superseded']===0
    &&$still['status']==='pending'&&$still['supersession_count']===PHP_INT_MAX,
    'Round 162 counter overflow fails closed without persisting float or dispatch');
echo "REVIEW ROUND 162 SUPERSESSION EVIDENCE: PASS\n";
