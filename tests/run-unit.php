<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,Auth,Crypto,DeletionService,DeliveryService,DomainRegistry,Error,Idempotency,IntegrationRegistry,LocalObjectStore,Policy,ProcessingService,ProviderRegistry,RecordStore,ScannerRegistry,TransferService,UploadService,Utils,Validator,WorkspaceUploadService};

RecordStore::resetMemory();
ProviderRegistry::reset();
DomainRegistry::reset();
ScannerRegistry::reset();
Audit::reset();

$store=new LocalObjectStore(SCM_PRIVATE_ROOT);
ProviderRegistry::register('local-private',$store,['test'=>true]);
DomainRegistry::register('file17','1.0.0',[
    'authorize_upload'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['object_version']??1)],
    'authorize_delivery'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['asset']['object_version']??$context['object_version']??1)],
    'authorize_download'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['asset']['object_version']??1)],
    'authorize_transfer_create'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['native_transfer_version']??1)],
    'authorize_transfer_revoke'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['transfer']['native_transfer_version']??1)],
    'authorize_deletion'=>fn(array $context)=>['authorized'=>true,'allowed'=>true,'object_version'=>(int)($context['asset']['object_version']??1)],
    'retention_decision'=>fn(array $context)=>['retain_until'=>time()+3600],
]);

$policy=policy();
ok($policy['max_upload_parts']===10000&&$policy['max_part_size_bytes']===8388608,'policy bounds and part ceiling');
$bad=$policy;$bad['required_scans']=[];
err(fn()=>Policy::validate($bad,true),'policy_malware_scan_required','malware scan fails closed');
$bad=$policy;$bad['max_part_size_bytes']=$bad['max_size_bytes'];$bad['max_upload_parts']=1;
ok(Policy::validate($bad,true)['max_upload_parts']===1,'single-part policy capacity');

$transfer=TransferService::validateEnvelope(['native_transfer_id'=>'smail:1','native_transfer_version'=>1,'sender_user_id'=>11,'recipient_type'=>'user','recipient_reference'=>'12','expected_size'=>TransferService::MAX_FILE_BYTES,'media_class'=>'document','declared_name'=>'../Clinical Notes.pdf']);
ok($transfer['declared_name']==='Clinical-Notes.pdf'&&strlen($transfer['recipient_ref_hash'])===64,'transfer normalization');
err(fn()=>TransferService::validateEnvelope(['native_transfer_id'=>'x','native_transfer_version'=>1,'sender_user_id'=>11,'recipient_type'=>'user','recipient_reference'=>'12','expected_size'=>TransferService::MAX_FILE_BYTES+1,'media_class'=>'document','declared_name'=>'x.pdf']),'transfer_file_size_exceeded','hard 1 GiB ceiling');
err(fn()=>Auth::assertVerifiedTransferUser(11,'transfer_create','file17'),'verified_transfer_assertion_unavailable','identity assertion has no permissive fallback');
add_filter('scm_verified_transfer_assertion',fn($value,int $userId)=>['verified'=>true,'approved'=>true,'active'=>true,'eligible'=>true,'suspended'=>false,'assertion_version'=>3,'user_id'=>$userId]);
ok(Auth::assertVerifiedTransferUser(11,'transfer_create','file17')['verified']===true,'verified File 00 assertion accepted');

$image=WorkspaceUploadService::normalizeContext('image',['source'=>'camera','rotation_degrees'=>90,'compression_quality'=>75,'crop_requested'=>true,'crop_x'=>0,'crop_y'=>0,'crop_width'=>10,'crop_height'=>20]);
ok($image['strip_location_metadata']===true&&$image['crop']['width']===10,'image workspace privacy');
err(fn()=>WorkspaceUploadService::normalizeContext('image',['rotation_degrees'=>45]),'image_rotation_invalid','invalid rotation rejected');

$claims=Crypto::verify(Crypto::sign(['asset_id'=>'a'],60));
ok($claims['asset_id']==='a','signed grant');
err(fn()=>Crypto::verify('broken'),'grant_invalid','malformed grant rejected');

$key=str_repeat('a',64);
$stored=$store->put($key,'secret',['token'=>'redact']);
ok($store->get($key)==='secret'&&$stored['size']===6,'encrypted object roundtrip');

$asset=['actor_id'=>11,'asset_id'=>'a1','owner_domain'=>'file17','owner_object'=>'m1','object_version'=>1,'object_key'=>$key,'content_sha256'=>hash('sha256','secret'),'size'=>6,'privacy_class'=>'C3','mime'=>'application/pdf','required_scans'=>['hash'],'status'=>'ready','scan_status'=>'passed'];
RecordStore::put('asset','a1',$asset);
$audience=['roles'=>['member'],'user_id'=>12];
$token=DeliveryService::issue(['asset_id'=>'a1'],$audience,60);
ok(DeliveryService::consume($token,['user_id'=>12,'roles'=>['member']])==='secret','canonical audience and domain reauthorized delivery');
err(fn()=>DeliveryService::consume($token,['user_id'=>13,'roles'=>['member']]),'grant_audience_mismatch','audience binding');
$grantId=Crypto::verify($token)['grant_id'];
DeliveryService::revoke($grantId);
err(fn()=>DeliveryService::consume($token,$audience),'grant_revoked','persistent grant revocation');
err(fn()=>DeliveryService::issue(['asset_id'=>'missing'],$audience,60),'asset_not_found','caller cannot inject noncanonical asset');

$token2=DeliveryService::issue(['asset_id'=>'a1'],$audience,60);
$tombstone=DeletionService::delete(['asset_id'=>'a1'],11,'user-request');
ok($tombstone['provider_purged']===true&&$tombstone['cdn_purged']===false&&RecordStore::get('asset','a1')['status']==='deleted','deletion persists truthful tombstone');
err(fn()=>DeliveryService::consume($token2,$audience),'grant_revoked','asset deletion revokes active grants');

$fingerprint=hash('sha256','request-one');
$claim=Idempotency::claim('unit','same-key',$fingerprint);
ok($claim['replay']===false,'idempotency first claim');
Idempotency::complete('unit','same-key',$fingerprint,'asset','a1');
$replay=Idempotency::claim('unit','same-key',$fingerprint);
ok($replay['replay']===true&&$replay['record']['result_id']==='a1','idempotency completed replay');
err(fn()=>Idempotency::claim('unit','same-key',hash('sha256','different')),'idempotency_conflict','idempotency fingerprint conflict');

$row=RecordStore::put('cas','one',['status'=>'active']);
$updated=RecordStore::put('cas','one',['status'=>'changed'],(int)$row['version']);
ok($updated['version']===2,'record compare-and-swap success');
err(fn()=>RecordStore::put('cas','one',['status'=>'stale'],(int)$row['version']),'record_version_conflict','record stale write rejected');

$pdf="%PDF-1.7\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
$uploadPolicy=policy('document',strlen($pdf));
$metadata=['name'=>'case.pdf','size'=>strlen($pdf),'mime'=>'application/pdf','sha256'=>hash('sha256',$pdf),'media_class'=>'document'];
$created=UploadService::create(11,$metadata,$uploadPolicy,'create-key');
$replayed=UploadService::create(11,$metadata,$uploadPolicy,'create-key');
ok($created['id']===$replayed['id'],'upload creation replays exact result');
UploadService::putPart($created['id'],11,1,$pdf,hash('sha256',$pdf));
$completed=UploadService::complete($created['id'],11,'complete-key');
$completedReplay=UploadService::complete($created['id'],11,'complete-key');
ok($completed['status']==='quarantined'&&$completedReplay['id']===$created['id'],'upload completion is replay safe');

ScannerRegistry::registerBuiltins();
err(fn()=>ProcessingService::scanAndPromote($created['id']),'media_scan_failed','missing scanner provider blocks promotion');
ok(RecordStore::get('asset',$created['id'])['scan_status']==='failed','scan failure persists evidence');
add_filter('scm_malware_scan_result',fn($value,string $bytes,array $context)=>['passed'=>true,'engine'=>'unit-malware','signature'=>'clean']);
add_filter('scm_metadata_scan_result',fn($value,string $bytes,array $context)=>['passed'=>true,'action'=>'metadata-policy-verified']);
$ready=ProcessingService::scanAndPromote($created['id']);
ok($ready['status']==='ready'&&$ready['scan_status']==='passed','approved scanner providers promote quarantined asset');

$temp=tempnam(sys_get_temp_dir(),'mime');file_put_contents($temp,$pdf);
ok(Validator::detectMime($temp)==='application/pdf'&&Validator::magicMatches($temp,'application/pdf'),'signature MIME detection');
unlink($temp);

$manifest=IntegrationRegistry::manifest();
ok($manifest['canonical_owner']==='binary-processing-storage-delivery'&&$manifest['identity_owner']==='file00','ownership manifest');
ok(count(Audit::memory())>0,'audit evidence captured');
echo "ALL UNIT TESTS PASSED\n";
