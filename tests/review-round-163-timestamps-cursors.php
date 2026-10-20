<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($GLOBALS['r163_block']??false))
        throw new \RuntimeException('Round 163 injected hook failure');
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RevocationDispatchService,RightsRevocationService,Utils};
$make=static function(string $id): array {
    $rights=rights(['view']);$rights['expires_at']=Utils::now()-10;
    return RecordStore::put('asset',$id,[
        'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>$id.'-owner','object_version'=>1,
        'policy_hash'=>hash('sha256',$id.'-policy'),'rights'=>$rights,
        'storage'=>['provider_id'=>'source-private'],'object_key'=>hash('sha256',$id.'-missing'),
    ]);
};
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];$GLOBALS['r163_block']=true;
$asset=$make('r163-outbox');
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){}
$projection=RecordStore::all('projection_revocation')[0];
$notice=RecordStore::all('revocation_dispatch')[0];
ok($notice['status']==='pending','Round 163 pending baseline');
foreach([
    'future created'=>static function(array $r): array {$r['created_at']=Utils::now()+600;return $r;},
] as $label=>$mutate){
    $current=RecordStore::get('revocation_dispatch',$notice['id']);
    $bad=RecordStore::put('revocation_dispatch',$notice['id'],$mutate($current),(int)$current['version']);
    err(fn()=>RevocationDispatchService::notify($asset,'rights_expired',$projection['id']),
        'revocation_dispatch_invalid','Round 163 rejects '.$label);
    ok(RecordStore::get('revocation_dispatch',$notice['id'])['version']===$bad['version'],
        'Round 163 invalid created timestamp does not mutate evidence');
    RecordStore::put('revocation_dispatch',$notice['id'],$current,(int)$bad['version']);
}
$GLOBALS['r163_block']=false;
ok(RevocationDispatchService::reconcile()['dispatched']===1,'Round 163 baseline dispatch');
$sent=RecordStore::get('revocation_dispatch',$notice['id']);
foreach([
    'dispatch before creation'=>static function(array $r): array {$r['dispatched_at']=$r['created_at']-1;return $r;},
    'future dispatch'=>static function(array $r): array {$r['dispatched_at']=Utils::now()+600;return $r;},
] as $label=>$mutate){
    $current=RecordStore::get('revocation_dispatch',$notice['id']);
    $bad=RecordStore::put('revocation_dispatch',$notice['id'],$mutate($current),(int)$current['version']);
    err(fn()=>RevocationDispatchService::notify($asset,'rights_expired',$projection['id']),
        'revocation_dispatch_invalid','Round 163 rejects '.$label);
    RecordStore::put('revocation_dispatch',$notice['id'],$current,(int)$bad['version']);
}
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];$GLOBALS['r163_block']=true;
$asset=$make('r163-supersession');
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){}
$projection=RecordStore::all('projection_revocation')[0];$notice=RecordStore::all('revocation_dispatch')[0];
$changed=RecordStore::get('asset',$asset['id']);$changed['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$changed,(int)$changed['version']);
$GLOBALS['r163_block']=false;
ok(RevocationDispatchService::reconcile()['superseded']===1,'Round 163 supersession baseline');
$current=RecordStore::get('revocation_dispatch',$notice['id']);
$future=$current;$future['superseded_at']=Utils::now()+600;
$future['supersession_history'][count($future['supersession_history'])-1]['at']=$future['superseded_at'];
$bad=RecordStore::put('revocation_dispatch',$notice['id'],$future,(int)$current['version']);
err(fn()=>RevocationDispatchService::notify($asset,'rights_expired',$projection['id']),
    'revocation_dispatch_invalid','Round 163 rejects internally consistent future supersession');
ok(RecordStore::get('revocation_dispatch',$notice['id'])['version']===$bad['version'],
    'Round 163 future supersession cannot mutate outbox');
RecordStore::put('revocation_dispatch',$notice['id'],$current,(int)$bad['version']);
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];$GLOBALS['r163_block']=true;
$asset=$make('r163-dispatch-cursor');
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException){}
$notice=RecordStore::all('revocation_dispatch')[0];$GLOBALS['r163_block']=false;
$badCursor=RecordStore::put('cron_cursor','revocation-dispatch',[
    'actor_id'=>0,'status'=>'active','last_id'=>['not-a-string'],
]);
err(fn()=>RevocationDispatchService::reconcile(),'revocation_cursor_invalid',
    'Round 163 rejects non-scalar dispatch cursor');
ok(RecordStore::get('revocation_dispatch',$notice['id'])['status']==='pending'
    &&count($GLOBALS['scm_actions'])===0,'Round 163 bad dispatch cursor causes no dispatch');
foreach([
    ['actor_id'=>1,'status'=>'active','last_id'=>str_repeat('0',64)],
    ['actor_id'=>0,'status'=>'inactive','last_id'=>str_repeat('0',64)],
    ['actor_id'=>0,'status'=>'active','last_id'=>'forged'],
] as $invalid){
    $cur=RecordStore::get('cron_cursor','revocation-dispatch');
    RecordStore::put('cron_cursor','revocation-dispatch',$invalid,(int)$cur['version']);
    err(fn()=>RevocationDispatchService::reconcile(),'revocation_cursor_invalid',
        'Round 163 rejects forged dispatch cursor identity/status/key');
}
$cur=RecordStore::get('cron_cursor','revocation-dispatch');
RecordStore::put('cron_cursor','revocation-dispatch',[
    'actor_id'=>0,'status'=>'active','last_id'=>str_repeat('0',64),
],(int)$cur['version']);
ok(RevocationDispatchService::reconcile()['dispatched']===1,
    'Round 163 valid repaired dispatch cursor resumes delivery');
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
$asset=$make('r163-rights-cursor');
$cursor=RecordStore::put('cron_cursor','rights-reconciliation',[
    'actor_id'=>0,'status'=>'active','last_key'=>['bad'],
]);
err(fn()=>RightsRevocationService::reconcileExpired(Utils::now(),1),
    'rights_cursor_invalid','Round 163 rejects non-scalar rights cursor');
ok(RecordStore::all('projection_revocation')===[],
    'Round 163 invalid rights cursor prevents revocation mutation');
foreach([
    ['actor_id'=>1,'status'=>'active','last_key'=>'0000000000000000000:r163-rights-cursor'],
    ['actor_id'=>0,'status'=>'inactive','last_key'=>'0000000000000000000:r163-rights-cursor'],
    ['actor_id'=>0,'status'=>'active','last_key'=>'invalid'],
] as $invalid){
    $cur=RecordStore::get('cron_cursor','rights-reconciliation');
    RecordStore::put('cron_cursor','rights-reconciliation',$invalid,(int)$cur['version']);
    err(fn()=>RightsRevocationService::reconcileExpired(Utils::now(),1),
        'rights_cursor_invalid','Round 163 rejects forged rights cursor');
}
$cur=RecordStore::get('cron_cursor','rights-reconciliation');
RecordStore::put('cron_cursor','rights-reconciliation',[
    'actor_id'=>0,'status'=>'active','last_key'=>'0000000000000000000:r163-rights-cursor',
],(int)$cur['version']);
ok(RightsRevocationService::reconcileExpired(Utils::now(),1)['revoked']===1,
    'Round 163 valid repaired rights cursor resumes reconciliation');
echo "REVIEW ROUND 163 TIMESTAMPS AND CURSORS: PASS\n";
