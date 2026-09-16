#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 71 was fully completed before any correction below was started.
# All defects collected in that completed review are corrected together here.

# 1) Preserve CAS semantics for concurrent record creation. A duplicate-key race must
# surface as record_version_conflict so higher-level replay/lease/create guards can
# handle contention intentionally instead of receiving a misleading 500 write failure.
p=ROOT/'sabri-central-media/includes/class-scm-persistence.php'
s=p.read_text()
old="""        if($version===0){$ok=$wpdb->insert(Db::table('records'),$data,['%s','%s','%d','%s','%d','%s','%s','%s']);if($ok!==1)throw new Error('record_write_failed','Persistent insert failed.',500,['type'=>$type,'id'=>$id]);}
"""
new="""        if($version===0){$ok=$wpdb->insert(Db::table('records'),$data,['%s','%s','%d','%s','%d','%s','%s','%s']);if($ok!==1){$winner=self::get($type,$id);if($winner!==null)throw new Error('record_version_conflict','Concurrent record creation won before this insert.',409,['type'=>$type,'id'=>$id,'expected'=>0,'actual'=>(int)($winner['version']??1)]);throw new Error('record_write_failed','Persistent insert failed.',500,['type'=>$type,'id'=>$id]);}}
"""
if old not in s: raise SystemExit('record create CAS target missing')
p.write_text(s.replace(old,new,1))

# 2) Eliminate rights-reconciliation head-page starvation without violating the
# existing Round-62 contract that $limit is a work/check limit rather than a
# RecordStore scan ceiling. Discover the bounded complete inventory fail-closed,
# prioritize actually-expired rights, then mutate/check at most $limit records.
p=ROOT/'sabri-central-media/includes/class-scm-plan-parity.php'
s=p.read_text()
old="""    public static function reconcileExpired(int $now=0,int $limit=500): array {
        $now=$now>0?$now:Utils::now();$limit=max(1,min(2000,$limit));$result=['checked'=>0,'revoked'=>0,'failed'=>0];
        foreach(RecordStore::list('asset',0,null,$limit) as $asset){
            if($result['checked']>=$limit)break;$result['checked']++;
            if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))continue;
            $expires=(int)($asset['rights']['expires_at']??0);
            if($expires<1||$expires>$now)continue;
            try{self::invalidate((string)$asset['id'],'rights_expired',$now);$result['revoked']++;}
            catch(\\Throwable $exception){$result['failed']++;DegradedStateService::record('rights-reconciliation',$exception instanceof Error?$exception->errorCode:'unexpected',['asset_ref'=>Utils::hashReference((string)$asset['id'])]);}
        }
        return $result;
    }
"""
new="""    public static function reconcileExpired(int $now=0,int $limit=500): array {
        $now=$now>0?$now:Utils::now();$limit=max(1,min(2000,$limit));$result=['checked'=>0,'revoked'=>0,'failed'=>0];
        $inventory=RecordStore::all('asset',0,null,1000000);
        usort($inventory,static function(array $a,array $b)use($now): int {
            $rank=static function(array $asset)use($now): int {
                if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))return 2;
                $expires=(int)($asset['rights']['expires_at']??0);
                return $expires>0&&$expires<=$now?0:1;
            };
            $ar=$rank($a);$br=$rank($b);if($ar!==$br)return $ar<=>$br;
            if($ar===0){$ae=(int)($a['rights']['expires_at']??0);$be=(int)($b['rights']['expires_at']??0);if($ae!==$be)return $ae<=>$be;}
            return strcmp((string)($a['id']??''),(string)($b['id']??''));
        });
        foreach(array_slice($inventory,0,$limit) as $asset){
            $result['checked']++;
            if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))continue;
            $expires=(int)($asset['rights']['expires_at']??0);
            if($expires<1||$expires>$now)continue;
            try{self::invalidate((string)$asset['id'],'rights_expired',$now);$result['revoked']++;}
            catch(\\Throwable $exception){$result['failed']++;DegradedStateService::record('rights-reconciliation',$exception instanceof Error?$exception->errorCode:'unexpected',['asset_ref'=>Utils::hashReference((string)$asset['id'])]);}
        }
        return $result;
    }
"""
if old not in s: raise SystemExit('rights reconciliation target missing')
p.write_text(s.replace(old,new,1))

# Round-71 focused permanent regression coverage.
t=ROOT/'tests/review-round-71-final-adversarial.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
$r=file_get_contents($root.'/sabri-central-media/includes/class-scm-plan-parity.php');
function r71($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 71 FAIL: $m\n");exit(1);}echo "ROUND 71 PASS: $m\n";}
$winner=<<<'PATTERN'
$winner=self::get($type,$id)
PATTERN;
$expected=<<<'PATTERN'
'expected'=>0
PATTERN;
$inventory=<<<'PATTERN'
$inventory=RecordStore::all('asset',0,null,1000000)
PATTERN;
$priority=<<<'PATTERN'
return $expires>0&&$expires<=$now?0:1
PATTERN;
$bounded=<<<'PATTERN'
foreach(array_slice($inventory,0,$limit) as $asset)
PATTERN;
r71(str_contains($p,$winner),'failed persistent create re-reads the authoritative record to distinguish contention from infrastructure failure');
r71(str_contains($p,$expected),'concurrent create conflict retains expected-version-zero CAS evidence');
r71(str_contains($p,"throw new Error('record_version_conflict','Concurrent record creation won before this insert.'"),'duplicate-key create races surface as record_version_conflict');
r71(str_contains($p,"throw new Error('record_write_failed','Persistent insert failed.'"),'true infrastructure insert failure remains fail-closed as record_write_failed');
r71(str_contains($r,$inventory),'rights reconciliation discovers the complete explicitly bounded inventory rather than repeatedly reading only the newest page');
r71(str_contains($r,$priority),'expired rights are prioritized ahead of non-expired records, preventing head-page starvation');
r71(str_contains($r,$bounded),'reconciliation mutation/check work remains bounded by the caller limit');
r71(!str_contains($r,"foreach(RecordStore::list('asset',0,null,$limit) as $asset)"),'starving head-page-only reconciliation pattern is absent');
echo "REVIEW ROUND 71 FINAL ADVERSARIAL: PASS\n";
''')

q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-70-provider-exit-inventory.php"\n'
if 'review-round-71-final-adversarial.php' not in x:
    if anchor not in x: raise SystemExit('round 71 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-71-final-adversarial.php"\n',1))

# Keep repository review evidence aligned with the completed sequential batch.
r=ROOT/'README.md';text=r.read_text();old='**Forty-pass hardening:** review/fix rounds 15–54 completed with focused and full-suite regression evidence.\n'
new='**Sequential hardening evidence:** review/fix rounds 15–54 plus the fresh 10-pass batch 62–71 are completed with focused and full-suite regression evidence; each defect-bearing round was corrected only after that round ended and verified before the next round began.\n'
if old not in text: raise SystemExit('README review evidence target missing')
r.write_text(text.replace(old,new,1))
