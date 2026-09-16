<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{AccessibilityMetadataService,Crypto,DeletionService,DeliveryService,DerivativeService,DomainRegistry,PlanParityRegistry,Policy,PrivacyTelemetry,ProcessingService,RecordStore,RenditionSelector,RightsRevocationService,UploadService,Utils};

function np_asset(string $owner='message:new-plan',string $privacy='C3'): array {
    $pdf="%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n2 0 obj\n<< /Type /Page >>\nendobj\n%%EOF\n";
    $p=policy('document',$privacy);
    $m=['name'=>'current-plan.pdf','mime'=>'application/pdf','size'=>strlen($pdf),'sha256'=>hash('sha256',$pdf),'owner_object'=>$owner];
    $u=UploadService::create(11,$m,$p,'np-create-'.hash('sha256',$owner.'|'.$privacy));
    $s=stream_of($pdf);UploadService::putPart($u['id'],11,1,$s,hash('sha256',$pdf),$u['upload_credential']);fclose($s);
    $a=UploadService::complete($u['id'],11,$u['upload_credential'],'np-complete-'.hash('sha256',$owner.'|'.$privacy));
    ProcessingService::start($a['id']);
    return ProcessingService::execute($a['id'],'new-plan-worker');
}

$manifest=PlanParityRegistry::manifest();
ok($manifest['data_classes']===['C0','C1','C2','C3','C4','C5'],'NEW-PLAN data-class constitution is C0-C5');
ok(count($manifest['requirements'])===10&&isset($manifest['requirements']['CF04-CEN-01'],$manifest['requirements']['CF04-CEN-10']),'NEW-PLAN CF04-CEN-01 through CF04-CEN-10 registered');
ok($manifest['native_journeys']===['CF04-NJ-01','CF04-NJ-02','CF04-NJ-03','CF04-NJ-04','CF04-NJ-05','CF04-NJ-06'],'NEW-PLAN native journeys registered');

// C0 is public. C1 is authenticated/account data. C2-C5 can never opt into public CDN.
$c0=policy('document','C0',['view','download']);
ok($c0['privacy_class']==='C0'&&$c0['delivery']['public_cdn']===true&&in_array('public',$c0['rights']['allowed_audiences'],true),'NEW-PLAN C0 public policy');
$c1=policy('document','C1',['view','download']);
ok($c1['privacy_class']==='C1'&&$c1['delivery']['public_cdn']===false&&!in_array('public',$c1['rights']['allowed_audiences'],true),'NEW-PLAN C1 authenticated/private-to-account policy');
$bad=policy('document','C2',['view']);$bad['delivery']['public_cdn']=true;$bad['rights']=rights(['view'],true);
err(fn()=>Policy::normalize($bad,true),'public_cdn_privacy_denied','NEW-PLAN C2-C5 public CDN denied');

$missing=$c1;unset($missing['lawful_basis']);
err(fn()=>Policy::normalize($missing,true),'policy_incomplete','NEW-PLAN lawful basis required');
$missing=$c1;unset($missing['revocation_hook']);
err(fn()=>Policy::normalize($missing,true),'policy_incomplete','NEW-PLAN revocation hook required');

// Typed owner reference and policy envelope survive ingest before processing.
$asset=np_asset('message:typed-owner','C3');
ok(($asset['owner_type']??'')==='message'&&($asset['policy']['lawful_basis']??'')!==''&&($asset['policy']['revocation_hook']??'')!=='','CF04-CEN-01 typed owner/lawful-basis/revocation envelope');
ok($asset['status']==='ready'&&$asset['scan_status']==='passed'&&$asset['processing_status']==='completed','CF04-CEN-02 fail-closed ingest reaches ready only after scan/process');

// C0 CDN mapping is rights/policy aware. C1/C2-C5 never publish publicly.
$public=np_asset('message:public-current-plan','C0');
$pd=DerivativeService::forAsset($public['id'])[0];
$cdn=DeliveryService::publishPublic($public['id'],$pd['id']);
ok(($cdn['privacy_class']??'')==='C0'&&($cdn['policy_hash']??'')===$public['policy_hash']&&($cdn['rights_hash']??'')===$public['rights']['policy_hash'],'CF04-CEN-04 public CDN mapping bound to C0 policy/rights');
err(fn()=>DeliveryService::publishPublic($asset['id'],DerivativeService::forAsset($asset['id'])[0]['id']),'public_cdn_denied','CF04-CEN-05 C2-C5 asset cannot reach public CDN');

// Signed grant explicitly binds purpose and privacy class as well as existing owner/policy/rights claims.
$aud=['type'=>'private'];$ctx=['audience_type'=>'private','territory'=>'GLOBAL','route'=>'current-plan'];
$token=DeliveryService::issue($asset['id'],null,11,'file17',$aud,$ctx,'download',['allow_ranges'=>true,'max_range_bytes'=>8388608],'new-plan-session',300,2);
$claims=Crypto::verify($token);
ok(($claims['purpose']??'')===$asset['policy']['purpose']&&($claims['privacy_class']??'')===$asset['privacy_class'],'CF04-CEN-04 grant purpose/privacy binding');

// Caption/transcript/alt-text provenance with mandatory human/domain approval for high-risk media.
$hash=hash('sha256','verified medical caption');
$meta=AccessibilityMetadataService::record($asset['id'],11,['kind'=>'caption','locale'=>'ur-PK','source_type'=>'machine','source_version'=>'mt-1','content_hash'=>$hash,'content_ref'=>'caption:1','risk_class'=>'medical','human_reviewed'=>true,'reviewer_id'=>11]);
ok($meta['human_reviewed']===true&&$meta['reviewer_id']===11&&$meta['owner_object_version']===$asset['object_version'],'CF04-CEN-06 high-risk accessibility provenance approved');
err(fn()=>AccessibilityMetadataService::record($asset['id'],11,['kind'=>'transcript','locale'=>'ur-PK','source_type'=>'machine','source_version'=>'mt-1','content_hash'=>$hash,'risk_class'=>'medical','human_reviewed'=>false,'reviewer_id'=>0]),'qualified_review_required','CF04-CEN-06 high-risk unreviewed metadata blocked');

// Low-bandwidth and audio-only choices use explicit derivatives and never silently substitute an original.
$videoPolicy=policy('video','C3',['view','download']);
$video=RecordStore::put('asset','video-current-plan',['actor_id'=>11,'asset_id'=>'video-current-plan','owner_domain'=>'file17','owner_object'=>'message:video-current-plan','owner_type'=>'message','object_version'=>1,'policy'=>$videoPolicy,'policy_hash'=>$videoPolicy['policy_hash'],'rights'=>$videoPolicy['rights'],'privacy_class'=>'C3','media_class'=>'video','declared_name'=>'video.mp4','mime'=>'video/mp4','size'=>$asset['size'],'sha256'=>$asset['sha256'],'fingerprint'=>$asset['fingerprint'],'storage'=>$asset['storage'],'object_key'=>$asset['object_key'],'status'=>'quarantined','scan_status'=>'pending','processing_status'=>'pending','manifest_version'=>0]);
ProcessingService::start($video['id']);$video=ProcessingService::execute($video['id'],'new-plan-video-worker');
$low=RenditionSelector::select($video['id'],'low_bandwidth');
ok($low['status']==='ready'&&in_array($low['rendition']['kind'],['video-low','audio-low','audio-aac'],true),'CF04-CEN-07 explicit low-bandwidth rendition');
$degraded=RenditionSelector::select($asset['id'],'audio_only');
ok($degraded['status']==='degraded'&&$degraded['rendition']===null,'CF04-CEN-09 no silent quality/original substitution');

// Privacy-minimal metrics reject person/content identifiers.
PrivacyTelemetry::metric('media_processing_completed',1.0,['domain'=>'file17','media_class'=>'document','privacy_class'=>'C3','status'=>'ready']);
err(fn()=>PrivacyTelemetry::metric('media_processing_completed',1.0,['user_id'=>'11']),'privacy_metric_label_denied','CF04-CEN-10 user-level media telemetry denied');
err(fn()=>PrivacyTelemetry::metric('media_processing_completed',1.0,['content'=>'secret']),'privacy_metric_label_denied','CF04-CEN-10 private-content telemetry denied');

// Rights expiry/revocation tears down grants/derivatives and emits downstream projection evidence.
DeliveryService::revokeForAsset($asset['id'],'pre-rights-expiry-reset');
$fresh=RecordStore::get('asset',$asset['id']);$fresh['rights']['expires_at']=Utils::now()-1;$fresh['policy']['rights']['expires_at']=Utils::now()-1;RecordStore::put('asset',$fresh['id'],$fresh,(int)$fresh['version']);
$r=RightsRevocationService::reconcileExpired(Utils::now(),1000);
ok($r['revoked']>=1,'CF04-CEN-08 rights expiry reconciliation executed');
$after=RecordStore::get('asset',$asset['id']);
ok($after['active_manifest_id']===null&&$after['status']==='quarantined','CF04-CEN-08 stale derivatives removed after rights expiry');
$projections=array_values(array_filter(RecordStore::all('projection_revocation',0,null,1000),fn($row)=>($row['asset_id']??'')===$asset['id']));
ok($projections!==[]&&$projections[0]['index_status']==='pending_owner_consumer','CF04-CEN-08 downstream index/backup propagation evidence recorded');

// Review 62 regression: the configured batch size is a processing limit, not a RecordStore scan ceiling.
RecordStore::resetMemory();
for($i=0;$i<500;$i++)RecordStore::put('asset','rights-batch-'.$i,['actor_id'=>0,'status'=>'ready','rights'=>['expires_at'=>0]]);
$bounded=RightsRevocationService::reconcileExpired(Utils::now(),500);
ok($bounded['checked']===500&&$bounded['revoked']===0&&$bounded['failed']===0,'REVIEW-62 rights reconciliation processes an exactly-full bounded batch without record_scan_limit');

echo "CF-04 CURRENT NEW-PLAN PARITY: PASS\n";
