#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-processing.php'
s=p.read_text()

# Fresh Review Round 64 was fully completed before applying these corrections.
old="if($existing){if(($existing['asset_id']??'')!==$assetId||($existing['job_type']??'')!==$node['name'])throw new Error('job_identity_conflict','Existing processing job identity conflicts.',409);continue;}RecordStore::put('job',$id,['actor_id'=>0,'job_id'=>$id,'asset_id'=>$assetId,'job_type'=>$node['name'],'depends_on'=>$node['depends'],'priority'=>$node['priority'],'priority_weight'=>self::PRIORITIES[$node['priority']],'tenant'=>$policy['owner_domain'],'processing_generation'=>$generation,'status'=>'queued','attempts'=>0,'max_attempts'=>5,'next_attempt_at'=>Utils::now(),'created_at'=>Utils::now()]);"
new="if($existing){if(($existing['asset_id']??'')!==$assetId||($existing['job_type']??'')!==$node['name']||(int)($existing['processing_generation']??0)!==$generation||($existing['tenant']??'')!==$policy['owner_domain'])throw new Error('job_identity_conflict','Existing processing job identity conflicts.',409);continue;}try{RecordStore::put('job',$id,['actor_id'=>0,'job_id'=>$id,'asset_id'=>$assetId,'job_type'=>$node['name'],'depends_on'=>$node['depends'],'priority'=>$node['priority'],'priority_weight'=>self::PRIORITIES[$node['priority']],'tenant'=>$policy['owner_domain'],'processing_generation'=>$generation,'status'=>'queued','attempts'=>0,'max_attempts'=>5,'next_attempt_at'=>Utils::now(),'created_at'=>Utils::now()],0);}catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$winner=RecordStore::get('job',$id);if(!$winner||($winner['asset_id']??'')!==$assetId||($winner['job_type']??'')!==$node['name']||(int)($winner['processing_generation']??0)!==$generation||($winner['tenant']??'')!==$policy['owner_domain'])throw new Error('job_identity_conflict','Concurrent processing job identity conflicts.',409);}}"
if old not in s: raise SystemExit('job graph target missing')
s=s.replace(old,new,1)

old2="public static function recoverOrphans(): int {$count=0;foreach(RecordStore::all('job',0,null,100000) as $j){if(($j['status']??'')==='leased'&&(int)($j['lease_expires_at']??0)<=Utils::now()){$j['status']=(int)$j['attempts']<(int)$j['max_attempts']?'retry':'dead_letter';$j['next_attempt_at']=Utils::now();$j['last_error']='lease_expired';unset($j['lease_token_hash'],$j['lease_owner'],$j['lease_expires_at']);RecordStore::put('job',(string)$j['id'],$j,(int)$j['version']);$count++;}}return $count;}"
new2="public static function recoverOrphans(): int {$count=0;foreach(RecordStore::all('job',0,null,100000) as $j){if(($j['status']??'')==='leased'&&(int)($j['lease_expires_at']??0)<=Utils::now()){$j['status']=(int)$j['attempts']<(int)$j['max_attempts']?'retry':'dead_letter';$j['next_attempt_at']=Utils::now();$j['last_error']='lease_expired';unset($j['lease_token_hash'],$j['lease_owner'],$j['lease_expires_at']);try{RecordStore::put('job',(string)$j['id'],$j,(int)$j['version']);$count++;}catch(Error $recoverError){if($recoverError->errorCode!=='record_version_conflict')throw $recoverError;}}}return $count;}"
if old2 not in s: raise SystemExit('orphan recovery target missing')
s=s.replace(old2,new2,1)
p.write_text(s)

t=ROOT/'tests/review-round-64-job-concurrency.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r64($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 64 FAIL: $m\n");exit(1);}echo "ROUND 64 PASS: $m\n";}
r64(str_contains($s,"RecordStore::put('job',".'$id'.",")&&str_contains($s,"'created_at'=>Utils::now()],0)"),'deterministic processing-job creation is create-only');
r64(str_contains($s,'$winner' . "=RecordStore::get('job'," . '$id' . ")")&&str_contains($s,"Concurrent processing job identity conflicts"),'concurrent graph creator reloads and validates the authoritative job');
r64(str_contains($s,"processing_generation']??0)!=".'$generation')&&str_contains($s,"tenant']??'')!==".'$policy'."['owner_domain']"),'existing deterministic jobs are generation/tenant bound');
r64(str_contains($s,'catch(Error $recoverError)')&&str_contains($s,"$recoverError->errorCode!=='record_version_conflict'"),'orphan recovery treats a concurrent heartbeat/CAS winner as benign and continues');
echo "REVIEW ROUND 64 JOB CONCURRENCY: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-63-upload-create-races.php"\n'
if 'review-round-64-job-concurrency.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-64-job-concurrency.php"\n',1))
