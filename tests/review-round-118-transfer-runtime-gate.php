<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-transfer.php');
function r118($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 118 FAIL: $m\n");exit(1);}echo "ROUND 118 PASS: $m\n";}
foreach(['bindAsset','markReady'] as $method){
    $start=strpos($s,'public static function '.$method.'(');
    r118($start!==false,$method.' entry exists');
    $slice=substr($s,$start,300);
    r118(str_contains($slice,"RuntimeGuard::requireReady(['streaming'])"),$method.' cannot mutate transfer state while CF-04 runtime is disabled');
}
echo "REVIEW ROUND 118 TRANSFER RUNTIME GATE: PASS\n";