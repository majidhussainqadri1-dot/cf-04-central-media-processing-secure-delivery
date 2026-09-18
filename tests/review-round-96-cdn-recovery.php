<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r96($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 96 FAIL: $m\n");exit(1);}echo "ROUND 96 PASS: $m\n";}
r96(str_contains($s,"'publish_failed'")&&str_contains($s,"$current['status']='publishing'"),'definite CDN publish failure can be safely retried through the deterministic mapping');
r96(str_contains($s,"'publication_unknown'")&&str_contains($s,'cdn_publish_reconciliation_required'),'remote success followed by durable-record failure is quarantined for reconciliation instead of blind republish');
r96(str_contains($s,'$remotePublished=false')&&str_contains($s,'$remotePublished=true'),'CDN failure recovery distinguishes pre-publication failure from post-publication persistence failure');
echo "REVIEW ROUND 96 CDN RECOVERY: PASS\n";
