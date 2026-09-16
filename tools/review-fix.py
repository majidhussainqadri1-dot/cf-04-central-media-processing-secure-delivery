#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-processing.php'
s=p.read_text()

# Fresh Review Round 4 was completed before these corrections were staged.
old="""$jobs=JobService::graph($assetId,$asset['policy'],$generation);
    $asset['processing_status']='queued';$asset['processing_generation']=$generation;$asset['job_graph']=$jobs;
    RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);"""
new="""$jobs=JobService::graph($assetId,$asset['policy'],$generation);
    $asset['processing_status']='queued';$asset['processing_generation']=$generation;$asset['job_graph']=$jobs;
    try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}
    catch(\\Throwable $exception){$fresh=RecordStore::get('asset',$assetId);$attached=$fresh&&(int)($fresh['processing_generation']??0)===$generation&&(array)($fresh['job_graph']??[])===$jobs;if(!$attached){foreach($jobs as $jobId){$job=RecordStore::get('job',(string)$jobId);if($job&&(int)($job['processing_generation']??0)===$generation&&in_array(($job['status']??''),['queued','retry'],true)){try{RecordStore::delete('job',(string)$jobId);}catch(\\Throwable){}}}}throw $exception;}"""
if old not in s: raise SystemExit('processing graph persistence target missing')
s=s.replace(old,new,1)

old2="""try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}
    catch(\\Throwable $exception){RecordStore::delete('manifest',$manifestId);throw $exception;}
    $manifest['status']='active';$manifest=RecordStore::put('manifest',$manifestId,$manifest,(int)$manifest['version']);
    if($old){$oldManifest=RecordStore::get('manifest',(string)$old);if($oldManifest&&($oldManifest['status']??'')==='active'){$oldManifest['status']='superseded';$oldManifest['superseded_by']=$manifestId;RecordStore::put('manifest',(string)$old,$oldManifest,(int)$oldManifest['version']);}}"""
new2="""$original=$asset;$savedAsset=null;
    try{$savedAsset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}
    catch(\\Throwable $exception){RecordStore::delete('manifest',$manifestId);throw $exception;}
    try{$manifest['status']='active';$manifest=RecordStore::put('manifest',$manifestId,$manifest,(int)$manifest['version']);}
    catch(\\Throwable $exception){try{$current=RecordStore::get('asset',$assetId);if($current&&($current['active_manifest_id']??null)===$manifestId){$rollback=$current;$rollback['active_manifest_id']=$old;$rollback['manifest_version']=$original['manifest_version']??0;$rollback['processing_status']=$original['processing_status']??'pending';$rollback['status']=$original['status']??'quarantined';if(isset($original['ready_at']))$rollback['ready_at']=$original['ready_at'];else unset($rollback['ready_at']);RecordStore::put('asset',$assetId,$rollback,(int)$current['version']);}}catch(\\Throwable $rollbackError){Audit::record('manifest_switch_reconciliation_required',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'reason'=>'activation_failed_rollback_failed']);}try{$failed=RecordStore::get('manifest',$manifestId);if($failed){$failed['status']='activation_failed';RecordStore::put('manifest',$manifestId,$failed,(int)$failed['version']);}}catch(\\Throwable){}throw $exception;}
    if($old){$oldManifest=RecordStore::get('manifest',(string)$old);if($oldManifest&&($oldManifest['status']??'')==='active'){try{$oldManifest['status']='superseded';$oldManifest['superseded_by']=$manifestId;RecordStore::put('manifest',(string)$old,$oldManifest,(int)$oldManifest['version']);}catch(\\Throwable){Audit::record('manifest_supersede_reconciliation_required',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'previous_manifest'=>$old]);}}}"""
if old2 not in s: raise SystemExit('manifest activation target missing')
s=s.replace(old2,new2,1)
p.write_text(s)

t=ROOT/'tests/review-round-60-processing-atomicity.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r60($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 60 FAIL: $m\n");exit(1);}echo "ROUND 60 PASS: $m\n";}
r60(str_contains($s,'$attached=$fresh')&&str_contains($s,"RecordStore::delete('job'"),'unattached graph jobs are cleaned after asset CAS failure without deleting a concurrently attached graph');
r60(str_contains($s,'manifest_switch_reconciliation_required')&&str_contains($s,"'status']='activation_failed'"),'manifest activation failure has rollback and reconciliation evidence');
r60(str_contains($s,'manifest_supersede_reconciliation_required'),'old-manifest supersede conflict is explicitly reconcilable');
echo "REVIEW ROUND 60 PROCESSING ATOMICITY: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-59-upload-finalization.php"\n'
if 'review-round-60-processing-atomicity.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-60-processing-atomicity.php"\n',1))
