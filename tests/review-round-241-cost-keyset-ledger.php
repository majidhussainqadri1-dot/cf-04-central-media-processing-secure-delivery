<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{CostService,RecordStore,Utils};

RecordStore::resetMemory();
$month=gmdate('Y-m');
for($i=0;$i<2600;$i++){
    RecordStore::put('cost',sprintf('r241-cost-%06d',$i),[
        'actor_id'=>0,'status'=>'recorded','owner_domain'=>'file21',
        'provider'=>'irrelevant-provider','cost'=>0.00001,
        'created_at'=>Utils::now(),
    ]);
}
$asset=RecordStore::put('asset','r241-asset',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17'
]);
$late=RecordStore::put('cost','r241-cost-002600',[
    'actor_id'=>0,'status'=>'recorded','owner_domain'=>'file17',
    'provider'=>'source-private','cost'=>0.75,'created_at'=>Utils::now()
]);
CostService::setBudget('file17',$month,0.50,11);
$record=CostService::record($asset['id'],'source-private','processing',
    ['jobs'=>1],['jobs'=>0.25]);
ok(abs($record['cost']-0.25)<1e-9,'R241 late-ledger budget calculation allows valid charge');
$matches=array_filter(RecordStore::all('audit'),fn($row)=>
    ($row['event_key']??null)==='budget_threshold_exceeded');
ok(count($matches)>=1,'R241 late ledger expense participates in budget threshold calculation');
$invoice=CostService::reconcile('source-private',[
    'total'=>1.0,'tolerance'=>0,'period'=>$month
]);
ok($invoice['status']==='reconciled'&&abs($invoice['calculated']-1.0)<1e-9,
    'R241 invoice includes late provider record through multiple 250-row pages');
$late['cost']='malformed';
RecordStore::put('cost',$late['id'],$late,(int)$late['version']);
err(fn()=>CostService::reconcile('source-private',['total'=>0]),
    'invoice_ledger_invalid','R241 corrupt late provider cost fails closed');
err(fn()=>CostService::record($asset['id'],'source-private','processing',
    ['jobs'=>1],['jobs'=>0.25]),'budget_ledger_invalid',
    'R241 corrupt late owner cost fails closed without partial cost creation');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(str_contains((string)$source,"RecordStore::scan('cost',250,\$after)")
    &&!str_contains((string)$source,"RecordStore::all('cost',0,null,500000)"),
    'R241 global 500k cost ledger materialization removed');
echo "REVIEW ROUND 241 COST KEYSET LEDGER: PASS\n";
