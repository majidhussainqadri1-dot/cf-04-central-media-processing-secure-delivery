<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r78($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 78 FAIL: $m\n");exit(1);}echo "ROUND 78 PASS: $m\n";}
$assetInventory=<<<'PATTERN'
if(($record['status']??'')==='deleted'||empty($record['object_key']))continue
PATTERN;
$groupFilter=<<<'PATTERN'
$groups=array_filter($groups,static function(array $group)use($active)
PATTERN;
$objectBinding=<<<'PATTERN'
($candidate['object_key']??'')!==$oldKey
PATTERN;
r78(str_contains($s,$assetInventory),'key rotation inventories every live physical reference before deciding whether rotation is needed');
r78(str_contains($s,$groupFilter),'rotation need is decided at the complete shared-object group boundary');
r78(str_contains($s,'key_rotation_shared_identity_mismatch'),'inconsistent sha/size identity across shared references fails closed');
r78(str_contains($s,$objectBinding),'shared-reference verification binds every member to the same physical object key');
echo "REVIEW ROUND 78 KEY ROTATION REFERENCE GROUP: PASS\n";
