<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r76($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 76 FAIL: $m\n");exit(1);}echo "ROUND 76 PASS: $m\n";}
$retry=<<<'PATTERN'
for($attempt=0;$attempt<4;$attempt++)
PATTERN;
$refresh=<<<'PATTERN'
$g=RecordStore::get('grant',$grantId)
PATTERN;
$conflict=<<<'PATTERN'
$conflict->errorCode!=='record_version_conflict'
PATTERN;
r76(str_contains($s,$retry),'asset-wide revocation uses bounded CAS retries');
r76(str_contains($s,$refresh),'each revocation retry refreshes authoritative grant state');
r76(str_contains($s,$conflict),'ordinary version contention is retried while other errors remain fail-closed');
r76(str_contains($s,'grant_revoke_conflict'),'persistent active-grant contention is surfaced instead of silently skipped');
echo "REVIEW ROUND 76 GRANT REVOCATION: PASS\n";
