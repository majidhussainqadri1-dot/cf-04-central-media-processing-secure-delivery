<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,
    ProviderExitCutoverManifestStage,ProviderExitOwnerReadiness,
    ProviderRegistry,RecordStore,DomainRegistry,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$key=hash('sha256','r310-clinical-source');
$bytes='r310-private-encoded-bytes';
$s=Utils::tempStream();Utils::writeAll($s,$bytes);rewind($s);
try{$source->putStream($key,$s);}finally{fclose($s);}
RecordStore::put('asset','r310-asset',[
    'actor_id'=>11,'owner_domain'=>'file17','object_version'=>7,'status'=>'ready',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
]);
$inventory=ProviderExitInventoryStage::begin('source-private','target-private',11);
while($inventory['status']==='planning')
    $inventory=ProviderExitInventoryStage::advance($inventory['id'],11);
foreach(RecordStore::all('provider_exit_inventory_segment',0,null,25) as $segment){
    if(($segment['inventory_id']??null)!==$inventory['id'])continue;
    ProviderExitShadowCopyStage::copySegment($inventory['id'],$segment['id'],11,25);
}
$plan=ProviderExitCutoverManifestStage::begin($inventory['id'],11);
while($plan['status']==='planning_unfenced')
    $plan=ProviderExitCutoverManifestStage::advance($plan['id'],11);
ok(ProviderExitCutoverManifestStage::verifyComplete($plan['id'],11)['candidates']===1,
    'R310 baseline full physical source and owner mapping is verified');
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'domain_contract_unavailable',
    'R310 absence of native owner approval callback fails closed');
$GLOBALS['r310_owner_version']=3;
$GLOBALS['r310_cas']=1;
$GLOBALS['r310_approved']=true;
$GLOBALS['r310_callback_context']=null;
DomainRegistry::reset();
DomainRegistry::register('file17','1.0.0',[
    'authorize_provider_exit'=>static function(array $context):array {
        $GLOBALS['r310_callback_context']=$context;
        if(!empty($GLOBALS['r310_mutate_during_approval'])){
            $GLOBALS['r310_mutate_during_approval']=false;
            $mutated=RecordStore::get('asset','r310-asset');
            $mutated['object_version']++;
            RecordStore::put('asset',$mutated['id'],$mutated,(int)$mutated['version']);
        }
        return ['allowed'=>true,'approved_for_provider_exit'=>$GLOBALS['r310_approved'],
            'asset_id'=>!empty($GLOBALS['r310_wrong_subject'])?'different-asset':$context['asset_id'],
            'record_id'=>$context['record_id'],
            'source_provider'=>$context['source_provider'],
            'target_provider'=>!empty($GLOBALS['r310_wrong_target'])?'other-provider':$context['target_provider'],
            'object_version'=>$GLOBALS['r310_owner_version'],
            'record_cas'=>$GLOBALS['r310_cas']];
    }
]);
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_owner_approval_stale',
    'R310 stale owner object generation fails closed');
$GLOBALS['r310_owner_version']=7;
$GLOBALS['r310_cas']=20;
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_owner_approval_stale',
    'R310 wrong current record CAS cannot approve provider migration');
$GLOBALS['r310_cas']=RecordStore::get('asset','r310-asset')['version'];
$GLOBALS['r310_approved']=false;
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_owner_approval_stale',
    'R310 owner approval must explicitly opt into provider exit');
$GLOBALS['r310_approved']=true;
$mapping=null;
foreach(RecordStore::all('provider_cutover_segment',0,null,30) as $seg){
    if(($seg['plan_id']??null)===$plan['id']&&
       is_array($seg['mappings']??null)&&$seg['mappings']!==[]){
        $mapping=$seg['mappings'][0];break;
    }
}
ok(is_array($mapping),'R315 native mapping fixture available for direct helper authorization');
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitOwnerReadiness::inspectMapping($mapping,$plan['id']),
    'capability_denied',
    'R315 direct approval helper cannot bypass privileged stage authorization');
unset($GLOBALS['scm_test_denied_caps']);
$GLOBALS['r310_wrong_subject']=true;
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_owner_subject_mismatch',
    'R313 owner cannot approve a different asset with matching CAS/version');
$GLOBALS['r310_wrong_subject']=false;
$GLOBALS['r310_wrong_target']=true;
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_owner_subject_mismatch',
    'R313 owner cannot substitute a different migration target provider');
$GLOBALS['r310_wrong_target']=false;
$GLOBALS['scm_test_denied_caps']=['media_manage_providers'];
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'capability_denied',
    'R310 unauthorized operator cannot request owner approval preflight');
unset($GLOBALS['scm_test_denied_caps']);
$ready=ProviderExitOwnerReadiness::inspect($plan['id'],11);
ok($ready['status']==='owner_approved_unfenced'&&$ready['approvals']===1&&
   $ready['registered_domains']===1&&
   $ready['native_owner_write_fenced']===false&&
   $ready['authorization_to_switch']===false&&
   preg_match('/^[a-f0-9]{64}$/D',$ready['owner_decision_chain'])===1,
   'R310 version-bound native owner callback acknowledged without switch authority');
$context=$GLOBALS['r310_callback_context'];
ok(($context['actor_id']??null)===11&&($context['service_id']??null)==='cf04',
   'R321 exact authenticated administrator and CF04 service identity passed to native owner');
ok(($context['object_version']??null)===7&&
   ($context['phase']??null)==='read_only_preflight'&&
   !array_key_exists('source_key',$context)&&
   !array_key_exists('target_key',$context)&&
   ($context['source_key_sha256']??null)===hash('sha256',$key),
   'R310 owner receives only source/target key hashes, not raw object keys');
ok(RecordStore::get('asset','r310-asset')['object_key']===$key&&
   $source->exists($key)&&RecordStore::all('provider_exit')===[],
   'R310 owner read-only attestation never remaps, purges or switches');
$GLOBALS['r310_mutate_during_approval']=true;
err(fn()=>ProviderExitOwnerReadiness::inspect($plan['id'],11),
    'provider_exit_inventory_drift',
    'R312 owner callback mutation invalidates the post-approval inventory witness');
ok(($GLOBALS['r310_mutate_during_approval']??true)===false,
    'R312 adversarial callback actually changed the source during inspection');
echo "REVIEW ROUND 310 OWNER APPROVAL-ONLY HANDSHAKE: PASS\n";
