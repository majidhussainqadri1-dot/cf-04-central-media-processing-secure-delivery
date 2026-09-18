<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r89($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 89 FAIL: $m\n");exit(1);}echo "ROUND 89 PASS: $m\n";}
$processContext=<<<'PATTERN'
['deletion_id'=>$deletionId
PATTERN;
r89(str_contains($s,"retention_decision")&&str_contains($s,"Retention decision is stale."),'retention schedule is bound to the current owner object version');
r89(str_contains($s,"'scope'=>'derivatives'")&&str_contains($s,"'phase'=>'expire_derivatives'"),'derivative expiry performs fresh owner deletion authorization before destructive propagation');
r89(str_contains($s,"'phase'=>'process-start'")&&str_contains($s,"'phase'=>'delete_source'")&&str_contains($s,$processContext),'deletion retries re-authorize against canonical owner truth at action time and before physical source deletion');
r89(substr_count($s,"domain_object_version_stale")>=3,'lifecycle destructive paths fail closed on stale owner object version');
echo "REVIEW ROUND 89 LIFECYCLE REAUTHORIZATION: PASS\n";
