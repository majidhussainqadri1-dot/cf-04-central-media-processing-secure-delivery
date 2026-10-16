<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationService,RecordStore};

RecordStore::resetMemory();
for($i=0;$i<2100;$i++){
    $id=sprintf('r228-%05d',$i);
    RecordStore::put('key_rotation_cleanup',$id,[
        'actor_id'=>0,'status'=>$i===2099?'deferred_lock':'completed',
        'asset_ids'=>$i===2099?[]:['r228-asset'],
        'not_before'=>time()-60
    ]);
}
$first=KeyRotationService::reconcileDeferredCleanup(200);
$c=RecordStore::get('cron_cursor','rotation-cleanup');
ok($first['checked']===0&&($c['last_id']??null)==='r228-01999',
    'R228 first scan advances past 2000 completed historical cleanup records');
$second=KeyRotationService::reconcileDeferredCleanup(200);
$c=RecordStore::get('cron_cursor','rotation-cleanup');
ok($second['checked']===1&&$second['failed']===1
    &&($c['last_id']??null)==='',
    'R228 subsequent scan reaches invalid late cleanup and fails it closed');
ok((RecordStore::get('key_rotation_cleanup','r228-02099')['status']??null)==='failed',
    'R228 invalid late cleanup cannot be misreported as completed');
$c['last_id']="bad\ncursor";
RecordStore::put('cron_cursor','rotation-cleanup',$c,(int)$c['version']);
err(fn()=>KeyRotationService::reconcileDeferredCleanup(200),'rotation_cleanup_cursor_invalid',
    'R228 corrupt cleanup keyset cursor fails closed');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(!str_contains($source,"RecordStore::all('key_rotation_cleanup',0,null,100000)")
    &&str_contains($source,"RecordStore::scan('key_rotation_cleanup',2000"),
    'R228 no full 100k cleanup inventory required');
echo "REVIEW ROUND 228 ROTATION CLEANUP KEYSET: PASS\n";
