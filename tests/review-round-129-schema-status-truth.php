<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');$status=file_get_contents($root.'/docs/runtime/STATUS.md');
function r129($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 129 FAIL: $m\n");exit(1);}echo "ROUND 129 PASS: $m\n";}
r129(substr_count($p,"SCM_SCHEMA_VERSION:'1.5.0'")===2,'schema install/readiness fallback identity matches the 1.5.0 runtime schema');
r129(!str_contains($p,"SCM_SCHEMA_VERSION:'1.4.0'"),'no stale 1.4.0 persistence fallback remains');
r129(str_contains($status,'C4 Clinical-Sensitive')&&str_contains($status,'C5 Security Secret'),'runtime status uses the current governing C0-C5 constitution');
r129(!str_contains($status,'C4 Financial/Legal')&&!str_contains($status,'C5 Clinical/High Sensitivity'),'superseded data-class labels are absent from current CF-04 status evidence');
echo "REVIEW ROUND 129 SCHEMA/STATUS TRUTH: PASS\n";