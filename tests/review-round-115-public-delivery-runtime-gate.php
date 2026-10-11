<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r115($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 115 FAIL: $m\n");exit(1);}echo "ROUND 115 PASS: $m\n";}
$start=strpos($s,'public static function publishPublic');
r115($start!==false,'public CDN publication entry exists');
$slice=substr($s,$start,350);
r115(str_contains($slice,"RuntimeGuard::requireReady(['streaming'])"),'public CDN publication cannot run while CF-04 is disabled or provider evidence is unavailable');
echo "REVIEW ROUND 115 PUBLIC DELIVERY RUNTIME GATE: PASS\n";