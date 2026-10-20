<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{Plugin,RecordStore,RetentionService,Utils};

RecordStore::resetMemory();
$now=Utils::now();
RecordStore::put('upload','r236-abandoned',[
    'actor_id'=>11,'owner_domain'=>'file17','status'=>'uploading',
    'expires_at'=>$now-120,'credential_hash'=>'r236-credential'
]);
Plugin::cronRetention();
$cursor=RecordStore::get('cron_cursor','upload-expiry');
ok(($cursor['version']??null)===1
    &&(RecordStore::get('upload','r236-abandoned')['status']??null)==='expired',
    'R236 cron reconciles expired uploads exactly once under its own failure boundary');

RetentionService::run($now,false);
$unchanged=RecordStore::get('cron_cursor','upload-expiry');
ok(($unchanged['version']??null)===$cursor['version'],
    'R236 disabling duplicate janitor sweep does not advance upload-expiry cursor');

RetentionService::run($now);
$explicit=RecordStore::get('cron_cursor','upload-expiry');
ok(($explicit['version']??0)>($cursor['version']??0),
    'R236 direct retention caller retains backwards-compatible expiry cleanup');

$plugin=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-plugin.php');
$life=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
ok(str_contains($plugin,'RetentionService::run(0,false)')
    &&str_contains($plugin,'UploadService::cleanupExpired()')
    &&str_contains($life,'bool $cleanupUploads=true'),
    'R236 cron has independent cleanup failure isolation without duplicate execution');
echo "REVIEW ROUND 236 RETENTION SINGLE JANITOR: PASS\n";
