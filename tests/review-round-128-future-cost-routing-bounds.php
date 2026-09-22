<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DisasterCostRoutingService,RecordStore};
$asset=RecordStore::put('asset','round128-asset',['actor_id'=>11,'asset_id'=>'round128-asset','status'=>'ready','size'=>1048576,'policy'=>['retention'=>['class'=>'standard']],'rights'=>['expires_at'=>0],'privacy_class'=>'C0']);
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],['storage_gb_month'=>INF,'transcode_minute'=>0.01,'egress_gb'=>0.01],[]),'cost_price_invalid','ROUND 128 non-finite provider prices fail closed');
err(fn()=>DisasterCostRoutingService::costEstimate($asset['id'],['storage_gb_month'=>0.01,'transcode_minute'=>0.01,'egress_gb'=>0.01],['minutes'=>NAN]),'cost_plan_invalid','ROUND 128 non-finite plan quantities fail closed');
err(fn()=>DisasterCostRoutingService::autoRoute([['id'=>'','approved'=>true,'healthy'=>true,'capabilities'=>['video'],'region'=>'PK','cost_score'=>0,'latency_score'=>0]],['capabilities'=>['video']]),'approved_provider_unavailable','ROUND 128 approved route requires a valid provider identity');
err(fn()=>DisasterCostRoutingService::autoRoute([['id'=>'p-bad','approved'=>true,'healthy'=>true,'capabilities'=>['video'],'region'=>'PK','cost_score'=>NAN,'latency_score'=>0]],['capabilities'=>['video']]),'approved_provider_unavailable','ROUND 128 routing excludes non-finite scoring evidence');
echo "REVIEW ROUND 128 FUTURE COST/ROUTING BOUNDS: PASS\n";