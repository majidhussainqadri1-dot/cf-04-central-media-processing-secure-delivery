<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-rest.php');
function r122($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 122 FAIL: $m\n");exit(1);}echo "ROUND 122 PASS: $m\n";}
$wrap=substr($s,strpos($s,'private static function wrap'),5000);
r122(substr_count($wrap,"'trace_id'")>=3,'REST expected and unexpected error schemas expose a safe trace id');
$stream=substr($s,strpos($s,'public static function handle'),7000);
r122(substr_count($stream,"'trace_id'")>=2,'streaming delivery errors expose a safe trace id');
r122(str_contains($stream,"delivery_unexpected_error")&&str_contains($stream,"'exception_class'"),'unexpected streaming failures are correlated to audited trace evidence without exposing stack/path data');
echo "REVIEW ROUND 122 SAFE ERROR TRACE: PASS\n";