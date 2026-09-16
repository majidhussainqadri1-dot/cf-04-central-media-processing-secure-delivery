#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"expected review target not found: {path}")
    path.write_text(text.replace(old, new, 1))

upload = ROOT / 'sabri-central-media/includes/class-scm-upload.php'

replace_once(
    upload,
    "$fingerprint=hash('sha256',$uploadId.'|'.$u['expected_sha256'].'|'.$u['expected_size']);$claim=Idempotency::claim('upload-complete',$idempotencyKey,$fingerprint);if($claim['replay'])return RecordStore::get('asset',(string)$claim['record']['result_id'])??throw new Error('asset_replay_missing','Completed asset missing.',500);\n        $stream=PartStore::assemble($uploadId,(int)$u['expected_size'],(string)$u['expected_sha256'],(int)$u['policy']['max_upload_parts']);$stored=null;$assetCreated=false;",
    "$fingerprint=hash('sha256',$uploadId.'|'.$u['expected_sha256'].'|'.$u['expected_size']);$claim=Idempotency::claim('upload-complete',$idempotencyKey,$fingerprint);if($claim['replay'])return RecordStore::get('asset',(string)$claim['record']['result_id'])??throw new Error('asset_replay_missing','Completed asset missing.',500);\n        $existingAsset=RecordStore::get('asset',$uploadId);\n        if($existingAsset){\n            if(($existingAsset['source_upload_id']??'')!==$uploadId||(int)($existingAsset['actor_id']??0)!==$actor||!hash_equals((string)($existingAsset['sha256']??''),(string)$u['expected_sha256'])||!hash_equals((string)($existingAsset['policy_hash']??''),(string)$u['policy_hash'])){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'asset_reconciliation_mismatch');throw new Error('asset_reconciliation_mismatch','Existing asset cannot be reconciled to this upload.',409);}\n            try{return self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint);}\n            catch(\\Throwable $e){Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected');throw $e;}\n        }\n        $stream=PartStore::assemble($uploadId,(int)$u['expected_size'],(string)$u['expected_sha256'],(int)$u['policy']['max_upload_parts']);$stored=null;$assetCreated=false;"
)

replace_once(
    upload,
    "$asset=['actor_id'=>$actor,'asset_id'=>$uploadId,'source_upload_id'=>$uploadId,'duplicate_of'=>$duplicate['id']??null,'owner_domain'=>$u['owner_domain'],'owner_object'=>$u['owner_object'],'owner_type'=>$u['owner_type'],'object_version'=>$u['object_version'],'policy'=>$u['policy'],'policy_hash'=>$u['policy_hash'],'rights'=>$u['policy']['rights'],'privacy_class'=>$u['policy']['privacy_class'],'media_class'=>$u['media_class'],'declared_name'=>$u['declared_name'],'mime'=>$inspection['mime'],'size'=>$inspection['size'],'sha256'=>$inspection['sha256'],'fingerprint'=>$inspection['fingerprint'],'storage'=>$stored,'object_key'=>$stored['object_key'],'status'=>'quarantined','scan_status'=>'pending','processing_status'=>'pending','manifest_version'=>0,'created_at'=>Utils::now()];$asset=RecordStore::put('asset',$uploadId,$asset);$assetCreated=true;$u['status']='completed';$u['completed_at']=Utils::now();RecordStore::put('upload',$uploadId,$u,(int)$u['version']);QuotaService::settle($u['quota']['quota_id'],$u['quota']['reservation_id'],true,(int)$u['expected_size'],1);PartStore::purge($uploadId);Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'asset',$uploadId);Audit::record('asset_quarantined',['asset_id'=>$uploadId,'actor_id'=>$actor,'privacy_class'=>$asset['privacy_class'],'sha256'=>$asset['sha256'],'duplicate_of'=>$asset['duplicate_of']]);self::emit('scm.asset.quarantined',$asset);return $asset;",
    "$asset=['actor_id'=>$actor,'asset_id'=>$uploadId,'source_upload_id'=>$uploadId,'duplicate_of'=>$duplicate['id']??null,'owner_domain'=>$u['owner_domain'],'owner_object'=>$u['owner_object'],'owner_type'=>$u['owner_type'],'object_version'=>$u['object_version'],'policy'=>$u['policy'],'policy_hash'=>$u['policy_hash'],'rights'=>$u['policy']['rights'],'privacy_class'=>$u['policy']['privacy_class'],'media_class'=>$u['media_class'],'declared_name'=>$u['declared_name'],'mime'=>$inspection['mime'],'size'=>$inspection['size'],'sha256'=>$inspection['sha256'],'fingerprint'=>$inspection['fingerprint'],'storage'=>$stored,'object_key'=>$stored['object_key'],'status'=>'quarantined','scan_status'=>'pending','processing_status'=>'pending','manifest_version'=>0,'created_at'=>Utils::now()];$asset=RecordStore::put('asset',$uploadId,$asset);$assetCreated=true;return self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint);"
)

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
text = upload.read_text()
if 'private static function finalizeCompletedUpload' not in text:
    if insert_before not in text:
        raise SystemExit('upload finalization insertion point missing')
    upload.write_text(text.replace(insert_before, '\n'+helper+insert_before, 1))

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
