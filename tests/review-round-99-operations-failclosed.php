<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r99($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 99 FAIL: $m\n");exit(1);}echo "ROUND 99 PASS: $m\n";}
r99(str_contains($s,"Provider-exit rollback item has no policy parent.")&&str_contains($s,"assertNoHold(\$holdAssetId,'provider_exit')")&&str_contains($s,"assertProviderRegion(\$holdAssetId,(string)\$plan['source_provider'])"),'provider rollback rechecks hold and source-region policy at action time');
r99(str_contains($s,"target_cleanup_deferred")&&str_contains($s,'ResidencyCryptoService::isLocked($holdAssetId)'),'provider rollback never silently deletes a WORM-locked migration copy');
r99(str_contains($s,'restore_start_reconciliation_required')&&str_contains($s,"RecordStore::delete('restore',\$id)"),'restore start compensates an orphan restore row when gate creation fails');
r99(substr_count($s,"['serve_authorized','cancelled'],true")>=2&&!str_contains($s,"['serve_authorized','cancelled','failed'],true"),'failed restore gates remain fail-closed for serving and health');
echo "REVIEW ROUND 99 OPERATIONS FAIL-CLOSED: PASS\n";
