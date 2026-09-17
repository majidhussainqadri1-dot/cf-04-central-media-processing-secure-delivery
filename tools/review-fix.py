#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 85 was fully completed before corrections began.
# Defect ledger:
# 1) sandbox provider output could override the trusted worker attestation because PHP
#    array-union preserves the left operand.
# 2) scanner callback output could likewise forge scanner_id/version provenance.
# 3) low-confidence safety review failures did not persist/reuse the signal on the asset,
#    so every retry created a new pending signal and a reviewed signal could never unblock it.

v=ROOT/'sabri-central-media/includes/class-scm-validation.php'
s=v.read_text()
old="return $provider['output']+['worker'=>['id'=>$workerId,'version'=>$workerVersion,'non_root'=>true,'network_isolated'=>true,'ephemeral'=>true,'resource_limits'=>$provider['resource_limits']]];"
new="return array_replace($provider['output'],['worker'=>['id'=>$workerId,'version'=>$workerVersion,'non_root'=>true,'network_isolated'=>true,'ephemeral'=>true,'resource_limits'=>$provider['resource_limits']]]);"
if old not in s and new not in s: raise SystemExit('round 85 worker-attestation anchor missing')
s=s.replace(old,new,1)
old2="$results[$id]=Utils::redact($result)+['version'=>$meta['version'],'scanner_id'=>$id];"
new2="$results[$id]=array_replace(Utils::redact($result),['version'=>$meta['version'],'scanner_id'=>$id]);"
if old2 not in s and new2 not in s: raise SystemExit('round 85 scanner-provenance anchor missing')
s=s.replace(old2,new2,1)
v.write_text(s)

p=ROOT/'sabri-central-media/includes/class-scm-processing.php'
x=p.read_text()
old3="    private static function scanNode($source,array $asset): array {$scans=ScannerRegistry::scan($source,$asset['policy']['required_scans'],['asset_id'=>$asset['asset_id'],'mime'=>$asset['mime'],'policy'=>$asset['policy']]);$signal=SafetySignalService::evaluate($source,$asset);if(($signal['status']??'')==='pending_review'&&$asset['policy']['safety']['require_reviewer_for_low_confidence'])throw new Error('safety_review_required','Low-confidence technical safety signal requires review.',409,['signal_id'=>$signal['id']]);$asset['scan_status']='passed';$asset['scan_results']=$scans;$asset['safety_signal_id']=$signal['id'];RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);return ['scans'=>$scans,'safety_signal'=>$signal['id']];}"
new3="    private static function scanNode($source,array $asset): array {$scans=ScannerRegistry::scan($source,$asset['policy']['required_scans'],['asset_id'=>$asset['asset_id'],'mime'=>$asset['mime'],'policy'=>$asset['policy']]);$signal=null;$existingSignalId=(string)($asset['safety_signal_id']??'');if($existingSignalId!==''){$candidate=RecordStore::get('safety_signal',$existingSignalId);if($candidate&&($candidate['asset_id']??'')===$asset['asset_id'])$signal=$candidate;}if(!$signal)$signal=SafetySignalService::evaluate($source,$asset);$status=(string)($signal['status']??'pending_review');$asset['scan_results']=$scans;$asset['safety_signal_id']=$signal['id'];if($status==='rejected'){$asset['scan_status']='rejected';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_rejected','Technical safety signal was rejected by review.',422,['signal_id'=>$signal['id']]);}if($status==='escalated'){$asset['scan_status']='awaiting_review';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_escalated','Technical safety signal remains escalated.',409,['signal_id'=>$signal['id']]);}if($status==='pending_review'&&$asset['policy']['safety']['require_reviewer_for_low_confidence']){$asset['scan_status']='awaiting_review';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_required','Low-confidence technical safety signal requires review.',409,['signal_id'=>$signal['id']]);}$asset['scan_status']='passed';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);return ['scans'=>$scans,'safety_signal'=>$signal['id']];}"
if old3 not in x and new3 not in x: raise SystemExit('round 85 safety-review anchor missing')
x=x.replace(old3,new3,1)
p.write_text(x)

t=ROOT/'tests/review-round-85-validation-provenance.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$v=file_get_contents($root.'/sabri-central-media/includes/class-scm-validation.php');$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r85($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 85 FAIL: $m\n");exit(1);}echo "ROUND 85 PASS: $m\n";}
r85(str_contains($v,"array_replace(\$provider['output'],['worker'=>"),'trusted sandbox attestation overrides provider-supplied worker provenance');
r85(str_contains($v,"array_replace(Utils::redact(\$result),['version'=>\$meta['version'],'scanner_id'=>\$id])"),'registered scanner identity/version override callback-supplied provenance');
r85(str_contains($p,"\$existingSignalId=(string)(\$asset['safety_signal_id']??'')")&&str_contains($p,"RecordStore::get('safety_signal',\$existingSignalId)"),'scan retry reuses the asset-bound safety signal');
r85(substr_count($p,"\$asset['scan_status']='awaiting_review'")>=2&&str_contains($p,"\$asset['safety_signal_id']=\$signal['id']"),'pending or escalated review state is durably linked before fail-closed retry');
r85(str_contains($p,"safety_review_rejected")&&str_contains($p,"\$asset['scan_status']='rejected'"),'human rejection fails closed and is persisted');
echo "REVIEW ROUND 85 VALIDATION PROVENANCE: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';qs=q.read_text();anchor='php "$ROOT/tests/review-round-84-upload-terminal-order.php"\n'
if 'review-round-85-validation-provenance.php' not in qs:
    if anchor not in qs: raise SystemExit('round 85 quality anchor missing')
    q.write_text(qs.replace(anchor,anchor+'php "$ROOT/tests/review-round-85-validation-provenance.php"\n',1))
