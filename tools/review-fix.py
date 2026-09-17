#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 74 was fully completed before any correction below was started.
# Defect ledger:
# - Validator::range() accepted the syntactically empty suffix request bytes=-0 and
#   silently converted it into a one-byte request. A zero-length suffix must fail
#   closed instead of widening/rewriting the client's requested range.

p=ROOT/'sabri-central-media/includes/class-scm-validation.php'
s=p.read_text()
old="""if($start===null){$length=min($size,max(1,$end??0));$start=$size-$length;$end=$size-1;}else{$end=$end??($size-1);}if($start<0||$end<$start||$end>=$size||$end-$start+1>$maxBytes)throw new Error('range_not_satisfiable','Requested range is outside policy.',416);return ['start'=>$start,'end'=>$end,'length'=>$end-$start+1,'partial'=>true];
"""
new="""if($start===null){$suffix=(int)($end??0);if($suffix<1)throw new Error('range_not_satisfiable','Requested suffix range is empty.',416);$length=min($size,$suffix);$start=$size-$length;$end=$size-1;}else{$end=$end??($size-1);}if($start<0||$end<$start||$end>=$size||$end-$start+1>$maxBytes)throw new Error('range_not_satisfiable','Requested range is outside policy.',416);return ['start'=>$start,'end'=>$end,'length'=>$end-$start+1,'partial'=>true];
"""
if old not in s: raise SystemExit('round 74 range target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-74-range-suffix.php'
t.write_text(r'''<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Error,Validator};
function r74($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 74 FAIL: $m\n");exit(1);}echo "ROUND 74 PASS: $m\n";}
$failed=false;try{Validator::range('bytes=-0',100,50);}catch(Error $e){$failed=$e->errorCode==='range_not_satisfiable'&&$e->httpStatus===416;}
r74($failed,'zero-length suffix range fails closed');
$r=Validator::range('bytes=-10',100,50);r74($r['start']===90&&$r['end']===99&&$r['length']===10&&$r['partial']===true,'valid suffix range keeps exact requested semantics');
$r=Validator::range('bytes=-200',100,200);r74($r['start']===0&&$r['end']===99&&$r['length']===100,'suffix larger than representation safely resolves to the full representation');
echo "REVIEW ROUND 74 RANGE SUFFIX: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-73-abort-recovery.php"\n'
if 'review-round-74-range-suffix.php' not in x:
    if anchor not in x: raise SystemExit('round 74 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-74-range-suffix.php"\n',1))
