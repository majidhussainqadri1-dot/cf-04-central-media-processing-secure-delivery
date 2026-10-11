<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeliveryResilienceService,DomainRegistry,RecordStore};
$p=policy('document','C3',['view','download']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r180','asset_id'=>'r180',
'owner_domain'=>'file17','owner_object'=>'message:r180','object_version'=>1,
'scan_status'=>'passed','processing_status'=>'completed',
'object_key'=>'r180-source','storage'=>['provider_id'=>'source-private'],
'sha256'=>hash('sha256','r180'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r180',$a);
DomainRegistry::register('r180-deny','1.0.0',[
'authorize_download'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r180-denied';$d['asset_id']='r180-denied';
$d['owner_domain']='r180-deny';RecordStore::put('asset','r180-denied',$d);
err(fn()=>DeliveryResilienceService::resumeDownload('r180-denied',11,
['offset'=>10,'device_id'=>'device-a']),
'domain_authorization_denied','Round 180 owner denies checkpoint');
foreach([
['offset'=>-1,'device_id'=>'device-a'],
['offset'=>'10junk','device_id'=>'device-a'],
['offset'=>1025,'device_id'=>'device-a'],
['offset'=>1.5,'device_id'=>'device-a']
] as $bad)
err(fn()=>DeliveryResilienceService::resumeDownload('r180',11,$bad),
'download_offset_invalid','Round 180 invalid checkpoint offset');
err(fn()=>DeliveryResilienceService::resumeDownload('r180',11,
['offset'=>10,'device_id'=>['device-a']]),
'download_resume_device_required','Round 180 nontext device denied');
ok(RecordStore::all('download_resume')===[],
'Round 180 rejected checkpoints not persisted');
$one=DeliveryResilienceService::resumeDownload('r180',11,
['offset'=>10,'device_id'=>'device-a']);
$two=DeliveryResilienceService::resumeDownload('r180',11,
['offset'=>20,'device_id'=>'device-b']);
ok($one['id']!==$two['id']&&count(RecordStore::all('download_resume'))===2,
'Round 180 per-device checkpoint isolation');
$grant=DeliveryResilienceService::resumeGrant('r180',11,'session-a','device-a');
ok($grant['offset']===10&&$grant['reauthorized']===true,
'Round 180 authorized device-a resume');
err(fn()=>DeliveryResilienceService::resumeGrant('r180',11,'session-x','device-x'),
'download_resume_state_invalid','Round 180 unknown device denied');
$row=RecordStore::get('download_resume',$one['id']);$row['offset']='2000';
RecordStore::put('download_resume',$one['id'],$row,(int)$row['version']);
err(fn()=>DeliveryResilienceService::resumeGrant('r180',11,'session-a','device-a'),
'download_offset_invalid','Round 180 tampered stored offset denied');
$grantB=DeliveryResilienceService::resumeGrant('r180',11,'session-b','device-b');
ok($grantB['offset']===20,'Round 180 independent device checkpoint remains valid');
echo "REVIEW ROUND 180 RESUME CHECKPOINT BOUNDARIES: PASS\n";
