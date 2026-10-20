<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r124($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 124 FAIL: $m\n");exit(1);}echo "ROUND 124 PASS: $m\n";}
$start=strpos($s,'public static function retryDeadLetter');
$block=substr($s,$start,1800);
r124(str_contains($block,"RuntimeGuard::requireReady(['streaming'])"),'dead-letter retry cannot requeue processing while runtime/provider activation is unavailable');
r124(str_contains($block,"Auth::capability('media_reprocess')")&&str_contains($block,'job_generation_stale'),'retry still requires operator capability and current processing generation');
echo "REVIEW ROUND 124 DEAD-LETTER RUNTIME GATE: PASS\n";