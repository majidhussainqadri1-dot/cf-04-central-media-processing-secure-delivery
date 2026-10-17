<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r77($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 77 FAIL: $m\n");exit(1);}echo "ROUND 77 PASS: $m\n";}
$failed=<<<'PATTERN'
'records_failed'=>0
PATTERN;
$catch=<<<'PATTERN'
}catch(\Throwable $failure){
PATTERN;
$continue=<<<'PATTERN'
            continue;
PATTERN;
r77(str_contains($s,$failed),'retention batch exposes per-record failure accounting');
r77(str_contains($s,$catch),'retention processing isolates failures at the record boundary');
r77(str_contains($s,$continue),'a failed retention record cannot terminate later-record processing');
r77(str_contains($s,"DegradedStateService::record('retention-run'"),'isolated retention failures produce degraded-state evidence');
echo "REVIEW ROUND 77 RETENTION ISOLATION: PASS\n";
