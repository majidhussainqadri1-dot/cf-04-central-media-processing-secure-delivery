<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,RecordStore,DisasterCostRoutingService};
RecordStore::resetMemory();
$p=policy('document','C3',['view','download']);
$a=RecordStore::put('asset','r185-bounds',[
'actor_id'=>11,'asset_id'=>'r185-bounds','status'=>'ready',
'owner_domain'=>'file17','owner_object'=>'r185-object','object_version'=>1,
'sha256'=>hash('sha256','r185-bounds'),'policy'=>$p,
'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$good=['primary_region'=>'TEST-A','secondary_region'=>'TEST-B',
'rpo_seconds'=>300,'rto_seconds'=>900];
foreach([31536001,PHP_INT_MAX,'123junk',true,[]] as $bad){
    $input=$good;$input['rto_seconds']=$bad;
    err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'dr_plan_numeric_invalid','Round 185 bounded DR target');
}
err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$good+['notes'=>'secret']),
    'dr_plan_invalid','Round 185 extra field denied');
DomainRegistry::register('r185-deny','1.0.0',[
    'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$b=$a;$b['owner_domain']='r185-deny';
RecordStore::put('asset','r185-denied',$b);
err(fn()=>DisasterCostRoutingService::disasterPlan('r185-denied',11,$good),
    'domain_authorization_denied','Round 185 owner denial enforced');
echo "REVIEW ROUND 185 DR AUTH AND BOUNDS: PASS\n";
