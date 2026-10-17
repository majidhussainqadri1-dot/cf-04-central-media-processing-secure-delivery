<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{ProviderExitService,RecordStore,ProviderRegistry};

RecordStore::resetMemory();
$oldKey=hash('sha256','r253-ciphertext-source');
$newKey=hash('sha256','r253-ciphertext-target');
$source='source-private';$target='target-private';
$parent='r253-owner-asset';
RecordStore::put('asset',$parent,[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
    'owner_object'=>'message:r253-owner-asset','object_version'=>1,
    'storage'=>['provider_id'=>$target,'key_id'=>'test-v1'],'object_key'=>$newKey,
    'sha256'=>hash('sha256','r253-original'),'size'=>10
]);
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r253-history-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','storage'=>['provider_id'=>$source],
        'object_key'=>$oldKey
    ]);
}
$late='r253-late-source-alias';
RecordStore::put('derivative',$late,[
    'actor_id'=>11,'status'=>'validated','asset_id'=>$parent,
    'storage'=>['provider_id'=>$source],'object_key'=>$oldKey
]);
$id='r253-provider-exit';
RecordStore::put('provider_exit',$id,[
    'actor_id'=>11,'status'=>'switched','source_provider'=>$source,
    'target_provider'=>$target,'source_purge_completed_at'=>null,
    'items'=>[[
        'type'=>'asset','id'=>$parent,'status'=>'switched','object_key'=>$oldKey,
        'target_key'=>$newKey,
    ]],
    'copied'=>1,'verified'=>1,'switched'=>1,'purged'=>0
]);
err(fn()=>ProviderExitService::purgeSource($id),'provider_exit_inventory_drift',
    'R253 late derivative source reference fails global pre-purge drain check');
ok((RecordStore::get('provider_exit',$id)['status']??null)==='purge_pending',
    'R253 source purge leaves plan pending for safe inspection/reconciliation');
ok((RecordStore::get('asset',$parent)['object_key']??null)===$newKey
    &&(RecordStore::get('derivative',$late)['object_key']??null)===$oldKey,
    'R253 source alias remains mapped and target mapping unchanged after refusal');
$src=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
$start=strpos((string)$src,'public static function purgeSource(');
$stop=strpos((string)$src,'private static function finalizeCredentials(', $start);
$block=substr((string)$src,$start,$stop-$start);
ok(str_contains($block,'self::assertSourceDrained($plan);')
   &&str_contains($block,"StorageReferenceWitness::first((string)\$plan['source_provider'],(string)\$item['object_key'])!==null")
   &&str_contains($block,'provider_exit_source_reference_live'),
   'R253 global plus per-object late reference evidence required immediately before any source deletion');
echo "REVIEW ROUND 253 PROVIDER EXIT PURGE LIVE-REFERENCE GATE: PASS\n";
