<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,DisasterCostRoutingService as Tier};
RecordStore::resetMemory();
$a=RecordStore::put('asset','r187-asset',[
'actor_id'=>11,'status'=>'ready','rights'=>['expires_at'=>0],
'policy'=>['retention'=>['class'=>'private-standard']],
'privacy_class'=>'C3']);
$out=Tier::optimizeTier($a['id'],['accesses_30d'=>20,'age_days'=>365]);
ok($out['recommended_tier']==='warm'&&!$out['automatic_move'],
    'Round 187 valid recommendation remains advisory');
foreach([
    ['extra'=>'x'],['accesses_30d'=>1000000001],
    ['age_days'=>365001],['cost_score'=>NAN],
    ['accesses_30d'=>null],['age_days'=>null],['cost_score'=>null],
    ['accesses_30d'=>[]],['age_days'=>true],['cost_score'=>'1junk']
] as $bad){
    err(fn()=>Tier::optimizeTier($a['id'],$bad),
    'storage_tier_metric_invalid','Round 187 invalid metrics denied');
}
echo "REVIEW ROUND 187 TIER METRICS: PASS\n";
