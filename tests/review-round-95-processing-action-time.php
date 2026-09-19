<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r95($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 95 FAIL: $m\n");exit(1);}echo "ROUND 95 PASS: $m\n";}
$executePos=strpos($s,'private static function executeNode');$probePos=strpos($s,'private static function probeNode',$executePos);$node=substr($s,$executePos,$probePos-$executePos);
r95(str_contains($node,"LegalHoldService::assertNoHold(\$assetId,'processing')"),'every processing node rechecks a newly placed processing hold at action time');
r95(str_contains($node,"'authorize_processing'")&&str_contains($node,"'operation'=>'execute'")&&str_contains($node,'Processing authorization is stale.'),'every processing node reauthorizes against current canonical owner version');
echo "REVIEW ROUND 95 PROCESSING ACTION TIME: PASS\n";
