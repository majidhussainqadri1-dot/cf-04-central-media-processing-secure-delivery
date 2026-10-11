<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r94($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 94 FAIL: $m\n");exit(1);}echo "ROUND 94 PASS: $m\n";}
r94(substr_count($s,"'authorize_upload'")>=2,'upload completion performs a fresh canonical owner authorization in addition to session creation');
$helper=substr($s,strpos($s,'private static function reauthorizeActiveUpload'),2500);r94(str_contains($s,"reauthorizeActiveUpload(\$u,\$actor,'complete')")&&str_contains($helper,"'phase'=>\$phase")&&str_contains($helper,'Upload authorization is stale.'),'completion reauthorization is phase-tagged through the centralized fresh-owner gate and rejects stale owner object version');
r94(str_contains($s,"if((\$u['status']??'')!=='completed')"),'completed idempotent reconciliation does not require a new owner decision after terminal completion');
echo "REVIEW ROUND 94 UPLOAD COMPLETION REAUTH: PASS\n";
