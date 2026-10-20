<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Audit,RecordStore,DisasterCostRoutingService};
RecordStore::resetMemory();
$p=policy('document','C3',['view','download']);
$a=RecordStore::put('asset','r185-audit',[
'actor_id'=>11,'asset_id'=>'r185-audit','status'=>'ready',
'owner_domain'=>'file17','owner_object'=>'r185-object','object_version'=>1,
'sha256'=>hash('sha256','r185-audit'),'policy'=>$p,
'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$input=['primary_region'=>'TEST-A','secondary_region'=>'TEST-B',
'rpo_seconds'=>300,'rto_seconds'=>900];
Audit::failNextForTest('future40_disaster_plan_intent');
err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'audit_write_failed','Round 185 intent failure blocks plan');
$id=hash('sha256',$a['id']);
ok(RecordStore::get('disaster_plan',$id)===null,'Round 185 no mutation after intent failure');
Audit::failNextForTest('future40_disaster_plan_set');
err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'audit_write_failed','Round 185 applied audit failure');
$pending=RecordStore::get('disaster_plan',$id);
ok($pending['version']===1&&isset($pending['audit_key']),'Round 185 pending audit bound');
$healed=DisasterCostRoutingService::disasterPlan($a['id'],11,$input);
ok($healed['version']===1&&Audit::verifyChain(),'Round 185 retry heals audit without version bump');
echo "REVIEW ROUND 185 DR AUDIT RECOVERY: PASS\n";
