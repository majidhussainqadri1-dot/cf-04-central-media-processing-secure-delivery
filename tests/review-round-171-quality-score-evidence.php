<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,MediaOptimizationService,RecordStore};

$p=policy('video','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r171','asset_id'=>'r171',
    'owner_domain'=>'file17','owner_object'=>'message:r171','object_version'=>1,
    'processing_generation'=>1,'sha256'=>hash('sha256','r171'),'size'=>1024,
    'media_class'=>'video','mime'=>'video/mp4','privacy_class'=>'C3',
    'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r171',$a);
$d=['actor_id'=>0,'status'=>'validated','asset_id'=>'r171','kind'=>'video-low',
    'sha256'=>hash('sha256','r171-derivative'),'superseded_by'=>null,
    'lineage'=>['source_sha256'=>$a['sha256'],'processing_generation'=>1]];
RecordStore::put('derivative','r171-derivative',$d);
DomainRegistry::register('r171-deny','1.0.0',['authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$deny=$a;$deny['id']='r171-deny';$deny['asset_id']='r171-deny';$deny['owner_domain']='r171-deny';
RecordStore::put('asset','r171-deny',$deny);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_010',static function(array $ctx)use(&$calls): array{
    $calls++;return ['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>94.2]];
});
err(fn()=>MediaOptimizationService::qualityScore('r171-deny','r171-derivative',11,['vmaf_min'=>90]),
    'domain_authorization_denied','Round 171 owner denies quality adapter');
err(fn()=>MediaOptimizationService::qualityScore('r171','r171-derivative',11,['unexpected_min'=>90]),
    'quality_threshold_invalid','Round 171 unknown threshold denied');
ok($calls===0,'Round 171 denied requests never invoke adapter');
$bad=RecordStore::get('derivative','r171-derivative');$bad['lineage']['source_sha256']=hash('sha256','old');
RecordStore::put('derivative','r171-derivative',$bad,(int)$bad['version']);
err(fn()=>MediaOptimizationService::qualityScore('r171','r171-derivative',11,['vmaf_min'=>90]),
    'derivative_not_validated','Round 171 stale derivative lineage denied');
$bad=RecordStore::get('derivative','r171-derivative');$bad['lineage']['source_sha256']=$a['sha256'];
RecordStore::put('derivative','r171-derivative',$bad,(int)$bad['version']);

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_010',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>'94.2']]);
err(fn()=>MediaOptimizationService::qualityScore('r171','r171-derivative',11,['vmaf_min'=>90]),
    'quality_metric_invalid','Round 171 string metric rejected');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_010',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>80.0]]);
err(fn()=>MediaOptimizationService::qualityScore('r171','r171-derivative',11,['vmaf_min'=>90]),
    'derivative_quality_failed','Round 171 adapter cannot override threshold failure');
$failed=RecordStore::all('quality_score');
ok(count($failed)===1&&$failed[0]['status']==='failed',
    'Round 171 failed score evidence persists');

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_010',static function(array $ctx): array{
    $row=RecordStore::get('asset','r171');$row['object_version']=2;
    RecordStore::put('asset','r171',$row,(int)$row['version']);
    return ['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>94.2]];
});
err(fn()=>MediaOptimizationService::qualityScore('r171','r171-derivative',11,['vmaf_min'=>90]),
    'asset_state_changed','Round 171 source race denied');
$row=RecordStore::get('asset','r171');$row['object_version']=1;
RecordStore::put('asset','r171',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_010',static fn()=>[
    'ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>94.2,'ssim'=>0.98]]);
$good=MediaOptimizationService::qualityScore('r171','r171-derivative',11,['vmaf_min'=>90]);
ok($good['status']==='passed'&&$good['source_sha256']===$a['sha256']
    &&$good['rights_hash']===$p['rights']['policy_hash'],
    'Round 171 valid score is source and rights bound');
echo "REVIEW ROUND 171 QUALITY SCORE EVIDENCE: PASS\n";
