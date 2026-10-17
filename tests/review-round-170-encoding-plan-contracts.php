<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,MediaOptimizationService,RecordStore};
$p=policy('video','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r170','asset_id'=>'r170',
    'owner_domain'=>'file17','owner_object'=>'message:r170','object_version'=>1,
    'sha256'=>hash('sha256','r170'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r170',$a);
DomainRegistry::register('r170-deny','1.0.0',['authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r170-denied';$d['asset_id']='r170-denied';$d['owner_domain']='r170-deny';
RecordStore::put('asset','r170-denied',$d);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static function(array $ctx)use(&$calls): array{
    $calls++;return ['ok'=>true,'complexity'=>0.4,'renditions'=>[['kind'=>'video-low','bitrate'=>400000]]];
});
err(fn()=>MediaOptimizationService::encodingPlan('r170-denied',11),
    'domain_authorization_denied','Round 170 owner denial before adapter');
err(fn()=>MediaOptimizationService::encodingPlan('r170',11,['unknown'=>'x']),
    'encoding_constraints_invalid','Round 170 unknown constraint');
ok($calls===0,'Round 170 denied inputs did not call adapter');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static fn()=>[
    'ok'=>true,'complexity'=>'invalid','renditions'=>[['kind'=>'video-low','bitrate'=>400000]]]);
err(fn()=>MediaOptimizationService::encodingPlan('r170',11),
    'encoding_complexity_invalid','Round 170 coerced complexity');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static fn()=>[
    'ok'=>true,'complexity'=>0.5,'renditions'=>[['kind'=>'video-low','bitrate'=>'invalid']]]);
err(fn()=>MediaOptimizationService::encodingPlan('r170',11),
    'encoding_plan_invalid','Round 170 malformed bitrate');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static fn()=>[
    'ok'=>true,'complexity'=>0.5,'renditions'=>[['kind'=>'video-low','bitrate'=>3000000]]]);
err(fn()=>MediaOptimizationService::encodingPlan('r170',11,['max_bitrate'=>2000000]),
    'encoding_plan_invalid','Round 170 bitrate policy');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static function(array $ctx): array{
    $row=RecordStore::get('asset','r170');$row['object_version']=2;
    RecordStore::put('asset','r170',$row,(int)$row['version']);
    return ['ok'=>true,'complexity'=>0.5,'renditions'=>[['kind'=>'video-low','bitrate'=>400000]]];
});
err(fn()=>MediaOptimizationService::encodingPlan('r170',11),
    'asset_state_changed','Round 170 concurrent source change');
ok(RecordStore::all('encoding_plan')===[],'Round 170 rejected plans not persisted');
$row=RecordStore::get('asset','r170');$row['object_version']=1;
RecordStore::put('asset','r170',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_009',static fn()=>[
    'ok'=>true,'complexity'=>0.42,'renditions'=>[['kind'=>'video-low','bitrate'=>400000],
        ['kind'=>'video-high','bitrate'=>1800000]]]);
$plan=MediaOptimizationService::encodingPlan('r170',11,['max_bitrate'=>2000000]);
ok($plan['status']==='approved'&&count($plan['renditions'])===2
    &&$plan['owner_object_version']===1&&$plan['rights_hash']===$p['rights']['policy_hash'],
    'Round 170 valid source-bound encoding plan');
echo "REVIEW ROUND 170 ENCODING PLAN CONTRACTS: PASS\n";
