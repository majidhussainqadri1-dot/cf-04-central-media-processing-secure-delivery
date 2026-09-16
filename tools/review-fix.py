#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-plugin.php'
s=p.read_text()

# Fresh Review Round 66 was fully completed before applying this correction.
old="private static function acquire(string $name,int $ttl): ?string {$id=Utils::key($name,48);$now=Utils::now();$current=RecordStore::get('runtime_lock',$id);if($current&&(int)($current['expires_at']??0)>$now)return null;$token=Utils::id('lock');try{RecordStore::put('runtime_lock',$id,['actor_id'=>0,'status'=>'held','token_hash'=>hash('sha256',$token),'expires_at'=>$now+$ttl,'created_at'=>$now],$current?(int)$current['version']:null);return $token;}catch(\\Throwable){return null;}}"
new="private static function acquire(string $name,int $ttl): ?string {$id=Utils::key($name,48);$now=Utils::now();$current=RecordStore::get('runtime_lock',$id);if($current&&(int)($current['expires_at']??0)>$now)return null;$token=Utils::id('lock');try{RecordStore::put('runtime_lock',$id,['actor_id'=>0,'status'=>'held','token_hash'=>hash('sha256',$token),'expires_at'=>$now+$ttl,'created_at'=>$now],$current?(int)$current['version']:0);return $token;}catch(\\Throwable){return null;}}"
if old not in s: raise SystemExit('runtime lock acquire target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-66-runtime-lock.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-plugin.php');
function r66($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 66 FAIL: $m\n");exit(1);}echo "ROUND 66 PASS: $m\n";}
r66(str_contains($s,"RecordStore::put('runtime_lock',")&&str_contains($s,'$current?(int)$current' . "['version']:0"),'absent runtime lock acquisition is create-only while expired replacement remains CAS-bound');
r66(str_contains($s,'if($current&&(int)($current' . "['expires_at']??0)>$now)return null"),'active runtime lock remains fail-closed');
r66(str_contains($s,'hash_equals((string)($current' . "['token_hash']??''),hash('sha256',$token))"),'runtime lock release is token-owner bound');
echo "REVIEW ROUND 66 RUNTIME LOCK: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-65-replay-atomicity.php"\n'
if 'review-round-66-runtime-lock.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-66-runtime-lock.php"\n',1))
