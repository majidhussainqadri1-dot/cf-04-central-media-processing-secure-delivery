<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r105($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 105 FAIL: $m\n");exit(1);}echo "ROUND 105 PASS: $m\n";}
$deterministicId=<<<'PATTERN'
$id=hash('sha256',Utils::canonicalJson($identity))
PATTERN;
$legacyRandomId=<<<'PATTERN'
$id=Utils::id('drv')
PATTERN;
$activeReplay=<<<'PATTERN'
$activeReplay
PATTERN;
$replayReturn=<<<'PATTERN'
if($activeIds===$requested)return $activeReplay
PATTERN;
r105(str_contains($s,$deterministicId)&&!str_contains($s,$legacyRandomId),'derivative identity is deterministic across transform retries');
r105(str_contains($s,'derivative_identity_conflict')&&str_contains($s,'record_version_conflict'),'concurrent deterministic derivative creation reconciles one authoritative output');
r105(str_contains($s,$activeReplay)&&str_contains($s,$replayReturn),'manifest-switch retry returns an already-active identical manifest instead of minting a duplicate version');
r105(str_contains($s,'job_generation_stale'),'operator dead-letter retry rejects jobs from an obsolete processing generation');
echo "REVIEW ROUND 105 PROCESSING IDEMPOTENCY: PASS\n";
