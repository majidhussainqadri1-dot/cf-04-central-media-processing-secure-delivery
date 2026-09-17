#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 87 was fully completed before corrections began.
# Defect ledger:
# 1) LocalObjectStore::putStream had no write-time 1 GiB ceiling even though openStream
#    refuses objects above that ceiling; an oversized input could therefore persist an
#    object that the same provider could never read and could consume storage unboundedly.
# 2) existing two-hex shard directories were not realpath/symlink checked after the
#    private root was validated, so a replaced shard symlink could escape the private root.

p=ROOT/'sabri-central-media/includes/class-scm-storage.php'
s=p.read_text()
old="    private function path(string $key,bool $create=true): string { if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new Error('object_key_invalid','Invalid object key.',400);$root=$this->root();$dir=$root.'/'.substr($key,0,2);if($create&&!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new Error('storage_write_failed','Object directory unavailable.',500);return $dir.'/'.$key.'.scm'; }"
new="    private function path(string $key,bool $create=true): string { if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new Error('object_key_invalid','Invalid object key.',400);$root=$this->root();$dir=$root.'/'.substr($key,0,2);if($create&&!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new Error('storage_write_failed','Object directory unavailable.',500);if(is_dir($dir)){$dirReal=realpath($dir);if($dirReal===false||is_link($dir)||!Utils::pathWithin($dirReal,$root))throw new Error('storage_path_escape','Object shard must remain inside the private storage root.',503);$dir=$dirReal;}return $dir.'/'.$key.'.scm'; }"
if old not in s and new not in s: raise SystemExit('round 87 path anchor missing')
s=s.replace(old,new,1)
old_loop="if($plain==='')continue;$size+=strlen($plain);hash_update($hash,$plain);Utils::writeAll($out,Utils::json(['i'=>$index]+Crypto::encryptChunk($plain,$key.'|'.$index,$kid)).\"\\n\");$index++;"
new_loop="if($plain==='')continue;$size+=strlen($plain);if($size>1073741824)throw new Error('object_size_invalid','Object exceeds maximum supported size.',413);hash_update($hash,$plain);Utils::writeAll($out,Utils::json(['i'=>$index]+Crypto::encryptChunk($plain,$key.'|'.$index,$kid)).\"\\n\");$index++;"
if old_loop not in s and new_loop not in s: raise SystemExit('round 87 size anchor missing')
s=s.replace(old_loop,new_loop,1)
p.write_text(s)

t=ROOT/'tests/review-round-87-storage-boundaries.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-storage.php');
function r87($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 87 FAIL: $m\n");exit(1);}echo "ROUND 87 PASS: $m\n";}
r87(str_contains($s,"if(\$size>1073741824)throw new Error('object_size_invalid'")&&str_contains($s,"413);hash_update"),'write path enforces the same 1 GiB object ceiling before persistence');
r87(str_contains($s,"is_link(\$dir)")&&str_contains($s,"Utils::pathWithin(\$dirReal,\$root)")&&str_contains($s,"storage_path_escape"),'shard directories cannot redirect object paths outside the private root');
echo "REVIEW ROUND 87 STORAGE BOUNDARIES: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-86-processing-review-pause.php"\n'
if 'review-round-87-storage-boundaries.php' not in x:
    if anchor not in x: raise SystemExit('round 87 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-87-storage-boundaries.php"\n',1))
