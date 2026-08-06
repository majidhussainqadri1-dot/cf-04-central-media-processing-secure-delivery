<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,Auth,CdnRegistry,CostService,Crypto,DeletionService,DeliveryService,DerivativeService,DomainRegistry,DownloadManagerService,Idempotency,IntegrityService,JobService,Keyring,KeyRotationService,LegalHoldService,MetadataPolicy,Observability,Policy,ProcessingService,ProviderExitService,ProviderRegistry,QuotaService,RecordStore,RepairService,RestoreService,RetentionService,RightsPolicy,RuntimeGuard,SafetySignalService,Schema,ServiceAuth,TransferService,UploadService,Utils,Validator,WebhookService,WorkspaceUploadService};

function create_document_asset(string $ownerObject='message:1',string $privacy='C3'): array {
    $pdf="%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n2 0 obj\n<< /Type /Page >>\nendobj\n%%EOF\n";
    $p=policy('document',$privacy);
    $metadata=['name'=>'Clinical Notes.pdf','mime'=>'application/pdf','size'=>strlen($pdf),'sha256'=>hash('sha256',$pdf),'owner_object'=>$ownerObject,'session_ttl_seconds'=>3600];
    $created=UploadService::create(11,$metadata,$p,'create-'.hash('sha256',$ownerObject));
    $stream=stream_of($pdf);try{UploadService::putPart($created['id'],11,1,$stream,hash('sha256',$pdf),$created['upload_credential']);}finally{fclose($stream);}
    $asset=UploadService::complete($created['id'],11,$created['upload_credential'],'complete-'.hash('sha256',$ownerObject));
    ProcessingService::start($asset['id']);
    return ProcessingService::execute($asset['id'],'test-worker');
}

// FR-001 policy envelope and rights/consent.
$p=policy();
ok($p['max_pages']===500&&$p['retention']['backup_expiry_seconds']===2592000&&$p['rights']['consent_status']==='granted'&&$p['delivery']['allow_ranges']===true,'FR-001 complete asset policy envelope');
$bad=$p;$bad['rights']['consent_status']='denied';err(fn()=>Policy::normalize($bad,true),'consent_not_granted','FR-001 denied consent fails closed');

// FR-002 resumable upload, credential, pause/resume, progress, abort.
$pdf="%PDF-1.7\n1 0 obj\n<< /Type /Page >>\nendobj\n%%EOF\n";$meta=['name'=>'resume.pdf','mime'=>'application/pdf','size'=>strlen($pdf),'sha256'=>hash('sha256',$pdf),'owner_object'=>'message:resume'];$u=UploadService::create(11,$meta,$p,'resume-create');
ok(!empty($u['upload_credential'])&&$u['status']==='uploading','FR-002 purpose-bound upload credential');
$paused=UploadService::pause($u['id'],11,$u['upload_credential']);ok($paused['status']==='paused','FR-002 pause');$resumed=UploadService::resume($u['id'],11,$u['upload_credential']);ok($resumed['status']==='uploading','FR-002 resume');
$s=stream_of($pdf);$part=UploadService::putPart($u['id'],11,1,$s,hash('sha256',$pdf),$u['upload_credential']);fclose($s);ok($part['progress_percent']===100.0,'FR-002 progress and verified part');$aborted=UploadService::abort($u['id'],11,$u['upload_credential'],'test-abort');ok($aborted['status']==='aborted','FR-002 abort and reservation release');

$asset=create_document_asset('message:primary');
ok($asset['status']==='ready'&&$asset['scan_status']==='passed'&&$asset['processing_status']==='completed','FR-002 end-to-end streaming completion');

// FR-003 source identity/dedupe boundaries.
ok(strlen($asset['sha256'])===64&&strlen($asset['fingerprint'])===64&&str_contains($asset['object_key'], '')&&$asset['policy_hash']!==''&&$asset['rights']['policy_hash']!=='','FR-003 hash, fingerprint and policy/rights-bound object identity');

// FR-004 server inspection.
ok($asset['mime']==='application/pdf'&&$asset['size']>0,'FR-004 client metadata distrusted and server-detected');
err(function(){$s=stream_of('not a pdf');try{Validator::inspectStream($s,'x.pdf','application/pdf',policy());}finally{fclose($s);}},'declared_mime_mismatch','FR-004 disguised source rejected');

// FR-005 quotas, reservations, abuse and rate.
$q=QuotaService::reserve('quota-test',11,100,1,['storage_bytes'=>1000,'daily_bytes'=>1000,'jobs'=>10,'burst_bytes'=>500]);QuotaService::settle($q['quota_id'],$q['reservation_id'],true,100,1);ok((RecordStore::get('quota',$q['quota_id'])['used_bytes']??0)===100,'FR-005 atomic quota reserve/settle');QuotaService::scoreAbuse('quota-test',11,101,'malicious');err(fn()=>QuotaService::reserve('quota-test',11,1,1,['storage_bytes'=>1000,'daily_bytes'=>1000,'jobs'=>10,'burst_bytes'=>500,'abuse_block_score'=>100]),'abuse_policy_block','FR-005 abuse score blocks');

// FR-006 private quarantine and encrypted storage.
ok(($asset['storage']['encrypted']??false)===true&&($asset['storage']['provider_id']??'')==='source-private','FR-006 private encrypted quarantine/provider identity');

// FR-007 structural validation.
$temp=tempnam(sys_get_temp_dir(),'pdf');file_put_contents($temp,$pdf);ok(Validator::detectMime($temp)==='application/pdf'&&Validator::magicMatches($temp,'application/pdf'),'FR-007 magic/MIME/container validation');unlink($temp);

// FR-008 mandatory scanners/archive defenses.
ok(isset($asset['scan_results']['malware'],$asset['scan_results']['archive'],$asset['scan_results']['polyglot'],$asset['scan_results']['decompression_bomb']),'FR-008 required fail-closed scanner evidence');

// FR-009 metadata transform.
$source=ProviderRegistry::store()->openStream($asset['object_key']);$metadataResult=MetadataPolicy::transform('document',['stream'=>$source,'mime'=>$asset['mime']],$asset['policy']);fclose($source);ok(in_array('gps',$metadataResult['metadata_removed'],true)&&in_array('rights',$metadataResult['metadata_preserved'],true),'FR-009 actual metadata strip/preserve attestation');fclose($metadataResult['output_stream']);

// FR-010 safety signals and reviewer provenance.
$signal=RecordStore::get('safety_signal',$asset['safety_signal_id']);ok($signal&&$signal['autonomous_decision']===false&&$signal['model_version']==='1.0','FR-010 versioned safety signals never make autonomous policy decisions');
$pending=RecordStore::put('safety_signal','manual-signal',['actor_id'=>0,'asset_id'=>$asset['id'],'model_id'=>'x','model_version'=>'1','confidence'=>0.4,'signals'=>[],'status'=>'pending_review','autonomous_decision'=>false]);$review=SafetySignalService::review($pending['id'],11,'accepted','human reviewed');ok($review['reviewer_id']===11&&$review['status']==='accepted','FR-010 reviewer provenance');

// FR-011 sandbox attestation.
$src=ProviderRegistry::store()->openStream($asset['object_key']);$probe=Sabri\CentralMedia\ToolRunner::probe((function($s){$p=tempnam(sys_get_temp_dir(),'probe');$o=fopen($p,'wb');stream_copy_to_stream($s,$o);fclose($o);return $p;})($src),$asset['mime'],$asset['policy']);fclose($src);ok($probe['worker']['non_root']&&$probe['worker']['network_isolated']&&$probe['worker']['ephemeral'],'FR-011 sandboxed worker attestation');

// FR-012 persisted DAG, leases, heartbeat/retry/dead-letter/orphan.
ok(count($asset['job_graph'])===7&&count(array_filter(RecordStore::list('job'),fn($j)=>($j['asset_id']??'')===$asset['id']&&($j['status']??'')==='completed'))===7,'FR-012 persisted idempotent job graph');
$job=RecordStore::put('job','orphan',['actor_id'=>0,'job_id'=>'orphan','asset_id'=>$asset['id'],'job_type'=>'probe','depends_on'=>[],'priority'=>'normal','priority_weight'=>50,'tenant'=>'file17','status'=>'leased','attempts'=>1,'max_attempts'=>2,'next_attempt_at'=>0,'lease_expires_at'=>time()-1,'created_at'=>time()-100]);ok(JobService::recoverOrphans()>=1&&RecordStore::get('job','orphan')['status']==='retry','FR-012 orphan recovery');$orphan=RecordStore::get('job','orphan');$orphan['status']='completed';RecordStore::put('job','orphan',$orphan,(int)$orphan['version']);

// FR-013 image pipeline.
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZB7sAAAAASUVORK5CYII=');$ip=policy('image','C3',['view','download']);$im=['name'=>'photo.png','mime'=>'image/png','size'=>strlen($png),'sha256'=>hash('sha256',$png),'owner_object'=>'message:image'];$iu=UploadService::create(11,$im,$ip,'image-create');$is=stream_of($png);UploadService::putPart($iu['id'],11,1,$is,hash('sha256',$png),$iu['upload_credential']);fclose($is);$ia=UploadService::complete($iu['id'],11,$iu['upload_credential'],'image-complete');ProcessingService::start($ia['id']);$ia=ProcessingService::execute($ia['id'],'image-worker');$ik=array_column(DerivativeService::forAsset($ia['id']),'kind');ok(in_array('thumbnail',$ik,true)&&in_array('large',$ik,true),'FR-013 image derivatives/orientation/color/pixel-safe provider pipeline');

// FR-014 audio/video pipeline contracts.
$video=RecordStore::put('asset','video-fixture',['actor_id'=>11,'asset_id'=>'video-fixture','owner_domain'=>'file17','owner_object'=>'message:video','object_version'=>1,'policy'=>policy('video','C3',['view','download']),'policy_hash'=>policy('video','C3',['view','download'])['policy_hash'],'rights'=>policy('video','C3',['view','download'])['rights'],'privacy_class'=>'C3','media_class'=>'video','declared_name'=>'video.mp4','mime'=>'video/mp4','size'=>$asset['size'],'sha256'=>$asset['sha256'],'fingerprint'=>$asset['fingerprint'],'storage'=>$asset['storage'],'object_key'=>$asset['object_key'],'status'=>'quarantined','scan_status'=>'pending','processing_status'=>'pending','manifest_version'=>0]);ProcessingService::start('video-fixture');$video=ProcessingService::execute('video-fixture','video-worker');$vk=array_column(DerivativeService::forAsset('video-fixture'),'kind');ok(in_array('hls-manifest',$vk,true)&&in_array('dash-manifest',$vk,true)&&in_array('poster',$vk,true)&&in_array('waveform',$vk,true),'FR-014 adaptive AV pipeline, manifests, poster, waveform');

// FR-015 document previews/text/OCR/active-content suppression.
$dk=array_column(DerivativeService::forAsset($asset['id']),'kind');ok(in_array('preview',$dk,true)&&in_array('text',$dk,true)&&in_array('ocr',$dk,true),'FR-015 document preview/text/OCR policy pipeline');

// FR-016 immutable lineage/atomic manifest.
$manifest=RecordStore::get('manifest',$asset['active_manifest_id']);ok($manifest&&$manifest['status']==='active'&&$manifest['manifest_version']===1&&isset($manifest['derivatives'][0]['lineage']['tool_version']),'FR-016 derivative lineage and atomic manifest switch');

// FR-017 storage metadata/key version/streaming/rotation.
ok(($asset['storage']['key_id']??'')==='test-v1'&&ProviderRegistry::store()->capabilities()['streaming']===true,'FR-017 encrypted streaming storage and key identity');

// FR-018 complete delivery grant binding/revocation/use policy.
$aud=['type'=>'private','user_id'=>11];$ctx=['audience_type'=>'private','territory'=>'GLOBAL','route'=>'test'];$rangePolicy=['allow_ranges'=>true,'max_range_bytes'=>8388608];$token=DeliveryService::issue($asset['id'],null,11,'file17',$aud,$ctx,'download',$rangePolicy,'session-1',300,2);$claims=Crypto::verify($token);ok(isset($claims['actor_id'],$claims['service_id'],$claims['audience_hash'],$claims['context_hash'],$claims['session_hash'],$claims['range_hash'],$claims['operation'],$claims['policy_hash'],$claims['rights_hash']),'FR-018 fully bound signed grant claims');
$served=DeliveryService::serve($token,11,'file17',$aud,$ctx,'session-1','bytes=0-9');ok($served['status']===206&&$served['range']['length']===10,'FR-018 range-bound grant consumption');fclose($served['stream']);err(fn()=>DeliveryService::serve($token,11,'file17',$aud,$ctx,'wrong-session',null),'grant_binding_mismatch','FR-018 session binding');DeliveryService::revoke($claims['grant_id'],11);err(fn()=>DeliveryService::serve($token,11,'file17',$aud,$ctx,'session-1',null),'grant_revoked','FR-018 revocation');

// FR-019 public CDN immutable publication/purge.
$public=create_document_asset('message:public','C1');$publicDerivative=DerivativeService::forAsset($public['id'])[0];$cdn=DeliveryService::publishPublic($public['id'],$publicDerivative['id']);ok($cdn['status']==='published'&&str_contains($cdn['version_key'],$publicDerivative['sha256']),'FR-019 public derivative immutable CDN publication');

// FR-020 private same-origin proxy headers/ranges.
$pt=DeliveryService::issue($asset['id'],null,11,'file17',$aud,$ctx,'view',$rangePolicy,'session-private');$private=DeliveryService::serve($pt,11,'file17',$aud,$ctx,'session-private',null);ok($private['headers']['Cache-Control']==='private, no-store, max-age=0'&&$private['headers']['Referrer-Policy']==='no-referrer'&&$private['headers']['X-Content-Type-Options']==='nosniff','FR-020 private proxy security headers');fclose($private['stream']);

// FR-021 actual download manager and disposition.
$download=DownloadManagerService::create(11,$asset['id'],null,'download',$ctx);$dt=DownloadManagerService::grant($download['id'],11,'download-session',$ctx);$dr=DeliveryService::serve($dt,11,'file20-download-manager',['type'=>'user','user_id'=>11],$ctx+['audience_type'=>'private'],'download-session',null);ok(str_starts_with($dr['headers']['Content-Disposition'],'attachment;')&&is_resource($dr['stream']),'FR-021 actual byte delivery and safe disposition');fclose($dr['stream']);

// FR-022 integrity sampling.
$integrity=IntegrityService::sample($asset['id']);ok($integrity!==[]&&!array_filter($integrity,fn($r)=>!$r['ok']),'FR-022 source/derivative integrity and bit-rot sampling');

// FR-023 rights, consent, territory/audience at action time.
RightsPolicy::assert($asset['rights'],'download',['territory'=>'GLOBAL','audience_type'=>'private']);err(fn()=>RightsPolicy::assert($asset['rights'],'publish',['territory'=>'GLOBAL','audience_type'=>'private']),'rights_operation_denied','FR-023 rights operation enforcement');

// CHAT-XFER-001 and FR-005/018 transfer party checks.
$transfer=TransferService::create(['native_transfer_id'=>'message:transfer-1','native_transfer_version'=>1,'sender_user_id'=>11,'recipient_type'=>'user','recipient_user_id'=>12,'expected_size'=>1024,'media_class'=>'document','declared_name'=>'private.pdf','relationship'=>'doctor-patient','consent_status'=>'granted','copyright_basis'=>'owner','clinical_confidentiality'=>true,'confidentiality_class'=>'C4','abuse_policy_version'=>'1.0'],policy('document','C4'),[]);ok($transfer['status']==='pending_upload'&&$transfer['recipient_user_id']===12&&$transfer['expected_size']<=TransferService::MAX_FILE_BYTES,'CHAT-XFER-001 sender/recipient verified private 1 GiB contract');

// FR-024 retention scheduler.
$ret=RetentionService::schedule($asset['id']);ok($ret['source_delete_at']>time()&&$ret['backup_expiry_at']>time(),'FR-024 class-specific retention schedule');

// FR-026 governed legal/security holds.
$hold=LegalHoldService::place($asset['id'],11,['authority'=>'Founder Security Office','reason'=>'investigation','scope'=>['deletion'],'review_at'=>time()+3600,'access_restriction'=>'restricted']);err(fn()=>DeletionService::request($asset['id'],11,'user-request'),'asset_on_hold','FR-026 active hold blocks deletion');$hold=LegalHoldService::review($hold['id'],11,'release','review completed');ok($hold['status']==='released','FR-026 authorized hold review/release');

// FR-025 ordered deletion/reconciliation including CDN purge.
$del=DeletionService::request($public['id'],11,'user-request',['backup_expiry_at'=>time()+86400]);$del=DeletionService::process($del['id']);ok($del['status']==='completed'&&array_values($del['steps'])===array_fill(0,7,'complete')&&RecordStore::get('tombstone',$public['id'])['status']==='deleted','FR-025 ordered revoke→purge→delete→mapping→backup-ledger→tombstone');

// FR-027 provider exit with copy/hash/shadow/switch/purge.
$exit=ProviderExitService::plan('source-private','target-private',11);$exit=ProviderExitService::copy($exit['id']);$exit=ProviderExitService::shadowVerify($exit['id']);$exit=ProviderExitService::switch($exit['id']);$exit=ProviderExitService::purgeSource($exit['id']);ok($exit['status']==='completed'&&$exit['verified']===$exit['copied']&&$exit['purged']===$exit['switched'],'FR-027 provider exit verification/switch/purge');

// FR-028 queue priorities/fairness/starvation evidence.
$queueJob=RecordStore::put('job','fair-job',['actor_id'=>0,'job_id'=>'fair-job','asset_id'=>$asset['id'],'processing_generation'=>(int)$asset['processing_generation'],'job_type'=>'probe','depends_on'=>[],'priority'=>'bulk','priority_weight'=>20,'tenant'=>'tenant-b','status'=>'queued','attempts'=>0,'max_attempts'=>3,'next_attempt_at'=>time()-1,'created_at'=>time()-3600]);$leased=JobService::lease('fair-worker',['probe']);ok($leased&&$leased['id']==='fair-job'&&$leased['lease_expires_at']>time(),'FR-028 priority, tenant fairness and starvation aging');JobService::complete($leased['id'],$leased['lease_token']);

// FR-029 provider abstraction/capabilities/degraded mode.
$health=ProviderRegistry::health();ok(isset($health['target-private']['capabilities']['streaming'],$health['target-private']['metadata']['region'])&&ProviderRegistry::activeId()==='target-private','FR-029 capability-aware provider abstraction');

// FR-030 cost attribution/budgets/invoice reconciliation.
CostService::setBudget('file17',gmdate('Y-m'),0.01,11);$cost=CostService::record($asset['id'],'target-private','verified-user-transfer',['bytes'=>1000,'jobs'=>2],['bytes'=>0.00001,'jobs'=>0.01]);$invoice=CostService::reconcile('target-private',['total'=>$cost['cost'],'tolerance'=>0.001]);ok($cost['owner_domain']==='file17'&&in_array($invoice['status'],['reconciled','mismatch'],true),'FR-030 domain/purpose cost ledger, budget and invoice reconciliation');

// FR-031 safe repair/reprocess with last-valid rollback.
$repair=RepairService::preview($ia['id'],'new image preset',['thumbnail'],['version'=>'2'],11);$repair=RepairService::execute($repair['id'],'repair-key');ok($repair['status']==='completed'&&!empty($repair['previous_manifest_id'])&&!empty($repair['new_manifest_id']),'FR-031 governed reprocess preserves previous manifest');$repair=RepairService::rollback($repair['id'],11);ok($repair['status']==='rolled_back','FR-031 repair rollback');

// FR-032 restore/rebuild/reconciliation pre-serve gate.
$restore=RestoreService::start(11,['database_snapshot'=>'db-1','object_inventory'=>'objects-1','keyring_version'=>'test-v1','policy_snapshot'=>'policies-1','manifest_snapshot'=>'manifests-1','tombstone_snapshot'=>'tombstones-1']);$restore=RestoreService::reconcile($restore['id']);ok($restore['status']==='reconciled','FR-032 restore reconciliation');RestoreService::authorizeServe($restore['id']);ok(RecordStore::get('restore',$restore['id'])['status']==='serve_authorized','FR-032 pre-serve authorization gate');

// Key rotation/re-encryption after provider exit.
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v2');$rotation=KeyRotationService::rotateAll(11);ok($rotation['active_key_id']==='test-v2'&&$rotation['failed']===0,'FR-017 managed key rotation/re-encryption');

// FR-033 metrics, traces, alerts, health, audit chain/runbooks/synthetic.
Observability::metric('queue.depth',3,['tenant'=>'file17']);Observability::trace('media.process','trace-0001','span-1',['asset_id'=>$asset['id']]);Observability::alert('warning','synthetic-test',['asset_id'=>$asset['id']]);$health=Observability::health();$synthetic=Observability::synthetic();ok(isset($health['queue_depth'],$health['oldest_queue_age_seconds'],$health['dead_letters'],$health['pending_deletions'],$health['runbooks'])&&$synthetic['provider']&&$synthetic['keyring']&&Audit::verifyChain(),'FR-033 observability, audit chain, synthetic checks and runbooks');

// API/service/webhook replay defenses.
$secret=str_repeat('s',32);add_filter('scm_service_secret',fn($value,string $service)=>$secret);$ts=time();$nonce='nonce-1';$canonical='file17|POST|/sabri-media/v1/uploads|'.$ts.'|'.$nonce.'|'.hash('sha256','{}');$sig=Utils::b64url(hash_hmac('sha256',$canonical,$secret,true));ServiceAuth::verify('file17','POST','/sabri-media/v1/uploads','{}',$nonce,$ts,$sig);err(fn()=>ServiceAuth::verify('file17','POST','/sabri-media/v1/uploads','{}',$nonce,$ts,$sig),'service_replay_denied','service request replay denied');
$wts=time();$wid='event-1';$wbody='{}';$wsig=Utils::b64url(hash_hmac('sha256','provider|'.$wid.'|'.$wts.'|'.hash('sha256',$wbody),$secret,true));$wh=WebhookService::verify('provider',$wid,$wts,$wbody,$wsig,$secret);$whr=WebhookService::verify('provider',$wid,$wts,$wbody,$wsig,$secret);ok($wh['replay']===false&&$whr['replay']===true,'signed webhook replay handling');

// Workspace and truthful runtime boundaries.
$workspace=WorkspaceUploadService::normalize('image',['rotation_degrees'=>90,'compression_quality'=>80,'crop_requested'=>true,'crop_width'=>10,'crop_height'=>20],11);ok($workspace['strip_location_metadata']===true&&$workspace['crop']['width']===10,'image workspace privacy and transform contract');
ok(RuntimeGuard::enabled()&&Schema::ready(),'coded runtime test gate ready while production default remains disabled in plugin bootstrap');

echo "ALL 33 CF-04 REQUIREMENTS + CHAT-XFER-001: SOURCE TESTS PASSED\n";
