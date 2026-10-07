<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\Policy;

$base=policy();
$cases=[
    ['policy_version','1.0','policy_integer_invalid','Policy version rejects decimal text'],
    ['max_size_bytes','1048576.5','policy_integer_invalid','Maximum size rejects fractional text'],
    ['max_upload_parts','10e2','policy_integer_invalid','Part count rejects exponent text'],
    ['max_width','640px','policy_integer_invalid','Dimension rejects unit-suffixed text'],
    ['issued_at','tomorrow','policy_integer_invalid','Issue time rejects non-numeric text'],
];
foreach($cases as [$field,$value,$code,$message]){
    $candidate=$base;$candidate[$field]=$value;
    err(fn()=>Policy::normalize($candidate),$code,$message);
}
$retention=$base;$retention['retention']['source_seconds']='forever';
err(fn()=>Policy::normalize($retention),'policy_integer_invalid','Retention rejects non-integer text');
$delivery=$base;$delivery['delivery']['grant_ttl_seconds']='300s';
err(fn()=>Policy::normalize($delivery),'policy_integer_invalid','Delivery TTL rejects unit-suffixed text');
$rightsVersion=$base;$rightsVersion['rights']['rights_version']='1.0';
err(fn()=>Policy::normalize($rightsVersion),'rights_integer_invalid','Rights version rejects decimal text');
$rightsExpiry=$base;$rightsExpiry['rights']['expires_at']='never';
err(fn()=>Policy::normalize($rightsExpiry),'rights_integer_invalid','Rights expiry rejects non-integer text');

$canonical=$base;
$canonical['policy_version']='2';
$canonical['max_size_bytes']='1048576';
$canonical['max_part_size_bytes']='65536';
$canonical['max_upload_parts']='16';
$canonical['retention']['temporary_seconds']='60';
$canonical['delivery']['grant_ttl_seconds']='300';
$canonical['rights']['rights_version']='2';
$normalized=Policy::normalize($canonical);
ok($normalized['policy_version']===2&&$normalized['max_size_bytes']===1048576&&$normalized['rights']['rights_version']===2,'Canonical decimal integer strings normalize safely');

echo "REVIEW ROUND 136 POLICY INTEGER CONTRACTS: PASS\n";
