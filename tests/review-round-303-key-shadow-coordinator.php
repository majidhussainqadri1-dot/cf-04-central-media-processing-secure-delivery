<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,KeyRotationShadowCoordinator,
    ProviderRegistry,Keyring,RecordStore,Utils};
RecordStore::resetMemory();
$store=ProviderRegistry::get('source-private');
function r303put(string $key,string $bytes):void {
    global $store;
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
for($i=0;$i<2601;$i++)
    RecordStore::put('asset',sprintf('r303-deleted-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted'
    ]);
$originals=[];
for($i=0;$i<4;$i++){
    $id=sprintf('r303-live-%02d',$i);
    $key=hash('sha256','r303-original-'.$i);
    $bytes='r303-private-actual-'.$i;
    r303put($key,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'owner_domain'=>'file17','status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $originals[$id]=[$key,$bytes];
}
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
$stage=KeyRotationInventoryStage::begin(11);
$iterations=0;
while(($stage['status']??null)==='planning'&&$iterations++<100)
    $stage=KeyRotationInventoryStage::advance($stage['id'],11);
ok($stage['visited_rows']===2605&&$stage['stale_references']===4&&
    $stage['segments']>10,
    'R303 database inventory crosses 2,600 rows in durable multiple keyset pages');
$run=KeyRotationShadowCoordinator::begin($stage['id'],11);
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>KeyRotationShadowCoordinator::advance($run['id'],11,2),
    'capability_denied','R303 unauthorized actor denied before physical shadow copies');
unset($GLOBALS['scm_test_denied_caps']);
$missing=$originals['r303-live-02'];
$store->delete($missing[0]);
$interrupted=false;
for($i=0;$i<75;$i++){
    try{$run=KeyRotationShadowCoordinator::advance($run['id'],11,2);}
    catch(\Sabri\CentralMedia\Error $e){
        ok($e->errorCode==='object_not_found',
            'R303 missing source blocks next physical segment');
        $interrupted=true;break;
    }
}
ok($interrupted&&
   RecordStore::get('key_rotation_shadow_run',$run['id'])['status']==='copying_unfenced',
   'R303 interruption cannot falsely finalize cross-page shadow rotation');
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v1');
r303put($missing[0],$missing[1]);
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
for($i=0;$i<100;$i++){
    $run=KeyRotationShadowCoordinator::advance($run['id'],11,2);
    if($run['status']==='shadow_verified_unfenced')break;
}
ok($run['status']==='shadow_verified_unfenced'&&
   $run['segments_done']===$stage['segments']&&$run['candidates_copied']===4,
   'R303 durable coordinator completes all physical source inventory segments after retry');
$proof=KeyRotationShadowCoordinator::verifyComplete($run['id'],11);
ok($proof['status']==='shadow_verified_unfenced'&&
   $proof['verified_aliases']===4&&$proof['segments']===$stage['segments']&&
   $proof['mappings_changed']===0&&$proof['key_retired']===false,
   'R303 independent full inventory rehash produces explicitly unfenced status');
$repeat=KeyRotationShadowCoordinator::advance($run['id'],11,2);
ok($repeat['version']===$run['version'],
   'R303 already verified coordinator is idempotent after fresh source recheck');
$target=null;$targetBytes=null;
foreach(RecordStore::all('key_rotation_shadow_segment',0,null,100) as $segment){
    foreach($segment['copies'] as $copy){
        if(isset($originals[$copy['id']])){
            $target=$copy['target_key'];
            $targetBytes=$originals[$copy['id']][1];
            break 2;
        }
    }
}
ok(is_string($target)&&is_string($targetBytes),
   'R303 full inventory contains a real authenticated new ciphertext witness');
$store->delete($target);
err(fn()=>KeyRotationShadowCoordinator::verifyComplete($run['id'],11),
   'object_not_found',
   'R303 missing earlier target invalidates previously completed shadow proof');
r303put($target,$targetBytes);
ok(KeyRotationShadowCoordinator::verifyComplete($run['id'],11)['verified_aliases']===4,
   'R303 recovered new ciphertext verified after fresh full proof');
foreach($originals as $id=>[$key,$bytes]){
    $row=RecordStore::get('asset',$id);
    ok($row['object_key']===$key&&$row['storage']['key_id']==='test-v1'&&
       $store->exists($key),
       'R303 native owner/old key mapping and source ciphertext are unchanged');
}
ok(RecordStore::all('key_rotation')===[]&&RecordStore::all('key_rotation_group')===[],
   'R303 all-durable shadow coordinator never runs destructive alias cutover');
echo "REVIEW ROUND 303 KEY SHADOW COORDINATOR: PASS\n";
