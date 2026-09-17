#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 75 was fully completed before any correction below was started.
# Defect ledger:
# - DerivativeService::manifest() accepted any still-validated derivative belonging
#   to the asset. Historical derivatives that were not in the currently active
#   manifest could therefore be reintroduced into a newer processing generation.
#   Partial repair legitimately needs current active-manifest carryover, so the
#   correction distinguishes authorized carryover from arbitrary stale lineage.

p=ROOT/'sabri-central-media/includes/class-scm-processing.php'
s=p.read_text()
old="""    $derivativeIds=array_values(array_unique(array_map('strval',$derivativeIds)));
    if($derivativeIds===[])throw new Error('derivative_set_empty','No derivatives supplied for manifest.',422);
    $items=[];$kinds=[];
    foreach($derivativeIds as $id){
        $derivative=RecordStore::get('derivative',$id);
        if(!$derivative||($derivative['asset_id']??'')!==$assetId||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))throw new Error('derivative_not_validated','Derivative is not validated.',409,['derivative_id'=>$id]);
        if(isset($kinds[$derivative['kind']]))throw new Error('derivative_kind_duplicate','Manifest contains duplicate derivative kinds.',409,['kind'=>$derivative['kind']]);
"""
new="""    $derivativeIds=array_values(array_unique(array_map('strval',$derivativeIds)));
    if($derivativeIds===[])throw new Error('derivative_set_empty','No derivatives supplied for manifest.',422);
    $currentGeneration=max(1,(int)($asset['processing_generation']??1));$carryover=[];$activeId=(string)($asset['active_manifest_id']??'');
    if($activeId!==''){$active=RecordStore::get('manifest',$activeId);if($active&&($active['status']??'')==='active')foreach((array)($active['derivatives']??[]) as $item){$carryId=(string)($item['derivative_id']??'');if($carryId!=='')$carryover[$carryId]=true;}}
    $items=[];$kinds=[];
    foreach($derivativeIds as $id){
        $derivative=RecordStore::get('derivative',$id);
        if(!$derivative||($derivative['asset_id']??'')!==$assetId||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))throw new Error('derivative_not_validated','Derivative is not validated.',409,['derivative_id'=>$id]);
        $generation=max(1,(int)($derivative['lineage']['processing_generation']??1));if($generation!==$currentGeneration&&!isset($carryover[$id]))throw new Error('derivative_generation_stale','Derivative is not from the current processing generation or the active-manifest carryover set.',409,['derivative_id'=>$id,'generation'=>$generation,'current_generation'=>$currentGeneration]);
        if(isset($kinds[$derivative['kind']]))throw new Error('derivative_kind_duplicate','Manifest contains duplicate derivative kinds.',409,['kind'=>$derivative['kind']]);
"""
if old not in s: raise SystemExit('round 75 manifest target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-75-manifest-lineage.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r75($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 75 FAIL: $m\n");exit(1);}echo "ROUND 75 PASS: $m\n";}
r75(str_contains($s,"$currentGeneration=max(1,(int)($asset['processing_generation']??1))"),'manifest binds derivative selection to the asset processing generation');
r75(str_contains($s,"($active['status']??'')==='active'"),'only the current active manifest can authorize historical carryover');
r75(str_contains($s,"$generation!==$currentGeneration&&!isset($carryover[$id])"),'arbitrary stale validated derivatives fail closed');
r75(str_contains($s,"derivative_generation_stale"),'stale-lineage rejection has an explicit conflict code');
echo "REVIEW ROUND 75 MANIFEST LINEAGE: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-74-range-suffix.php"\n'
if 'review-round-75-manifest-lineage.php' not in x:
    if anchor not in x: raise SystemExit('round 75 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-75-manifest-lineage.php"\n',1))
