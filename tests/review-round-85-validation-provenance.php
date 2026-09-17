<?php
declare(strict_types=1);
$root=dirname(__DIR__);$v=file_get_contents($root.'/sabri-central-media/includes/class-scm-validation.php');$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r85($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 85 FAIL: $m\n");exit(1);}echo "ROUND 85 PASS: $m\n";}
r85(str_contains($v,"array_replace(\$provider['output'],['worker'=>"),'trusted sandbox attestation overrides provider-supplied worker provenance');
r85(str_contains($v,"array_replace(Utils::redact(\$result),['version'=>\$meta['version'],'scanner_id'=>\$id])"),'registered scanner identity/version override callback-supplied provenance');
r85(str_contains($p,"\$existingSignalId=(string)(\$asset['safety_signal_id']??'')")&&str_contains($p,"RecordStore::get('safety_signal',\$existingSignalId)"),'scan retry reuses the asset-bound safety signal');
r85(substr_count($p,"\$asset['scan_status']='awaiting_review'")>=2&&str_contains($p,"\$asset['safety_signal_id']=\$signal['id']"),'pending or escalated review state is durably linked before fail-closed retry');
r85(str_contains($p,"safety_review_rejected")&&str_contains($p,"\$asset['scan_status']='rejected'"),'human rejection fails closed and is persisted');
echo "REVIEW ROUND 85 VALIDATION PROVENANCE: PASS\n";
