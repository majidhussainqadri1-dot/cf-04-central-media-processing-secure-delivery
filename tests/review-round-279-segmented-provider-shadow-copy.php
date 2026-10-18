<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r279put($store,string $key,string $data): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$data);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
function r279record(string $type,string $id,string $sourceKey,string $bytes,string $assetId=''): void {
    $row=['actor_id'=>11,'status'=>$type==='asset'?'ready':'validated',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$sourceKey,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)];
    if($type==='asset')$row['owner_domain']='file17';
    else $row['asset_id']=$assetId;
    RecordStore::put($type,$id,$row);
}
$sourceKeyA=hash('sha256','r279-original-a');
$sourceKeyB=hash('sha256','r279-original-b');
$sourceKeyD=hash('sha256','r279-original-derivative');
$bytesA='r279-asset-a-bytes';
$bytesB='r279-asset-b-bytes';
$bytesD='r279-file12-pdf-thumbnail-bytes';
r279put($source,$sourceKeyA,$bytesA);
r279put($source,$sourceKeyD,$bytesD);
// Second source object is initially unavailable. Fail after first durable item.
r279record('asset','r279-asset-a',$sourceKeyA,$bytesA);
r279record('asset','r279-asset-b',$sourceKeyB,$bytesB);
r279record('derivative','r279-pdf-thumbnail',$sourceKeyD,$bytesD,'r279-asset-a');
$active=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($stage['status']??null)==='planning')$stage=ProviderExitInventoryStage::advance($stage['id'],11);
$assetSeg=hash('sha256',$stage['id'].'|asset|');
$derSeg=hash('sha256',$stage['id'].'|derivative|');
$first=ProviderExitShadowCopyStage::copySegment($stage['id'],$assetSeg,11,1);
ok(($first['next_index']??null)===1&&($first['status']??null)==='copying_unfenced'
    &&count($first['copies'])===1&&$source->exists($sourceKeyA),
    'R279 first real encrypted shadow copy and durable one-item cursor succeed');
err(fn()=>ProviderExitShadowCopyStage::copySegment($stage['id'],$assetSeg,11,1),
    'object_not_found',
    'R279 absent second physical source cannot advance past first verified item');
$checkpoint=RecordStore::get('provider_exit_shadow_segment',hash('sha256',$stage['id'].'|'.$assetSeg));
ok(($checkpoint['next_index']??null)===1&&count($checkpoint['copies'])===1,
    'R279 missing source leaves last durable successful checkpoint untouched');
err(fn()=>ProviderExitShadowCopyStage::verifyComplete($stage['id'],11),
    'provider_exit_shadow_incomplete',
    'R279 partial shadow staging cannot claim verified entire provider inventory');
r279put($source,$sourceKeyB,$bytesB);
$second=ProviderExitShadowCopyStage::copySegment($stage['id'],$assetSeg,11,1);
ok(($second['next_index']??null)===2&&($second['status']??null)==='copied_unfenced',
    'R279 interrupted segmented copy resumes to complete both assets');
$derivative=ProviderExitShadowCopyStage::copySegment($stage['id'],$derSeg,11,1);
ok(($derivative['next_index']??null)===1&&($derivative['status']??null)==='copied_unfenced',
    'R279 separate File12 derivative segment copied with native parent binding');
$verified=ProviderExitShadowCopyStage::verifyComplete($stage['id'],11);
ok(($verified['status']??null)==='shadow_verified_unfenced'
   &&$verified['verified_objects']===3&&$verified['segments']===2,
   'R279 all three source and shadow ciphertexts rehashed after durable copy');
ok(ProviderRegistry::activeId()===$active&&RecordStore::all('provider_exit')===[]
    &&RecordStore::get('asset','r279-asset-a')['object_key']===$sourceKeyA
    &&$source->exists($sourceKeyB),
    'R279 segmented shadow copy never remaps owners, switches providers or deletes source');
$copy=RecordStore::get('provider_exit_shadow_segment',hash('sha256',$stage['id'].'|'.$assetSeg));
$targetKey=$copy['copies'][0]['target_key'];
$target->delete($targetKey);
r279put($target,$targetKey,'r279-corrupted-target');
err(fn()=>ProviderExitShadowCopyStage::verifyComplete($stage['id'],11),
    'provider_exit_shadow_integrity_failed',
    'R279 corruption after successful copy prevents even unfenced shadow verification');
$target->delete($targetKey);
r279put($target,$targetKey,$bytesA);
$copy=RecordStore::get('provider_exit_shadow_segment',hash('sha256',$stage['id'].'|'.$assetSeg));
$copy['copies'][0]['source_key']=hash('sha256','r279-forged-saved-proof');
RecordStore::put('provider_exit_shadow_segment',$copy['id'],$copy,(int)$copy['version']);
err(fn()=>ProviderExitShadowCopyStage::verifyComplete($stage['id'],11),
    'provider_exit_shadow_journal_invalid',
    'R279 altered saved copy proof fails closed even with genuine target ciphertext');
echo "REVIEW ROUND 279 SEGMENTED ENCRYPTED PROVIDER SHADOW COPY: PASS\n";
