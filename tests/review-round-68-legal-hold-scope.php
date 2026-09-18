<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r68($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 68 FAIL: $m\n");exit(1);}echo "ROUND 68 PASS: $m\n";}
r68(str_contains($s,"'expire-derivatives'=>'deletion'")&&str_contains($s,"'expire_derivatives'=>'deletion'"),'derivative expiry operations map to deletion hold scope');
r68(str_contains($s,"self::authorizeCurrent($assetId,0,$reason,['scope'=>'derivatives','phase'=>'expire_derivatives'],'expire-derivatives')")&&str_contains($s,'LegalHoldService::assertNoHold($assetId,$holdOperation)'),'derivative expiry path remains protected by the centralized hold assertion through action-time authorization');
r68(str_contains($s,'$allowed=[\'delivery\',\'processing\',\'deletion\',\'reprocess\',\'provider_exit\',\'all\'];'),'legal-hold accepted scopes remain canonical and bounded');
echo "REVIEW ROUND 68 LEGAL HOLD SCOPE: PASS\n";
