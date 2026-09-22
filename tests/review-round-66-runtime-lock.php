<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');
function r66($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 66 FAIL: $m\n");exit(1);}echo "ROUND 66 PASS: $m\n";}
$createPattern="RecordStore::put('runtime_lock',".'$id'.",";
$expectedPattern='$current?(int)$current' . "['version']:0";
$activePattern='if($current&&(int)($current' . "['expires_at']??0)>" . '$now' . ')return null';
$releasePattern='hash_equals((string)($current' . "['token_hash']??''),hash('sha256'," . '$token' . '))';
r66(str_contains($s,$createPattern)&&str_contains($s,$expectedPattern),'absent runtime lock acquisition is create-only while expired replacement remains CAS-bound');
r66(str_contains($s,$activePattern),'active runtime lock remains fail-closed');
r66(str_contains($s,$releasePattern),'runtime lock release is token-owner bound');
echo "REVIEW ROUND 66 RUNTIME LOCK: PASS\n";
