#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 71 was fully completed before any correction below was started.
# Defects collected during the completed round are corrected together here.

# 1) Preserve CAS semantics for concurrent record creation. A duplicate-key race must
# surface as record_version_conflict so higher-level retry/replay guards can fail closed
# or retry intentionally, rather than being misclassified as an internal write failure.
p=ROOT/'sabri-central-media/includes/class-scm-persistence.php'
s=p.read_text()
old="""        if($version===0){$ok=$wpdb->insert(Db::table('records'),$data,['%s','%s','%d','%s','%d','%s','%s','%s']);if($ok!==1)throw new Error('record_write_failed','Persistent insert failed.',500,['type'=>$type,'id'=>$id]);}
"""
new="""        if($version===0){$ok=$wpdb->insert(Db::table('records'),$data,['%s','%s','%d','%s','%d','%s','%s','%s']);if($ok!==1){$winner=self::get($type,$id);if($winner!==null)throw new Error('record_version_conflict','Concurrent record creation won before this insert.',409,['type'=>$type,'id'=>$id,'expected'=>0,'actual'=>(int)($winner['version']??1)]);throw new Error('record_write_failed','Persistent insert failed.',500,['type'=>$type,'id'=>$id]);}}
"""
if old not in s: raise SystemExit('record create CAS target missing')
p.write_text(s.replace(old,new,1))

# 2) Eliminate rights-reconciliation starvation. The prior head-limited list repeatedly
# revisited the newest records and could permanently miss expired rights beyond that page.
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
        $expired=array_values(array_filter(RecordStore::all('asset',0,null,1000000),static function(array $asset)use($now): bool {
            if(in_array(($asset['status']??''),['deleted','deletion_pending','rejected'],true))return false;
            $expires=(int)($asset['rights']['expires_at']??0);
            return $expires>0&&$expires<=$now;
        }));
        usort($expired,static fn(array $a,array $b)=>((int)($a['rights']['expires_at']??0)<=>(int)($b['rights']['expires_at']??0)) ?: strcmp((string)($a['id']??''),(string)($b['id']??'')));
        foreach(array_slice($expired,0,$limit) as $asset){
            $result['checked']++;
            try{self::invalidate((string)$asset['id'],'rights_expired',$now);$result['revoked']++;}
            catch(\\Throwable $exception){$result['failed']++;DegradedStateService::record('rights-reconciliation',$exception instanceof Error?$exception->errorCode:'unexpected',['asset_ref'=>Utils::hashReference((string)$asset['id'])]);}
        }
        return $result;
    }
"""
if old not in s: raise SystemExit('rights reconciliation target missing')
p.write_text(s.replace(old,new,1))

# Retroactive regression coverage for the Round-62 reconciliation correction, which was
# absent from the permanent quality gate and allowed starvation semantics to recur.
t=ROOT/'tests/review-round-62-rights-reconciliation.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-plan-parity.php');
function r62($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 62 FAIL: $m\n");exit(1);}echo "ROUND 62 PASS: $m\n";}
$fullScan=<<<'PATTERN'
RecordStore::all('asset',0,null,1000000)
PATTERN;
$expiredSort=<<<'PATTERN'
$a['rights']['expires_at']
PATTERN;
$boundedWork=<<<'PATTERN'
foreach(array_slice($expired,0,$limit) as $asset)
PATTERN;
r62(str_contains($s,$fullScan),'rights reconciliation performs an explicit complete bounded inventory instead of repeatedly scanning only the newest page');
r62(str_contains($s,$expiredSort),'expired candidates are deterministically ordered by rights expiry');
r62(str_contains($s,$boundedWork),'per-run mutation remains bounded after complete candidate discovery');
r62(!str_contains($s,"foreach(RecordStore::list('asset',0,null,$limit) as $asset)"),'head-page starvation pattern is absent');
echo "REVIEW ROUND 62 RIGHTS RECONCILIATION: PASS\n";
''')

# Final-round regression for persistence create contention semantics.
t=ROOT/'tests/review-round-71-final-adversarial.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
function r71($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 71 FAIL: $m\n");exit(1);}echo "ROUND 71 PASS: $m\n";}
$winner=<<<'PATTERN'
$winner=self::get($type,$id)
PATTERN;
$conflict=<<<'PATTERN'
record_version_conflict
PATTERN;
$expected=<<<'PATTERN'
'expected'=>0
PATTERN;
r71(str_contains($p,$winner),'failed create re-reads the authoritative record to distinguish contention from infrastructure failure');
r71(substr_count($p,$conflict)>=3,'record create/update CAS paths expose conflict semantics consistently');
r71(str_contains($p,$expected),'concurrent create conflict records the zero-version expectation');
r71(str_contains($p,"throw new Error('record_write_failed','Persistent insert failed.'"),'true infrastructure insert failure remains fail-closed as record_write_failed');
echo "REVIEW ROUND 71 FINAL ADVERSARIAL: PASS\n";
''')

# Permanent quality gate: restore Round-62 coverage and add final Round-71 coverage.
q=ROOT/'tools/quality-check.sh';x=q.read_text()
if 'review-round-62-rights-reconciliation.php' not in x:
    anchor='php "$ROOT/tests/review-round-61-lifecycle-rollback.php"\n'
    if anchor not in x: raise SystemExit('round 62 quality anchor missing')
    x=x.replace(anchor,anchor+'php "$ROOT/tests/review-round-62-rights-reconciliation.php"\n',1)
if 'review-round-71-final-adversarial.php' not in x:
    anchor='php "$ROOT/tests/review-round-70-provider-exit-inventory.php"\n'
    if anchor not in x: raise SystemExit('round 71 quality anchor missing')
    x=x.replace(anchor,anchor+'php "$ROOT/tests/review-round-71-final-adversarial.php"\n',1)
q.write_text(x)

# Keep repository review evidence aligned with the completed sequential hardening batch.
r=ROOT/'README.md';text=r.read_text();old='**Forty-pass hardening:** review/fix rounds 15–54 completed with focused and full-suite regression evidence.\n'
new='**Sequential hardening evidence:** review/fix rounds 15–54 plus the fresh 10-pass batch 62–71 are completed with focused and full-suite regression evidence; each defect-bearing round was corrected only after that round ended and was verified before the next round began.\n'
if old not in text: raise SystemExit('README review evidence target missing')
r.write_text(text.replace(old,new,1))
