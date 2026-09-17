#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 78 was fully completed before corrections began.
# Defect ledger:
# - Key rotation inventory grouped only records whose metadata said their key_id was
#   stale. If the same physical provider/object key was shared by another live
#   record whose metadata already claimed the active key, that reference was left
#   on the old object while rotation could delete the old object. The group also
#   trusted the first record's sha/size without proving all shared references had
#   identical content identity. Shared physical objects must rotate as one complete
#   reference group or fail closed.

p=ROOT/'sabri-central-media/includes/class-scm-operations.php'
s=p.read_text()
old="""    $groups=[];
    foreach(RecordStore::all('asset',0,null,100000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key'])||($record['storage']['key_id']??'')===$active)continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'asset','record'=>$record];}
    foreach(RecordStore::all('derivative',0,null,200000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key'])||($record['storage']['key_id']??'')===$active)continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'derivative','record'=>$record];}
    RecordStore::put('key_rotation',$runId,['actor_id'=>$actor,'rotation_id'=>$runId,'active_key_id'=>$active,'status'=>'running','groups_total'=>count($groups),'result'=>$result,'created_at'=>Utils::now()]);
    foreach($groups as $groupKey=>$group){
        $first=$group[0]['record'];$providerId=Utils::key((string)($first['storage']['provider_id']??ProviderRegistry::activeId()),64);$oldKey=(string)$first['object_key'];$newKey='';
"""
new="""    $groups=[];
    foreach(RecordStore::all('asset',0,null,100000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key']))continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'asset','record'=>$record];}
    foreach(RecordStore::all('derivative',0,null,200000) as $record){if(($record['status']??'')==='deleted'||empty($record['object_key']))continue;$groups[($record['storage']['provider_id']??ProviderRegistry::activeId()).'|'.$record['object_key']][]=['type'=>'derivative','record'=>$record];}
    $groups=array_filter($groups,static function(array $group)use($active): bool {foreach($group as $entry)if(($entry['record']['storage']['key_id']??'')!==$active)return true;return false;});
    RecordStore::put('key_rotation',$runId,['actor_id'=>$actor,'rotation_id'=>$runId,'active_key_id'=>$active,'status'=>'running','groups_total'=>count($groups),'result'=>$result,'created_at'=>Utils::now()]);
    foreach($groups as $groupKey=>$group){
        $first=$group[0]['record'];$providerId=Utils::key((string)($first['storage']['provider_id']??ProviderRegistry::activeId()),64);$oldKey=(string)$first['object_key'];$newKey='';
        foreach($group as $entry){$candidate=$entry['record'];if(!hash_equals((string)$first['sha256'],(string)($candidate['sha256']??''))||(int)$first['size']!==(int)($candidate['size']??-1)||($candidate['object_key']??'')!==$oldKey||Utils::key((string)($candidate['storage']['provider_id']??ProviderRegistry::activeId()),64)!==$providerId)throw new Error('key_rotation_shared_identity_mismatch','Shared storage references disagree about physical content identity.',409,['group_hash'=>hash('sha256',$groupKey)]);}
"""
if old not in s: raise SystemExit('round 78 key rotation target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-78-key-rotation-reference-group.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r78($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 78 FAIL: $m\n");exit(1);}echo "ROUND 78 PASS: $m\n";}
$assetInventory=<<<'PATTERN'
if(($record['status']??'')==='deleted'||empty($record['object_key']))continue
PATTERN;
$groupFilter=<<<'PATTERN'
$groups=array_filter($groups,static function(array $group)use($active)
PATTERN;
r78(str_contains($s,$assetInventory),'key rotation inventories every live physical reference before deciding whether rotation is needed');
r78(str_contains($s,$groupFilter),'rotation need is decided at the complete shared-object group boundary');
r78(str_contains($s,'key_rotation_shared_identity_mismatch'),'inconsistent sha/size identity across shared references fails closed');
r78(str_contains($s,"($candidate['object_key']??'')!==$oldKey"),'shared-reference verification binds every member to the same physical object key');
echo "REVIEW ROUND 78 KEY ROTATION REFERENCE GROUP: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-77-retention-isolation.php"\n'
if 'review-round-78-key-rotation-reference-group.php' not in x:
    if anchor not in x: raise SystemExit('round 78 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-78-key-rotation-reference-group.php"\n',1))
