<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\DisasterCostRoutingService as Router;
$good=['id'=>'p-one','approved'=>true,'healthy'=>true,
    'capabilities'=>['video'],'region'=>'TEST-A',
    'cost_score'=>1,'latency_score'=>2];
$req=['capabilities'=>['video'],'regions'=>['TEST-A']];
foreach([
    ['id'=>'P-ONE'],['id'=>'p-one'.str_repeat('x',70)],
    ['region'=>'test-a'],['region'=>'TEST-A'.str_repeat('x',40)],
    ['capabilities'=>['video','video']],
    ['capabilities'=>['video',[]]],
    ['capabilities'=>['Video']],
    ['capabilities'=>array_fill(0,33,'video')],
] as $change){
    $bad=array_replace($good,$change);
    err(fn()=>Router::autoRoute([$bad],$req),
        'approved_provider_unavailable','Round 186 malformed provider excluded');
}
err(fn()=>Router::autoRoute([$good,$good],$req),
    'route_provider_conflict','Round 186 duplicate approved provider identity rejected');
$second=array_replace($good,['id'=>'p-two','cost_score'=>0,'latency_score'=>1]);
ok(Router::autoRoute([$good,$second],$req)['provider']==='p-two',
    'Round 186 lower combined score wins deterministically');
echo "REVIEW ROUND 186 ROUTE PROVIDERS: PASS\n";
