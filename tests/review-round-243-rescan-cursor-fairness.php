<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ContentSafetyUpgradeService,RecordStore,Utils};

RecordStore::resetMemory();
$rights=hash('sha256','r243-rights');
$policy=hash('sha256','r243-policy');
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r243-asset-%05d',$i),[
        'actor_id'=>11,'status'=>'ready',
        'sha256'=>hash('sha256','r243-asset-'.$i),
        'policy_hash'=>$policy,'rights'=>['policy_hash'=>$rights],
        'object_version'=>1,'mime'=>'application/pdf','media_class'=>'document',
    ]);
}
RecordStore::put('asset','r243-asset-02600',[
    'actor_id'=>11,'status'=>'deleted','sha256'=>hash('sha256','deleted'),
    'policy_hash'=>$policy,'rights'=>['policy_hash'=>$rights],
    'object_version'=>1,'mime'=>'application/pdf','media_class'=>'document',
]);
$filters=['mime'=>'application/pdf'];
$reason='r243-signature-update';
$first=ContentSafetyUpgradeService::scheduleRescan($reason,$filters,5000,11);
ok($first['scanned']===2000&&$first['queued']===2000
    &&$first['scan_truncated']===true&&$first['scan_complete']===false,
    'R243 first rescan is strictly bounded at 2000 and advertises remaining work');
$second=ContentSafetyUpgradeService::scheduleRescan($reason,$filters,5000,11);
ok($second['scanned']===601&&$second['queued']===600
    &&$second['scan_complete']===true&&!$second['scan_truncated'],
    'R243 rescan continues to last source, excludes deleted and completes');
$last=hash('sha256','r243-asset-02599|'.$reason.'|'.
    hash('sha256','r243-asset-2599').'|1|'.$policy.'|'.$rights);
ok(RecordStore::get('future_rescan',$last)!==null,
    'R243 late asset is scheduled under exact bound source hash/rights/owner-version');
ok(count(RecordStore::all('future_rescan'))===2600,
    'R243 exactly-once logical rescan idempotency across pages');
$again=ContentSafetyUpgradeService::scheduleRescan($reason,$filters,5000,11);
ok($again['queued']===0,'R243 completed rescan repeat does not duplicate queue records');
$cursorId=hash('sha256','future-rescan|11|'.$reason.'|'.Utils::canonicalJson($filters));
$cursor=RecordStore::get('cron_cursor',$cursorId);
ok(($cursor['last_id']??null)===''&&($cursor['status']??null)==='active',
    'R243 durable cursor wraps only after completed source traversal');
$cursor['last_id']="forged\ncursor";
RecordStore::put('cron_cursor',$cursorId,$cursor,(int)$cursor['version']);
err(fn()=>ContentSafetyUpgradeService::scheduleRescan($reason,$filters,5000,11),
    'rescan_cursor_invalid','R243 invalid stored cursor fails closed without replay');

$src=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-future40.php');
ok(str_contains((string)$src,"RecordStore::scan('asset',\$pageSize,\$after)")
    &&!str_contains((string)$src,"RecordStore::list('asset',0,null,\$pageSize,\$offset)"),
    'R243 source avoids mutable offset pagination and one-million ceiling');
echo "REVIEW ROUND 243 RESCAN CURSOR FAIRNESS: PASS\n";
