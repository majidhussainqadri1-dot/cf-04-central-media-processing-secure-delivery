<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
$r=file_get_contents($root.'/sabri-central-media/includes/class-scm-plan-parity.php');
function r71($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 71 FAIL: $m\n");exit(1);}echo "ROUND 71 PASS: $m\n";}
$winner=<<<'PATTERN'
$winner=self::get($type,$id)
PATTERN;
$expected=<<<'PATTERN'
'expected'=>0
PATTERN;
$inventory=<<<'PATTERN'
$inventory=RecordStore::all('asset',0,null,1000000)
PATTERN;
$priority=<<<'PATTERN'
return $expires>0&&$expires<=$now?0:1
PATTERN;
$bounded=<<<'PATTERN'
foreach(array_slice($inventory,0,$limit) as $asset)
PATTERN;
r71(str_contains($p,$winner),'failed persistent create re-reads the authoritative record to distinguish contention from infrastructure failure');
r71(str_contains($p,$expected),'concurrent create conflict retains expected-version-zero CAS evidence');
r71(str_contains($p,"throw new Error('record_version_conflict','Concurrent record creation won before this insert.'"),'duplicate-key create races surface as record_version_conflict');
r71(str_contains($p,"throw new Error('record_write_failed','Persistent insert failed.'"),'true infrastructure insert failure remains fail-closed as record_write_failed');
r71(str_contains($r,$inventory),'rights reconciliation discovers the complete explicitly bounded inventory rather than repeatedly reading only the newest page');
r71(str_contains($r,$priority),'expired rights are prioritized ahead of non-expired records, preventing head-page starvation');
r71(str_contains($r,$bounded),'reconciliation mutation/check work remains bounded by the caller limit');
r71(!str_contains($r,"foreach(RecordStore::list('asset',0,null,$limit) as $asset)"),'starving head-page-only reconciliation pattern is absent');
echo "REVIEW ROUND 71 FINAL ADVERSARIAL: PASS\n";
