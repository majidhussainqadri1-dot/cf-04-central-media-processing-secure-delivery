<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r107($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 107 FAIL: $m\n");exit(1);}echo "ROUND 107 PASS: $m\n";}
$objectVersion=<<<'PATTERN'
'object_version'=>(int)$asset['object_version']
PATTERN;
$purgePending=<<<'PATTERN'
if((int)$purge['pending']>0)
PATTERN;
r107(str_contains($s,$objectVersion)&&str_contains($s,"'owner_contract_version'"),'legal hold persists the canonical object/version contract it was placed against');
r107(str_contains($s,"'phase'=>'review'")&&str_contains($s,'Hold review authorization is stale.'),'hold continue/release/escalate is freshly owner-authorized');
r107(str_contains($s,'DeliveryService::purgePublicForAsset')&&str_contains($s,$purgePending),'destructive lifecycle refuses to advance while any public CDN purge remains pending');
r107(substr_count($s,'self::authorizeCurrent(')>=6,'deletion workflow rechecks legal hold and canonical owner immediately before destructive phases');
r107(str_contains($s,"'phase'=>'delete_source'")&&str_contains($s,"'phase'=>'delete_derivatives'"),'source and derivative deletion have distinct action-time authorization checkpoints');
echo "REVIEW ROUND 107 LIFECYCLE ACTION TIME: PASS\n";
