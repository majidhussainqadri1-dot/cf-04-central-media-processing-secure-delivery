<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{OperationsFutureService,RecordStore,Utils};
RecordStore::resetMemory();
$hash=hash('sha256','round147');
$asset=RecordStore::put('asset','round147-asset',['actor_id'=>11,'status'=>'ready','object_version'=>1,'sha256'=>$hash,'privacy_class'=>'C3','policy_hash'=>$hash,'rights'=>['policy_hash'=>$hash]]);
$result=OperationsFutureService::migrationBundle([$asset['id'],$asset['id']],11,'provider-next');
$bundle=$result['bundle'];
ok(count($bundle['items'])===1&&OperationsFutureService::verifyMigrationBundle($bundle),'Round 147 canonical deduplicated signed bundle');
foreach(['bundle_hash','signature','signing_kid','format','target','items'] as $field){
    $tampered=$bundle;$tampered[$field]=$field==='items'?[]:'tampered';
    ok(!OperationsFutureService::verifyMigrationBundle($tampered),'Round 147 altered '.$field.' rejected');
}
$missing=$bundle;unset($missing['bundle_hash']);
ok(!OperationsFutureService::verifyMigrationBundle($missing),'Round 147 missing bundle hash rejected');
$bad=$bundle;$bad['signature']=[];
ok(!OperationsFutureService::verifyMigrationBundle($bad),'Round 147 malformed signature type rejected');
err(fn()=>OperationsFutureService::migrationBundle([[]],11,'provider-next'),'migration_bundle_invalid','Round 147 array asset ID rejected');
$badVersion=RecordStore::put('asset','round147-version',['actor_id'=>11,'status'=>'ready','object_version'=>'1junk','sha256'=>$hash,'policy_hash'=>$hash,'rights'=>['policy_hash'=>$hash]]);
err(fn()=>OperationsFutureService::migrationBundle([$badVersion['id']],11,'provider-next'),'migration_asset_identity_invalid','Round 147 malformed version rejected');
$badPolicy=RecordStore::put('asset','round147-policy',['actor_id'=>11,'status'=>'ready','object_version'=>1,'sha256'=>$hash,'policy_hash'=>'bad','rights'=>['policy_hash'=>$hash]]);
err(fn()=>OperationsFutureService::migrationBundle([$badPolicy['id']],11,'provider-next'),'migration_asset_identity_invalid','Round 147 malformed policy identity rejected');
$deleted=RecordStore::put('asset','round147-deleted',['actor_id'=>11,'status'=>'deleted','object_version'=>1,'policy_hash'=>$hash,'rights'=>['policy_hash'=>$hash]]);
err(fn()=>OperationsFutureService::migrationBundle([$deleted['id']],11,'provider-next'),'migration_tombstone_missing','Round 147 deleted asset requires tombstone');
$tomb=RecordStore::put('tombstone','round147-tomb',['actor_id'=>11,'status'=>'deleted','object_version'=>1,'policy_hash'=>$hash,'rights_hash'=>$hash,'deleted_at'=>Utils::now(),'backup_expiry_at'=>Utils::now()+86400]);
$deletedBundle=OperationsFutureService::migrationBundle([$tomb['id']],11,'provider-next');
ok(OperationsFutureService::verifyMigrationBundle($deletedBundle['bundle']),'Round 147 canonical tombstone-only export');
echo "REVIEW ROUND 147 MIGRATION BUNDLE INTEGRITY: PASS\n";
