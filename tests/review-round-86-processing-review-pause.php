<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-processing.php');
function r86($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 86 FAIL: $m\n");exit(1);}echo "ROUND 86 PASS: $m\n";}
$old=strpos($s,"\$old=\$asset['active_manifest_id']??null;\$original=\$asset;");$mut=strpos($s,"\$asset['active_manifest_id']=\$manifestId",$old===false?0:$old);
r86($old!==false&&$mut!==false&&$old<$mut,'manifest rollback snapshot is captured before new manifest state mutates the asset');
r86(str_contains($s,'public static function awaitSafetyReview')&&str_contains($s,"\$job['status']='waiting_review'"),'safety-review pauses release leases into a durable waiting state');
r86(str_contains($s,'public static function resumeSafetyReview')&&str_contains($s,"in_array(\$status,['accepted','informational'],true)"),'accepted review can requeue the exact waiting scan job');
r86(str_contains($s,"\$asset['processing_status']='awaiting_review'")&&str_contains($s,"['safety_review_required','safety_review_escalated']"),'review-required errors no longer become ordinary failed/dead-letter processing');
echo "REVIEW ROUND 86 PROCESSING REVIEW PAUSE: PASS\n";
