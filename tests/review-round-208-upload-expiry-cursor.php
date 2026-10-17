<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,UploadService,Utils};

RecordStore::resetMemory();
$now=Utils::now();
$quota=['storage_bytes'=>10000,'daily_bytes'=>10000,'jobs'=>10,'burst_bytes'=>1000];
$res=Sabri\CentralMedia\QuotaService::reserve('file17',11,25,1,$quota);
for($i=0;$i<2100;$i++){
    $id=sprintf('r208-%05d',$i);
    RecordStore::put('upload',$id,[
        'actor_id'=>11,'status'=>'uploading',
        'expires_at'=>$i===2099?$now-60:$now+3600,
        'owner_domain'=>'file17','credential_hash'=>'test-hash',
        'quota'=>$i===2099?$res:[]
    ]);
}
$first=UploadService::cleanupExpired($now,500);
ok($first['scanned']===2000&&$first['expired']===0,
    'R208 first bounded scan stops after 2000 active records');
$cursor=RecordStore::get('cron_cursor','upload-expiry');
ok(($cursor['last_id']??null)==='r208-01999',
    'R208 stable ID cursor durably records progress past active uploads');

$second=UploadService::cleanupExpired($now,500);
$expired=RecordStore::get('upload','r208-02099');
ok($second['expired']===1&&($expired['status']??null)==='expired',
    'R208 later expired upload is reached rather than starved by first 2000 active uploads');
ok(($second['scanned']??0)===100,
    'R208 finite second page ends without rescanning the entire inventory');
ok((RecordStore::get('upload','r208-00000')['status']??null)==='uploading',
    'R208 unexpired upload remains untouched');
$q=RecordStore::get('quota',$res['quota_id']);
ok(($q['reservations'][$res['reservation_id']]['status']??null)==='released',
    'R208 later expired upload settles its quota reservation');
$after=RecordStore::get('cron_cursor','upload-expiry');
ok(($after['last_id']??null)==='',
    'R208 cursor wraps only after reaching the end of the keyset');

$after['last_id']="r208-00000\n";
RecordStore::put('cron_cursor','upload-expiry',$after,(int)$after['version']);
err(fn()=>UploadService::cleanupExpired($now,500),'upload_expiry_cursor_invalid',
    'R208 malformed persistent cursor fails closed rather than aliasing another record');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-upload.php');
ok(!str_contains($source,"RecordStore::all('upload',0,null,100000)"),
    'R208 expiry no longer materializes or fails on the global 100k-upload list');
echo "REVIEW ROUND 208 UPLOAD EXPIRY KEYSET: PASS\n";
