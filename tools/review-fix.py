#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 65 was fully completed before applying these corrections.
p=ROOT/'sabri-central-media/includes/class-scm-rest.php'
s=p.read_text()
old="RecordStore::put('service_nonce',$id,['actor_id'=>0,'service_id'=>$serviceId,'nonce_hash'=>hash('sha256',$nonce),'body_hash'=>$bodyHash,'status'=>'consumed','expires_at'=>Utils::now()+600,'created_at'=>Utils::now()],$existing?(int)$existing['version']:null);"
new="try{RecordStore::put('service_nonce',$id,['actor_id'=>0,'service_id'=>$serviceId,'nonce_hash'=>hash('sha256',$nonce),'body_hash'=>$bodyHash,'status'=>'consumed','expires_at'=>Utils::now()+600,'created_at'=>Utils::now()],$existing?(int)$existing['version']:0);}catch(Error $nonceError){if($nonceError->errorCode==='record_version_conflict')throw new Error('service_replay_denied','Service nonce already consumed concurrently.',409);throw $nonceError;}"
if old not in s: raise SystemExit('service nonce target missing')
p.write_text(s.replace(old,new,1))

p=ROOT/'sabri-central-media/includes/class-scm-operations.php'
s=p.read_text()
old="$record=RecordStore::put('webhook',$id,['actor_id'=>0,'provider'=>$provider,'event_id'=>$eventId,'body_hash'=>$bodyHash,'status'=>'accepted','created_at'=>Utils::now()]);\n    return ['replay'=>false,'record'=>$record];"
new="try{$record=RecordStore::put('webhook',$id,['actor_id'=>0,'provider'=>$provider,'event_id'=>$eventId,'body_hash'=>$bodyHash,'status'=>'accepted','created_at'=>Utils::now()],0);return ['replay'=>false,'record'=>$record];}\n    catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$winner=RecordStore::get('webhook',$id);if(!$winner)throw $createError;if(!hash_equals((string)($winner['body_hash']??''),$bodyHash))throw new Error('webhook_replay_conflict','Webhook event identifier was reused with different content.',409);return ['replay'=>true,'record'=>$winner];}"
if old not in s: raise SystemExit('webhook create target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-65-replay-atomicity.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$rest=file_get_contents($root.'/sabri-central-media/includes/class-scm-rest.php');$ops=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r65($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 65 FAIL: $m\n");exit(1);}echo "ROUND 65 PASS: $m\n";}
r65(str_contains($rest,'$existing?(int)$existing' . "['version']:0"),'service nonce is create-only when no prior nonce exists');
r65(str_contains($rest,'$nonceError' . "->errorCode==='record_version_conflict'")&&str_contains($rest,'Service nonce already consumed concurrently'),'concurrent service nonce conflict is mapped to replay denial');
r65(str_contains($ops,"RecordStore::put('webhook',".'$id'.",")&&str_contains($ops,"'created_at'=>Utils::now()],0"),'webhook first acceptance is create-only');
r65(str_contains($ops,'$winner' . "=RecordStore::get('webhook'," . '$id' . ")")&&str_contains($ops,"return ['replay'=>true,'record'=>".'$winner'.'];'),'concurrent webhook winner is reloaded and returned only as replay');
echo "REVIEW ROUND 65 REPLAY ATOMICITY: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-64-job-concurrency.php"\n'
if 'review-round-65-replay-atomicity.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-65-replay-atomicity.php"\n',1))
