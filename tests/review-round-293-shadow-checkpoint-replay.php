<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r293put($store,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
$assets=[];
for($i=0;$i<2;$i++){
    $sourceKey=hash('sha256','r293-original-'.$i);
    $bytes='r293-private-source-'.$i;
    r293put($source,$sourceKey,$bytes);
    RecordStore::put('asset','r293-asset-'.$i,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$sourceKey,'sha256'=>hash('sha256',$bytes),
        'size'=>strlen($bytes)
    ]);
    $assets[]=[$sourceKey,$bytes];
}
$active=ProviderRegistry::activeId();
$inventory=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($inventory['status']??null)==='planning'){
    $inventory=ProviderExitInventoryStage::advance($inventory['id'],11);
}
$segment=hash('sha256',$inventory['id'].'|asset|');
$first=ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment,11,1);
ok(($first['next_index']??null)===1&&count($first['copies'])===1,
    'R293 first physical shadow copy journal persisted');
$targetFirst=$first['copies'][0]['target_key'];
$targetSecond=hash('sha256','provider-shadow|'.$inventory['id'].'|'.$segment.
    '|'.$assets[1][0].'|'.hash('sha256',$assets[1][1]));
ok(!$target->exists($targetSecond),'R293 next target is absent before replay');
$target->delete($targetFirst);
err(fn()=>ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment,11,1),
    'provider_exit_shadow_checkpoint_stale',
    'R293 missing last checkpointed target fails closed before next copy');
ok(!$target->exists($targetSecond)&&
   RecordStore::get('provider_exit_shadow_segment',
       hash('sha256',$inventory['id'].'|'.$segment))['next_index']===1,
    'R293 missing checkpoint does not advance the durable cursor');
r293put($target,$targetFirst,$assets[0][1]);
$second=ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment,11,1);
ok(($second['status']??null)==='copied_unfenced'&&$second['next_index']===2,
    'R293 verified checkpoint resumes without duplicate source remapping');
$target->delete($targetSecond);
r293put($target,$targetSecond,'malicious-corrupt-shadow-bytes');
err(fn()=>ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment,11,1),
    'provider_exit_shadow_checkpoint_stale',
    'R293 completed-journal replay rejects corrupted last target bytes');
$target->delete($targetSecond);
r293put($target,$targetSecond,$assets[1][1]);
$again=ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment,11,1);
ok(($again['status']??null)==='copied_unfenced'&&
   ($again['version']??null)===($second['version']??null),
    'R293 intact completed journal remains idempotent');
$emptySegment=hash('sha256',$inventory['id'].'|derivative|');
ProviderExitShadowCopyStage::copySegment($inventory['id'],$emptySegment,11,1);
$verified=ProviderExitShadowCopyStage::verifyComplete($inventory['id'],11);
ok(($verified['status']??null)==='shadow_verified_unfenced'&&
   ($verified['verified_objects']??null)===2,
    'R293 all source/target bytes revalidated after successful replay');
ok(ProviderRegistry::activeId()===$active&&
   RecordStore::get('asset','r293-asset-0')['object_key']===$assets[0][0]&&
   $source->exists($assets[0][0])&&$source->exists($assets[1][0]),
    'R293 no native owner mutation, provider cutover or destructive source deletion');
echo "REVIEW ROUND 293 SHADOW CHECKPOINT REPLAY INTEGRITY: PASS\n";
