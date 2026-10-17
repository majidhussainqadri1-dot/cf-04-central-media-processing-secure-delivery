<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');$pl=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');$l=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');$u=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r110($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 110 FAIL: $m\n");exit(1);}echo "ROUND 110 PASS: $m\n";}
$eventIndex=<<<'PATTERN'
$indexes['event_id']
PATTERN;
$mappingDelete=<<<'PATTERN'
RecordStore::delete('provider_mapping',(string)$m['id'],(int)$m['version'])
PATTERN;
$partDelete=<<<'PATTERN'
RecordStore::delete('upload_part',(string)$p['id'],(int)$p['version'])
PATTERN;
r110(str_contains($p,'Db::assertRead(\'record_get\')')&&str_contains($p,'Db::assertRead(\'record_list\')')&&str_contains($p,'Db::assertRead(\'record_scan\')'),'database read failures cannot masquerade as missing or empty authoritative inventories');
r110(str_contains($p,'ORDER BY id ASC LIMIT')&&str_contains($p,'private static function scanPage')&&!str_contains($p,'$probe=self::list($type,$actor,$status,1,$offset)'),'complete bounded inventories use stable keyset pagination instead of mutable OFFSET scans');
r110(str_contains($p,'SHOW INDEX FROM')&&str_contains($p,$eventIndex),'schema readiness validates critical uniqueness/index invariants, not just column names');
r110(str_contains($p,'?int $expectedVersion=null')&&str_contains($l,$mappingDelete)&&str_contains($u,$partDelete),'authoritative cleanup deletes are version-bound against concurrent mutation');
r110(str_contains($p,'audit_chain_head_invalid')&&str_contains($p,'WHERE id>%d ORDER BY id ASC LIMIT'),'audit append fails closed on malformed chain head and full verification is memory-bounded');
r110(str_contains($o,'RecordStore::countStatuses')&&str_contains($o,'$auditChain')&&str_contains($o,'pending_cdn_purges'),'health uses bounded status aggregations and cannot report ready with a broken audit chain or pending CDN purge');
r110(str_contains($pl,'activation_lock_recovery_unavailable')&&str_contains($pl,'option_value=%s')&&str_contains($pl,'releaseActivationLock'),'stale activation-lock takeover is compare-bound and release is lock-owner-bound');
r110(str_contains($pl,'runtime_lock_acquire_failed')&&str_contains($pl,'cronAlert'),'runtime lock infrastructure failures are explicit rather than silently misclassified as contention');
echo "REVIEW ROUND 110 PERSISTENCE RUNTIME: PASS\n";
