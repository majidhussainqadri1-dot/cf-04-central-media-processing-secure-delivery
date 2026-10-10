<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($GLOBALS['r157_fail_next']??0)>0){
        $GLOBALS['r157_fail_next']--;
        throw new \RuntimeException('Injected nonterminal revocation callback failure');
    }
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore,RevocationDispatchService,RightsRevocationService,Utils};
RecordStore::resetMemory();
$make=static function(string $id): array {
    $r=rights(['view']);$r['expires_at']=Utils::now()-10;
    return RecordStore::put('asset',$id,[
        'actor_id'=>11,'asset_id'=>$id,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>$id.'-owner','object_version'=>1,
        'policy_hash'=>hash('sha256',$id.'-policy'),'rights'=>$r,
        'storage'=>['provider_id'=>'source-private'],
        'object_key'=>hash('sha256',$id.'-absent'),
    ]);
};
$events=static function(): array {
    return array_values(array_filter($GLOBALS['scm_actions'],
        static fn(array $a): bool=>$a['tag']==='scm.media.revoked'));
};
$asset=$make('r157-rights');
$first=RightsRevocationService::invalidate($asset['id'],'rights_expired',Utils::now());
$projectionId=$first['projection']['id'];
$noticeId=hash('sha256',Utils::canonicalJson([
    'asset_id'=>$asset['id'],'owner_domain'=>$asset['owner_domain'],
    'owner_object_hash'=>Utils::hashReference($asset['owner_object']),
    'object_version'=>1,'reason'=>'rights_expired',
    'rights_fingerprint'=>hash('sha256',Utils::canonicalJson($asset['rights'])),
    'projection_revocation_id'=>$projectionId,
]));
$dispatchId=hash('sha256','nonterminal-revocation|'.$noticeId);
$notice=RecordStore::get('revocation_dispatch',$dispatchId);
ok($notice!==null&&$notice['status']==='dispatched'&&count($events())===1,
    'Round 157 rights invalidation emits one persisted local revocation');
$again=RightsRevocationService::invalidate($asset['id'],'rights_expired',Utils::now()+30);
ok($again['projection']['id']===$projectionId&&count($events())===1,
    'Round 157 retries retain projection and single logical event');
$fresh=RecordStore::get('asset',$asset['id']);$fresh['rights']['expires_at']=Utils::now()-20;
RecordStore::put('asset',$asset['id'],$fresh,(int)$fresh['version']);
$changed=RightsRevocationService::invalidate($asset['id'],'rights_expired',Utils::now());
ok($changed['projection']['id']!==$projectionId&&count($events())===2,
    'Round 157 genuinely changed rights create a new revocation identity');
$failedAsset=$make('r157-failure');
$GLOBALS['r157_fail_next']=1;
$thrown=false;
try{RightsRevocationService::invalidate($failedAsset['id'],'rights_expired');}
catch(\RuntimeException $e){$thrown=str_contains($e->getMessage(),'Injected nonterminal');}
ok($thrown,'Round 157 hook failure is surfaced after durable pending outbox');
$pending=array_values(array_filter(RecordStore::all('revocation_dispatch'),static fn(array $row): bool=>
    $row['asset_id']==='r157-failure'&&$row['status']==='pending'));
ok(count($pending)===1,'Round 157 failed hook leaves one pending outbox');
$recovery=RevocationDispatchService::reconcile();
ok($recovery['dispatched']===1&&$recovery['failed']===0
    &&RecordStore::get('revocation_dispatch',$pending[0]['id'])['status']==='dispatched',
    'Round 157 pending outbox is recoverable');
RightsRevocationService::invalidate($failedAsset['id'],'rights_expired');
ok(count($events())===3,'Round 157 post-recovery replay does not duplicate hook');
$direct=$make('r157-direct');
DeletionService::expireDerivatives($direct['id'],'retention-expiry');
DeletionService::expireDerivatives($direct['id'],'retention-expiry');
ok(count($events())===4,'Round 157 direct derivative expiry emits one logical hook');
$good=RecordStore::get('revocation_dispatch',$pending[0]['id']);
$bad=RecordStore::put('revocation_dispatch',$good['id'],
    array_replace($good,['event_id'=>'forged']),(int)$good['version']);
err(fn()=>RevocationDispatchService::notify($failedAsset,'rights_expired',
    hash('sha256',Utils::canonicalJson([
        'asset_id'=>$failedAsset['id'],'reason'=>'rights_expired',
        'rights_fingerprint'=>hash('sha256',Utils::canonicalJson($failedAsset['rights'])),
        'object_version'=>1,
    ]))),'revocation_dispatch_invalid',
    'Round 157 tampered event identity fails closed');
ok(Audit::verifyChain(),'Round 157 audit chain remains valid');
echo "REVIEW ROUND 157 NONTERMINAL REVOCATION RECOVERY: PASS\n";
