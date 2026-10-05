<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Policy,SafetySignalService,FutureAdapterRegistry,PerceptualMediaService,MediaOptimizationService,DeliveryResilienceService,WorkspaceUploadService,RecordStore};

$raw=[
'policy_id'=>'r133','policy_version'=>1,'owner_domain'=>'file17','purpose'=>'verified-user-transfer','lawful_basis'=>'contract','revocation_hook'=>'owner-contract',
'privacy_class'=>'C3','media_class'=>'document','max_size_bytes'=>1073741824,'max_part_size_bytes'=>8388608,'max_upload_parts'=>20000,
'allowed_mime_types'=>['application/pdf'],'allowed_extensions'=>['pdf'],'required_scans'=>['hash','magic','mime','malware','archive','polyglot','decompression_bomb','metadata'],
'derivative_set'=>['preview'],'retention'=>['class'=>'private-standard','source_seconds'=>86400,'derivative_seconds'=>86400,'temporary_seconds'=>3600,'backup_expiry_seconds'=>2592000],
'rights'=>rights(),'delivery'=>['modes'=>['same_origin_proxy'],'grant_ttl_seconds'=>300,'allow_ranges'=>true,'max_range_bytes'=>8388608,'allow_download'=>true,'public_cdn'=>false],
'safety'=>['require_reviewer_for_low_confidence'=>true,'minimum_confidence'=>NAN]
];
err(fn()=>Policy::normalize($raw,true),'safety_confidence_invalid','Round 133 policy minimum confidence rejects NaN');

add_filter('scm_technical_safety_signal',static fn()=>['confidence'=>NAN,'signals'=>[],'requires_review'=>false,'model_id'=>'nan','model_version'=>'1']);
$stream=stream_of('safe');
try{err(fn()=>SafetySignalService::evaluate($stream,['asset_id'=>'r133-signal','policy'=>policy()]),'safety_signal_invalid','Round 133 scanner confidence rejects NaN');}finally{fclose($stream);}

$p=policy('video','C3',['view','download','transform','reprocess']);
$asset=['actor_id'=>11,'asset_id'=>'r133-a','id'=>'r133-a','status'=>'ready','sha256'=>hash('sha256','r133'),'size'=>4,'media_class'=>'video','mime'=>'video/mp4','owner_domain'=>'file17','owner_object'=>'message:r133','object_version'=>1,'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r133-a',$asset);
$other=$asset;$other['asset_id']=$other['id']='r133-b';$other['owner_object']='message:r133-b';RecordStore::put('asset','r133-b',$other);
RecordStore::put('perceptual_fingerprint','r133-fa',['actor_id'=>11,'status'=>'active','asset_id'=>'r133-a','algorithm'=>'phash','algorithm_version'=>'1','fingerprint'=>'a']);
RecordStore::put('perceptual_fingerprint','r133-fb',['actor_id'=>11,'status'=>'active','asset_id'=>'r133-b','algorithm'=>'phash','algorithm_version'=>'1','fingerprint'=>'b']);
FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_004',static fn()=>['ok'=>true,'similarity'=>0.9]);
err(fn()=>PerceptualMediaService::nearDuplicates('r133-a',11,NAN),'perceptual_threshold_invalid','Round 133 near-duplicate threshold rejects NaN');

FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_009',static fn()=>['ok'=>true,'complexity'=>NAN,'renditions'=>[['kind'=>'video-low']]]);
err(fn()=>MediaOptimizationService::encodingPlan('r133-a',11,[]),'encoding_complexity_invalid','Round 133 encoding complexity rejects NaN');
err(fn()=>DeliveryResilienceService::adaptiveUpload(['rtt_ms'=>10,'mbps'=>NAN,'unstable'=>false],['max_part_size_bytes'=>8388608]),'network_profile_invalid','Round 133 adaptive upload rejects NaN throughput');
err(fn()=>WorkspaceUploadService::normalize('audio',['trim_start'=>NAN,'trim_end'=>10],11),'audio_trim_invalid','Round 133 audio trim rejects NaN');

echo "REVIEW ROUND 133 RUNTIME NUMERIC FAIL-CLOSED: PASS\n";
