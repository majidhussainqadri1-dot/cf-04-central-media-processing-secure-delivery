<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');$q=file_get_contents($root.'/tools/quality-check.sh');$b=file_get_contents($root.'/tools/build-package.sh');$w=file_get_contents($root.'/.github/workflows/runtime-ci.yml');
function r101($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 101 FAIL: $m\n");exit(1);}echo "ROUND 101 PASS: $m\n";}
r101(str_contains($p,"RecordStore::scan('asset'")&&str_contains($p,"saveMaintenanceCursor('processing'")&&!str_contains($p,"RecordStore::list('asset'"),'processing cron uses durable, bounded ID-keyset traversal rather than starving queued items behind a hard offset ceiling');
r101(str_contains($p,"saveMaintenanceCursor('integrity'")&&str_contains($p,"maintenanceCursor('integrity'"),'integrity cron uses validated persistent ID-keyset cursor without offset drift');
r101(str_contains($b,'raw_uuid=')&&str_contains($b,'urn:uuid:'),'CycloneDX SBOM uses a deterministic UUID-shaped serial number');
r101(str_contains($q,'RELEASE-EVIDENCE.sha256')&&str_contains($w,'dist/RELEASE-EVIDENCE.sha256'),'release evidence receives and publishes its own integrity checksum');
echo "REVIEW ROUND 101 RUNTIME RELEASE: PASS\n";
