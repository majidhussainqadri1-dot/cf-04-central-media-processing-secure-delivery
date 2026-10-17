<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\DeliveryResilienceService;
$base=['rtt_ms'=>200,'mbps'=>20.0,'unstable'=>false];
foreach([
['rtt_ms'=>'200'],['rtt_ms'=>-1],['rtt_ms'=>70000],
['mbps'=>'20'],['mbps'=>NAN],['mbps'=>INF],['mbps'=>-1],
['unstable'=>'false'],['unknown'=>true]
] as $change)
err(fn()=>DeliveryResilienceService::adaptiveUpload(array_replace($base,$change),[]),
'network_profile_invalid','Round 179 malformed network evidence rejected');
foreach([
['max_part_size_bytes'=>'8388608'],['max_part_size_bytes'=>65535],
['max_part_size_bytes'=>67108865],['max_parallel_parts'=>0],
['max_parallel_parts'=>'4'],['unexpected'=>1]
] as $bounds)
err(fn()=>DeliveryResilienceService::adaptiveUpload($base,$bounds),
'network_profile_invalid','Round 179 malformed upload policy bounds rejected');
$fast=DeliveryResilienceService::adaptiveUpload($base,['max_parallel_parts'=>2]);
ok($fast['parallel_parts']===2&&$fast['part_size_bytes']===8388608,
'Round 179 approved bounds constrain concurrency and part size');
$unstable=DeliveryResilienceService::adaptiveUpload(
['rtt_ms'=>550,'mbps'=>1.5,'unstable'=>true],['max_part_size_bytes'=>8388608]);
ok($unstable['parallel_parts']===1&&$unstable['part_size_bytes']===1048576,
'Round 179 unstable network uses conservative upload profile');
echo "REVIEW ROUND 179 ADAPTIVE UPLOAD BOUNDS: PASS\n";
