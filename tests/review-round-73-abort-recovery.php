<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r73($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 73 FAIL: $m\n");exit(1);}echo "ROUND 73 PASS: $m\n";}
$allowed=<<<'PATTERN'
['uploading','paused','aborting']
PATTERN;
$transition=<<<'PATTERN'
$u['status']='aborting'
PATTERN;
$purgeNeedle=<<<'PATTERN'
PartStore::purge($uploadId)
PATTERN;
$finalNeedle=<<<'PATTERN'
$fresh['status']='aborted'
PATTERN;
$settleNeedle=<<<'PATTERN'
QuotaService::settle((string)$u['quota']['quota_id']
PATTERN;
$doneNeedle=<<<'PATTERN'
if($state==='aborted')return $u
PATTERN;
r73(str_contains($s,$allowed),'abort has an explicit retryable intermediate state');
r73(str_contains($s,$transition),'abort blocks further upload writes before destructive cleanup starts');
$purge=strpos($s,$purgeNeedle);$final=strpos($s,$finalNeedle);
r73($purge!==false&&$final!==false&&$purge<$final,'final aborted status is persisted only after part purge is attempted');
$settle=strpos($s,$settleNeedle);
r73($settle!==false&&$settle<$final,'quota release precedes final aborted status');
r73(str_contains($s,$doneNeedle),'already-finalized abort remains idempotent');
echo "REVIEW ROUND 73 ABORT RECOVERY: PASS\n";
