<?php
declare(strict_types=1);
$root=dirname(__DIR__);$d=json_decode((string)file_get_contents($root.'/contracts/job-record.schema.json'),true,64,JSON_THROW_ON_ERROR);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r126($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 126 FAIL: $m\n");exit(1);}echo "ROUND 126 PASS: $m\n";}
$states=$d['properties']['status']['enum']??[];
foreach(['queued','leased','retry','waiting_review','completed','dead_letter'] as $state)r126(in_array($state,$states,true),"job schema supports runtime state $state");
r126(str_contains($s,"\$job['status']='waiting_review'")&&str_contains($s,"\$job['status']='dead_letter'"),'schema state set is regression-bound to safety-review and dead-letter runtime transitions');
echo "REVIEW ROUND 126 JOB STATE CONTRACT: PASS\n";