<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
function r296put($store,string $key,string $bytes):void {
    $stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
    try{$store->putStream($key,$stream);}finally{fclose($stream);}
}
$rows=[];
for($i=0;$i<2;$i++){
    $key=hash('sha256','r296-source-'.$i);$bytes='r296-medical-private-'.$i;
    r296put($source,$key,$bytes);
    RecordStore::put('asset','r296-asset-'.$i,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
    $rows[]=[$key,$bytes,'r296-asset-'.$i];
}
$plan=ProviderExitService::plan('source-private','target-private',11);
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitService::copy($plan['id']),'capability_denied',
    'R296 cross-provider copy requires current administrator capability');
unset($GLOBALS['scm_test_denied_caps']);
ProviderExitService::copy($plan['id']);
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitService::shadowVerify($plan['id']),'capability_denied',
    'R296 shadow verification requires administrator capability');
unset($GLOBALS['scm_test_denied_caps']);
$shadow=ProviderExitService::shadowVerify($plan['id']);
ok($shadow['status']==='shadow_verified',
    'R296 both copies verified in the initial shadow phase');
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitService::switch($plan['id']),'capability_denied',
    'R296 provider switch denies unprivileged operator');
err(fn()=>ProviderExitService::purgeSource($plan['id']),'capability_denied',
    'R296 destructive source purge denies unprivileged operator');
unset($GLOBALS['scm_test_denied_caps']);
$targetKey=$shadow['items'][1]['target_key'];
$target->delete($targetKey);
r296put($target,$targetKey,'r296-corrupted-private');
err(fn()=>ProviderExitService::switch($plan['id']),
    'provider_exit_preflight_integrity_failed',
    'R296 stale second target stops switch before any first mapping changes');
foreach($rows as [$key,$bytes,$id]){
    $current=RecordStore::get('asset',$id);
    ok($current['object_key']===$key&&
       $current['storage']['provider_id']==='source-private',
       'R296 source mapping remains authoritative after preflight failure');
}
$target->delete($targetKey);
r296put($target,$targetKey,$rows[1][1]);
$source->delete($rows[0][0]);
err(fn()=>ProviderExitService::switch($plan['id']),'object_not_found',
    'R296 missing rollback source also stops switch before any remap');
r296put($source,$rows[0][0],$rows[0][1]);
$currentPlan=RecordStore::get('provider_exit',$plan['id']);
$original=$currentPlan['items'][0]['target_storage'];
$currentPlan['items'][0]['target_storage']['provider_id']='forged-target';
RecordStore::put('provider_exit',$plan['id'],$currentPlan,(int)$currentPlan['version']);
err(fn()=>ProviderExitService::switch($plan['id']),'provider_exit_target_storage_invalid',
    'R296 forged target provider identity rejected before remap');
$currentPlan=RecordStore::get('provider_exit',$plan['id']);
$currentPlan['items'][0]['target_storage']=$original;
RecordStore::put('provider_exit',$plan['id'],$currentPlan,(int)$currentPlan['version']);
$done=ProviderExitService::switch($plan['id']);
ok($done['status']==='switched'&&$done['switched']===2,
    'R296 valid complete preflight permits two object mapping transitions');
foreach($rows as [$key,$bytes,$id]){
    $current=RecordStore::get('asset',$id);
    ok($current['storage']['provider_id']==='target-private'&&$source->exists($key),
       'R296 target activated without deleting rollback source');
}
echo "REVIEW ROUND 296 PROVIDER SWITCH PREFLIGHT AND ADMIN AUTHORIZATION: PASS\n";
