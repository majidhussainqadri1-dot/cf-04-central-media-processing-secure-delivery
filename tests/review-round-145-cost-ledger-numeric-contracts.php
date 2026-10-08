<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{CostService,RecordStore,Utils};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','round145-asset',['actor_id'=>11,'status'=>'ready','owner_domain'=>'file17']);
$period=gmdate('Y-m');
CostService::setBudget('file17',$period,0.01,11);
$valid=CostService::record($asset['id'],'source-private','processing',['bytes'=>1000,'jobs'=>2],['bytes'=>0.00001,'jobs'=>0.01]);
ok($valid['cost']===0.03&&$valid['currency']==='USD','Round 145 canonical cost and explicit rates');
$invoice=CostService::reconcile('source-private',['total'=>0.03,'tolerance'=>0,'period'=>$period]);
ok($invoice['status']==='reconciled','Round 145 canonical invoice reconciles');
foreach(['junk','10junk','1e2',' 1','-1',true,[],null,INF,NAN] as $bad){
    err(fn()=>CostService::record($asset['id'],'source-private','processing',['bytes'=>$bad],['bytes'=>1]),'cost_value_invalid','Round 145 invalid unit');
    err(fn()=>CostService::record($asset['id'],'source-private','processing',['bytes'=>1],['bytes'=>$bad]),'cost_value_invalid','Round 145 invalid rate');
    err(fn()=>CostService::reconcile('source-private',['total'=>$bad]),'invoice_invalid','Round 145 invalid invoice total');
    err(fn()=>CostService::reconcile('source-private',['total'=>0,'tolerance'=>$bad]),'invoice_invalid','Round 145 invalid invoice tolerance');
}
err(fn()=>CostService::record($asset['id'],'source-private','processing',['bytes'=>1],[]),'cost_rate_missing','Round 145 missing rate');
err(fn()=>CostService::reconcile('source-private',['total'=>0,'period'=>[]]),'invoice_invalid','Round 145 invalid period type');
err(fn()=>CostService::reconcile('source-private',['total'=>0,'period'=>'2026-1']),'invoice_period_invalid','Round 145 noncanonical month');
$badDomain=RecordStore::put('asset','round145-invalid-domain',['actor_id'=>11,'status'=>'ready','owner_domain'=>'file17 ']);
err(fn()=>CostService::record($badDomain['id'],'source-private','processing',['jobs'=>1],['jobs'=>1]),'cost_identity_invalid','Round 145 owner attribution');
RecordStore::put('cost','round145-corrupt',['actor_id'=>0,'status'=>'recorded','owner_domain'=>'file17','provider'=>'source-private','cost'=>'99junk','created_at'=>Utils::now()]);
err(fn()=>CostService::reconcile('source-private',['total'=>0]),'invoice_ledger_invalid','Round 145 corrupted invoice ledger');
err(fn()=>CostService::record($asset['id'],'source-private','processing',['jobs'=>1],['jobs'=>1]),'budget_ledger_invalid','Round 145 corrupted budget ledger');
RecordStore::resetMemory();
$other=RecordStore::put('asset','round145-preflight',['actor_id'=>11,'status'=>'ready','owner_domain'=>'file21']);
RecordStore::put('budget',hash('sha256','file21|'.$period),['actor_id'=>11,'status'=>'active','domain'=>'file21','period'=>$period,'limit'=>'bad']);
$before=count(RecordStore::all('cost'));
err(fn()=>CostService::record($other['id'],'source-private','processing',['jobs'=>1],['jobs'=>1]),'budget_ledger_invalid','Round 145 malformed budget');
ok(count(RecordStore::all('cost'))===$before,'Round 145 no partial cost write on bad budget');
echo "REVIEW ROUND 145 COST LEDGER NUMERIC CONTRACTS: PASS\n";
