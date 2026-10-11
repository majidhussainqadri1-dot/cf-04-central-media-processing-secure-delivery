<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Keyring,KeyRotationService,ProviderRegistry,RecordStore,Utils};

function r144_asset(string $bytes,array $overrides=[]): array {
    RecordStore::resetMemory();
    Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v1');
    $key=hash('sha256','round-144-'.bin2hex(random_bytes(12)));
    $stream=stream_of($bytes);
    try{$stored=ProviderRegistry::store()->putStream($key,$stream);}finally{fclose($stream);}
    $record=['actor_id'=>11,'status'=>'ready','object_key'=>$key,'size'=>$stored['size'],'sha256'=>$stored['sha256'],'storage'=>$stored+['provider_id'=>ProviderRegistry::activeId()]];
    $record=array_replace_recursive($record,$overrides);
    return RecordStore::put('asset','round144-'.bin2hex(random_bytes(8)),$record);
}
function r144_rotate(): array {
    Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v2');
    return KeyRotationService::rotateAll(11);
}
$asset=r144_asset('round144-valid-bytes');
$old=$asset['object_key'];$run=r144_rotate();$fresh=RecordStore::get('asset',$asset['id']);
ok($run['failed']===0&&$run['rotated']===1&&$fresh['storage']['key_id']==='test-v2'&&$fresh['object_key']!==$old,'Round 144 canonical key rotation succeeds');
ok(!ProviderRegistry::store()->exists($old),'Round 144 old ciphertext removed only after reference remapping');

foreach(['10junk','1e2','1.5','-1',false,[],null] as $bad){
    $asset=r144_asset('bad-size-'.bin2hex(random_bytes(4)),['size'=>$bad]);
    $run=r144_rotate();
    ok($run['failed']===1&&RecordStore::get('asset',$asset['id'])['object_key']===$asset['object_key'],'Round 144 noncanonical size fails closed');
}
$asset=r144_asset('source-mismatch-bytes',['sha256'=>hash('sha256','not-the-source')]);
$run=r144_rotate();
ok($run['failed']===1&&RecordStore::get('asset',$asset['id'])['object_key']===$asset['object_key'],'Round 144 source-content mismatch blocks re-encryption');
foreach([['sha256'=>'bad'],['object_key'=>'bad'],['storage'=>['provider_id'=>'invalid provider']],['storage'=>['key_id'=>'invalid key']]] as $override){
    $asset=r144_asset('invalid-identity-'.bin2hex(random_bytes(4)),$override);
    $run=r144_rotate();
    ok($run['failed']===1&&RecordStore::get('asset',$asset['id'])['object_key']===$asset['object_key'],'Round 144 invalid content/storage identity fails closed');
}
$asset=r144_asset('reuse-must-have-active-key');
$targetKey=hash('sha256','rekey|'.$asset['object_key'].'|test-v2|'.$asset['sha256']);
$source=stream_of('reuse-must-have-active-key');
try{ProviderRegistry::store()->putStream($targetKey,$source);}finally{fclose($source);}
$run=r144_rotate();
ok($run['failed']===1&&RecordStore::get('asset',$asset['id'])['object_key']===$asset['object_key'],'Round 144 reused ciphertext under old key cannot masquerade as rotated');
ok(ProviderRegistry::store()->exists($targetKey),'Round 144 reused ciphertext is not deleted during failure cleanup');
$operations=file_get_contents(__DIR__.'/../sabri-central-media/includes/class-scm-operations.php');
ok(str_contains($operations,"$"."fresh['size']!==$"."expected['size']")&&str_contains($operations,"$"."fresh['key_id']!==$"."expected['key_id']"),'Round 144 fresh-record drift guards cover size and key identity');
echo "REVIEW ROUND 144 KEY ROTATION INTEGRITY: PASS\n";
