<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r81($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 81 FAIL: $m\n");exit(1);}echo "ROUND 81 PASS: $m\n";}
r81(str_contains($s,'self::assertSourceDrained($plan);ProviderRegistry::activate'),'source inventory is rechecked immediately before target-provider activation');
r81(str_contains($s,"provider_exit_inventory_drift"),'inventory drift has an explicit fail-closed error');
r81(str_contains($s,"foreach(self::inventoryRows('asset') as $asset)")&&str_contains($s,"foreach(self::inventoryRows('derivative') as $derivative)")&&str_contains($s,"RecordStore::scan($type,250,$after)"),'provider exit plans independently scan complete source asset and derivative inventories without global cap');
echo "REVIEW ROUND 81 PROVIDER EXIT DRIFT: PASS\n";
