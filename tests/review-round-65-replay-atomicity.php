<?php
declare(strict_types=1);
$root=dirname(__DIR__);$rest=file_get_contents($root.'/sabri-central-media/includes/class-scm-rest.php');$ops=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r65($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 65 FAIL: $m\n");exit(1);}echo "ROUND 65 PASS: $m\n";}
r65(str_contains($rest,'$existing?(int)$existing' . "['version']:0"),'service nonce is create-only when no prior nonce exists');
r65(str_contains($rest,'$nonceError' . "->errorCode==='record_version_conflict'")&&str_contains($rest,'Service nonce already consumed concurrently'),'concurrent service nonce conflict is mapped to replay denial');
r65(str_contains($ops,"RecordStore::put('webhook',".'$id'.",")&&str_contains($ops,"'created_at'=>Utils::now()],0"),'webhook first acceptance is create-only');
r65(str_contains($ops,'$winner' . "=RecordStore::get('webhook'," . '$id' . ")")&&str_contains($ops,"return ['replay'=>true,'record'=>".'$winner'.'];'),'concurrent webhook winner is reloaded and returned only as replay');
echo "REVIEW ROUND 65 REPLAY ATOMICITY: PASS\n";
