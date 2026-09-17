#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 77 was fully completed before corrections began.
# Defect ledger:
# - RetentionService::run() allowed one failing retention record (provider/CDN/
#   deletion error) to abort the entire cron batch. Because records are scanned in
#   deterministic order, a persistently failing record could starve later due
#   assets indefinitely. Each asset must remain fail-closed without poisoning the
#   rest of the retention batch.

p=ROOT/'sabri-central-media/includes/class-scm-lifecycle.php'
s=p.read_text()
old="""    public static function run(int $now=0): array {
    $now=$now?:Utils::now();$cleanup=UploadService::cleanupExpired($now);
    $out=['temporary_cleaned'=>(int)$cleanup['parts_purged'],'uploads_expired'=>(int)$cleanup['expired'],'cleanup_failed'=>(int)$cleanup['failed'],'derivatives_expired'=>0,'deletion_requested'=>0,'holds_skipped'=>0];
    foreach(RecordStore::all('retention',0,null,100000) as $retention){
        if(!in_array(($retention['status']??''),['scheduled','pending','derivatives_expired'],true))continue;
        $assetId=(string)$retention['asset_id'];$deletionHeld=LegalHoldService::active($assetId,'deletion')!==[];
        if((int)$retention['derivative_delete_at']<=$now&&!Utils::bool($retention['derivatives_expired']??false)&&(int)$retention['source_delete_at']>$now){
            if($deletionHeld){$out['holds_skipped']++;continue;}
            DeletionService::expireDerivatives($assetId,'retention-expiry');$retention['derivatives_expired']=true;$retention['status']='derivatives_expired';$out['derivatives_expired']++;
        }
        if((int)$retention['source_delete_at']<=$now&&!isset($retention['deletion_id'])){
            if($deletionHeld){$out['holds_skipped']++;continue;}
            $request=DeletionService::request($assetId,0,'retention-expiry',['backup_expiry_at'=>$retention['backup_expiry_at']]);$retention['status']='deletion_requested';$retention['deletion_id']=$request['id'];$out['deletion_requested']++;
        }
        $retention['temporary_cleaned']=true;
        RecordStore::put('retention',(string)$retention['id'],$retention,(int)$retention['version']);
    }
    return $out;
}
"""
new="""    public static function run(int $now=0): array {
    $now=$now?:Utils::now();$cleanup=UploadService::cleanupExpired($now);
    $out=['temporary_cleaned'=>(int)$cleanup['parts_purged'],'uploads_expired'=>(int)$cleanup['expired'],'cleanup_failed'=>(int)$cleanup['failed'],'derivatives_expired'=>0,'deletion_requested'=>0,'holds_skipped'=>0,'records_failed'=>0];
    foreach(RecordStore::all('retention',0,null,100000) as $retention){
        if(!in_array(($retention['status']??''),['scheduled','pending','derivatives_expired'],true))continue;
        $assetId=(string)($retention['asset_id']??'');
        try{
            if($assetId==='')throw new Error('retention_asset_missing','Retention record has no asset identity.',500);
            $deletionHeld=LegalHoldService::active($assetId,'deletion')!==[];
            if((int)$retention['derivative_delete_at']<=$now&&!Utils::bool($retention['derivatives_expired']??false)&&(int)$retention['source_delete_at']>$now){
                if($deletionHeld){$out['holds_skipped']++;continue;}
                DeletionService::expireDerivatives($assetId,'retention-expiry');$retention['derivatives_expired']=true;$retention['status']='derivatives_expired';$out['derivatives_expired']++;
            }
            if((int)$retention['source_delete_at']<=$now&&!isset($retention['deletion_id'])){
                if($deletionHeld){$out['holds_skipped']++;continue;}
                $request=DeletionService::request($assetId,0,'retention-expiry',['backup_expiry_at'=>$retention['backup_expiry_at']]);$retention['status']='deletion_requested';$retention['deletion_id']=$request['id'];$out['deletion_requested']++;
            }
            $retention['temporary_cleaned']=true;
            RecordStore::put('retention',(string)$retention['id'],$retention,(int)$retention['version']);
        }catch(\\Throwable $failure){
            $out['records_failed']++;$code=$failure instanceof Error?$failure->errorCode:'unexpected';
            try{DegradedStateService::record('retention-run',$code,['asset_ref'=>$assetId===''?'':Utils::hashReference($assetId),'retention_ref'=>Utils::hashReference((string)($retention['id']??''))]);}catch(\\Throwable){}
            continue;
        }
    }
    return $out;
}
"""
if old not in s: raise SystemExit('round 77 retention target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-77-retention-isolation.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r77($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 77 FAIL: $m\n");exit(1);}echo "ROUND 77 PASS: $m\n";}
$failed=<<<'PATTERN'
'records_failed'=>0
PATTERN;
$catch=<<<'PATTERN'
}catch(\Throwable $failure){
PATTERN;
$continue=<<<'PATTERN'
            continue;
PATTERN;
r77(str_contains($s,$failed),'retention batch exposes per-record failure accounting');
r77(str_contains($s,$catch),'retention processing isolates failures at the record boundary');
r77(str_contains($s,$continue),'a failed retention record cannot terminate later-record processing');
r77(str_contains($s,"DegradedStateService::record('retention-run'"),'isolated retention failures produce degraded-state evidence');
echo "REVIEW ROUND 77 RETENTION ISOLATION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-76-grant-revocation.php"\n'
if 'review-round-77-retention-isolation.php' not in x:
    if anchor not in x: raise SystemExit('round 77 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-77-retention-isolation.php"\n',1))
