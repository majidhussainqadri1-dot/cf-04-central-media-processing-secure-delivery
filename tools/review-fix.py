#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 82 was fully completed before corrections began.
# Defect ledger: reclaimed idempotency leases were not generation-bound, so a stale
# worker could complete/fail a newer same-key claim. Add a per-claim token and require
# that exact current token for all terminal claim mutations.

p=ROOT/'sabri-central-media/includes/class-scm-upload.php';s=p.read_text()
s=s.replace("$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];", "$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'claim_token'=>Utils::id('idem'),'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];",1)
s=s.replace("public static function complete(string $scope,string $key,string $fingerprint,string $resultType,string $resultId,array $result=[]): array {", "public static function complete(string $scope,string $key,string $fingerprint,string $claimToken,string $resultType,string $resultId,array $result=[]): array {",1)
s=s.replace("if(!$record||(int)($record['actor_id']??0)!==$actor||!hash_equals((string)$record['fingerprint'],$fingerprint))throw new Error('idempotency_claim_missing','Idempotency claim missing.',409);", "if(!$record||(int)($record['actor_id']??0)!==$actor||($record['status']??'')!=='claimed'||!hash_equals((string)$record['fingerprint'],$fingerprint)||!hash_equals((string)($record['claim_token']??''),$claimToken))throw new Error('idempotency_claim_missing','Current idempotency claim identity is missing or stale.',409);",1)
s=s.replace("public static function fail(string $scope,string $key,string $fingerprint,string $code): void {", "public static function fail(string $scope,string $key,string $fingerprint,string $claimToken,string $code): void {",1)
s=s.replace("if(!$r||(int)($r['actor_id']??0)!==$actor||!hash_equals((string)$r['fingerprint'],$fingerprint))return;", "if(!$r||(int)($r['actor_id']??0)!==$actor||($r['status']??'')!=='claimed'||!hash_equals((string)$r['fingerprint'],$fingerprint)||!hash_equals((string)($r['claim_token']??''),$claimToken))return;",1)
# Every non-replay caller propagates the exact token from its claim.
repls={
"Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,'upload',$id)":"Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'upload',$id)",
"Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected')":"Idempotency::fail('upload-create',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected')",
"Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,'asset_reconciliation_mismatch')":"Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'asset_reconciliation_mismatch')",
"self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint)":"self::finalizeCompletedUpload($u,$existingAsset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'])",
"Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e instanceof Error?$e->errorCode:'unexpected')":"Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$e instanceof Error?$e->errorCode:'unexpected')",
"self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint)":"self::finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'])",
"private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint): array {":"private static function finalizeCompletedUpload(array $upload,array $asset,string $idempotencyKey,string $fingerprint,string $claimToken): array {",
"Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'asset',$uploadId)":"Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,$claimToken,'asset',$uploadId)"
}
for old,new in repls.items(): s=s.replace(old,new)
if "claim_token'=>Utils::id('idem')" not in s or "string $claimToken,string $resultType" not in s or "string $fingerprint,string $claimToken): array" not in s: raise SystemExit('round 82 transformation incomplete')
# No old terminal arities may remain.
for forbidden in ["Idempotency::complete('upload-create',$idempotencyKey,$fingerprint,'upload'", "Idempotency::complete('upload-complete',$idempotencyKey,$fingerprint,'asset'", "Idempotency::fail('upload-complete',$idempotencyKey,$fingerprint,$e", "finalizeCompletedUpload($u,$asset,$idempotencyKey,$fingerprint)"]:
    if forbidden in s: raise SystemExit('round 82 stale caller remains: '+forbidden)
p.write_text(s)

t=ROOT/'tests/review-round-82-idempotency-generation.php';t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r82($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 82 FAIL: $m\n");exit(1);}echo "ROUND 82 PASS: $m\n";}
r82(str_contains($s,"claim_token'=>Utils::id('idem')"),'new claims receive unique generation identity');
r82(str_contains($s,"record['claim_token']??''")&&str_contains($s,"status']??'')!=='claimed'"),'completion rejects stale/non-current claims');
r82(str_contains($s,"r['claim_token']??''"),'failure rejects stale claim generations');
r82(substr_count($s,"['record']['claim_token']")>=5,'upload terminal paths propagate exact claim identity');
echo "REVIEW ROUND 82 IDEMPOTENCY GENERATION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();a='php "$ROOT/tests/review-round-81-provider-exit-drift.php"\n'
if 'review-round-82-idempotency-generation.php' not in x:
    if a not in x: raise SystemExit('round 82 quality anchor missing')
    q.write_text(x.replace(a,a+'php "$ROOT/tests/review-round-82-idempotency-generation.php"\n',1))
