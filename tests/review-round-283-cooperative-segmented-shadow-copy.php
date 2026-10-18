<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r283put($store,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
for($i=0;$i<250;$i++){
    RecordStore::put('asset',sprintf('r283-a-deleted-%04d',$i),[
        'actor_id'=>11,'status'=>'deleted'
    ]);
}
$originals=[];
for($i=0;$i<4;$i++){
    $id=sprintf('r283-z-file17-%02d',$i);
    $key=hash('sha256','r283-private-cipher-'.$i);
    $bytes='r283-private-encrypted-media-'.$i;
    r283put($source,$key,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $originals[$key]=$bytes;
}
$derKey=hash('sha256','r283-file12-thumbnail-object');
$derBytes='r283-file12-encrypted-thumbnail';
r283put($source,$derKey,$derBytes);
RecordStore::put('derivative','r283-file12-thumbnail',[
    'actor_id'=>11,'status'=>'validated','asset_id'=>'r283-z-file17-00',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>$derKey,'sha256'=>hash('sha256',$derBytes),'size'=>strlen($derBytes)
]);
$originals[$derKey]=$derBytes;
$active=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($stage['status']??null)==='planning')$stage=ProviderExitInventoryStage::advance($stage['id'],11);
ok(($stage['segments']??0)===3&&($stage['eligible_items']??0)===5
   &&ProviderExitInventoryStage::verifyComplete($stage['id'],11)['eligible_items']===5,
   'R283 multi-page asset and derivative inventory staged with 250 irrelevant deleted history');
$first=ProviderExitShadowCopyStage::advanceInventory($stage['id'],11,1);
ok(($first['status']??null)==='copying_unfenced'
   &&($first['inventory_id']??null)===$stage['id']
   &&($first['completed_objects']??0)<5,
   'R283 bounded coordinator starts with persistent progress and does not execute all copies at once');
$copied=[];
foreach(RecordStore::all('provider_exit_shadow_segment',0,null,20) as $segment){
    foreach($segment['copies'] as $entry)$copied[$entry['source_key']]=true;
}
ok(count($copied)===1,'R283 first coordinator invocation copies exactly one real object');
$removeKey=null;
foreach($originals as $key=>$bytes){if(!isset($copied[$key])){$removeKey=$key;break;}}
ok(is_string($removeKey)&&$source->exists($removeKey),
   'R283 choose an unprocessed source object for a controlled interrupted-copy failure');
$source->delete($removeKey);
$failed=false;
for($i=0;$i<15;$i++){
    try{ProviderExitShadowCopyStage::advanceInventory($stage['id'],11,1);}
    catch(\Sabri\CentralMedia\Error $e){
        ok($e->errorCode==='object_not_found',
           'R283 unavailable source fails closed rather than being marked shadow-copied');
        $failed=true;break;
    }
}
ok($failed,'R283 unprocessed source must prevent completion of the bounded worker');
$before=RecordStore::get('provider_exit_shadow_run',hash('sha256','shadow-run|'.$stage['id']));
ok(($before['status']??null)==='copying_unfenced',
   'R283 failed segment leaves cooperative run resumable, not falsely completed');
r283put($source,$removeKey,$originals[$removeKey]);
$finished=null;
for($i=0;$i<25;$i++){
    $finished=ProviderExitShadowCopyStage::advanceInventory($stage['id'],11,1);
    if(($finished['status']??null)==='shadow_verified_unfenced')break;
}
ok(($finished['status']??null)==='shadow_verified_unfenced'
   &&$finished['segments_done']===3
   &&$finished['completed_objects']===5,
   'R283 entire multi-segment shadow copy resumes and completes with exact item/segment totals');
$proof=ProviderExitShadowCopyStage::verifyComplete($stage['id'],11);
ok($proof['verified_objects']===5&&$proof['segments']===3,
   'R283 completed coordinator produces independently rehashed source/target proof');
$repeat=ProviderExitShadowCopyStage::advanceInventory($stage['id'],11,1);
ok($repeat['version']===$finished['version'],
   'R283 completed shadow worker idempotent on retry without redoing copies');
ok(ProviderRegistry::activeId()===$active&&RecordStore::all('provider_exit')===[]
   &&RecordStore::get('asset','r283-z-file17-00')['storage']['provider_id']==='source-private',
   'R283 large inventory shadow coordinator cannot cut over, purge source or modify native owners');
$copies=RecordStore::all('provider_exit_shadow_segment',0,null,20);
$key=null;
foreach($copies as $segment){
    foreach(($segment['copies']??[]) as $entry){
        if(is_string($entry['target_key']??null)&&$entry['target_key']!==''){
            $key=$entry['target_key'];
            break 2;
        }
    }
}
ok(is_string($key)&&$key!=='',
   'R283 choose a genuine completed shadow-copy target across all segments');
$target->delete($key);
r283put($target,$key,'r283-corrupted-shadow-bytes');
err(fn()=>ProviderExitShadowCopyStage::advanceInventory($stage['id'],11,1),
   'provider_exit_shadow_integrity_failed',
   'R283 formerly completed shadow run must refuse a newly corrupted target object');
echo "REVIEW ROUND 283 COOPERATIVE SEGMENTED SHADOW COPY: PASS\n";
