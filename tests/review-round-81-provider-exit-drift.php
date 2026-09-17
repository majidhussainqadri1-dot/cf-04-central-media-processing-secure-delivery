<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r81($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 81 FAIL: $m\n");exit(1);}echo "ROUND 81 PASS: $m\n";}
r81(str_contains($s,'self::assertSourceDrained($plan);ProviderRegistry::activate'),'source inventory is rechecked immediately before target-provider activation');
r81(str_contains($s,"provider_exit_inventory_drift"),'inventory drift has an explicit fail-closed error');
r81(substr_count($s,"RecordStore::all('asset',0,null,100000)")>=2&&substr_count($s,"RecordStore::all('derivative',0,null,200000)")>=2,'drift check independently covers authoritative source assets and derivatives');
echo "REVIEW ROUND 81 PROVIDER EXIT DRIFT: PASS\n";
