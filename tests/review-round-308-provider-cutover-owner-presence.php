<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,
    ProviderExitCutoverManifestStage,ProviderRegistry,RecordStore,Utils};

RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$key=hash('sha256','r308-source');
$data='r308-sensitive-media';
$stream=Utils::tempStream();Utils::writeAll($stream,$data);rewind($stream);
try{$source->putStream($key,$stream);}finally{fclose($stream);}
$assetId='r308-fake-owner';
$asset=RecordStore::put('asset',$assetId,[
    'actor_id'=>11,'owner_domain'=>'nonexistent_owner',
    'object_version'=>3,'status'=>'ready',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>$key,'sha256'=>hash('sha256',$data),'size'=>strlen($data)
]);
$inventory=ProviderExitInventoryStage::begin('source-private','target-private',11);
while($inventory['status']==='planning')
    $inventory=ProviderExitInventoryStage::advance($inventory['id'],11);
foreach(RecordStore::all('provider_exit_inventory_segment',0,null,20) as $seg){
    if($seg['inventory_id']!==$inventory['id'])continue;
    ProviderExitShadowCopyStage::copySegment($inventory['id'],$seg['id'],11,25);
}
$plan=ProviderExitCutoverManifestStage::begin($inventory['id'],11);
err(fn()=>ProviderExitCutoverManifestStage::advance($plan['id'],11),
    'provider_cutover_owner_unregistered',
    'R308 unregistered native owner cannot produce a fake complete cutover manifest');
ok((RecordStore::get('provider_cutover_plan',$plan['id'])['status']??null)==='planning_unfenced',
    'R308 unregistered owner leaves cutover manifest unfinished');
$changed=RecordStore::get('asset',$assetId);
$changed['owner_domain']='file17';
RecordStore::put('asset',$assetId,$changed,(int)$changed['version']);
$plan=ProviderExitCutoverManifestStage::advance($plan['id'],11);
while(($plan['status']??null)==='planning_unfenced')
    $plan=ProviderExitCutoverManifestStage::advance($plan['id'],11);
$proof=ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11);
ok($proof['status']==='verified_unfenced'&&$proof['candidates']===1&&
   $proof['owner_write_fenced']===false&&
   $proof['authorization_to_switch']===false,
   'R308 registered owner only allows proof generation, never cutover or consent');
ok(RecordStore::get('asset',$assetId)['object_key']===$key&&$source->exists($key),
   'R308 owner check cannot remap or destroy original encrypted object');
echo "REVIEW ROUND 308 REGISTERED NATIVE OWNER PRESENCE: PASS\n";
