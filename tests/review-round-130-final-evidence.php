<?php
declare(strict_types=1);
$root=dirname(__DIR__);$readme=file_get_contents($root.'/README.md');$q=file_get_contents($root.'/tools/quality-check.sh');
function r130($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 130 FAIL: $m\n");exit(1);}echo "ROUND 130 PASS: $m\n";}
preg_match('/subsequent focused rounds 72–(\d+)/u',$readme,$ledger);r130(isset($ledger[1])&&(int)$ledger[1]>=130,'repository-facing review ledger reaches Round 130 or later');
foreach(range(121,130) as $round){
    if(in_array($round,[/* clean rounds would be listed here */],true))continue;
    r130(str_contains($q,'review-round-'.$round.'-'),"quality gate contains Round $round regression evidence");
}
$t120=file_get_contents($root.'/tests/review-round-120-evidence-freshness.php');
r130(str_contains($t120,'>=120')&&!str_contains($t120,"str_contains(\$readme,'subsequent focused rounds 72–120')"),'older evidence-freshness regression is monotonic rather than freezing the ledger at Round 120');
r130(!is_file($root.'/docs/runtime/review-diagnostics/latest-review-failure.log'),'no stale unresolved-review failure marker is present at the reviewed source head');
echo "REVIEW ROUND 130 FINAL EVIDENCE: PASS\n";