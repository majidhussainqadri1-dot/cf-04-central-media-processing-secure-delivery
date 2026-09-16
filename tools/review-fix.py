#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-upload.php'
s=p.read_text()

# Fresh Review Round 63 was fully completed before applying this correction.
old="try{return RecordStore::put('upload_part',$id,['actor_id'=>Auth::currentUser(),'upload_id'=>$uploadId,'part_number'=>$part,'provider_id'=>ProviderRegistry::activeId(),'object_key'=>$stored['object_key'],'size'=>$hash['size'],'sha256'=>$expectedHash,'status'=>'stored','created_at'=>Utils::now()]);}\n        catch(\\Throwable $e){$store->delete((string)$stored['object_key']);throw $e;}"
new="try{return RecordStore::put('upload_part',$id,['actor_id'=>Auth::currentUser(),'upload_id'=>$uploadId,'part_number'=>$part,'provider_id'=>ProviderRegistry::activeId(),'object_key'=>$stored['object_key'],'size'=>$hash['size'],'sha256'=>$expectedHash,'status'=>'stored','created_at'=>Utils::now()],0);}\n        catch(\\Throwable $e){$authoritative=RecordStore::get('upload_part',$id);if($authoritative&&hash_equals((string)($authoritative['sha256']??''),$expectedHash)&&($authoritative['object_key']??'')===($stored['object_key']??''))return $authoritative;if(!$authoritative||($authoritative['object_key']??'')!==($stored['object_key']??''))$store->delete((string)$stored['object_key']);throw $e;}"
if old not in s: raise SystemExit('upload-part create/cleanup target missing')
s=s.replace(old,new,1)

old2=";$asset=RecordStore::put('asset',$uploadId,$asset);$assetCreated=true;return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint);"
new2=";try{$asset=RecordStore::put('asset',$uploadId,$asset,0);$assetCreated=true;}catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$concurrent=RecordStore::get('asset',$uploadId);if(!$concurrent||($concurrent['source_upload_id']??'')!==$uploadId||(int)($concurrent['actor_id']??0)!==$actor||!hash_equals((string)($concurrent['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($concurrent['policy_hash']??''),(string)$u['policy_hash']))throw new Error('asset_reconciliation_mismatch','Concurrent asset cannot be reconciled to this upload.',409);$asset=$concurrent;$assetCreated=true;}return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint);"
if old2 not in s: raise SystemExit('asset create race target missing')
s=s.replace(old2,new2,1)
p.write_text(s)

t=ROOT/'tests/review-round-63-upload-create-races.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r63($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 63 FAIL: $m\n");exit(1);}echo "ROUND 63 PASS: $m\n";}
r63(str_contains($s,"RecordStore::put('upload_part',$id,")&&str_contains($s,"'created_at'=>Utils::now()],0)"),'upload-part creation is create-only instead of an implicit upsert');
r63(str_contains($s,"$authoritative=RecordStore::get('upload_part',$id)")&&str_contains($s,"return $authoritative"),'concurrent identical upload-part winner is reconciled without deleting its object');
r63(str_contains($s,"RecordStore::put('asset',$uploadId,$asset,0)"),'asset creation is create-only under concurrent completion');
r63(str_contains($s,"$createError->errorCode!=='record_version_conflict'")&&str_contains($s,"$concurrent=RecordStore::get('asset',$uploadId)"),'concurrent asset winner is reloaded and reconciled before finalization');
echo "REVIEW ROUND 63 UPLOAD CREATE RACES: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-61-lifecycle-rollback.php"\n'
if 'review-round-63-upload-create-races.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-63-upload-create-races.php"\n',1))
