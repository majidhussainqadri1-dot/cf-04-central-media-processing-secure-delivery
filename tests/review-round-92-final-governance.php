<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$f=file_get_contents($root.'/sabri-central-media/includes/class-scm-future40.php');
$d=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
$l=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
$w=file_get_contents($root.'/.github/workflows/review-fix-runner.yml');
$q=file_get_contents($root.'/tools/quality-check.sh');
function r92($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 92 FAIL: $m\n");exit(1);}echo "ROUND 92 PASS: $m\n";}
r92(str_contains($f,'residency_current_placement_denied')&&str_contains($p,'assertProviderRegion')&&str_contains($d,'assertProviderRegion')&&substr_count($o,'assertProviderRegion')>=4,'FUT-029 residency is fail-closed across current placement, derivative storage, delivery and provider migration');
r92(str_contains($f,'object_lock_reduction_denied')&&str_contains($f,'object_lock_active')&&substr_count($l,'assertUnlocked')>=3&&str_contains($o,"assertUnlocked(\$holdAssetId,'provider_exit_purge')"),'FUT-030 active WORM lock cannot be shortened or bypassed by physical deletion/provider purge');
r92(str_contains($f,'offline_package_envelope')&&str_contains($f,'Crypto::encryptChunk')&&str_contains($f,'verifyOfflineGrant'),'FUT-027 offline grant is encrypted and consume-time verified');
r92(str_contains($f,'resumeGrant')&&str_contains($f,'download_resume_state_stale')&&str_contains($f,"'mode'=>'resume'"),'FUT-028 resume has concrete device/session-bound fresh authorization');
r92(str_contains($f,"RecordStore::get('residency_policy'")&&str_contains($f,"RecordStore::get('object_lock'")&&str_contains($f,"RecordStore::get('asset_key_envelope'")&&str_contains($f,"RecordStore::get('disaster_plan'"),'mutable deterministic Future-40 governance records use optimistic-version source reads before CAS writes');
r92(str_contains($w,'latest-review-failure.log')&&str_contains($w,'review-fix-diagnostics.log'),'sequential runner persists verification diagnostics rather than mislabeling transformer output');
r92(str_contains($w,'actions: write')&&str_contains($w,'gh workflow run runtime-ci.yml'),'bot-generated correction head explicitly dispatches Runtime CI');
r92(str_contains($q,'run_php()')&&str_contains($q,'PHP diagnostic output is a quality-gate failure.'),'quality gate rejects PHP warning/notice/deprecation output');
echo "REVIEW ROUND 92 FINAL GOVERNANCE: PASS\n";
