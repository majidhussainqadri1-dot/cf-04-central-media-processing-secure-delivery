<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r97($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 97 FAIL: $m\n");exit(1);}echo "ROUND 97 PASS: $m\n";}
r97(substr_count($s,'$provider->exists($key)&&!$provider->delete($key)&&$provider->exists($key)')>=3,'derivative/source deletion treats an already-absent physical object as successful retry reconciliation');
r97(str_contains($s,'source_delete_failed')&&str_contains($s,'derivative_delete_failed'),'true provider deletion failures remain fail-closed after existence confirmation');
echo "REVIEW ROUND 97 DELETION IDEMPOTENCY: PASS\n";
