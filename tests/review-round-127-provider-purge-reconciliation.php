<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r127($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 127 FAIL: $m\n");exit(1);}echo "ROUND 127 PASS: $m\n";}
$start=strpos($s,'public static function purgeSource');
$block=substr($s,$start,5500);
r127(str_contains($block,"\$source->exists((string)\$item['object_key'])&&!\$source->delete((string)\$item['object_key'])&&\$source->exists((string)\$item['object_key'])"),'provider-exit purge reconciles an ambiguous false delete result against actual source existence');
r127(str_contains($block,"ResidencyCryptoService::assertUnlocked")&&str_contains($block,"LegalHoldService::assertNoHold"),'source purge remains protected by legal/WORM policy');
r127(str_contains($block,"credentials_revocation_required"),'provider exit still requires credential-revocation evidence after source purge');
echo "REVIEW ROUND 127 PROVIDER PURGE RECONCILIATION: PASS\n";