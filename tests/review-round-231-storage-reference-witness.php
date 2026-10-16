<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,StorageReferenceWitness,ProviderExitService,KeyRotationService};

RecordStore::resetMemory();
$source='source-private';
$old=hash('sha256','r231-old-ciphertext');
$other=hash('sha256','r231-another-ciphertext');

// Both record families contain >250-page boundaries, mostly irrelevant rows.
for($i=0;$i<2520;$i++){
    RecordStore::put('asset',sprintf('r231-asset-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','storage'=>['provider_id'=>$source],
        'object_key'=>$old
    ]);
    RecordStore::put('derivative',sprintf('r231-derivative-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','storage'=>['provider_id'=>$source],
        'object_key'=>$old
    ]);
}
$assetId='r231-asset-02519';
$asset=RecordStore::get('asset',$assetId);
$asset['status']='ready';
RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);
ok(StorageReferenceWitness::first($source,$old)===[
    'type'=>'asset','id'=>$assetId
], 'R231 sees a live source asset after ten 250-record pages');

$exit=new ReflectionMethod(ProviderExitService::class,'assertSourceDrained');
err(fn()=>$exit->invoke(null,['source_provider'=>$source]),
    'provider_exit_inventory_drift',
    'R231 source-provider cutover fails closed with one late active asset');

$live=new ReflectionMethod(KeyRotationService::class,'liveReferences');
ok($live->invoke(null,$source,$old)===[['type'=>'asset','id'=>$assetId]],
   'R231 key-rotation cannot delete ciphertext still referenced by late asset');
$asset['status']='deleted';
RecordStore::put('asset',$assetId,$asset,(int)RecordStore::get('asset',$assetId)['version']);
$derivativeId='r231-derivative-02519';
$derivative=RecordStore::get('derivative',$derivativeId);
$derivative['status']='ready';
RecordStore::put('derivative',$derivativeId,$derivative,(int)$derivative['version']);

ok(StorageReferenceWitness::first($source,$old)===[
    'type'=>'derivative','id'=>$derivativeId
], 'R231 falls through to a late derivative when all assets are terminal');
ok($live->invoke(null,$source,$old)===[['type'=>'derivative','id'=>$derivativeId]],
   'R231 old ciphertext remains protected by late derivative reference');
err(fn()=>$exit->invoke(null,['source_provider'=>$source]),
    'provider_exit_inventory_drift',
    'R231 source cutover refuses a derivative-only source reference');

ok($live->invoke(null,$source,$other)===[],
   'R231 same provider but different ciphertext is not falsely treated as a live alias');
$derivative['status']='deleted';
RecordStore::put('derivative',$derivativeId,$derivative,
    (int)RecordStore::get('derivative',$derivativeId)['version']);
ok(StorageReferenceWitness::first($source,$old)===null,
   'R231 full bounded traversal proves no live source references remain');
$exit->invoke(null,['source_provider'=>$source]);
ok(true,'R231 source provider drain passes after all source references are tombstoned');
err(fn()=>StorageReferenceWitness::first('bad provider'),'storage_reference_query_invalid',
    'R231 provider name fails canonical validation');
err(fn()=>StorageReferenceWitness::first($source,'bad-key'),'storage_reference_query_invalid',
    'R231 ciphertext identity fails canonical validation');

$script=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(str_contains($script,"RecordStore::scan($type,250,$after)")
    &&!str_contains($script,"RecordStore::all('asset',0,null,100000) as $record)if((")
    &&!str_contains($script,"RecordStore::all('derivative',0,null,200000) as $record)if(("),
    'R231 safety witness uses bounded DB pages rather than whole-inventory array materialization');
echo "REVIEW ROUND 231 STORAGE REFERENCE WITNESS: PASS\n";
