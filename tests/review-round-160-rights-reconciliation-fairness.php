<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($args[0]['asset_id']??'')===($GLOBALS['r160_fail_id']??null))
        throw new \RuntimeException('Injected persistent owner hook failure');
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Plugin,RecordStore,RightsRevocationService,Utils};
RecordStore::resetMemory();
$make=static function(string $id): array {
    $r=rights(['view']);$r['expires_at']=Utils::now()-10;
    return RecordStore::put('asset',$id,[
        'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>$id.'-owner','object_version'=>1,
        'policy_hash'=>hash('sha256',$id.'-policy'),'rights'=>$r,
        'storage'=>['provider_id'=>'source-private'],'object_key'=>hash('sha256',$id.'-absent'),
    ]);
};
$make('r160-a');$make('r160-b');$make('r160-c');
$GLOBALS['r160_fail_id']='r160-a';
$first=RightsRevocationService::reconcileExpired(Utils::now(),1);
ok($first['checked']===1&&$first['failed']===1&&$first['revoked']===0,
    'Round 160 first repeatedly failing record is accounted for');
$second=RightsRevocationService::reconcileExpired(Utils::now(),1);
$third=RightsRevocationService::reconcileExpired(Utils::now(),1);
ok($second['revoked']===1&&$third['revoked']===1,
    'Round 160 durable cursor advances past failed record to later rights expiries');
ok(isset(RecordStore::get('asset','r160-b')['rights_reconciled_hash'])
    &&isset(RecordStore::get('asset','r160-c')['rights_reconciled_hash']),
    'Round 160 later expired rights receive persisted reconciliation');
$cursor=RecordStore::get('cron_cursor','rights-reconciliation');
ok(is_array($cursor)&&is_string($cursor['last_key']??null)&&$cursor['last_key']!=='',
    'Round 160 reconciliation cursor is durable');
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];$GLOBALS['r160_fail_id']=null;
$make('r160-cron');
Plugin::cronRetention();
ok(isset(RecordStore::get('asset','r160-cron')['rights_reconciled_hash']),
    'Round 160 registered retention cron performs automatic rights expiry reconciliation');
echo "REVIEW ROUND 160 RIGHTS RECONCILIATION FAIRNESS: PASS\n";
