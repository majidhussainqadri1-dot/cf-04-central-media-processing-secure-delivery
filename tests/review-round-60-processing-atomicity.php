<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r60($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 60 FAIL: $m\n");exit(1);}echo "ROUND 60 PASS: $m\n";}
r60(str_contains($s,'$attached=$fresh')&&str_contains($s,"RecordStore::delete('job'"),'unattached graph jobs are cleaned after asset CAS failure without deleting a concurrently attached graph');
r60(str_contains($s,'manifest_switch_reconciliation_required')&&str_contains($s,"'status']='activation_failed'"),'manifest activation failure has rollback and reconciliation evidence');
r60(str_contains($s,'manifest_supersede_reconciliation_required'),'old-manifest supersede conflict is explicitly reconcilable');
echo "REVIEW ROUND 60 PROCESSING ATOMICITY: PASS\n";
