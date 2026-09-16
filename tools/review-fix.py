#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-lifecycle.php'
s=p.read_text()

# Fresh Review Round 6 was fully completed before applying this correction.
old="$record=['actor_id'=>$actor,'deletion_id'=>$id,'asset_id'=>$assetId,'reason'=>$reason,'status'=>'pending_revoke','steps'=>['revoke_grants'=>'pending','purge_cdn'=>'pending','delete_derivatives'=>'pending','delete_source'=>'pending','delete_mappings'=>'pending','backup_ledger'=>'pending','tombstone'=>'pending'],'attempts'=>0,'next_attempt_at'=>$now,'backup_expiry_at'=>$backup,'created_at'=>$now];$asset['status']='deletion_pending';$asset['deletion_id']=$id;$asset['deletion_requested_at']=$now;RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);try{$record=RecordStore::put('deletion',$id,$record);}catch(\\Throwable $exception){$fresh=RecordStore::get('asset',$assetId);if($fresh&&($fresh['deletion_id']??'')===$id){unset($fresh['deletion_id'],$fresh['deletion_requested_at']);$fresh['status']='ready';RecordStore::put('asset',$assetId,$fresh,(int)$fresh['version']);}throw $exception;}"
new="$record=['actor_id'=>$actor,'deletion_id'=>$id,'asset_id'=>$assetId,'reason'=>$reason,'status'=>'pending_revoke','steps'=>['revoke_grants'=>'pending','purge_cdn'=>'pending','delete_derivatives'=>'pending','delete_source'=>'pending','delete_mappings'=>'pending','backup_ledger'=>'pending','tombstone'=>'pending'],'attempts'=>0,'next_attempt_at'=>$now,'backup_expiry_at'=>$backup,'created_at'=>$now];$previousState=['status'=>$asset['status']??null,'deletion_id'=>$asset['deletion_id']??null,'deletion_requested_at'=>$asset['deletion_requested_at']??null];$asset['status']='deletion_pending';$asset['deletion_id']=$id;$asset['deletion_requested_at']=$now;RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);try{$record=RecordStore::put('deletion',$id,$record);}catch(\\Throwable $exception){$fresh=RecordStore::get('asset',$assetId);if($fresh&&($fresh['deletion_id']??'')===$id){$fresh['status']=$previousState['status'];if($previousState['deletion_id']===null)unset($fresh['deletion_id']);else $fresh['deletion_id']=$previousState['deletion_id'];if($previousState['deletion_requested_at']===null)unset($fresh['deletion_requested_at']);else $fresh['deletion_requested_at']=$previousState['deletion_requested_at'];RecordStore::put('asset',$assetId,$fresh,(int)$fresh['version']);}throw $exception;}"
if old not in s: raise SystemExit('deletion rollback target missing')
s=s.replace(old,new,1)
p.write_text(s)

t=ROOT/'tests/review-round-61-lifecycle-rollback.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r61($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 61 FAIL: $m\n");exit(1);}echo "ROUND 61 PASS: $m\n";}
r61(str_contains($s,"$previousState=['status'=>"),'deletion request captures the exact prior asset lifecycle state');
r61(str_contains($s,"$fresh['status']=$previousState['status']"),'deletion-record persistence failure restores the prior status instead of forcing ready');
r61(str_contains($s,"$previousState['deletion_id']")&&str_contains($s,"$previousState['deletion_requested_at']"),'rollback restores prior deletion metadata exactly');
echo "REVIEW ROUND 61 LIFECYCLE ROLLBACK: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-60-processing-atomicity.php"\n'
if 'review-round-61-lifecycle-rollback.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-61-lifecycle-rollback.php"\n',1))
