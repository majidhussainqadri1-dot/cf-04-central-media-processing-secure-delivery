<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r270put($store,string $key,string $contents): void {
    $handle=Utils::tempStream();Utils::writeAll($handle,$contents);rewind($handle);
    try{$store->putStream($key,$handle);}finally{fclose($handle);}
}
function r270ready(string $suffix): array {
    global $source,$target;
    $id='r270-asset-'.$suffix;
    $key=hash('sha256','r270-source-'.$suffix);
    $bytes='r270-real-original-'.$suffix;
    r270put($source,$key,$bytes);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $plan=ProviderExitService::plan('source-private','target-private',11);
    $copied=ProviderExitService::copy($plan['id']);
    $shadow=ProviderExitService::shadowVerify($plan['id']);
    $switched=ProviderExitService::switch($plan['id']);
    ok($copied['verified']===1&&$shadow['status']==='shadow_verified'
        &&$switched['status']==='switched','R270 pre-purge verified provider exit plan');
    return [$plan['id'],$key,$bytes,(string)$switched['items'][0]['target_key']];
}
[$exit,$key,$bytes,$newKey]=r270ready('missing');
ok($source->exists($key)&&$target->exists($newKey),'R270 both copies exist before induced target loss');
$target->delete($newKey);
err(fn()=>ProviderExitService::purgeSource($exit),
    'object_not_found',
    'R270 missing target after shadow verification stops source purge');
ok($source->exists($key)
    &&(RecordStore::get('provider_exit',$exit)['status']??null)==='purge_pending',
    'R270 old source ciphertext retained while target is missing');
r270put($target,$newKey,'r270-altered-bytes');
err(fn()=>ProviderExitService::purgeSource($exit),
    'provider_exit_purge_target_integrity_failed',
    'R270 changed target bytes cannot replace old source ciphertext');
ok($source->exists($key),
    'R270 old ciphertext remains intact after target hash/size mismatch');
$target->delete($newKey);
r270put($target,$newKey,$bytes);
$done=ProviderExitService::purgeSource($exit);
ok(($done['status']??null)==='completed'&&!$source->exists($key),
    'R270 verified restored target permits source purge and provider completion');
echo "REVIEW ROUND 270 PROVIDER EXIT LIVE TARGET INTEGRITY: PASS\n";
