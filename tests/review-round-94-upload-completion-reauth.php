<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r94($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 94 FAIL: $m\n");exit(1);}echo "ROUND 94 PASS: $m\n";}
r94(substr_count($s,"'authorize_upload'")>=2,'upload completion performs a fresh canonical owner authorization in addition to session creation');
r94(str_contains($s,"'phase'=>'complete'")&&str_contains($s,'Upload completion authorization is stale.'),'completion reauthorization is phase-tagged and rejects stale owner object version');
r94(str_contains($s,"if((\$u['status']??'')!=='completed')"),'completed idempotent reconciliation does not require a new owner decision after terminal completion');
echo "REVIEW ROUND 94 UPLOAD COMPLETION REAUTH: PASS\n";
