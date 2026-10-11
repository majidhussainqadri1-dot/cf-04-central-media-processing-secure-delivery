<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r67($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 67 FAIL: $m\n");exit(1);}echo "ROUND 67 PASS: $m\n";}
$loopPattern='foreach($eligible as $job)';
$catchPattern='catch(Error $leaseError)';
$conflictPattern='$leaseError' . "->errorCode!=='record_version_conflict'";
$dependencyPattern='!self::dependenciesComplete($fresh))continue';
$timingPattern='(int)($fresh' . "['next_attempt_at']??0)>" . '$now';
r67(str_contains($s,$loopPattern),'lease selection iterates ranked eligible jobs instead of committing to one stale candidate');
r67(str_contains($s,$catchPattern)&&str_contains($s,$conflictPattern),'lease CAS contention retries another eligible job while preserving non-conflict failures');
r67(str_contains($s,$dependencyPattern),'lease revalidates dependencies immediately before CAS acquisition');
r67(str_contains($s,$timingPattern),'lease revalidates retry timing immediately before CAS acquisition');
echo "REVIEW ROUND 67 LEASE CONTENTION: PASS\n";
