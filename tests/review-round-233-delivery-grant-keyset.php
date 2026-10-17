<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,DeliveryService};

RecordStore::resetMemory();
for($i=0;$i<2530;$i++){
    $id=sprintf('r233-%05d',$i);
    RecordStore::put('grant',$id,[
        'actor_id'=>11,
        'asset_id'=>$i===2529?'r233-sensitive':'r233-unrelated',
        'status'=>in_array($i,[1020,2529],true)?'active':'revoked',
        'grant_id'=>$id,'max_uses'=>1,'uses'=>0
    ]);
}
$first=DeliveryService::revokeForAsset('r233-sensitive','rights_revoked');
ok($first===1,'R233 revokes an active late grant beyond ten pages of historic records');
ok((RecordStore::get('grant','r233-02529')['status']??null)==='revoked',
    'R233 late target grant is securely persisted as revoked');
ok((RecordStore::get('grant','r233-01020')['status']??null)==='active',
    'R233 unrelated active grant remains untouched');
ok(DeliveryService::revokeForAsset('r233-sensitive','rights_revoked')===0,
    'R233 repeated grant revocation is idempotent');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-delivery.php');
ok(!str_contains($source,"RecordStore::all('grant',0,null,100000)")
    &&str_contains($source,"RecordStore::scan('grant',250"),
    'R233 grant revocation no longer materializes an entire 100k grant inventory');
echo "REVIEW ROUND 233 GRANT KEYSET REVOCATION: PASS\n";
