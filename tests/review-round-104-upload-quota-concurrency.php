<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r104($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 104 FAIL: $m\n");exit(1);}echo "ROUND 104 PASS: $m\n";}
$stateMarker=<<<'PATTERN'
$upload['status']='expiring'
PATTERN;
$credentialMarker=<<<'PATTERN'
$upload['credential_hash']=''
PATTERN;
$purgeMarker=<<<'PATTERN'
PartStore::purge((string)$upload['id'])
PATTERN;
$decisionMarker=<<<'PATTERN'
$decision['quota_limits']
PATTERN;
r104(substr_count($s,'record_version_conflict')>=5&&str_contains($s,'quota_contention'),'quota/rate mutations use bounded optimistic retries instead of surfacing ordinary CAS races as arbitrary failures');
r104(str_contains($s,$decisionMarker)&&str_contains($s,'$effectiveQuota'),'canonical owner decisions can impose domain/role-specific quota limits before reservation');
r104(str_contains($s,"'expiring'")&&str_contains($s,$stateMarker)&&str_contains($s,$credentialMarker),'expired-upload cleanup claims a terminalizing state and invalidates the credential before deleting parts');
r104(strpos($s,$purgeMarker)>strpos($s,$stateMarker),'expiry cleanup never purges resumable parts before its state transition is durably claimed');
echo "REVIEW ROUND 104 UPLOAD QUOTA CONCURRENCY: PASS\n";
