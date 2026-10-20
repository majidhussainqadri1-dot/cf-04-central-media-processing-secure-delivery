<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\DisasterCostRoutingService as Router;
$p=[['id'=>'p-one','approved'=>true,'healthy'=>true,
    'capabilities'=>['video','av1'],'region'=>'TEST-A',
    'cost_score'=>1,'latency_score'=>1]];
$req=['capabilities'=>['video'],'regions'=>['TEST-A']];
ok(Router::autoRoute($p,$req)['provider']==='p-one','Round 186 canonical route');
foreach([
    ['capabilities'=>['video','video']],
    ['capabilities'=>['Video']],
    ['capabilities'=>['video?']],
    ['capabilities'=>array_fill(0,33,'video')],
    ['regions'=>['TEST-A','TEST-A']],
    ['regions'=>['test-a']],
    ['regions'=>['TEST-A'.str_repeat('X',32)]],
    ['capabilities'=>['video'],'unexpected'=>true],
] as $bad){
    err(fn()=>Router::autoRoute($p,$bad),
        'route_requirements_invalid','Round 186 malformed requirements rejected');
}
err(fn()=>Router::autoRoute(array_fill(0,257,$p[0]),$req),
    'route_providers_invalid','Round 186 oversized provider list rejected');
echo "REVIEW ROUND 186 ROUTE REQUIREMENTS: PASS\n";
