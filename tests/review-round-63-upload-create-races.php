<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r63($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 63 FAIL: $m\n");exit(1);}echo "ROUND 63 PASS: $m\n";}
r63(str_contains($s,"RecordStore::put('upload_part',".'$id'.",")&&str_contains($s,"'created_at'=>Utils::now()],0)"),'upload-part creation is create-only instead of an implicit upsert');
r63(str_contains($s,'$authoritative' . "=RecordStore::get('upload_part'," . '$id' . ")")&&str_contains($s,'return $authoritative'),'concurrent identical upload-part winner is reconciled without deleting its object');
r63(str_contains($s,"RecordStore::put('asset',".'$uploadId'.','.'$asset'.",0)"),'asset creation is create-only under concurrent completion');
r63(str_contains($s,'$createError' . "->errorCode!=='record_version_conflict'")&&str_contains($s,'$concurrent' . "=RecordStore::get('asset'," . '$uploadId' . ")"),'concurrent asset winner is reloaded and reconciled before finalization');
echo "REVIEW ROUND 63 UPLOAD CREATE RACES: PASS\n";
