<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,RecordStore,ProviderRegistry,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r266bytes($store,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
$items=[];$storage=[];
foreach(['first','second'] as $which){
    $id='r266-'.$which;$bytes='r266-plain-'.$which;
    $sourceKey=hash('sha256','r266-old-'.$which);
    $targetKey=hash('sha256','r266-new-'.$which);
    r266bytes($target,$targetKey,$bytes);
    if($which==='first')r266bytes($source,$sourceKey,$bytes);
    $storage[$which]=['source'=>$sourceKey,'target'=>$targetKey,'bytes'=>$bytes,'id'=>$id];
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','sha256'=>hash('sha256',$bytes),
        'size'=>strlen($bytes),'object_key'=>$targetKey,
        'storage'=>['provider_id'=>'target-private','key_id'=>'test-v1'],
        'previous_object_key'=>$sourceKey,
        'previous_storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    ]);
    $items[]=[
        'type'=>'asset','id'=>$id,'status'=>'switched',
        'object_key'=>$sourceKey,'target_key'=>$targetKey
    ];
}
$plan='r266-rollback';
RecordStore::put('provider_exit',$plan,[
    'actor_id'=>11,'status'=>'switched','source_provider'=>'source-private',
    'target_provider'=>'target-private','items'=>$items,
]);
err(fn()=>ProviderExitService::rollback($plan,11,'preflight missing second'),
    'object_not_found',
    'R266 missing last source blocks rollback before any compensating remaps');
foreach($storage as $entry){
    ok((RecordStore::get('asset',$entry['id'])['object_key']??null)===$entry['target']
        &&$target->exists($entry['target']),
        'R266 every target mapping/byte object is preserved on preflight failure');
}
ok((RecordStore::get('provider_exit',$plan)['status']??null)==='switched',
    'R266 no partially rolled-back provider plan on missing final original');
r266bytes($source,$storage['second']['source'],$storage['second']['bytes']);
$done=ProviderExitService::rollback($plan,11,'all original sources restored');
ok(($done['status']??null)==='rolled_back'
    &&count(array_filter($done['items'],static fn($item)=>($item['status']??null)==='verified'))===2,
    'R266 both planned records roll back after complete source preflight');
foreach($storage as $entry){
    ok((RecordStore::get('asset',$entry['id'])['object_key']??null)===$entry['source'],
        'R266 remap commits only after both source ciphertexts have been validated');
}
echo "REVIEW ROUND 266 PROVIDER ROLLBACK ALL-SOURCES PREFLIGHT: PASS\n";
