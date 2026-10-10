<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'){
        $mode=$GLOBALS['r159_mutation']??null;
        if($mode==='throw')throw new \RuntimeException('Injected revocation hook failure');
        if($mode!==null){
            $id=(string)($args[0]['asset_id']??'');
            $asset=\Sabri\CentralMedia\RecordStore::get('asset',$id);
            if($mode==='missing'){
                \Sabri\CentralMedia\RecordStore::delete('asset',$id,(int)$asset['version']);
            }elseif(is_array($asset)){
                if($mode==='rights')$asset['rights']['expires_at']--;
                if($mode==='owner')$asset['owner_object']='new-owner-without-version-bump';
                if($mode==='terminal')$asset['status']='deleted';
                if($mode==='policy')$asset['policy_hash']=hash('sha256','new-policy');
                \Sabri\CentralMedia\RecordStore::put('asset',$id,$asset,(int)$asset['version']);
            }
        }
    }
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RevocationDispatchService,RightsRevocationService,Utils};
$make=static function(string $id): array {
    $r=rights(['view']);$r['expires_at']=Utils::now()-10;
    return RecordStore::put('asset',$id,[
        'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>$id.'-owner','object_version'=>1,
        'policy_hash'=>hash('sha256',$id.'-policy'),'rights'=>$r,
        'storage'=>['provider_id'=>'source-private'],'object_key'=>hash('sha256',$id.'-absent'),
    ]);
};
foreach(['rights'=>'revocation_rights_stale','owner'=>'revocation_owner_stale',
    'terminal'=>'revocation_asset_terminal','missing'=>'revocation_asset_stale',
    'policy'=>'revocation_policy_stale'] as $mode=>$error){
    RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
    $asset=$make('r159-'.$mode);$GLOBALS['r159_mutation']=$mode;
    err(fn()=>RightsRevocationService::invalidate($asset['id'],'rights_expired'),$error,
        'Round 159 rejects changed '.$mode.' after synchronous dispatch');
    $fresh=RecordStore::get('asset',$asset['id']);
    ok($fresh===null||!isset($fresh['rights_reconciled_hash']),
        'Round 159 never marks changed '.$mode.' reconciled');
    $events=array_values(array_filter($GLOBALS['scm_actions'],
        static fn(array $a): bool=>$a['tag']==='scm.media.revoked'));
    ok(count($events)===1,'Round 159 records local dispatch without false reconciliation');
}
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
$asset=$make('r159-pending');$GLOBALS['r159_mutation']='throw';
$thrown=false;
try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
catch(\RuntimeException $e){$thrown=str_contains($e->getMessage(),'Injected');}
ok($thrown,'Round 159 injected hook failure leaves durable pending notice');
$pending=array_values(array_filter(RecordStore::all('revocation_dispatch'),
    static fn(array $row): bool=>$row['asset_id']==='r159-pending'&&$row['status']==='pending'));
ok(count($pending)===1,'Round 159 pending outbox is retained');
$fresh=RecordStore::get('asset',$asset['id']);$fresh['rights']['expires_at']--;
RecordStore::put('asset',$asset['id'],$fresh,(int)$fresh['version']);
$GLOBALS['r159_mutation']=null;
$recovery=RevocationDispatchService::reconcile();
ok($recovery['checked']===1&&$recovery['failed']===1&&$recovery['dispatched']===0
    &&RecordStore::get('revocation_dispatch',$pending[0]['id'])['status']==='pending',
    'Round 159 stale pending rights cannot emit an obsolete revocation event');
$events=array_values(array_filter($GLOBALS['scm_actions'],
    static fn(array $a): bool=>$a['tag']==='scm.media.revoked'));
ok(count($events)===0,'Round 159 stale pending notice never reaches the hook');
RecordStore::resetMemory();$GLOBALS['scm_actions']=[];
$asset=$make('r159-clean');
RightsRevocationService::invalidate($asset['id'],'rights_expired');
$fresh=RecordStore::get('asset',$asset['id']);
ok($fresh['rights_reconciled_hash']===hash('sha256',Utils::canonicalJson($asset['rights'])),
    'Round 159 clean reconciliation binds marker to original verified rights');
echo "REVIEW ROUND 159 CONCURRENT REVOCATION: PASS\n";
