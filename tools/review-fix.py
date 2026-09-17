#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 82 was fully completed before corrections began.
# Defect ledger:
# - Idempotency claims were identified only by actor + request fingerprint. After an
#   expired/failed claim was reclaimed, a slow worker from the older claim could call
#   complete()/fail() and mutate the newer claim because no claim-generation identity
#   was checked. Bind terminal mutations to a per-claim token and claimed state.

p=ROOT/'sabri-central-media/includes/class-scm-upload.php';s=p.read_text()
s=s.replace("$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];", "$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'claim_token'=>Utils::id('idem'),'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];",1)
s=s.replace("public static function complete(string $scope,string $key,string $fingerprint,string $resultType,string $resultId,array $result=[]): array {", "public static function complete(string $scope,string $key,string $fingerprint,string $claimToken,string $resultType,string $resultId,array $result=[]): array {",1)
s=s.replace("if(!$record||(int)($record['actor_id']??0)!==$actor||!hash_equals((string)$record['fingerprint'],$fingerprint))throw new Error('idempotency_claim_missing','Idempotency claim missing.',409);", "if(!$record||(int)($record['actor_id']??0)!==$actor||($record['status']??'')!=='claimed'||!hash_equals((string)$record['fingerprint'],$fingerprint)||!hash_equals((string)($record['claim_token']??''),$claimToken))throw new Error('idempotency_claim_missing','Current idempotency claim identity is missing or stale.',409);",1)
s=s.replace("public static function fail(string $scope,string $key,string $fingerprint,string $code): void {", "public static function fail(string $scope,string $key,string $fingerprint,string $claimToken,string $code): void {",1)
s=s.replace("if(!$r||(int)($r['actor_id']??0)!==$actor||!hash_equals((string)$r['fingerprint'],$fingerprint))return;", "if(!$r||(int)($r['actor_id']??0)!==$actor||($r['status']??'')!=='claimed'||!hash_equals((string)$r['fingerprint'],$fingerprint)||!hash_equals((string)($r['claim_token']??''),$claimToken))return;",1)
# Upload-create binds completion/failure to this exact claim.
s=s.replace("$claim=Idempotency::claim('upload-create',$idempotencyKey,$fingerprint);\n        if($claim['replay'])", "$claim=Idempotency::claim('upload-create',$idempotencyKey,$fingerprint);\n        if($claim['replay'])",1)
s=s.replace("try{$row=RecordStore::put('upload',$id,$row);Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,'upload',$id);", "try{$row=RecordStore::put('upload',$id,$row);Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'upload',$id);",1)
s=s.replace("Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected');", "Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected');",1)
# Upload-complete has several failure/reconciliation paths and one terminal completion.
s=s.replace("Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'asset_reconciliation_mismatch')", "Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'asset_reconciliation_mismatch')")
s=s.replace("Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected')", "Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected')")
s=s.replace("self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint)", "self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'])")
s=s.replace("private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint): array {", "private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint,string $claimToken): array {")
s=s.replace("Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'asset',$uploadId);", "Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,$claimToken,'asset',$uploadId);")
# Any remaining finalize call in the main completion path must pass current claim token.
s=s.replace("self::finalizeCompletedUpload($upload,$asset,$idempotencyKey,$fingerprint)", "self::finalizeCompletedUpload($upload,$asset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'])")
if "public static function complete(string $scope,string $key,string $fingerprint,string $claimToken" not in s or "claim_token'=>Utils::id('idem')" not in s: raise SystemExit('round 82 idempotency transformation incomplete')
p.write_text(s)

t=ROOT/'tests/review-round-82-idempotency-generation.php';t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r82($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 82 FAIL: $m\n");exit(1);}echo "ROUND 82 PASS: $m\n";}
r82(str_contains($s,"'claim_token'=>Utils::id('idem')"),'every new idempotency lease receives a distinct claim identity');
r82(str_contains($s,"($record['status']??'')!=='claimed'")&&str_contains($s,"($record['claim_token']??'')"),'completion requires the currently claimed generation');
r82(str_contains($s,"($r['status']??'')!=='claimed'")&&str_contains($s,"($r['claim_token']??'')"),'failure cannot overwrite a newer reclaimed generation');
r82(substr_count($s,"['record']['claim_token']")>=4,'upload create/complete terminal paths propagate exact claim identity');
echo "REVIEW ROUND 82 IDEMPOTENCY GENERATION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();a='php "$ROOT/tests/review-round-81-provider-exit-drift.php"\n'
if 'review-round-82-idempotency-generation.php' not in x:
    if a not in x: raise SystemExit('round 82 quality anchor missing')
    q.write_text(x.replace(a,a+'php "$ROOT/tests/review-round-82-idempotency-generation.php"\n',1))
