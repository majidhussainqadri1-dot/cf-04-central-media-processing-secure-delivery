<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{FutureAdapterRegistry,MediaOptimizationService,RecordStore};

$p=policy('video','C3',['view','download','transform','reprocess']);
$asset=['actor_id'=>11,'asset_id'=>'r134-a','id'=>'r134-a','status'=>'ready','sha256'=>hash('sha256','r134'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4','owner_domain'=>'file17','owner_object'=>'message:r134','object_version'=>1,'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r134-a',$asset);
RecordStore::put('derivative','r134-d',['actor_id'=>0,'asset_id'=>'r134-a','derivative_id'=>'r134-d','sha256'=>hash('sha256','r134-d'),'kind'=>'video-low','status'=>'ready']);

FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_010',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>94.0]]);
err(fn()=>MediaOptimizationService::qualityScore('r134-a','r134-d',11,['vmaf_min'=>NAN]),'quality_threshold_invalid','Round 134 quality threshold rejects NaN');
FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_010',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>NAN]]);
err(fn()=>MediaOptimizationService::qualityScore('r134-a','r134-d',11,['vmaf_min'=>90]),'quality_metric_invalid','Round 134 quality adapter evidence rejects NaN metric');

FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_013',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0]]);
err(fn()=>MediaOptimizationService::audioQc('r134-a',11,['lufs_target'=>INF]),'audio_qc_threshold_invalid','Round 134 audio-QC threshold rejects infinity');
FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_013',static fn()=>['ok'=>true,'passed'=>true,'metrics'=>['lufs'=>INF]]);
err(fn()=>MediaOptimizationService::audioQc('r134-a',11,['lufs_target'=>-16]),'audio_qc_metric_invalid','Round 134 audio-QC adapter evidence rejects infinity metric');

echo "REVIEW ROUND 134 ADAPTER QUALITY EVIDENCE: PASS\n";
