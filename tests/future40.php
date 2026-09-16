<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{
    ContentSafetyUpgradeService,DeliveryResilienceService,DisasterCostRoutingService,Future40Registry,FutureAdapterRegistry,
    MediaOptimizationService,OperationsFutureService,PerceptualMediaService,ProvenanceCredentialService,RecordStore,
    ResidencyCryptoService,RichMediaMetadataService,SensitiveDataProtectionService,ProcessingService,UploadService,Utils
};

function f40_asset(string $owner,string $privacy='C3',string $media='document'): array {
    $bytes=$media==='video'?"....ftypisom future40-video....":($media==='audio'?"ID3future40-audio":"%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    $p=policy($media,$privacy,['view','download','extract_text','ocr','transform','reprocess']);
    $mime=$media==='video'?'video/mp4':($media==='audio'?'audio/mpeg':'application/pdf');
    $name=$media==='video'?'future.mp4':($media==='audio'?'future.mp3':'future.pdf');
    $m=['name'=>$name,'mime'=>$mime,'size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'owner_object'=>$owner];
    $u=UploadService::create(11,$m,$p,'f40-create-'.hash('sha256',$owner.'|'.$privacy.'|'.$media));
    $s=stream_of($bytes);UploadService::putPart($u['id'],11,1,$s,hash('sha256',$bytes),$u['upload_credential']);fclose($s);
    $a=UploadService::complete($u['id'],11,$u['upload_credential'],'f40-complete-'.hash('sha256',$owner.'|'.$privacy.'|'.$media));
    ProcessingService::start($a['id']);return ProcessingService::execute($a['id'],'future40-worker');
}

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static fn(array $c)=>['ok'=>true,'format'=>'c2pa-compatible-envelope','credential_id'=>'cred-'.substr(hash('sha256',Utils::canonicalJson($c)),0,16)]);
FutureAdapterRegistry::register('future_003',static fn(array $c)=>['ok'=>true,'fingerprint'=>'phash:'.substr((string)$c['asset']['sha256'],0,24)]);
FutureAdapterRegistry::register('future_004',static fn(array $c)=>['ok'=>true,'similarity'=>$c['left']===$c['right']?1.0:0.75]);
FutureAdapterRegistry::register('future_006',static fn(array $c)=>['ok'=>true,'safe_sha256'=>hash('sha256','safe|'.$c['asset_id']),'removed_features'=>['javascript','embedded-action']]);
FutureAdapterRegistry::register('future_009',static fn(array $c)=>['ok'=>true,'complexity'=>0.42,'renditions'=>[['kind'=>'video-low','bitrate'=>400000],['kind'=>'video-high','bitrate'=>1800000]]]);
FutureAdapterRegistry::register('future_010',static fn(array $c)=>['ok'=>true,'passed'=>true,'metrics'=>['vmaf'=>94.2,'ssim'=>0.98]]);
FutureAdapterRegistry::register('future_013',static fn(array $c)=>['ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0,'peak_db'=>-1.2],'issues'=>[]]);
FutureAdapterRegistry::register('future_014',static fn(array $c)=>['ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:storyboard','selection'=>['method'=>'scene-change']]);
FutureAdapterRegistry::register('future_021',static fn(array $c)=>['ok'=>true,'signals'=>[['type'=>'email','confidence'=>0.99,'region'=>[1,2,3,4]]]]);
FutureAdapterRegistry::register('future_022',static fn(array $c)=>['ok'=>true,'redacted_sha256'=>hash('sha256','redacted|'.$c['asset_id'])]);

$m=Future40Registry::manifest();
ok($m['count']===40&&isset($m['requirements']['CF04-FUT-001'],$m['requirements']['CF04-FUT-040']),'FUTURE40 registry has 40 governed capabilities');

$private=f40_asset('message:future40-private','C3','document');
$public=f40_asset('message:future40-public','C0','document');
$video=f40_asset('message:future40-video','C3','video');

$p=ProvenanceCredentialService::record($private['id'],11,['origin'=>'camera_upload','synthetic_state'=>'ai_edited','tool'=>'approved-editor','tool_version'=>'1.0','transformations'=>['crop','redaction']]);
ok(($p['payload']['synthetic_state']??'')==='ai_edited'&&($p['credential_hash']??'')!=='','CF04-FUT-001/002 provenance and synthetic declaration');

PerceptualMediaService::fingerprint($private['id'],11,'phash','1');
$clone=$private;$clone['asset_id']='future40-clone';$clone['owner_object']='message:future40-clone';$clone['id']='future40-clone';unset($clone['version'],$clone['record_type']);$clone=RecordStore::put('asset','future40-clone',$clone);
PerceptualMediaService::fingerprint($clone['id'],11,'phash','1');
$near=PerceptualMediaService::nearDuplicates($private['id'],11,0.9);ok($near!==[]&&$near[0]['similarity']===1.0,'CF04-FUT-003/004 perceptual fingerprint and safe near-duplicate detection');
$dedupe=PerceptualMediaService::dedupeDecision($private['id'],$clone['id'],11);ok($dedupe['status']==='eligible','CF04-FUT-005 same-envelope physical dedupe eligibility');

$cdr=ContentSafetyUpgradeService::disarm($private['id'],11,'document-safe-v1');ok($cdr['status']==='validated','CF04-FUT-006 content disarm/reconstruction');
$rescan=ContentSafetyUpgradeService::scheduleRescan('scanner-signature-update',['media_class'=>'document'],100);ok($rescan['queued']>=2,'CF04-FUT-007 automatic re-scan scheduling');
ContentSafetyUpgradeService::killSwitch('application/x-danger',11,true,'critical parser CVE');err(fn()=>ContentSafetyUpgradeService::assertAllowed('application/x-danger'),'format_emergency_blocked','CF04-FUT-008 emergency format kill switch');ContentSafetyUpgradeService::killSwitch('application/x-danger',11,false,'patched');

$plan=MediaOptimizationService::encodingPlan($video['id'],11,['max_bitrate'=>2000000]);ok(count($plan['renditions'])===2,'CF04-FUT-009 content-aware encoding plan');
$derivatives=array_values(array_filter(RecordStore::all('derivative',0,null,10000),fn($r)=>($r['asset_id']??'')===$video['id']));
$score=MediaOptimizationService::qualityScore($video['id'],$derivatives[0]['id'],11,['vmaf_min'=>90]);ok($score['status']==='passed','CF04-FUT-010 objective quality score');
ok(MediaOptimizationService::negotiateCodec(['h264','av1'],['av1','h264'])==='av1','CF04-FUT-011 codec capability negotiation');
$color=MediaOptimizationService::colorPolicy($video['id'],11,['source_space'=>'bt2020','target_space'=>'srgb','hdr_mode'=>'tone_map','icc_hash'=>hash('sha256','icc'),'tone_map'=>'approved-v1']);ok($color['hdr_mode']==='tone_map','CF04-FUT-012 color/HDR lineage policy');
$audio=MediaOptimizationService::audioQc($video['id'],11,['lufs_target'=>-16]);ok($audio['status']==='passed','CF04-FUT-013 audio technical QC');

$preview=RichMediaMetadataService::smartPreview($video['id'],11,['poster'=>'scene-change']);ok($preview['status']==='active','CF04-FUT-014 smart poster/storyboard');
$chap=RichMediaMetadataService::chapters($video['id'],11,[['start_ms'=>0,'title'=>'Intro'],['start_ms'=>5000,'title'=>'Main']]);ok($chap['status']==='active','CF04-FUT-015 chapter/timecode manifest');
$caption=RichMediaMetadataService::captionTrack($video['id'],11,['locale'=>'ur-PK','kind'=>'sdh','content_hash'=>hash('sha256','caption'),'source'=>'human']);ok($caption['status']==='active','CF04-FUT-016 rich SDH/forced caption tracks');
$ad=RichMediaMetadataService::audioDescription($video['id'],11,['locale'=>'ur-PK','track_ref'=>'asset:audio-description','content_hash'=>hash('sha256','audio-description')]);ok($ad['status']==='active','CF04-FUT-017 audio-description track');
$sl=RichMediaMetadataService::signLanguage($video['id'],11,['locale'=>'pks','track_ref'=>'asset:sign-language','synchronization_hash'=>hash('sha256','sync')]);ok($sl['status']==='active','CF04-FUT-018 sign-language synchronized track');
$ocr=RichMediaMetadataService::ocrMap($private['id'],11,[['page'=>1,'items'=>[['text'=>'Sabri','confidence'=>0.98,'box'=>[0.1,0.1,0.2,0.05]]]]]);ok($ocr['status']==='active','CF04-FUT-019 OCR confidence/coordinate map');
$am=RichMediaMetadataService::accessibilityManifest($video['id']);ok(isset($am['manifest_hash'])&&$am['tracks']['rich_caption']!==[],'CF04-FUT-020 accessibility manifest');

$sensitive=SensitiveDataProtectionService::detect($public['id'],11,['email','phone','gps']);ok($sensitive['status']==='review_required','CF04-FUT-021 sensitive-data detection');
$redacted=SensitiveDataProtectionService::redact($public['id'],11,[['type'=>'email','box'=>[1,2,3,4]]],'public privacy cleanup');ok($redacted['status']==='approved','CF04-FUT-022 governed redaction derivative');

$route=DeliveryResilienceService::routeCdn($public['id'],[['id'=>'cdn-a','approved'=>true,'healthy'=>false,'priority'=>1,'region'=>'PK'],['id'=>'cdn-b','approved'=>true,'healthy'=>true,'priority'=>2,'region'=>'SG']]);ok($route['provider']==='cdn-b','CF04-FUT-023 multi-CDN failover');
$shield=DeliveryResilienceService::originShield($public['id'],['ttl_seconds'=>600,'request_collapsing'=>true]);ok($shield['enabled']===true&&$shield['request_collapsing']===true,'CF04-FUT-024 origin shield/cache protection');
$edge=DeliveryResilienceService::edgeAuthorization($private['id'],['ttl_seconds'=>120]);ok($edge['canonical_owner']==='file17'&&$edge['authorization_refresh_required']===true,'CF04-FUT-025 edge authorization without ownership transfer');
$upload=DeliveryResilienceService::adaptiveUpload(['rtt_ms'=>550,'mbps'=>1.5,'unstable'=>true],['max_part_size_bytes'=>8388608]);ok($upload['parallel_parts']===1&&$upload['checkpoint_each_part']===true,'CF04-FUT-026 network-adaptive upload');
$offline=DeliveryResilienceService::offlineGrant($private['id'],11,3600);ok($offline['encrypted']===true&&$offline['token']!=='','CF04-FUT-027 encrypted bounded offline grant');
$resume=DeliveryResilienceService::resumeDownload($private['id'],11,['offset'=>10,'device_id'=>'device-a']);ok($resume['reauthorize']===true,'CF04-FUT-028 cross-session/device resumable download state');

$residency=ResidencyCryptoService::residency($private['id'],11,['TEST-A','TEST-B']);ok(count($residency['allowed_regions'])===2,'CF04-FUT-029 regional residency pinning');
$lock=ResidencyCryptoService::objectLock($private['id'],11,time()+86400,'legal hold');ok($lock['status']==='locked','CF04-FUT-030 WORM/object-lock evidence');
$key=ResidencyCryptoService::keyEnvelope($private['id'],11,'test-v1');ok($key['rotatable']===true,'CF04-FUT-031 per-asset envelope encryption key reference');
$crypto=ResidencyCryptoService::cryptoAgility(11,['approved_algorithms'=>['aes-256-gcm','future-approved'],'minimum_key_bits'=>256,'migration_window_seconds'=>86400]);ok(in_array('aes-256-gcm',$crypto['approved_algorithms'],true),'CF04-FUT-032 crypto-agility policy');

$dr=DisasterCostRoutingService::disasterPlan($private['id'],11,['primary_region'=>'TEST-A','secondary_region'=>'TEST-B','rpo_seconds'=>300,'rto_seconds'=>900]);ok($dr['failback_required']===true,'CF04-FUT-033 multi-region disaster recovery plan');
$tier=DisasterCostRoutingService::optimizeTier($private['id'],['accesses_30d'=>0,'age_days'=>365]);ok($tier['recommended_tier']==='archive_locked','CF04-FUT-034 intelligent policy-aware storage tier');
$cost=DisasterCostRoutingService::costEstimate($video['id'],['storage_gb_month'=>0.02,'transcode_minute'=>0.01,'egress_gb'=>0.05,'currency'=>'USD'],['minutes'=>10,'egress_gb'=>1]);ok($cost['estimated_cost']>0,'CF04-FUT-035 pre-processing cost estimate');
$provider=DisasterCostRoutingService::autoRoute([['id'=>'p1','approved'=>true,'healthy'=>true,'capabilities'=>['video','av1'],'region'=>'TEST-A','cost_score'=>2,'latency_score'=>1],['id'=>'p2','approved'=>true,'healthy'=>true,'capabilities'=>['video','av1'],'region'=>'TEST-B','cost_score'=>1,'latency_score'=>1]],['capabilities'=>['video','av1'],'regions'=>['TEST-A','TEST-B']]);ok($provider['provider']==='p2','CF04-FUT-036 approved-provider auto-routing');

$qoe=OperationsFutureService::qoe('media_playback_startup_ms',120,['domain'=>'file17','media_class'=>'video','privacy_class'=>'C3','status'=>'ready']);ok($qoe!==[],'CF04-FUT-037 privacy-minimal QoE telemetry');
$chaos=OperationsFutureService::chaos(11,'scanner_down',['scope'=>'future40-test']);ok($chaos['status']==='scheduled','CF04-FUT-038 staging/test-only chaos exercise');
$bundle=OperationsFutureService::migrationBundle([$private['id'],$public['id']],11,'provider-next');ok(OperationsFutureService::verifyMigrationBundle($bundle['bundle'])===true,'CF04-FUT-039 signed migration/export bundle');
$sdk=OperationsFutureService::sdkManifest();ok($sdk['sdk_contract']==='CF04-MEDIA-SDK-1'&&count($sdk['future40'])===40,'CF04-FUT-040 versioned SDK/contract kit');

echo "CF-04 FUTURE-40: PASS\n";
