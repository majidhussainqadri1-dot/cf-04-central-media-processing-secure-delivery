<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeliveryService,RecordStore};

RecordStore::resetMemory();
// Place a target after 2,100 unrelated historical CDN mappings. Purge must
// not depend on materializing an entire global 100,000-record array.
for($i=0;$i<2100;$i++){
    RecordStore::put('cdn_mapping',sprintf('r229-%05d',$i),[
        'actor_id'=>0,'status'=>'purged','asset_id'=>'r229-unrelated'
    ]);
}
$target='r229-99999';
RecordStore::put('cdn_mapping',$target,[
    'actor_id'=>0,'status'=>'purge_pending','asset_id'=>'r229-target',
    'version_key'=>'r229-target-cdn','purge_reason'=>'rights-revoked',
    'privacy_class'=>'C0'
]);
$result=DeliveryService::reconcilePublicPurges(1);
$cursor=RecordStore::get('cron_cursor','cdn-purge-reconciliation');
ok($result['assets']===1&&$result['purged']===1
    &&(RecordStore::get('cdn_mapping',$target)['status']??null)==='purged',
    'R229 CDN reconciler reaches late pending map after thousands of unrelated history rows');
ok(($cursor['last_id']??null)===$target,
    'R229 bounded CDN cursor retains exact last processed mapping ID at work limit');
$again=DeliveryService::reconcilePublicPurges(1);
$cursor=RecordStore::get('cron_cursor','cdn-purge-reconciliation');
ok($again['assets']===0&&($cursor['last_id']??null)==='',
    'R229 empty pending queue safely wraps CDN purge cursor');
$cursor['last_id']="r229\nbad";
RecordStore::put('cron_cursor','cdn-purge-reconciliation',$cursor,(int)$cursor['version']);
err(fn()=>DeliveryService::reconcilePublicPurges(1),'cdn_purge_cursor_invalid',
    'R229 malformed CDN purge cursor fails closed');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-delivery.php');
ok(!str_contains($source,"RecordStore::all('cdn_mapping',0,'purge_pending',100000)")
    &&!str_contains($source,"RecordStore::all('cdn_mapping',0,null,100000) as $snapshot")
    &&str_contains($source,"RecordStore::scan('cdn_mapping',250"),
    'R229 CDN purge processing never needs a 100k global in-memory mapping list');
echo "REVIEW ROUND 229 CDN PURGE KEYSET: PASS\n";
