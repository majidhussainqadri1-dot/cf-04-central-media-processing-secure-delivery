#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 90 was fully completed before corrections began.
# Defect ledger:
# 1) Provider-exit legal holds were checked only while the plan was created. A hold
#    placed after planning could not stop later copy/switch/source-purge phases.
# 2) Repair rollback changed canonical active derivatives without a fresh owner-domain
#    reauthorization/object-version check.
# 3) Repair rollback mutated current manifest, previous manifest, then asset without
#    rollback/reconciliation protection, so a CAS/storage failure could split manifest
#    state from the asset pointer.
# 4) Restore serve authorization wrote the restore row before the gate. If the second
#    write failed, retry was rejected because the restore was already serve_authorized,
#    permanently stranding a reconciled-but-blocking gate.

p=ROOT/'sabri-central-media/includes/class-scm-operations.php'
s=p.read_text()

# Provider-exit: re-check holds at every consequential phase.
old="try{foreach($plan['items'] as $index=>$item){if(in_array(($item['status']??''),['verified','switched','purged'],true))continue;$targetKey="
new="try{foreach($plan['items'] as $index=>$item){if(in_array(($item['status']??''),['verified','switched','purged'],true))continue;$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');$targetKey="
if old not in s and new not in s: raise SystemExit('round 90 provider copy hold anchor missing')
s=s.replace(old,new,1)

old="try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified'"
new="try{foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='switched')continue;if(($item['status']??'')!=='verified')throw new Error('provider_exit_item_not_verified','Provider exit item is not verified.',409,['id'=>$item['id']]);$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing'"
if old in s:
    # this anchor intentionally replaces through the beginning of the record lookup;
    # remove the duplicated tail from the original after replacement below.
    tail="'Provider exit item is not verified.',409,['id'=>$item['id']]);$type=$item['type']==='asset'?'asset':'derivative';$record=RecordStore::get($type,(string)$item['id']);if(!$record)throw new Error('provider_exit_record_missing'"
    start=s.find(old)
    end=s.find(tail,start)
    if end<0: raise SystemExit('round 90 provider switch tail missing')
    end += len(tail)
    s=s[:start]+new+s[end:]
elif new not in s:
    raise SystemExit('round 90 provider switch hold anchor missing')

old="foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='purged')continue;if(($item['status']??'')!=='switched')throw new Error('provider_exit_item_not_switched','Provider exit item was not switched.',409,['id'=>$item['id']]);$type="
new="foreach($plan['items'] as $index=>$item){if(($item['status']??'')==='purged')continue;if(($item['status']??'')!=='switched')throw new Error('provider_exit_item_not_switched','Provider exit item was not switched.',409,['id'=>$item['id']]);$holdAssetId=($item['type']??'')==='asset'?(string)$item['id']:(string)($item['asset_id']??'');if($holdAssetId==='')throw new Error('provider_exit_parent_missing','Provider-exit item has no hold parent.',409,['id'=>$item['id']??'']);LegalHoldService::assertNoHold($holdAssetId,'provider_exit');$type="
if old not in s and new not in s: raise SystemExit('round 90 provider purge hold anchor missing')
s=s.replace(old,new,1)

# Repair rollback: fresh owner authorization and recoverable manifest transition.
old="""    $previous=RecordStore::get('manifest',(string)$repair['previous_manifest_id']);if(!$previous||($previous['asset_id']??'')!==$asset['id'])throw new Error('repair_rollback_manifest_missing','Previous manifest is unavailable.',409);
    $current=!empty($asset['active_manifest_id'])?RecordStore::get('manifest',(string)$asset['active_manifest_id']):null;
    if($current&&($current['status']??'')==='active'){$current['status']='rolled_back';$current['rolled_back_to']=$previous['id'];RecordStore::put('manifest',(string)$current['id'],$current,(int)$current['version']);}
    $previous['status']='active';unset($previous['superseded_by']);RecordStore::put('manifest',(string)$previous['id'],$previous,(int)$previous['version']);
    $asset['active_manifest_id']=$previous['id'];$asset['manifest_version']=max((int)$asset['manifest_version'],(int)$previous['manifest_version']);$asset['status']='ready';$asset['processing_status']='completed';unset($asset['reprocess_context']);
    RecordStore::put('asset',(string)$asset['id'],$asset,(int)$asset['version']);
"""
new="""    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'reason'=>'rollback:'.(string)$repair['reason'],'target_kinds'=>$repair['target_kinds']??[],'preset'=>$repair['preset']??[],'rollback_to_manifest'=>$repair['previous_manifest_id']]);
    if((int)$decision['object_version']!==(int)$asset['object_version']||(int)$decision['object_version']!==(int)($repair['owner_object_version']??0))throw new Error('domain_object_version_stale','Repair rollback authorization is stale.',409);
    $previous=RecordStore::get('manifest',(string)$repair['previous_manifest_id']);if(!$previous||($previous['asset_id']??'')!==$asset['id'])throw new Error('repair_rollback_manifest_missing','Previous manifest is unavailable.',409);
    $current=!empty($asset['active_manifest_id'])?RecordStore::get('manifest',(string)$asset['active_manifest_id']):null;
    $previousOriginal=$previous;$previous['status']='active';unset($previous['superseded_by']);$previous=RecordStore::put('manifest',(string)$previous['id'],$previous,(int)$previous['version']);
    try{$asset['active_manifest_id']=$previous['id'];$asset['manifest_version']=max((int)$asset['manifest_version'],(int)$previous['manifest_version']);$asset['status']='ready';$asset['processing_status']='completed';unset($asset['reprocess_context']);$asset=RecordStore::put('asset',(string)$asset['id'],$asset,(int)$asset['version']);}
    catch(\\Throwable $exception){try{$freshPrevious=RecordStore::get('manifest',(string)$previous['id']);if($freshPrevious){$restore=$previousOriginal;unset($restore['id'],$restore['version'],$restore['created_at'],$restore['updated_at']);foreach($restore as $k=>$v)$freshPrevious[$k]=$v;if(($previousOriginal['superseded_by']??null)===null)unset($freshPrevious['superseded_by']);RecordStore::put('manifest',(string)$freshPrevious['id'],$freshPrevious,(int)$freshPrevious['version']);}}catch(\\Throwable){Audit::record('repair_rollback_reconciliation_required',['repair_id'=>$repairId,'asset_id'=>$asset['id'],'reason'=>'asset_switch_failed_previous_restore_failed']);}throw $exception;}
    if($current&&($current['id']??'')!==$previous['id']&&($current['status']??'')==='active'){try{$freshCurrent=RecordStore::get('manifest',(string)$current['id']);if($freshCurrent&&($freshCurrent['status']??'')==='active'){$freshCurrent['status']='rolled_back';$freshCurrent['rolled_back_to']=$previous['id'];RecordStore::put('manifest',(string)$freshCurrent['id'],$freshCurrent,(int)$freshCurrent['version']);}}catch(\\Throwable){Audit::record('repair_rollback_reconciliation_required',['repair_id'=>$repairId,'asset_id'=>$asset['id'],'reason'=>'previous_activated_current_retire_failed']);}}
"""
if old not in s and new not in s: raise SystemExit('round 90 repair rollback block missing')
s=s.replace(old,new,1)

# Restore authorization: permit exact recovery of a partial restore-row-first transition.
old="""    if(!$restore||!$gate||($gate['restore_id']??'')!==$restoreId||($restore['status']??'')!=='reconciled'||($gate['status']??'')!=='reconciled')throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    $restore['status']='serve_authorized';$restore['serve_authorized_at']=Utils::now();RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);
    $gate['status']='serve_authorized';$gate['serve_authorized_at']=Utils::now();$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);
"""
new="""    if(!$restore||!$gate||($gate['restore_id']??'')!==$restoreId)throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    $restoreStatus=(string)($restore['status']??'');$gateStatus=(string)($gate['status']??'');
    if(!in_array($restoreStatus,['reconciled','serve_authorized'],true)||!in_array($gateStatus,['reconciled','serve_authorized'],true))throw new Error('restore_gate_blocked','Restore reconciliation has not passed.',503);
    if($restoreStatus!=='serve_authorized'){$restore['status']='serve_authorized';$restore['serve_authorized_at']=Utils::now();$restore=RecordStore::put('restore',$restoreId,$restore,(int)$restore['version']);}
    $gate=RecordStore::get('restore_gate','current')??$gate;if(($gate['restore_id']??'')!==$restoreId||!in_array(($gate['status']??''),['reconciled','serve_authorized'],true))throw new Error('restore_gate_mismatch','Restore gate changed during serve authorization.',409);
    if(($gate['status']??'')!=='serve_authorized'){$gate['status']='serve_authorized';$gate['serve_authorized_at']=Utils::now();$gate['updated_at']=Utils::now();RecordStore::put('restore_gate','current',$gate,(int)$gate['version']);}
"""
if old not in s and new not in s: raise SystemExit('round 90 restore authorize block missing')
s=s.replace(old,new,1)

p.write_text(s)

t=ROOT/'tests/review-round-90-operations-recovery.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r90($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 90 FAIL: $m\n");exit(1);}echo "ROUND 90 PASS: $m\n";}
r90(substr_count($s,"LegalHoldService::assertNoHold(\$holdAssetId,'provider_exit')")>=3,'provider exit rechecks newly placed holds during copy, switch and source purge');
r90(str_contains($s,"rollback_to_manifest")&&str_contains($s,"Repair rollback authorization is stale."),'repair rollback is freshly owner-authorized and object-version bound');
r90(str_contains($s,"repair_rollback_reconciliation_required")&&str_contains($s,"asset_switch_failed_previous_restore_failed"),'repair rollback has explicit recovery/reconciliation for partial manifest transition');
r90(str_contains($s,"in_array(\$restoreStatus,['reconciled','serve_authorized'],true)")&&str_contains($s,"if(\$restoreStatus!=='serve_authorized')"),'restore serve authorization can resume a safe partial transition');
r90(str_contains($s,"\$gate=RecordStore::get('restore_gate','current')??\$gate"),'restore gate is refreshed before the final unblock write');
echo "REVIEW ROUND 90 OPERATIONS RECOVERY: PASS\n";
''')

q=ROOT/'tools/quality-check.sh';x=q.read_text()
anchor='php "$ROOT/tests/review-round-89-lifecycle-reauthorization.php"\n'
line='php "$ROOT/tests/review-round-90-operations-recovery.php"\n'
if line not in x:
    if anchor not in x: raise SystemExit('round 90 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+line,1))
