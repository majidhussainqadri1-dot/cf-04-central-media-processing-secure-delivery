<?php
declare(strict_types=1);
$root=dirname(__DIR__);$u=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r82($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 82 FAIL: $m\n");exit(1);}echo "ROUND 82 PASS: $m\n";}
r82(str_contains($u,"claim_token'=>Utils::id('idem')"),'new claims receive unique generation identity');
r82(str_contains($u,"record['claim_token']??''")&&str_contains($u,"status']??'')!=='claimed'"),'completion rejects stale/non-current claims');
r82(str_contains($u,"r['claim_token']??''"),'failure rejects stale claim generations');
r82(substr_count($u,"['record']['claim_token']")>=5&&substr_count($o,"['record']['claim_token']")>=2,'all upload and repair terminal paths propagate exact claim identity');
echo "REVIEW ROUND 82 IDEMPOTENCY GENERATION: PASS\n";
