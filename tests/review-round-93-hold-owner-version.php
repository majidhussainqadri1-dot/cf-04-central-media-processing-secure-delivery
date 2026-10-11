<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r93($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 93 FAIL: $m\n");exit(1);}echo "ROUND 93 PASS: $m\n";}
r93(str_contains($s,"'authorize_hold'")&&!str_contains($s,"'authorize_hold',['asset'=>\$asset,'actor_id'=>\$actor,'hold'=>Utils::redact(\$input)],false"),'hold placement no longer opts out of canonical object-version decision');
r93(str_contains($s,"Hold authorization is stale."),'hold placement rejects stale owner object version');
echo "REVIEW ROUND 93 HOLD OWNER VERSION: PASS\n";
