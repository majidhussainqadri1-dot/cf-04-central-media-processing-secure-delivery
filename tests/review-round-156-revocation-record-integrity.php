<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,DeletionService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','r156-asset',[
    'actor_id'=>11,'asset_id'=>'r156-asset','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'r156-object','object_version'=>1,
    'policy_hash'=>hash('sha256','r156-policy'),
    'rights'=>['policy_hash'=>hash('sha256','r156-rights')],
    'storage'=>['provider_id'=>'source-private'],
    'object_key'=>hash('sha256','r156-missing'),
]);
$d=DeletionService::request($asset['id'],11,'user-request');
DeletionService::process($d['id']);
$id=hash('sha256',$d['id'].'|completion-revocation');
$good=RecordStore::get('revocation_notice',$id);
ok($good['status']==='dispatched'&&$good['actor_id']===$d['actor_id'],
    'Round 156 valid outbox binds actor and dispatch evidence');
$variants=[
    'forged actor'=>static function(array $row): array {$row['actor_id']=999;return $row;},
    'missing created timestamp'=>static function(array $row): array {unset($row['created_at']);return $row;},
    'invalid created timestamp'=>static function(array $row): array {$row['created_at']='not-a-time';return $row;},
    'dispatched with false delivered timestamp'=>static function(array $row): array {$row['delivered_at']=time();return $row;},
    'pending with prior dispatch timestamp'=>static function(array $row): array {$row['status']='pending';return $row;},
    'legacy delivered with dispatch timestamp'=>static function(array $row): array {$row['status']='delivered';$row['delivered_at']=time();return $row;},
];
foreach($variants as $label=>$mutate){
    $current=RecordStore::get('revocation_notice',$id);
    $bad=RecordStore::put('revocation_notice',$id,$mutate($current),(int)$current['version']);
    err(fn()=>DeletionService::process($d['id']),'revocation_notice_invalid',
        'Round 156 rejects '.$label);
    $restored=RecordStore::put('revocation_notice',$id,$good,(int)$bad['version']);
}
ok(DeletionService::process($d['id'])['status']==='completed'
    &&Audit::verifyChain(),
    'Round 156 valid outbox still replays without invalidating audit');
echo "REVIEW ROUND 156 REVOCATION RECORD INTEGRITY: PASS\n";
