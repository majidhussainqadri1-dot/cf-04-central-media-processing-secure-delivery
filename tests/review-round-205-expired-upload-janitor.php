<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,ProviderRegistry,QuotaService,RecordStore,Utils};

// R205: the scheduled retention worker must reconcile abandoned resumable upload
// parts and release quotas; an active upload must remain untouched.
RecordStore::resetMemory();
$quota=QuotaService::reserve('file17',11,23,1,[
    'storage_bytes'=>10000,'daily_bytes'=>10000,'jobs'=>10,'burst_bytes'=>1000
]);
$stale='r205-expired-upload';
$active='r205-live-upload';
$paused='r205-paused-upload';
$now=Utils::now();
$expired=RecordStore::put('upload',$stale,[
    'actor_id'=>11,'status'=>'uploading','owner_domain'=>'file17',
    'expires_at'=>$now-120,'credential_hash'=>'stale-secret-hash','quota'=>$quota
]);
RecordStore::put('upload',$active,[
    'actor_id'=>11,'status'=>'uploading','owner_domain'=>'file17',
    'expires_at'=>$now+3600,'credential_hash'=>'active-secret-hash'
]);
RecordStore::put('upload',$paused,[
    'actor_id'=>11,'status'=>'paused','owner_domain'=>'file17',
    'expires_at'=>$now-120,'credential_hash'=>'paused-secret-hash'
]);
$source=stream_of('expired temporary media');
$key=hash('sha256','r205-expired-temporary-part');
$provider=ProviderRegistry::store();
$put=$provider->putStream($key,$source,['scope'=>'upload-part']);
fclose($source);
$partId=hash('sha256',$stale.'|1');
RecordStore::put('upload_part',$partId,[
    'actor_id'=>11,'status'=>'stored','upload_id'=>$stale,
    'part_number'=>1,'provider_id'=>ProviderRegistry::activeId(),
    'object_key'=>$put['object_key'],'size'=>23,
    'sha256'=>hash('sha256','expired temporary media')
]);
ok($provider->exists($put['object_key']),'R205 fixture stores a quarantined upload part');
ok((int)(RecordStore::get('quota',$quota['quota_id'])['reserved_bytes']??0)===23,
   'R205 fixture starts with reserved quota');
Plugin::cronRetention();
ok((RecordStore::get('upload',$stale)['status']??null)==='expired',
   'R205 retention cron expires abandoned upload');
ok((RecordStore::get('upload',$paused)['status']??null)==='expired',
   'R205 retention cron expires paused abandoned upload');
ok((RecordStore::get('upload',$active)['status']??null)==='uploading',
   'R205 active unexpired upload stays available');
ok(RecordStore::get('upload_part',$partId)===null&&!$provider->exists($put['object_key']),
   'R205 expired upload part and object are removed');
$q=RecordStore::get('quota',$quota['quota_id']);
ok(($q['reservations'][$quota['reservation_id']]['status']??null)==='released'
   &&(int)($q['reserved_bytes']??-1)===0,
   'R205 abandoned upload reservation releases storage quota');
Plugin::cronRetention();
ok((RecordStore::get('upload',$stale)['status']??null)==='expired'
   &&(RecordStore::get('upload',$active)['status']??null)==='uploading',
   'R205 hourly retry is idempotent and preserves active sessions');
echo "REVIEW ROUND 205 EXPIRY CRON: PASS\n";
