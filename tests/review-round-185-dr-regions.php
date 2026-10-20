<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,DisasterCostRoutingService};
RecordStore::resetMemory();
$p=policy('document','C3',['view','download']);
$a=RecordStore::put('asset','r185-dr',[
'actor_id'=>11,'asset_id'=>'r185-dr','status'=>'ready',
'owner_domain'=>'file17','owner_object'=>'r185-object','object_version'=>1,
'sha256'=>hash('sha256','r185'),'policy'=>$p,
'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$good=['primary_region'=>'TEST-A','secondary_region'=>'TEST-B',
'rpo_seconds'=>300,'rto_seconds'=>900];
$one=DisasterCostRoutingService::disasterPlan($a['id'],11,$good);
$again=DisasterCostRoutingService::disasterPlan($a['id'],11,$good);
ok($one['version']===1&&$again['version']===1,'Round 185 DR no-op is idempotent');
foreach(['test-a','TEST-A'.str_repeat('X',40),'TEST A',[]] as $bad){
    $input=$good;$input['primary_region']=$bad;
    err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'dr_regions_invalid','Round 185 canonical region required');
}
echo "REVIEW ROUND 185 DR REGIONS: PASS\n";
