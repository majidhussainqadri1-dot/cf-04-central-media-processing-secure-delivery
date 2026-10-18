<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r290store($store,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
$objects=[];
for($i=0;$i<2;$i++){
    $id='r290-file17-'.sprintf('%02d',$i);
    $sourceKey=hash('sha256','r290-source-'.$i);
    $bytes='r290-private-encrypted-bytes-'.$i;
    r290store($source,$sourceKey,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$sourceKey,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $objects[]=[$sourceKey,hash('sha256',$bytes)];
}
$active=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($stage['status']??null)==='planning')$stage=ProviderExitInventoryStage::advance($stage['id'],11);
$segmentId=hash('sha256',$stage['id'].'|asset|');
$derSegment=hash('sha256',$stage['id'].'|derivative|');
$copyId=hash('sha256',$stage['id'].'|'.$segmentId);
$partial=ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1);
ok(($partial['next_index']??null)===1&&($partial['status']??null)==='copying_unfenced',
    'R290 first physical private object copied with bounded durable cursor');
$secondTarget=hash('sha256','provider-shadow|'.$stage['id'].'|'.$segmentId.
    '|'.$objects[1][0].'|'.$objects[1][1]);
ok(!$target->exists($secondTarget),'R290 second object not yet copied');
$original=RecordStore::get('provider_exit_shadow_segment',$copyId);
$forged=$original;
$forged['copies'][0]['target_key']=hash('sha256','r290-forged-valid-looking-target-key');
$changed=RecordStore::put('provider_exit_shadow_segment',$copyId,$forged,(int)$forged['version']);
err(fn()=>ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1),
    'provider_exit_shadow_journal_invalid',
    'R290 partial checkpoint rejects forged target key before copying next item');
ok(!$target->exists($secondTarget)&&
    RecordStore::get('provider_exit_shadow_segment',$copyId)['next_index']===1,
    'R290 rejected partial replay leaves next target and durable cursor unchanged');
RecordStore::put('provider_exit_shadow_segment',$copyId,$original,(int)$changed['version']);
$complete=ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1);
ok(($complete['status']??null)==='copied_unfenced'&&$complete['next_index']===2,
    'R290 genuine partial checkpoint resumes and copies second physical object');
ProviderExitShadowCopyStage::copySegment($stage['id'],$derSegment,11,1);
ok(ProviderExitShadowCopyStage::verifyComplete($stage['id'],11)['verified_objects']===2,
    'R290 baseline full inventory verification hashes source and target');
$original=RecordStore::get('provider_exit_shadow_segment',$copyId);
$forged=$original;
$forged['copies'][1]['target_key']=hash('sha256','r290-other-forged-target-key');
$changed=RecordStore::put('provider_exit_shadow_segment',$copyId,$forged,(int)$forged['version']);
err(fn()=>ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1),
    'provider_exit_shadow_journal_invalid',
    'R290 completed idempotent replay also rejects forged target key');
err(fn()=>ProviderExitShadowCopyStage::verifyComplete($stage['id'],11),
    'provider_exit_shadow_journal_invalid',
    'R290 independently rehashed verification rejects forged completed proof');
RecordStore::put('provider_exit_shadow_segment',$copyId,$original,(int)$changed['version']);
ok(ProviderExitShadowCopyStage::verifyComplete($stage['id'],11)['verified_objects']===2,
    'R290 restored legitimate proof verifies again');
ok(ProviderRegistry::activeId()===$active&&RecordStore::all('provider_exit')===[]
    &&RecordStore::get('asset','r290-file17-00')['storage']['provider_id']==='source-private'
    &&$source->exists($objects[0][0])&&$source->exists($objects[1][0]),
    'R290 no native owner mutation, active provider change, or source deletion');
echo "REVIEW ROUND 290 SHADOW TARGET KEY REPLAY: PASS\n";
