<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DisasterCostRoutingService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','round188-cost-asset',[
    'actor_id'=>11,'asset_id'=>'round188-cost-asset','status'=>'ready','size'=>1048576,
    'policy'=>['retention'=>['class'=>'standard']],'rights'=>['expires_at'=>0],'privacy_class'=>'C0'
]);
$prices=['storage_gb_month'=>0.02,'transcode_minute'=>0.01,'egress_gb'=>0.05,'currency'=>'USD'];
$good=DisasterCostRoutingService::costEstimate($asset['id'],$prices,['minutes'=>10,'egress_gb'=>1]);
ok($good['currency']==='USD' && $good['estimated_cost']>0,'Round 188 explicit canonical currency preserved');
$zero=DisasterCostRoutingService::costEstimate($asset['id'],$prices,['minutes'=>0,'egress_gb'=>0]);
ok($zero['currency']==='USD' && $zero['components']['minutes']===0.0,'Round 188 canonical zero plan remains accepted');
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$prices+['unquoted_component'=>1],[]),
    'cost_price_invalid','Round 188 unknown provider price component fails closed');
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$prices,['minutes'=>10,'unplanned_component'=>2]),
    'cost_plan_invalid','Round 188 unknown plan component fails closed');
$missing=$prices;unset($missing['currency']);
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$missing,[]),
    'cost_currency_invalid','Round 188 missing currency cannot silently become USD');
foreach(['usd','Usd','uSD','USd','USD ', '',null,[],123] as $bad){
    $invalid=$prices;$invalid['currency']=$bad;
    err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$invalid,[]),
        'cost_currency_invalid','Round 188 noncanonical currency rejected');
}
echo "REVIEW ROUND 188 COST CURRENCY CONTRACT: PASS\n";
