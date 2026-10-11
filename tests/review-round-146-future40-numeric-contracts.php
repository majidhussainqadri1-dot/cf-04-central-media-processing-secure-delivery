<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DisasterCostRoutingService,RecordStore};
RecordStore::resetMemory();
$asset=RecordStore::put('asset','round146-asset',['actor_id'=>11,'status'=>'ready','size'=>1048576,'sha256'=>hash('sha256','round146'),'policy'=>['retention'=>['class'=>'standard']],'rights'=>['expires_at'=>0],'privacy_class'=>'C0']);
$prices=['storage_gb_month'=>0.02,'transcode_minute'=>0.01,'egress_gb'=>0.05,'currency'=>'USD'];
$good=DisasterCostRoutingService::costEstimate($asset['id'],$prices,['minutes'=>10,'egress_gb'=>1]);
ok($good['estimated_cost']>0,'Round 146 canonical cost estimate');
$dr=DisasterCostRoutingService::disasterPlan($asset['id'],11,['primary_region'=>'TEST-A','secondary_region'=>'TEST-B','rpo_seconds'=>300,'rto_seconds'=>900]);
ok($dr['rpo_seconds']===300&&$dr['rto_seconds']===900,'Round 146 canonical disaster recovery targets');
$tier=DisasterCostRoutingService::optimizeTier($asset['id'],['accesses_30d'=>0,'age_days'=>365,'cost_score'=>0.01]);
ok($tier['reason']['age_days']===365,'Round 146 canonical tier metrics');
foreach(['junk','10junk','1e2',' 1','-1',true,[],null,INF,NAN] as $bad){
    err(fn()=>DisasterCostRoutingService::disasterPlan($asset['id'],11,['primary_region'=>'TEST-A','secondary_region'=>'TEST-B','rpo_seconds'=>$bad,'rto_seconds'=>900]),$bad===null?'dr_plan_incomplete':'dr_plan_numeric_invalid','Round 146 malformed RPO');
    err(fn()=>DisasterCostRoutingService::optimizeTier($asset['id'],['accesses_30d'=>$bad]),'storage_tier_metric_invalid','Round 146 malformed access metric');
    err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],['storage_gb_month'=>$bad,'transcode_minute'=>0.01,'egress_gb'=>0.01],[]),'cost_price_invalid','Round 146 malformed provider price');
    err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$prices,['minutes'=>$bad]),'cost_plan_invalid','Round 146 malformed planned minutes');
}
err(fn()=>DisasterCostRoutingService::disasterPlan($asset['id'],11,['primary_region'=>[],'secondary_region'=>'TEST-B','rpo_seconds'=>1,'rto_seconds'=>1]),'dr_regions_invalid','Round 146 region type');
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],$prices+['currency'=>'USD'],['egress_gb'=>null]),'cost_plan_invalid','Round 146 explicit null plan quantity');
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],array_replace($prices,['currency'=>'not-a-currency']),[]),'cost_currency_invalid','Round 146 currency code');
$badAsset=RecordStore::put('asset','round146-badsize',['actor_id'=>11,'status'=>'ready','size'=>'1048576junk']);
err(fn()=>DisasterCostRoutingService::costEstimate($badAsset['id'],$prices,[]),'cost_asset_size_invalid','Round 146 persisted asset size');
$badRights=RecordStore::put('asset','round146-badrights',['actor_id'=>11,'status'=>'ready','rights'=>['expires_at'=>'garbage']]);
err(fn()=>DisasterCostRoutingService::optimizeTier($badRights['id'],[]),'storage_tier_rights_invalid','Round 146 rights expiry cannot become unlimited');
$badRoute=[['id'=>'route-one','approved'=>true,'healthy'=>true,'capabilities'=>['video'],'region'=>'TEST-A','cost_score'=>'junk','latency_score'=>0]];
err(fn()=>DisasterCostRoutingService::autoRoute($badRoute,['capabilities'=>['video']]),'approved_provider_unavailable','Round 146 malformed route score excluded');
err(fn()=>DisasterCostRoutingService::autoRoute([],['capabilities'=>'video']),'route_requirements_invalid','Round 146 requirements shape');
$goodRoute=DisasterCostRoutingService::autoRoute([['id'=>'route-one','approved'=>true,'healthy'=>true,'capabilities'=>['video'],'region'=>'TEST-A','cost_score'=>1,'latency_score'=>2]],['capabilities'=>['video']]);
ok($goodRoute['provider']==='route-one','Round 146 canonical approved routing');
echo "REVIEW ROUND 146 FUTURE-40 NUMERIC CONTRACTS: PASS\n";
