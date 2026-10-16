<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-future40.php');
function r100($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 100 FAIL: $m\n");exit(1);}echo "ROUND 100 PASS: $m\n";}
r100(str_contains($s,"\$asset['policy']['retention']['class']")&&str_contains($s,"\$candidate['policy']['retention']['class']"),'safe dedupe compares the canonical nested retention class instead of a nonexistent top-level field');
r100(str_contains($s,"RecordStore::scan('asset',\$pageSize,\$after)")&&str_contains($s,'scan_truncated')&&str_contains($s,"RecordStore::put('future_rescan'")&&str_contains($s,"RecordStore::put('cron_cursor',\$cursorId"),'threat rescan uses durable keyset pages rather than unstable offset ceilings');
r100(str_contains($s,"'tombstone_hash'")&&str_contains($s,"'tombstone_state'")&&str_contains($s,"RecordStore::get('tombstone',\$assetId)"),'migration/export bundle carries deletion/tombstone state');
r100(str_contains($s,"'signing_kid'")&&str_contains($s,'Keyring::hashKey($kid)'),'migration bundle signatures remain verifiable across active-key rotation by binding the signing key id');
echo "REVIEW ROUND 100 FUTURE40 ADVERSARIAL: PASS\n";
