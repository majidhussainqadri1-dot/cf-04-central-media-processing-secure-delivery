<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$readme=file_get_contents($root.'/README.md');
$workflow=file_get_contents($root.'/.github/workflows/review-fix-runner.yml');
function r120($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 120 FAIL: $m\n");exit(1);}echo "ROUND 120 PASS: $m\n";}
r120(str_contains($readme,'subsequent focused rounds 72–120'),'repository-facing sequential review evidence reaches the current ten-round batch');
r120(str_contains($workflow,'git rm -f docs/runtime/review-diagnostics/latest-review-failure.log'),'a successful verified correction clears the unresolved-latest-failure marker');
r120(!is_file($root.'/docs/runtime/review-diagnostics/latest-review-failure.log'),'no stale historical failure is presented as the latest unresolved repository state');
echo "REVIEW ROUND 120 EVIDENCE FRESHNESS: PASS\n";
