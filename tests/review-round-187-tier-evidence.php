<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,DisasterCostRoutingService as Tier};
RecordStore::resetMemory();
$base=['actor_id'=>11,'status'=>'ready','rights'=>['expires_at'=>0],
'policy'=>['retention'=>['class'=>'private-standard']],
'privacy_class'=>'C3'];
foreach([
    [['rights'=>[]],'storage_tier_rights_invalid'],
    [['rights'=>['expires_at'=>null]],'storage_tier_rights_invalid'],
    [['policy'=>['retention'=>['class'=>[]]]],'storage_tier_policy_invalid'],
    [['policy'=>['retention'=>['class'=>'bad class']]],'storage_tier_policy_invalid'],
    [['privacy_class'=>[]],'storage_tier_privacy_invalid'],
    [['privacy_class'=>'C9'],'storage_tier_privacy_invalid']
] as $i=>$case){
    $asset=RecordStore::put('asset','r187-'.$i,array_replace($base,$case[0]));
    err(fn()=>Tier::optimizeTier($asset['id'],[]),$case[1],
    'Round 187 malformed rights or metadata denied');
}
echo "REVIEW ROUND 187 TIER EVIDENCE: PASS\n";
