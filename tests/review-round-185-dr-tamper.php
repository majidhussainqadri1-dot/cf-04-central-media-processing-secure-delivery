<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,DisasterCostRoutingService};
RecordStore::resetMemory();
$p=policy('document','C3',['view','download']);
$a=RecordStore::put('asset','r185-tamper',[
'actor_id'=>11,'asset_id'=>'r185-tamper','status'=>'ready',
'owner_domain'=>'file17','owner_object'=>'r185-object','object_version'=>1,
'sha256'=>hash('sha256','r185-tamper'),'policy'=>$p,
'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$input=['primary_region'=>'TEST-A','secondary_region'=>'TEST-B',
'rpo_seconds'=>300,'rto_seconds'=>900];
$plan=DisasterCostRoutingService::disasterPlan($a['id'],11,$input);
$id=hash('sha256',$a['id']);
$bad=$plan;$bad['audit_key']=str_repeat('0',64);
RecordStore::put('disaster_plan',$id,$bad,(int)$plan['version']);
err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'dr_plan_record_invalid','Round 185 tampered audit binding denied');
RecordStore::resetMemory();
$a=RecordStore::put('asset','r185-stale',array_replace($a,[
    'id'=>'r185-stale','asset_id'=>'r185-stale']));
DisasterCostRoutingService::disasterPlan($a['id'],11,$input);
$a['sha256']=hash('sha256','changed');
RecordStore::put('asset',$a['id'],$a,(int)$a['version']);
err(fn()=>DisasterCostRoutingService::disasterPlan($a['id'],11,$input),
    'dr_plan_record_invalid','Round 185 stale asset hash denied');
echo "REVIEW ROUND 185 DR TAMPER: PASS\n";
