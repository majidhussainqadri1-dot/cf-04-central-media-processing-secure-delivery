<?php
declare(strict_types=1);
$root=dirname(__DIR__);$u=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r84($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 84 FAIL: $m\n");exit(1);}echo "ROUND 84 PASS: $m\n";}
$createAudit=strpos($u,"Audit::record('upload_session_created'");$createComplete=strpos($u,"Idempotency::complete('upload-create'");
r84($createAudit!==false&&$createComplete!==false&&$createAudit<$createComplete,'upload-create audit evidence precedes terminal idempotency');
$final=strpos($u,'private static function finalizeCompletedUpload');$finalAudit=strpos($u,"Audit::record('asset_quarantined'",$final);$finalComplete=strpos($u,"Idempotency::complete('upload-complete'",$final);
r84($final!==false&&$finalAudit!==false&&$finalComplete!==false&&$finalAudit<$finalComplete,'upload completion audit evidence precedes terminal idempotency');
r84(str_contains($u,"RecordStore::all('upload',0,null,100000)")&&str_contains($u,"\$result['expired']+\$result['failed']>=\$limit"),'expiry cleanup scans beyond the newest page while preserving a bounded work limit');
echo "REVIEW ROUND 84 UPLOAD TERMINAL ORDER: PASS\n";
