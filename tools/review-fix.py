#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 76 was fully completed before corrections began.
# Defect ledger:
# - Asset-wide grant revocation used one stale snapshot and a single CAS write per
#   grant. A concurrent delivery consumption could win that CAS, causing revocation
#   to abort mid-loop and leave later grants active. Revocation/deletion must not
#   silently retain active grants because of ordinary use-count contention.

p=ROOT/'sabri-central-media/includes/class-scm-delivery.php'
s=p.read_text()
old="""    public static function revokeForAsset(string $assetId,string $reason): int {$count=0;foreach(RecordStore::all('grant',0,null,100000) as $g){if(($g['asset_id']??'')===$assetId&&($g['status']??'')==='active'){$g['status']='revoked';$g['revoked_at']=Utils::now();$g['revoke_reason']=Utils::key($reason,64);RecordStore::put('grant',(string)$g['id'],$g,(int)$g['version']);$count++;}}return $count;}
"""
new="""    public static function revokeForAsset(string $assetId,string $reason): int {
        $reason=Utils::key($reason,64);if($assetId===''||$reason==='')throw new Error('revoke_context_invalid','Asset and revoke reason are required.',400);$count=0;
        foreach(RecordStore::all('grant',0,null,100000) as $snapshot){
            if(($snapshot['asset_id']??'')!==$assetId||($snapshot['status']??'')!=='active')continue;$grantId=(string)$snapshot['id'];$settled=false;
            for($attempt=0;$attempt<4;$attempt++){
                $g=RecordStore::get('grant',$grantId);if(!$g||($g['asset_id']??'')!==$assetId||($g['status']??'')!=='active'){$settled=true;break;}
                $g['status']='revoked';$g['revoked_at']=Utils::now();$g['revoke_reason']=$reason;
                try{RecordStore::put('grant',$grantId,$g,(int)$g['version']);$count++;$settled=true;break;}
                catch(Error $conflict){if($conflict->errorCode!=='record_version_conflict')throw $conflict;}
            }
            if(!$settled){$latest=RecordStore::get('grant',$grantId);if($latest&&($latest['asset_id']??'')===$assetId&&($latest['status']??'')==='active')throw new Error('grant_revoke_conflict','Grant remained active after bounded revocation retries.',409,['grant_id'=>$grantId]);}
        }
        return $count;
    }
"""
if old not in s: raise SystemExit('round 76 revokeForAsset target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-76-grant-revocation.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r76($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 76 FAIL: $m\n");exit(1);}echo "ROUND 76 PASS: $m\n";}
$retry=<<<'PATTERN'
for($attempt=0;$attempt<4;$attempt++)
PATTERN;
$refresh=<<<'PATTERN'
$g=RecordStore::get('grant',$grantId)
PATTERN;
$conflict=<<<'PATTERN'
$conflict->errorCode!=='record_version_conflict'
PATTERN;
r76(str_contains($s,$retry),'asset-wide revocation uses bounded CAS retries');
r76(str_contains($s,$refresh),'each revocation retry refreshes authoritative grant state');
r76(str_contains($s,$conflict),'ordinary version contention is retried while other errors remain fail-closed');
r76(str_contains($s,'grant_revoke_conflict'),'persistent active-grant contention is surfaced instead of silently skipped');
echo "REVIEW ROUND 76 GRANT REVOCATION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-75-manifest-lineage.php"\n'
if 'review-round-76-grant-revocation.php' not in x:
    if anchor not in x: raise SystemExit('round 76 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-76-grant-revocation.php"\n',1))
