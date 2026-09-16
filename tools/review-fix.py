#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-processing.php'
s=p.read_text()

# Fresh Review Round 67 was fully completed before applying this correction.
old="""    public static function lease(string $workerId,array $capabilities,int $leaseSeconds=120): ?array {
        $workerId=Utils::text($workerId,96);$capabilities=array_values(array_filter(array_unique(array_map(fn($value)=>Utils::key((string)$value,64),$capabilities))));if($workerId===''||$capabilities===[])throw new Error('worker_identity_invalid','Worker identity and capabilities are required.',400);$now=Utils::now();$queued=RecordStore::all('job',0,null,100000);$eligible=[];foreach($queued as $job){if(!in_array(($job['status']??''),['queued','retry'],true)||(int)($job['next_attempt_at']??0)>$now)continue;if(!in_array((string)$job['job_type'],$capabilities,true))continue;if(!self::dependenciesComplete($job))continue;$age=max(0,$now-(int)($job['created_at']??$now));$fairness=min(30,(int)floor($age/60));$tenantPenalty=self::tenantActiveLeases((string)$job['tenant'])*10;$job['_score']=(int)$job['priority_weight']+$fairness-$tenantPenalty;$eligible[]=$job;}if($eligible===[])return null;usort($eligible,fn($a,$b)=>$b['_score']<=>$a['_score'] ?: ((int)$a['created_at']<=>(int)$b['created_at']));$job=$eligible[0];unset($job['_score']);$fresh=RecordStore::get('job',(string)$job['id']);if(!$fresh||!in_array(($fresh['status']??''),['queued','retry'],true))return null;$fresh['status']='leased';$fresh['lease_owner']=$workerId;$leaseToken=Utils::id('lease');$fresh['lease_token_hash']=hash('sha256',$leaseToken);$fresh['lease_expires_at']=$now+max(30,min(600,$leaseSeconds));$fresh['heartbeat_at']=$now;$fresh['attempts']=(int)$fresh['attempts']+1;$saved=RecordStore::put('job',(string)$fresh['id'],$fresh,(int)$fresh['version']);return $saved+['lease_token'=>$leaseToken];
    }
"""
new="""    public static function lease(string $workerId,array $capabilities,int $leaseSeconds=120): ?array {
        $workerId=Utils::text($workerId,96);$capabilities=array_values(array_filter(array_unique(array_map(fn($value)=>Utils::key((string)$value,64),$capabilities))));if($workerId===''||$capabilities===[])throw new Error('worker_identity_invalid','Worker identity and capabilities are required.',400);$now=Utils::now();$queued=RecordStore::all('job',0,null,100000);$eligible=[];foreach($queued as $job){if(!in_array(($job['status']??''),['queued','retry'],true)||(int)($job['next_attempt_at']??0)>$now)continue;if(!in_array((string)$job['job_type'],$capabilities,true))continue;if(!self::dependenciesComplete($job))continue;$age=max(0,$now-(int)($job['created_at']??$now));$fairness=min(30,(int)floor($age/60));$tenantPenalty=self::tenantActiveLeases((string)$job['tenant'])*10;$job['_score']=(int)$job['priority_weight']+$fairness-$tenantPenalty;$eligible[]=$job;}if($eligible===[])return null;usort($eligible,fn($a,$b)=>$b['_score']<=>$a['_score'] ?: ((int)$a['created_at']<=>(int)$b['created_at']));foreach($eligible as $job){unset($job['_score']);$fresh=RecordStore::get('job',(string)$job['id']);if(!$fresh||!in_array(($fresh['status']??''),['queued','retry'],true)||(int)($fresh['next_attempt_at']??0)>$now||!in_array((string)$fresh['job_type'],$capabilities,true)||!self::dependenciesComplete($fresh))continue;$fresh['status']='leased';$fresh['lease_owner']=$workerId;$leaseToken=Utils::id('lease');$fresh['lease_token_hash']=hash('sha256',$leaseToken);$fresh['lease_expires_at']=$now+max(30,min(600,$leaseSeconds));$fresh['heartbeat_at']=$now;$fresh['attempts']=(int)$fresh['attempts']+1;try{$saved=RecordStore::put('job',(string)$fresh['id'],$fresh,(int)$fresh['version']);return $saved+['lease_token'=>$leaseToken];}catch(Error $leaseError){if($leaseError->errorCode!=='record_version_conflict')throw $leaseError;}}return null;
    }
"""
if old not in s: raise SystemExit('lease contention target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-67-lease-contention.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r67($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 67 FAIL: $m\n");exit(1);}echo "ROUND 67 PASS: $m\n";}
r67(str_contains($s,'foreach($eligible as $job)'),'lease selection iterates ranked eligible jobs instead of committing to one stale candidate');
r67(str_contains($s,'catch(Error $leaseError)')&&str_contains($s,"$leaseError->errorCode!=='record_version_conflict'"),'lease CAS contention retries another eligible job while preserving non-conflict failures');
r67(str_contains($s,"!self::dependenciesComplete($fresh))continue"),'lease revalidates dependencies immediately before CAS acquisition');
r67(str_contains($s,"(int)($fresh['next_attempt_at']??0)>$now"),'lease revalidates retry timing immediately before CAS acquisition');
echo "REVIEW ROUND 67 LEASE CONTENTION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-66-runtime-lock.php"\n'
if 'review-round-67-lease-contention.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-67-lease-contention.php"\n',1))
