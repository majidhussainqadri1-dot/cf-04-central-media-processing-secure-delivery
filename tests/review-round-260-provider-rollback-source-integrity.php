<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r260store($provider,string $key,string $bytes): void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$provider->putStream($key,$stream);}finally{fclose($stream);}
}
function r260case(string $suffix,string $expected,string $actual,bool $storeSource): array {
    $id='r260-'.$suffix;$sourceKey=hash('sha256',$id.'-old');$targetKey=hash('sha256',$id.'-target');
    $bytes='r260-valid-'.$suffix;
    global $source,$target;
    if($storeSource)r260store($source,$sourceKey,$actual);
    r260store($target,$targetKey,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','sha256'=>hash('sha256',$expected),'size'=>strlen($expected),
        'object_key'=>$targetKey,
        'storage'=>['provider_id'=>'target-private','key_id'=>'test-v1'],
        'previous_object_key'=>$sourceKey,
        'previous_storage'=>['provider_id'=>'source-private','key_id'=>'test-v1']
    ]);
    $exit='r260-exit-'.$suffix;
    RecordStore::put('provider_exit',$exit,[
        'actor_id'=>11,'status'=>'switched','source_provider'=>'source-private',
        'target_provider'=>'target-private','items'=>[[
            'type'=>'asset','id'=>$id,'status'=>'switched',
            'object_key'=>$sourceKey,'target_key'=>$targetKey
        ]]
    ]);
    return [$id,$exit,$sourceKey,$targetKey];
}
[$id1,$exit1,$src1,$tgt1]=r260case('missing','r260-valid-missing','',false);
err(fn()=>ProviderExitService::rollback($exit1,11,'test missing source'),'object_not_found',
    'R260 absent original source blocks rollback remapping');
ok((RecordStore::get('asset',$id1)['object_key']??null)===$tgt1&&$target->exists($tgt1),
    'R260 failed missing-source rollback preserves live target bytes');
[$id2,$exit2,$src2,$tgt2]=r260case('changed','r260-valid-changed','r260-forged-bytes',true);
err(fn()=>ProviderExitService::rollback($exit2,11,'test changed source'),
    'provider_exit_rollback_source_mismatch',
    'R260 changed original source bytes block rollback remapping');
ok((RecordStore::get('asset',$id2)['object_key']??null)===$tgt2&&$target->exists($tgt2),
    'R260 corrupt source leaves target mapping and bytes intact');
[$id3,$exit3,$src3,$tgt3]=r260case('intact','r260-valid-intact','r260-valid-intact',true);
$rolled=ProviderExitService::rollback($exit3,11,'test intact source');
ok(($rolled['status']??null)==='rolled_back'
    &&(RecordStore::get('asset',$id3)['object_key']??null)===$src3,
    'R260 validated original source bytes permit safe rollback');
echo "REVIEW ROUND 260 PROVIDER ROLLBACK SOURCE-BYTE INTEGRITY: PASS\n";
