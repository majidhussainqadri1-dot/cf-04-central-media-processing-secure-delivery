<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\MediaOptimizationService;

ok(MediaOptimizationService::negotiateCodec(['h264','av1'],['av1','h264'])==='av1',
    'Round 172 approved priority preserved');
ok(MediaOptimizationService::negotiateCodec(['H264','AV1'],['av1','h264'])==='av1',
    'Round 172 canonical case folding supported');
foreach([
    [[['av1']],['av1']],
    [['av1'],[['av1']]],
    [['av1!'],['av1']],
    [['av1','AV1'],['av1']],
    [['av1'],[]],
    [array_fill(0,65,'av1'),['av1']],
    [['av1'],['av1','AV1']],
    [['av1'],['av1!']]
] as $pair)
    err(fn()=>MediaOptimizationService::negotiateCodec($pair[0],$pair[1]),
        'codec_profile_invalid','Round 172 malformed codec profiles fail closed');
err(fn()=>MediaOptimizationService::negotiateCodec(['vp9'],['av1']),
    'codec_unavailable','Round 172 no compatible codec remains explicit');
echo "REVIEW ROUND 172 CODEC NEGOTIATION: PASS\n";
