<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r113($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 113 FAIL: $m\n");exit(1);}echo "ROUND 113 PASS: $m\n";}
foreach(['pause','resume','abort'] as $method){
    $start=strpos($s,'public static function '.$method.'(');
    r113($start!==false,$method.' exists');
    $slice=substr($s,$start,350);
    r113(str_contains($slice,"RuntimeGuard::requireReady(['streaming'])"),$method.' is fail-closed behind the runtime/provider activation gate');
}
echo "REVIEW ROUND 113 UPLOAD RUNTIME GATE: PASS\n";
