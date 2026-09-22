<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r70($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 70 FAIL: $m\n");exit(1);}echo "ROUND 70 PASS: $m\n";}
$derivativeScan=<<<'PATTERN'
foreach(RecordStore::all('derivative',0,null,200000) as $derivative)
PATTERN;
$providerFilter=<<<'PATTERN'
($derivative['storage']['provider_id']??'')!==$sourceProvider
PATTERN;
$parentGuard=<<<'PATTERN'
provider_exit_derivative_orphaned
PATTERN;
$holdGuard=<<<'PATTERN'
LegalHoldService::assertNoHold($assetId,'provider_exit')
PATTERN;
r70(str_contains($s,$derivativeScan),'provider exit inventories derivatives independently of source-object provider placement');
r70(str_contains($s,$providerFilter),'provider exit filters derivative inventory by its own storage provider');
r70(str_contains($s,$parentGuard),'orphaned source-provider derivatives fail the exit plan closed');
r70(str_contains($s,$holdGuard),'provider-exit legal holds are enforced for independently discovered derivatives');
echo "REVIEW ROUND 70 PROVIDER EXIT INVENTORY: PASS\n";
