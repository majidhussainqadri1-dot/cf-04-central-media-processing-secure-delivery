#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 72 was fully completed before any correction below was started.
# Defect ledger for the completed round:
# - RecordStore::all() treated an exactly-full bounded result set as overflow merely
#   because the final page size equalled the page size. This produced a false
#   record_scan_limit even when no additional record existed.

p=ROOT/'sabri-central-media/includes/class-scm-persistence.php'
s=p.read_text()
old="""    public static function all(string $type,int $actor=0,?string $status=null,int $maximum=100000): array {
        $maximum=max(1,min(1000000,$maximum));$pageSize=min(2000,$maximum);$offset=0;$out=[];
        while(true){
            $page=self::list($type,$actor,$status,$pageSize,$offset);$count=count($page);
            if($count===0)break;
            array_push($out,...$page);$offset+=$count;
            if(count($out)>=$maximum){
                if($count===$pageSize)throw new Error('record_scan_limit','Record scan exceeded its explicit safety limit.',503,['record_type'=>Utils::key($type,48),'maximum'=>$maximum]);
                break;
            }
            if($count<$pageSize)break;
        }
        return $out;
    }
"""
new="""    public static function all(string $type,int $actor=0,?string $status=null,int $maximum=100000): array {
        $maximum=max(1,min(1000000,$maximum));$offset=0;$out=[];
        while(count($out)<$maximum){
            $remaining=$maximum-count($out);$pageSize=min(2000,$remaining);
            $page=self::list($type,$actor,$status,$pageSize,$offset);$count=count($page);
            if($count===0)break;
            array_push($out,...$page);$offset+=$count;
            if(count($out)>=$maximum){
                $probe=self::list($type,$actor,$status,1,$offset);
                if($probe!==[])throw new Error('record_scan_limit','Record scan exceeded its explicit safety limit.',503,['record_type'=>Utils::key($type,48),'maximum'=>$maximum]);
                break;
            }
            if($count<$pageSize)break;
        }
        return $out;
    }
"""
if old not in s: raise SystemExit('round 72 RecordStore::all target missing')
p.write_text(s.replace(old,new,1))

# Permanent dynamic regression coverage: exact ceiling succeeds; true overflow fails closed.
t=ROOT/'tests/review-round-72-record-scan-boundary.php'
t.write_text(r'''<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{Error,RecordStore};
function r72($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 72 FAIL: $m\n");exit(1);}echo "ROUND 72 PASS: $m\n";}
RecordStore::resetMemory();
for($i=0;$i<3;$i++)RecordStore::put('r72','row-'.$i,['actor_id'=>0,'status'=>'active']);
$exact=RecordStore::all('r72',0,null,3);
r72(count($exact)===3,'exactly-full bounded scan returns its records instead of a false record_scan_limit');
RecordStore::put('r72','row-3',['actor_id'=>0,'status'=>'active']);
$overflow=false;
try{RecordStore::all('r72',0,null,3);}catch(Error $e){$overflow=$e->errorCode==='record_scan_limit';}
r72($overflow,'true record population beyond the explicit maximum still fails closed');
$src=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-persistence.php');
r72(str_contains($src,'$probe=self::list($type,$actor,$status,1,$offset)'),'boundary overflow is determined by an explicit one-record probe');
echo "REVIEW ROUND 72 RECORD SCAN BOUNDARY: PASS\n";
''')

q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-71-final-adversarial.php"\n'
if 'review-round-72-record-scan-boundary.php' not in x:
    if anchor not in x: raise SystemExit('round 72 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-72-record-scan-boundary.php"\n',1))
