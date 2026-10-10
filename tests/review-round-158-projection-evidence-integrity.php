<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,RecordStore,RevocationDispatchService,RightsRevocationService,Utils};

RecordStore::resetMemory();
$rights=rights(['view']);$rights['expires_at']=Utils::now()-10;
$asset=RecordStore::put('asset','r158-asset',[
    'actor_id'=>11,'asset_id'=>'r158-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r158-owner','object_version'=>1,
    'policy_hash'=>hash('sha256','r158-policy'),'rights'=>$rights,
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r158-missing'),
]);
$first=RightsRevocationService::invalidate($asset['id'],'rights_expired');
$projection=$first['projection'];
$notice=array_values(array_filter(RecordStore::all('revocation_dispatch'),
    static fn(array $row): bool=>$row['asset_id']==='r158-asset'))[0]??null;
ok(is_array($notice)&&$notice['status']==='dispatched'
    &&$notice['projection_revocation_id']===$projection['id'],
    'Round 158 establishes a projection-bound dispatch baseline');
$variants=[
    'forged owner hash'=>static function(array $row): array {$row['owner_object_hash']=str_repeat('a',64);return $row;},
    'forged rights hash'=>static function(array $row): array {$row['rights_fingerprint']=str_repeat('b',64);return $row;},
    'forged actor'=>static function(array $row): array {$row['actor_id']=123;return $row;},
    'forged status'=>static function(array $row): array {$row['status']='complete';return $row;},
    'invalid creation timestamp'=>static function(array $row): array {$row['created_at']='invalid';return $row;},
    'forged asset hash'=>static function(array $row): array {$row['asset_ref_hash']=str_repeat('c',64);return $row;},
];
foreach($variants as $label=>$mutate){
    $current=RecordStore::get('projection_revocation',$projection['id']);
    $bad=RecordStore::put('projection_revocation',$projection['id'],
        $mutate($current),(int)$current['version']);
    $before=RecordStore::get('asset',$asset['id']);
    err(fn()=>RightsRevocationService::invalidate($asset['id'],'rights_expired'),
        'revocation_projection_invalid','Round 158 fails closed before replay on '.$label);
    ok(RecordStore::get('asset',$asset['id'])['version']===$before['version'],
        'Round 158 rejected projection causes no additional asset mutation');
    RecordStore::put('projection_revocation',$projection['id'],$projection,(int)$bad['version']);
}
$current=RecordStore::get('revocation_dispatch',$notice['id']);
unset($current['dispatched_at']);$current['status']='pending';
RecordStore::put('revocation_dispatch',$notice['id'],$current,(int)$current['version']);
$currentProjection=RecordStore::get('projection_revocation',$projection['id']);
$bad=RecordStore::put('projection_revocation',$projection['id'],
    array_replace($currentProjection,['rights_fingerprint'=>str_repeat('d',64)]),
    (int)$currentProjection['version']);
$blocked=RevocationDispatchService::reconcile();
ok($blocked['checked']===1&&$blocked['failed']===1&&$blocked['dispatched']===0
    &&RecordStore::get('revocation_dispatch',$notice['id'])['status']==='pending',
    'Round 158 pending recovery rejects mismatched projection evidence');
RecordStore::put('projection_revocation',$projection['id'],$projection,(int)$bad['version']);
$recovered=RevocationDispatchService::reconcile();
ok($recovered['dispatched']===1&&$recovered['failed']===0
    &&RecordStore::get('revocation_dispatch',$notice['id'])['status']==='dispatched',
    'Round 158 verified projection permits pending recovery');
$changed=RecordStore::get('asset',$asset['id']);
$changed['owner_object']='new-owner-without-version-bump';
RecordStore::put('asset',$asset['id'],$changed,(int)$changed['version']);
err(fn()=>RightsRevocationService::invalidate($asset['id'],'rights_expired'),
    'revocation_projection_invalid',
    'Round 158 owner identity change without version increment fails closed');
ok(Audit::verifyChain(),'Round 158 audit chain remains intact');
echo "REVIEW ROUND 158 PROJECTION EVIDENCE INTEGRITY: PASS\n";
