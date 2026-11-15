<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,
    ProviderExitCutoverManifestStage,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$originals=[];
function r306put(string $key,string $data):void {
    global $source;
    $stream=Utils::tempStream();Utils::writeAll($stream,$data);rewind($stream);
    try{$source->putStream($key,$stream);}finally{fclose($stream);}
}
for($i=0;$i<2;$i++){
    $id='r306-asset-'.$i;
    $key=hash('sha256','r306-original-'.$i);
    $data='r306-private-media-payload-'.$i;
    r306put($key,$data);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'owner_domain'=>$i===0?'file17':'file12',
        'object_version'=>1+$i,'status'=>'ready',
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>$key,'sha256'=>hash('sha256',$data),
        'size'=>strlen($data)
    ]);
    $originals[$id]=[$key,$data];
}
$key=hash('sha256','r306-private-derivative');
$data='r306-derived-private-image';
r306put($key,$data);
RecordStore::put('derivative','r306-derivative',[
    'actor_id'=>11,'asset_id'=>'r306-asset-0','status'=>'validated',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>$key,'sha256'=>hash('sha256',$data),'size'=>strlen($data)
]);
$originals['r306-derivative']=[$key,$data];
$active=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
while($stage['status']==='planning')
    $stage=ProviderExitInventoryStage::advance($stage['id'],11);
foreach(RecordStore::all('provider_exit_inventory_segment',0,null,20) as $segment){
    if(($segment['inventory_id']??'')!==$stage['id'])continue;
    ProviderExitShadowCopyStage::copySegment($stage['id'],$segment['id'],11,25);
}
ok(ProviderExitShadowCopyStage::verifyComplete($stage['id'],11)['verified_objects']===3,
    'R306 all source and shadow provider ciphertexts preverified');
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitCutoverManifestStage::begin($stage['id'],11),
    'capability_denied','R306 unauthorized actor cannot prepare private owner manifest');
unset($GLOBALS['scm_test_denied_caps']);
$plan=ProviderExitCutoverManifestStage::begin($stage['id'],11);
$loops=0;
while($plan['status']==='planning_unfenced'&&$loops++<20)
    $plan=ProviderExitCutoverManifestStage::advance($plan['id'],11);
ok($plan['status']==='verified_unfenced'&&$loops<20,
    'R306 bounded native owner manifest completes with durable segment checkpoints');
$verified=ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11);
ok($verified['segments']===2&&$verified['candidates']===3&&
    $verified['owner_write_fenced']===false&&
    $verified['authorization_to_switch']===false,
    'R306 manifests bind all owners/records without granting a provider switch');
$segments=RecordStore::all('provider_cutover_segment',0,null,20);
$hasOwner=false;
foreach($segments as $segment){
    foreach($segment['mappings'] as $mapping){
        if($mapping['id']==='r306-asset-1'){
            ok($mapping['native_domain']==='file12'&&
               $mapping['owner_generation']===2&&
               $mapping['record_cas']>=1,
               'R306 file12 native domain, owner version and DB CAS recorded');
            $hasOwner=true;
        }
    }
}
ok($hasOwner,'R306 all distinct owner records included');
$saved=null;
foreach($segments as $segment)if(count($segment['mappings'])>0){$saved=$segment;break;}
ok(is_array($saved),'R306 a canonical nonempty segment exists');
$forged=$saved;
$forged['mappings'][0]['target_key']=hash('sha256','r306-forged-target-key');
$changed=RecordStore::put('provider_cutover_segment',$saved['id'],$forged,(int)$saved['version']);
err(fn()=>ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11),
    'provider_cutover_manifest_drift',
    'R306 forged native target mapping invalidates verified manifest');
RecordStore::put('provider_cutover_segment',$saved['id'],$saved,(int)$changed['version']);
$firstTarget=$saved['mappings'][0]['target_key'];
$target=ProviderRegistry::get('target-private');
$target->delete($firstTarget);
err(fn()=>ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11),
    'object_not_found',
    'R306 missing physical target denies formerly completed cutover manifest');
$origId=$saved['mappings'][0]['id'];
$orig=$originals[$origId];
$s=Utils::tempStream();Utils::writeAll($s,$orig[1]);rewind($s);
try{$target->putStream($firstTarget,$s);}finally{fclose($s);}
ok(ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11)
    ['candidates']===3,
    'R306 reverified source and target restore original non-executable manifest');
foreach($originals as $id=>[$src,$bytes]){
    $kind=$id==='r306-derivative'?'derivative':'asset';
    $row=RecordStore::get($kind,$id);
    ok($row['object_key']===$src&&
       $row['storage']['provider_id']==='source-private'&&
       $source->exists($src),
       'R306 owner record and source ciphertext unchanged before approved cutover');
}
ok(ProviderRegistry::activeId()===$active&&
   RecordStore::all('provider_exit')===[],
   'R306 manifest never triggers legacy cutover, original purge or provider activation');
echo "REVIEW ROUND 306 UNFENCED PROVIDER CUTOVER MANIFEST: PASS\n";
