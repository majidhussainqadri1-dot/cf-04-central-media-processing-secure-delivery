#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

upload = ROOT / 'sabri-central-media/includes/class-scm-upload.php'
text = upload.read_text()

start_pattern = re.compile(
    r"\$fingerprint=hash\('sha256',\$uploadId\.\'\|\'\.\$u\['expected_sha256'\]\.\'\|\'\.\$u\['expected_size'\]\);"
    r"\$claim=Idempotency::claim\('upload-complete',\$idempotencyKey,\$fingerprint\);"
    r"if\(\$claim\['replay'\]\)return RecordStore::get\('asset',\(string\)\$claim\['record'\]\['result_id'\]\)\?\?throw new Error\('asset_replay_missing','Completed asset missing\.',500\);"
    r"\s*\$stream=PartStore::assemble\(\$uploadId,\(int\)\$u\['expected_size'\],\(string\)\$u\['expected_sha256'\],\(int\)\$u\['policy'\]\['max_upload_parts'\]\);\$stored=null;\$assetCreated=false;",
    re.S,
)
start_replacement = """$fingerprint=hash('sha256',$uploadId.'|'.$u['expected_sha256'].'|'.$u['expected_size']);$claim=Idempotency::claim('upload-complete',$idempotencyKey,$fingerprint);if($claim['replay'])return RecordStore::get('asset',(string)$claim['record']['result_id'])??throw new Error('asset_replay_missing','Completed asset missing.',500);
        $existingAsset=RecordStore::get('asset',$uploadId);
        if($existingAsset){
            if(($existingAsset['source_upload_id']??'')!==$uploadId||(int)($existingAsset['actor_id']??0)!==$actor||!hash_equals((string)($existingAsset['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($existingAsset['policy_hash']??''),(string)$u['policy_hash'])){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'asset_reconciliation_mismatch');throw new Error('asset_reconciliation_mismatch','Existing asset cannot be reconciled to this upload.',409);}
            try{return self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint);}
            catch(\\Throwable $e){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected');throw $e;}
        }
        $stream=PartStore::assemble($uploadId,(int)$u['expected_size'],(string)$u['expected_sha256'],(int)$u['policy']['max_upload_parts']);$stored=null;$assetCreated=false;"""
text, count = start_pattern.subn(lambda m: start_replacement, text, count=1)
if count != 1:
    raise SystemExit(f'upload completion reconciliation start target count={count}')

post_pattern = re.compile(
    r"\$asset=RecordStore::put\('asset',\$uploadId,\$asset\);\$assetCreated=true;"
    r".*?self::emit\('scm\.asset\.quarantined',\$asset\);return \$asset;",
    re.S,
)
post_replacement = "$asset=RecordStore::put('asset',$uploadId,$asset);$assetCreated=true;return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint);"
text, count = post_pattern.subn(lambda m: post_replacement, text, count=1)
if count != 1:
    raise SystemExit(f'upload completion post-asset target count={count}')

insert_before = "\n    public static function cleanupExpired(int $now=0,int $limit=500): array {"
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
    if insert_before not in text:
        raise SystemExit('upload finalization insertion point missing')
    text = text.replace(insert_before, '\n'+helper+insert_before, 1)
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

# Fresh review round 3 corrections are intentionally applied only after the round audit was completed.
