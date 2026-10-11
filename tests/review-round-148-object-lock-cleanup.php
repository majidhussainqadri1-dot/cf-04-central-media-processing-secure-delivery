<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationService,ProviderRegistry,RecordStore,ResidencyCryptoService};

function r148_lock(string $assetId,mixed $until,string $status='locked',?string $storedAsset=null): array {
    $id=hash('sha256',$assetId);$previous=RecordStore::get('object_lock',$id);
    return RecordStore::put('object_lock',$id,['actor_id'=>11,'status'=>$status,'asset_id'=>$storedAsset??$assetId,'locked_until'=>$until],$previous?(int)$previous['version']:0);
}
function r148_cleanup(string $suffix,array $overrides=[]): array {
    RecordStore::resetMemory();
    $key=hash('sha256','round148-'.$suffix);$provider=ProviderRegistry::activeId();
    $stream=stream_of('round148-ciphertext-'.$suffix);
    try{ProviderRegistry::store()->putStream($key,$stream);}finally{fclose($stream);}
    $id=hash('sha256',$provider.'|'.$key);
    $row=['actor_id'=>0,'status'=>'deferred_lock','provider_id'=>$provider,'object_key'=>$key,'object_key_hash'=>hash('sha256',$key),'asset_ids'=>['round148-parent'],'not_before'=>time()-1,'rotation_id'=>'krot-round148'];
    RecordStore::put('key_rotation_cleanup',$id,array_replace($row,$overrides));
    return [$key,$id];
}
RecordStore::resetMemory();
$asset=RecordStore::put('asset','round148-asset',['actor_id'=>11,'status'=>'ready','policy'=>['retention'=>['class'=>'standard']]]);
$until=time()+7200;r148_lock($asset['id'],$until);
ok(ResidencyCryptoService::isLocked($asset['id']),'Round 148 valid object lock blocks physical mutation');
err(fn()=>ResidencyCryptoService::assertUnlocked($asset['id'],'delete'),'object_lock_active','Round 148 active lock blocks deletion');
err(fn()=>ResidencyCryptoService::objectLock($asset['id'],11,time()+3600,'shorten'),'object_lock_reduction_denied','Round 148 active lock cannot be shortened');
$extended=ResidencyCryptoService::objectLock($asset['id'],11,time()+10800,'extend');
ok($extended['locked_until']>$until,'Round 148 valid lock extension preserves monotonic retention');
foreach(['10junk','1e2','-1',false,[],null] as $bad){
    r148_lock($asset['id'],$bad);
    err(fn()=>ResidencyCryptoService::isLocked($asset['id']),'object_lock_record_invalid','Round 148 invalid lock expiry cannot bypass lock');
    err(fn()=>ResidencyCryptoService::objectLock($asset['id'],11,time()+14400,'replace'),'object_lock_record_invalid','Round 148 malformed prior lock cannot be overwritten');
}
r148_lock($asset['id'],time()+7200,'released');
err(fn()=>ResidencyCryptoService::assertUnlocked($asset['id'],'delete'),'object_lock_record_invalid','Round 148 unexpected lock state fails closed');
r148_lock($asset['id'],time()+7200,'locked','wrong-asset');
err(fn()=>ResidencyCryptoService::isLocked($asset['id']),'object_lock_record_invalid','Round 148 mismatched lock parent fails closed');

[$key,$id]=r148_cleanup('valid');
$run=KeyRotationService::reconcileDeferredCleanup();
ok($run['completed']===1&&!ProviderRegistry::store()->exists($key),'Round 148 valid due unreferenced ciphertext is removed');
[$key,$id]=r148_cleanup('active-lock');
r148_lock('round148-parent',time()+7200);
$run=KeyRotationService::reconcileDeferredCleanup();
ok($run['pending']===1&&ProviderRegistry::store()->exists($key),'Round 148 active lock defers cleanup');
foreach(['junk','1e2',false,null] as $index=>$bad){
    [$key,$id]=r148_cleanup('bad-due-'.$index,['not_before'=>$bad]);
    $run=KeyRotationService::reconcileDeferredCleanup();
    ok($run['failed']>=1&&ProviderRegistry::store()->exists($key),'Round 148 invalid deferred timestamp fails closed');
}
foreach([[],[''],['dup','dup'],'not-an-array'] as $index=>$bad){
    [$key,$id]=r148_cleanup('bad-parents-'.$index,['asset_ids'=>$bad]);
    $run=KeyRotationService::reconcileDeferredCleanup();
    ok($run['failed']>=1&&ProviderRegistry::store()->exists($key),'Round 148 invalid cleanup parent list fails closed');
}
[$key,$id]=r148_cleanup('bad-lock');
r148_lock('round148-parent','garbage');
$run=KeyRotationService::reconcileDeferredCleanup();
ok($run['failed']===1&&ProviderRegistry::store()->exists($key),'Round 148 corrupt active lock cannot permit cleanup');
foreach([['object_key_hash'=>'bad'],['provider_id'=>'source private'],['object_key'=>'bad'],['status'=>'unknown']] as $index=>$bad){
    [$key,$id]=r148_cleanup('bad-identity-'.$index,$bad);
    $run=KeyRotationService::reconcileDeferredCleanup();
    ok($run['failed']>=1&&ProviderRegistry::store()->exists($key),'Round 148 corrupt cleanup identity/state fails closed');
}
echo "REVIEW ROUND 148 OBJECT LOCK AND DEFERRED CLEANUP: PASS\n";
