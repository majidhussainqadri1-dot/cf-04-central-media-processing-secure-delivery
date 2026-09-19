<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r114($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 114 FAIL: $m\n");exit(1);}echo "ROUND 114 PASS: $m\n";}
$start=strpos($s,"public static function execute(string \$assetId,string \$workerId='inline-worker')");
r114($start!==false,'processing execute entry exists');
$slice=substr($s,$start,300);
r114(str_contains($slice,"RuntimeGuard::requireReady(['streaming'])"),'existing job graphs cannot execute while the CF-04 runtime/provider gate is disabled');
echo "REVIEW ROUND 114 PROCESSING RUNTIME GATE: PASS\n";