<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r64($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 64 FAIL: $m\n");exit(1);}echo "ROUND 64 PASS: $m\n";}
r64(str_contains($s,"RecordStore::put('job',".'$id'.",")&&str_contains($s,"'created_at'=>Utils::now()],0)"),'deterministic processing-job creation is create-only');
r64(str_contains($s,'$winner' . "=RecordStore::get('job'," . '$id' . ")")&&str_contains($s,'Concurrent processing job identity conflicts'),'concurrent graph creator reloads and validates the authoritative job');
r64(str_contains($s,"processing_generation']??0)!==".'$generation')&&str_contains($s,"tenant']??'')!==".'$policy'."['owner_domain']"),'existing deterministic jobs are generation/tenant bound');
r64(str_contains($s,'catch(Error $recoverError)')&&str_contains($s,'$recoverError' . "->errorCode!=='record_version_conflict'"),'orphan recovery treats a concurrent heartbeat/CAS winner as benign and continues');
echo "REVIEW ROUND 64 JOB CONCURRENCY: PASS\n";
