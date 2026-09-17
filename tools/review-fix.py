#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 73 was fully completed before any correction below was started.
# Defect ledger:
# - Upload abort persisted final status before part purge/quota release. If either
#   cleanup side effect failed, retries returned the already-aborted row and could
#   permanently leak temporary objects or reserved quota.

p=ROOT/'sabri-central-media/includes/class-scm-upload.php'
s=p.read_text()
old="""    public static function abort(string $uploadId,int $actor,string $credential,string $reason): array {$u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);if(in_array(($u['status']??''),['completed','aborted'],true))return $u;$u['status']='aborted';$u['abort_reason']=Utils::key($reason,64);$u['aborted_at']=Utils::now();$u=RecordStore::put('upload',$uploadId,$u,(int)$u['version']);PartStore::purge($uploadId);QuotaService::settle($u['quota']['quota_id'],$u['quota']['reservation_id'],false);Audit::record('upload_aborted',['upload_id'=>$uploadId,'actor_id'=>$actor,'reason'=>$reason]);return $u;}
"""
new="""    public static function abort(string $uploadId,int $actor,string $credential,string $reason): array {
        $u=RecordStore::get('upload',$uploadId);if(!$u)throw new Error('upload_not_found','Upload not found.',404);self::authenticate($u,$actor,$credential);
        $state=(string)($u['status']??'');if($state==='completed')return $u;if($state==='aborted')return $u;
        if(!in_array($state,['uploading','paused','aborting'],true))throw new Error('upload_state_invalid','Upload cannot be aborted from its current state.',409);
        if($state!=='aborting'){$u['status']='aborting';$u['abort_reason']=Utils::key($reason,64);$u['abort_started_at']=$u['abort_started_at']??Utils::now();$u=RecordStore::put('upload',$uploadId,$u,(int)$u['version']);}
        PartStore::purge($uploadId);
        if(isset($u['quota']['quota_id'],$u['quota']['reservation_id']))QuotaService::settle((string)$u['quota']['quota_id'],(string)$u['quota']['reservation_id'],false);
        $fresh=RecordStore::get('upload',$uploadId)??$u;$fresh['status']='aborted';$fresh['abort_reason']=Utils::key($reason,64);$fresh['aborted_at']=$fresh['aborted_at']??Utils::now();unset($fresh['abort_started_at']);
        $fresh=RecordStore::put('upload',$uploadId,$fresh,(int)$fresh['version']);Audit::record('upload_aborted',['upload_id'=>$uploadId,'actor_id'=>$actor,'reason'=>$reason]);return $fresh;
    }
"""
if old not in s: raise SystemExit('round 73 upload abort target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-73-abort-recovery.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r73($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 73 FAIL: $m\n");exit(1);}echo "ROUND 73 PASS: $m\n";}
$allowed=<<<'PATTERN'
['uploading','paused','aborting']
PATTERN;
$transition=<<<'PATTERN'
$u['status']='aborting'
PATTERN;
$purgeNeedle=<<<'PATTERN'
PartStore::purge($uploadId)
PATTERN;
$finalNeedle=<<<'PATTERN'
$fresh['status']='aborted'
PATTERN;
$settleNeedle=<<<'PATTERN'
QuotaService::settle((string)$u['quota']['quota_id']
PATTERN;
$doneNeedle=<<<'PATTERN'
if($state==='aborted')return $u
PATTERN;
r73(str_contains($s,$allowed),'abort has an explicit retryable intermediate state');
r73(str_contains($s,$transition),'abort blocks further upload writes before destructive cleanup starts');
$purge=strpos($s,$purgeNeedle);$final=strpos($s,$finalNeedle);
r73($purge!==false&&$final!==false&&$purge<$final,'final aborted status is persisted only after part purge is attempted');
$settle=strpos($s,$settleNeedle);
r73($settle!==false&&$settle<$final,'quota release precedes final aborted status');
r73(str_contains($s,$doneNeedle),'already-finalized abort remains idempotent');
echo "REVIEW ROUND 73 ABORT RECOVERY: PASS\n";
''')

q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-72-record-scan-boundary.php"\n'
if 'review-round-73-abort-recovery.php' not in x:
    if anchor not in x: raise SystemExit('round 73 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-73-abort-recovery.php"\n',1))
