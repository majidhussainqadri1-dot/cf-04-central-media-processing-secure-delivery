#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
upload = ROOT / 'sabri-central-media/includes/class-scm-upload.php'
text = upload.read_text()

# This transformer is intentionally staged only after Fresh Review Round 3 completed.
# Diagnostic-enabled rerun; anchor on semantic tokens so formatting changes do not make the correction brittle.
claim_anchor = "Idempotency::claim('upload-complete'"
claim_pos = text.find(claim_anchor)
if claim_pos < 0:
    raise SystemExit('upload-complete claim anchor missing')
replay_pos = text.find("asset_replay_missing", claim_pos)
if replay_pos < 0:
    raise SystemExit('upload replay anchor missing')
insert_pos = text.find(';', replay_pos)
if insert_pos < 0:
    raise SystemExit('upload replay terminator missing')
insert_pos += 1
reconcile = """
        $existingAsset=RecordStore::get('asset',$uploadId);
        if($existingAsset){
            if(($existingAsset['source_upload_id']??'')!==$uploadId||(int)($existingAsset['actor_id']??0)!==$actor||!hash_equals((string)($existingAsset['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($existingAsset['policy_hash']??''),(string)$u['policy_hash'])){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'asset_reconciliation_mismatch');throw new Error('asset_reconciliation_mismatch','Existing asset cannot be reconciled to this upload.',409);}
            try{return self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint);}
            catch(\\Throwable $e){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected');throw $e;}
        }"""
if "$existingAsset=RecordStore::get('asset',$uploadId)" not in text:
    text = text[:insert_pos] + reconcile + text[insert_pos:]

asset_put_anchor = "RecordStore::put('asset',$uploadId,$asset)"
asset_put_pos = text.find(asset_put_anchor, claim_pos)
if asset_put_pos < 0:
    raise SystemExit('asset persistence anchor missing')
statement_start = text.rfind('$asset=', claim_pos, asset_put_pos + 1)
if statement_start < 0:
    raise SystemExit('asset assignment start missing')
return_pos = text.find('return $asset;', asset_put_pos)
if return_pos < 0:
    raise SystemExit('asset completion return missing')
return_end = return_pos + len('return $asset;')
put_end = text.find(';', asset_put_pos)
if put_end < 0 or put_end > return_end:
    raise SystemExit('asset persistence terminator missing')
put_end += 1
asset_statement = text[statement_start:put_end]
new_tail = asset_statement + "$assetCreated=true;return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint);"
text = text[:statement_start] + new_tail + text[return_end:]

insert_before = "\n    public static function cleanupExpired("
helper = r'''
    private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint): array {
        $uploadId=(string)$upload['id'];
        if(!in_array(($upload['status']??''),['uploading','finalizing','completed'],true))throw new Error('upload_finalization_state_invalid','Upload cannot enter completion reconciliation.',409);
        if(($upload['status']??'')!=='completed'){
            $upload['status']='finalizing';$upload['finalizing_at']=$upload['finalizing_at']??Utils::now();
            $upload=RecordStore::put('upload',$uploadId,$upload,(int)$upload['version']);
        }
        if(isset($upload['quota']['quota_id'],$upload['quota']['reservation_id']))QuotaService::settle((string)$upload['quota']['quota_id'],(string)$upload['quota']['reservation_id'],true,(int)$upload['expected_size'],1);
        PartStore::purge($uploadId);
        Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'asset',$uploadId);
        if(($upload['status']??'')!=='completed'){
            $upload['status']='completed';$upload['completed_at']=Utils::now();unset($upload['finalizing_at']);
            RecordStore::put('upload',$uploadId,$upload,(int)$upload['version']);
        }
        Audit::record('asset_quarantined',['asset_id'=>$uploadId,'actor_id'=>(int)$upload['actor_id'],'privacy_class'=>$asset['privacy_class'],'sha256'=>$asset['sha256'],'duplicate_of'=>$asset['duplicate_of']??null]);
        self::emit('scm.asset.quarantined',$asset);
        return $asset;
    }
'''
if 'private static function finalizeCompletedUpload' not in text:
    idx = text.find(insert_before)
    if idx < 0:
        raise SystemExit('upload cleanup insertion anchor missing')
    text = text[:idx] + '\n' + helper + text[idx:]
upload.write_text(text)

regression = ROOT / 'tests/review-round-59-upload-finalization.php'
regression.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$source=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r59(bool $ok,string $message): void {if(!$ok){fwrite(STDERR,"ROUND 59 FAIL: $message\n");exit(1);}echo "ROUND 59 PASS: $message\n";}
r59(str_contains($source,"private static function finalizeCompletedUpload"),'completion has an explicit idempotent reconciliation phase');
r59(str_contains($source,"$upload['status']='finalizing'"),'upload is durably marked finalizing before post-asset cleanup');
r59(str_contains($source,"$existingAsset=RecordStore::get('asset',$uploadId)")&&str_contains($source,"asset_reconciliation_mismatch"),'retry reconciles an already-created matching asset and rejects mismatches');
r59(str_contains($source,"QuotaService::settle")&&str_contains($source,"PartStore::purge")&&str_contains($source,"Idempotency::complete"),'finalizer covers quota, multipart cleanup and idempotency completion');
echo "REVIEW ROUND 59 UPLOAD FINALIZATION: PASS\n";
''')

quality = ROOT / 'tools/quality-check.sh'
q = quality.read_text()
needle = 'php "$ROOT/tests/review-round-58-processing-consistency.php"\n'
insert = needle + 'php "$ROOT/tests/review-round-59-upload-finalization.php"\n'
if 'review-round-59-upload-finalization.php' not in q:
    if needle not in q:
        raise SystemExit('quality-check insertion point missing')
    quality.write_text(q.replace(needle, insert, 1))
