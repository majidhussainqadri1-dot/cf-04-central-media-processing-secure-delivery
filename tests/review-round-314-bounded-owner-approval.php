<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,
    ProviderExitCutoverManifestStage,ProviderExitOwnerApprovalCheckpointStage,
    ProviderRegistry,RecordStore,DomainRegistry,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$target=ProviderRegistry::get('target-private');
for($i=0;$i<2;$i++){
    $key=hash('sha256','r314-original-'.$i);
    $bytes='r314-sensitive-'.$i;
    $s=Utils::tempStream();Utils::writeAll($s,$bytes);rewind($s);
    try{$source->putStream($key,$s);}finally{fclose($s);}
    RecordStore::put('asset','r314-asset-'.$i,[
        'actor_id'=>11,'owner_domain'=>$i===0?'file17':'file12',
        'object_version'=>11+$i,'status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
    ]);
}
$inv=ProviderExitInventoryStage::begin('source-private','target-private',11);
while($inv['status']==='planning')
    $inv=ProviderExitInventoryStage::advance($inv['id'],11);
foreach(RecordStore::all('provider_exit_inventory_segment',0,null,20) as $seg)
    if(($seg['inventory_id']??null)===$inv['id'])
        ProviderExitShadowCopyStage::copySegment($inv['id'],$seg['id'],11,25);
$plan=ProviderExitCutoverManifestStage::begin($inv['id'],11);
while($plan['status']==='planning_unfenced')
    $plan=ProviderExitCutoverManifestStage::advance($plan['id'],11);
DomainRegistry::reset();
$approval=static function(array $c):array {
    return ['allowed'=>true,'approved_for_provider_exit'=>true,
        'asset_id'=>$c['asset_id'],'record_id'=>$c['record_id'],
        'source_provider'=>$c['source_provider'],
        'target_provider'=>$c['target_provider'],
        'object_version'=>$c['object_version'],'record_cas'=>$c['record_cas']];
};
DomainRegistry::register('file17','1.0.0',['authorize_provider_exit'=>$approval]);
DomainRegistry::register('file12','1.0.0',['authorize_provider_exit'=>$approval]);
$run=ProviderExitOwnerApprovalCheckpointStage::begin($plan['id'],11);
$loops=0;
while($run['status']==='collecting_unfenced'&&$loops++<20)
    $run=ProviderExitOwnerApprovalCheckpointStage::advance($run['id'],11);
ok($run['status']==='owner_approved_unfenced'&&$run['approvals']===2&&$loops<20,
    'R314 bounded durable owner approval coordinator completes all source segments');
$proof=ProviderExitOwnerApprovalCheckpointStage::verifyComplete($run['id'],11);
ok($proof['approvals']===2&&$proof['segments']===2&&
   $proof['native_owner_write_fenced']===false&&
   $proof['authorization_to_switch']===false,
   'R314 complete approval evidence is expressly unfenced and non-executable');
$again=ProviderExitOwnerApprovalCheckpointStage::advance($run['id'],11);
ok($again['version']===$run['version'],
    'R314 completed owner approval checkpoint is idempotent');
$segments=RecordStore::all('provider_owner_approval_segment',0,null,30);
ok(count($segments)===2,'R314 every owner segment retains a separate CAS journal');
$broken=$segments[0];$broken['decision_hash']=hash('sha256','r314-forged');
$changed=RecordStore::put('provider_owner_approval_segment',
    $broken['id'],$broken,(int)$broken['version']);
err(fn()=>ProviderExitOwnerApprovalCheckpointStage::verifyComplete($run['id'],11),
    'provider_owner_checkpoint_drift',
    'R314 forged saved owner approval hash cannot pass final verification');
$restore=$segments[0];
RecordStore::put('provider_owner_approval_segment',
    $broken['id'],$restore,(int)$changed['version']);
ok(ProviderExitOwnerApprovalCheckpointStage::verifyComplete($run['id'],11)
    ['approvals']===2,'R314 authentic owner approval checkpoint can be reverified');
ok(ProviderRegistry::activeId()==='source-private'&&
   RecordStore::get('asset','r314-asset-0')['storage']['provider_id']==='source-private'&&
   RecordStore::all('provider_exit')===[],
   'R314 journals neither remap providers nor create legacy cutover or delete sources');
echo "REVIEW ROUND 314 DURABLE BOUNDED OWNER APPROVAL: PASS\n";
