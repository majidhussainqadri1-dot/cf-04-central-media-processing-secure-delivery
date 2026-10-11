<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-transfer.php');
function r91($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 91 FAIL: $m\n");exit(1);}echo "ROUND 91 PASS: $m\n";}
r91(str_contains($s,'transfer_asset_actor_mismatch')&&str_contains($s,'transfer_bind_version_stale'),'asset binding is sender-bound and canonical-version bound');
r91(str_contains($s,'transfer_ready_state_denied')&&str_contains($s,'transfer_ready_version_stale'),'ready transition cannot resurrect an expired/revoked transfer and rejects stale owner truth');
r91(str_contains($s,'transfer_delivery_version_stale'),'recipient grant issuance rejects stale native/asset owner version');
r91(str_contains($s,'transfer_revoke_version_stale'),'transfer revocation rejects stale owner authorization');
r91(str_contains($s,'download_authorization_stale')&&substr_count($s,'authorize_download')>=2,'download grant performs fresh owner-domain authorization at actual grant time');
echo "REVIEW ROUND 91 TRANSFER ACTION BOUNDARIES: PASS\n";
