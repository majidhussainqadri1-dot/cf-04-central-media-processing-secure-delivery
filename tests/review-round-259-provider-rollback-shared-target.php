<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,ProviderRegistry,RecordStore,StorageReferenceWitness,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r259put($store,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
function r259asset(string $id,string $sourceKey,string $targetKey,string $sha,int $size): void {
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'object_version'=>1,'storage'=>['provider_id'=>'target-private','key_id'=>'test-v1'],
        'object_key'=>$targetKey,'sha256'=>$sha,'size'=>$size,
        'previous_storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'previous_object_key'=>$sourceKey
    ]);
}
function r259plan(string $id,array $ids,string $sourceKey,string $targetKey): void {
    RecordStore::put('provider_exit',$id,[
        'actor_id'=>11,'status'=>'switched','source_provider'=>'source-private',
        'target_provider'=>'target-private',
        'items'=>array_map(static fn(string $assetId)=>[
            'type'=>'asset','id'=>$assetId,'status'=>'switched',
            'object_key'=>$sourceKey,'target_key'=>$targetKey
        ],$ids)
    ]);
}
// An independently registered, unplanned active alias must retain its ciphertext.
$bytes='r259-one-owner-content';$sha=hash('sha256',$bytes);$size=strlen($bytes);
$old=hash('sha256','r259-source-object-one');
$dest=hash('sha256','r259-target-object-one');
r259put($source,$old,$bytes);r259put($target,$dest,$bytes);
r259asset('r259-to-rollback',$old,$dest,$sha,$size);
RecordStore::put('derivative','r259-unplanned-file17-alias',[
    'actor_id'=>11,'status'=>'ready','asset_id'=>'r259-to-rollback',
    'storage'=>['provider_id'=>'target-private','key_id'=>'test-v1'],
    'object_key'=>$dest,'sha256'=>$sha,'size'=>$size
]);
r259plan('r259-first-plan',['r259-to-rollback'],$old,$dest);
$rollback=ProviderExitService::rollback('r259-first-plan',11,'test shared-target alias');
ok(($rollback['status']??null)==='rolled_back'
    &&($rollback['items'][0]['target_cleanup_deferred']??null)==='live_reference',
    'R259 rollback defers physical deletion when an unplanned File 17 alias still uses target');
ok($target->exists($dest)
    &&StorageReferenceWitness::first('target-private',$dest)!==null,
    'R259 target ciphertext remains readable to late active derivative alias');
ok((RecordStore::get('asset','r259-to-rollback')['object_key']??null)===$old,
    'R259 rolled-back owner mapping points to retained source bytes');
// Two planned aliases to the same physical target object roll back safely together.
$bytes2='r259-shared-owner-content';$sha2=hash('sha256',$bytes2);$size2=strlen($bytes2);
$old2=hash('sha256','r259-source-object-two');
$dest2=hash('sha256','r259-target-object-two');
r259put($source,$old2,$bytes2);r259put($target,$dest2,$bytes2);
r259asset('r259-planned-a',$old2,$dest2,$sha2,$size2);
r259asset('r259-planned-b',$old2,$dest2,$sha2,$size2);
r259plan('r259-second-plan',['r259-planned-a','r259-planned-b'],$old2,$dest2);
$second=ProviderExitService::rollback('r259-second-plan',11,'test planned alias reuse');
ok(($second['status']??null)==='rolled_back'
    &&($second['items'][0]['target_cleanup_deferred']??null)==='live_reference'
    &&($second['items'][1]['status']??null)==='verified',
    'R259 first planned alias defers cleanup while another still references same ciphertext');
ok(!$target->exists($dest2)
    &&StorageReferenceWitness::first('target-private',$dest2)===null,
    'R259 last planned alias removes redundant ciphertext only after all references drain');
echo "REVIEW ROUND 259 PROVIDER ROLLBACK SHARED-TARGET REFERENCE: PASS\n";
