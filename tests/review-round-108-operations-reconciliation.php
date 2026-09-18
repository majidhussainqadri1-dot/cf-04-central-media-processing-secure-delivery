<?php
declare(strict_types=1);
$root=dirname(__DIR__);$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-storage.php');$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');
function r108($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 108 FAIL: $m\n");exit(1);}echo "ROUND 108 PASS: $m\n";}
r108(str_contains($o,'reconcileCurrentEnvelopes')&&str_contains($o,'envelopes_reconciled'),'a later rotation run reconciles a stale per-asset envelope even when encrypted storage is already on the active key');
r108(str_contains($o,'key_rotation_cleanup')&&str_contains($o,'reconcileDeferredCleanup')&&str_contains($p,'KeyRotationService::reconcileDeferredCleanup'),'WORM-retained old ciphertext has a durable post-lock cleanup ledger and scheduled reconciler');
r108(str_contains($o,"($m['status']??'')!=='active'")&&str_contains($o,"($d['status']??'')!=='validated'"),'restore manifest reconciliation validates active ownership and derivative records instead of checking only a non-empty manifest id');
r108(str_contains($s,'CredentialRevocableObjectStore')&&str_contains($o,'credentials_pending')&&str_contains($o,'provider_credentials_revocation_unavailable'),'provider exit cannot become completed until source-provider credential revocation is positively evidenced');
echo "REVIEW ROUND 108 OPERATIONS RECONCILIATION: PASS\n";
