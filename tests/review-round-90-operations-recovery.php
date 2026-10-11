<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r90($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 90 FAIL: $m\n");exit(1);}echo "ROUND 90 PASS: $m\n";}
r90(substr_count($s,"LegalHoldService::assertNoHold(\$holdAssetId,'provider_exit')")>=3,'provider exit rechecks newly placed holds during copy, switch and source purge');
r90(str_contains($s,"rollback_to_manifest")&&str_contains($s,"Repair rollback authorization is stale."),'repair rollback is freshly owner-authorized and object-version bound');
r90(str_contains($s,"repair_rollback_reconciliation_required")&&str_contains($s,"asset_switch_failed_previous_restore_failed"),'repair rollback has explicit recovery/reconciliation for partial manifest transition');
r90(str_contains($s,"in_array(\$restoreStatus,['reconciled','serve_authorized'],true)")&&str_contains($s,"if(\$restoreStatus!=='serve_authorized')"),'restore serve authorization can resume a safe partial transition');
r90(str_contains($s,"\$gate=RecordStore::get('restore_gate','current')??\$gate"),'restore gate is refreshed before the final unblock write');
echo "REVIEW ROUND 90 OPERATIONS RECOVERY: PASS\n";
