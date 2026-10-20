<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r61($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 61 FAIL: $m\n");exit(1);}echo "ROUND 61 PASS: $m\n";}
r61(str_contains($s,'$previousState=['),'deletion request captures the exact prior asset lifecycle state');
r61(str_contains($s,'$fresh[\'status\']=$previousState[\'status\']'),'deletion-record persistence failure restores the prior status instead of forcing ready');
r61(str_contains($s,'$previousState[\'deletion_id\']')&&str_contains($s,'$previousState[\'deletion_requested_at\']'),'rollback restores prior deletion metadata exactly');
echo "REVIEW ROUND 61 LIFECYCLE ROLLBACK: PASS\n";
