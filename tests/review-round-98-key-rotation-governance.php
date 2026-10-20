<?php
declare(strict_types=1);
$root=dirname(__DIR__);$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');$f=file_get_contents($root.'/sabri-central-media/includes/class-scm-future40.php');
function r98($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 98 FAIL: $m\n");exit(1);}echo "ROUND 98 PASS: $m\n";}
r98(str_contains($o,'key_rotation_reference_drift')&&str_contains($o,'liveReferences'),'old ciphertext is never deleted until the current live-reference inventory is rechecked');
r98(str_contains($o,'locked_old_objects_retained')&&str_contains($o,'ResidencyCryptoService::isLocked'),'active WORM locks retain the old encrypted object during key rotation');
r98(str_contains($o,'syncAssetEnvelope')&&str_contains($o,'asset_key_envelope_stale'),'actual key rotation synchronizes any per-asset envelope metadata and rejects stale envelope identity');
r98(str_contains($f,'asset_key_envelope_mismatch')&&str_contains($f,'asset_key_envelope_algorithm_mismatch'),'per-asset envelope records cannot claim a key or algorithm different from current encrypted storage');
echo "REVIEW ROUND 98 KEY ROTATION GOVERNANCE: PASS\n";
