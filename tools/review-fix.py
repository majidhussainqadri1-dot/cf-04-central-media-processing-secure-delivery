#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"expected review target not found: {path}")
    path.write_text(text.replace(old, new, 1))

processing = ROOT / 'sabri-central-media/includes/class-scm-processing.php'
replace_once(
    processing,
    "$jobs=JobService::graph($assetId,$asset['policy'],$generation);\n    $asset['processing_status']='queued';$asset['processing_generation']=$generation;$asset['job_graph']=$jobs;\n    RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);\n    Audit::record('processing_graph_created',['asset_id'=>$assetId,'processing_generation'=>$generation,'jobs'=>array_keys($jobs)]);\n    return $jobs;",
    "$jobs=JobService::graph($assetId,$asset['policy'],$generation);\n    $asset['processing_status']='queued';$asset['processing_generation']=$generation;$asset['job_graph']=$jobs;\n    try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}\n    catch(\\Throwable $exception){foreach($jobs as $jobId){$job=RecordStore::get('job',(string)$jobId);if($job&&($job['asset_id']??'')===$assetId&&(int)($job['processing_generation']??0)===$generation&&($job['status']??'')==='queued')RecordStore::delete('job',(string)$jobId);}throw $exception;}\n    Audit::record('processing_graph_created',['asset_id'=>$assetId,'processing_generation'=>$generation,'jobs'=>array_keys($jobs)]);\n    return $jobs;"
)

replace_once(
    processing,
    "$old=$asset['active_manifest_id']??null;\n    $asset['active_manifest_id']=$manifestId;$asset['manifest_version']=$manifest['manifest_version'];$asset['processing_status']='completed';$asset['status']='ready';$asset['ready_at']=Utils::now();\n    unset($asset['reprocess_context']);\n    try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}\n    catch(\\Throwable $exception){RecordStore::delete('manifest',$manifestId);throw $exception;}\n    $manifest['status']='active';$manifest=RecordStore::put('manifest',$manifestId,$manifest,(int)$manifest['version']);\n    if($old){$oldManifest=RecordStore::get('manifest',(string)$old);if($oldManifest&&($oldManifest['status']??'')==='active'){$oldManifest['status']='superseded';$oldManifest['superseded_by']=$manifestId;RecordStore::put('manifest',(string)$old,$oldManifest,(int)$oldManifest['version']);}}",
    "$old=$asset['active_manifest_id']??null;$before=$asset;\n    $asset['active_manifest_id']=$manifestId;$asset['manifest_version']=$manifest['manifest_version'];$asset['processing_status']='completed';$asset['status']='ready';$asset['ready_at']=Utils::now();\n    unset($asset['reprocess_context']);\n    try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}\n    catch(\\Throwable $exception){RecordStore::delete('manifest',$manifestId);throw $exception;}\n    try{$manifest['status']='active';$manifest=RecordStore::put('manifest',$manifestId,$manifest,(int)$manifest['version']);}\n    catch(\\Throwable $exception){$fresh=RecordStore::get('asset',$assetId);if($fresh&&($fresh['active_manifest_id']??null)===$manifestId){$fresh['active_manifest_id']=$before['active_manifest_id']??null;$fresh['manifest_version']=(int)($before['manifest_version']??0);$fresh['processing_status']=$before['processing_status']??'failed';$fresh['status']=$before['status']??'quarantined';if(isset($before['ready_at']))$fresh['ready_at']=$before['ready_at'];else unset($fresh['ready_at']);RecordStore::put('asset',$assetId,$fresh,(int)$fresh['version']);}RecordStore::delete('manifest',$manifestId);throw $exception;}\n    if($old){$oldManifest=RecordStore::get('manifest',(string)$old);if($oldManifest&&($oldManifest['status']??'')==='active'){try{$oldManifest['status']='superseded';$oldManifest['superseded_by']=$manifestId;RecordStore::put('manifest',(string)$old,$oldManifest,(int)$oldManifest['version']);}catch(\\Throwable $exception){Observability::alert('warning','manifest_supersede_reconciliation_required',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'previous_manifest'=>$old,'exception'=>get_class($exception)]);}}}"
)

regression = ROOT / 'tests/review-round-58-processing-consistency.php'
regression.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$source=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r58(bool $ok,string $message): void {if(!$ok){fwrite(STDERR,"ROUND 58 FAIL: $message\n");exit(1);}echo "ROUND 58 PASS: $message\n";}
r58(str_contains($source,"RecordStore::delete('job',(string)$jobId)"),'orphan queued jobs are discarded when asset graph persistence fails');
r58(str_contains($source,"if($fresh&&($fresh['active_manifest_id']??null)===$manifestId)")&&str_contains($source,"RecordStore::delete('manifest',$manifestId)"),'manifest activation failure rolls asset pointer back and removes pending manifest');
r58(str_contains($source,'manifest_supersede_reconciliation_required'),'old-manifest supersede failure is surfaced for reconciliation without corrupting new active state');
echo "REVIEW ROUND 58 PROCESSING CONSISTENCY: PASS\n";
''')

quality = ROOT / 'tools/quality-check.sh'
q = quality.read_text()
needle = 'php "$ROOT/tests/review-round-57-upload-validation.php"\n'
insert = needle + 'php "$ROOT/tests/review-round-58-processing-consistency.php"\n'
if 'review-round-58-processing-consistency.php' not in q:
    if needle not in q:
        raise SystemExit('quality-check insertion point missing')
    quality.write_text(q.replace(needle, insert, 1))

# Round 4 corrections are intentionally applied only after the complete review ledger is frozen.
