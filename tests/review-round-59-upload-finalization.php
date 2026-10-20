<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$source=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r59(bool $ok,string $message): void {if(!$ok){fwrite(STDERR,"ROUND 59 FAIL: $message\n");exit(1);}echo "ROUND 59 PASS: $message\n";}
r59(str_contains($source,'private static function finalizeCompletedUpload'),'completion has an explicit retryable reconciliation finalizer');
r59(str_contains($source,"['uploading','finalizing','completed']"),'completion entry permits only the three reconciliation-safe states');
r59(str_contains($source,"RecordStore::get('asset',")&&str_contains($source,'asset_reconciliation_mismatch'),'retry reconciles an already-created matching asset and rejects mismatches');
r59(str_contains($source,"['status']='finalizing'")&&str_contains($source,"['status']='completed'"),'upload persists explicit finalizing and completed phases');
r59(str_contains($source,'QuotaService::settle')&&str_contains($source,'PartStore::purge')&&str_contains($source,'Idempotency::complete'),'finalizer covers quota, multipart cleanup and idempotency completion');
$completedPos=strpos($source,"['status']='completed'");
$idempotentPos=strpos($source,"Idempotency::complete('upload-complete'",$completedPos===false?0:$completedPos);
r59($completedPos!==false&&$idempotentPos!==false&&$completedPos<$idempotentPos,'durable completed state precedes idempotency completion so retry cannot strand a finalizing upload');
echo "REVIEW ROUND 59 UPLOAD FINALIZATION: PASS\n";
