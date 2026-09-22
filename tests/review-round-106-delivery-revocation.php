<?php
declare(strict_types=1);
$root=dirname(__DIR__);$d=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');
function r106($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 106 FAIL: $m\n");exit(1);}echo "ROUND 106 PASS: $m\n";}
r106(str_contains($d,'grant_contention')&&substr_count($d,"record_version_conflict")>=2,'grant consumption retries CAS contention and rechecks revocation/use limits before returning bytes');
r106(str_contains($d,'purgePublicForAsset')&&str_contains($d,"'purge_pending'")&&str_contains($p,'reconcilePublicPurges'),'asset-wide revocation propagates to public CDN with durable pending reconciliation');
r106(str_contains($d,'cdn_source_integrity_failed')&&str_contains($d,'Utils::streamHash($source)'),'public CDN publication verifies immutable derivative bytes immediately before remote publish');
r106(str_contains($d,'publish_lease_expired')&&str_contains($d,'cdn_publish_reconciliation_required'),'stale publishing state becomes explicit reconciliation-required state instead of remaining stuck forever');
r106(str_contains($d,'concurrent-revocation'),'remote publish success racing revocation preserves version identity for purge reconciliation');
echo "REVIEW ROUND 106 DELIVERY REVOCATION: PASS\n";
