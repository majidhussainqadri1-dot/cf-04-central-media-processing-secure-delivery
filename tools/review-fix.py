#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 82 was fully completed before corrections began.
# Defect ledger: reclaimed idempotency leases were not generation-bound, so a stale
# worker could complete/fail a newer same-key claim. Add a per-claim token and require
# that exact current token for all terminal claim mutations across upload and repair.

p=ROOT/'sabri-central-media/includes/class-scm-upload.php';s=p.read_text()
s=s.replace("$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];", "$row=['actor_id'=>$actor,'scope'=>$scope,'fingerprint'=>$fingerprint,'claim_token'=>Utils::id('idem'),'status'=>'claimed','expires_at'=>Utils::now()+$ttl,'claimed_at'=>Utils::now()];",1)
s=s.replace("public static function complete(string $scope,string $key,string $fingerprint,string $resultType,string $resultId,array $result=[]): array {", "public static function complete(string $scope,string $key,string $fingerprint,string $claimToken,string $resultType,string $resultId,array $result=[]): array {",1)
s=s.replace("if(!$record||(int)($record['actor_id']??0)!==$actor||!hash_equals((string)$record['fingerprint'],$fingerprint))throw new Error('idempotency_claim_missing','Idempotency claim missing.',409);", "if(!$record||(int)($record['actor_id']??0)!==$actor||($record['status']??'')!=='claimed'||!hash_equals((string)$record['fingerprint'],$fingerprint)||!hash_equals((string)($record['claim_token']??''),$claimToken))throw new Error('idempotency_claim_missing','Current idempotency claim identity is missing or stale.',409);",1)
s=s.replace("public static function fail(string $scope,string $key,string $fingerprint,string $code): void {", "public static function fail(string $scope,string $key,string $fingerprint,string $claimToken,string $code): void {",1)
s=s.replace("if(!$r||(int)($r['actor_id']??0)!==$actor||!hash_equals((string)$r['fingerprint'],$fingerprint))return;", "if(!$r||(int)($r['actor_id']??0)!==$actor||($r['status']??'')!=='claimed'||!hash_equals((string)$r['fingerprint'],$fingerprint)||!hash_equals((string)($r['claim_token']??''),$claimToken))return;",1)
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
if "claim_token'=>Utils::id('idem')" not in s or "string $claimToken,string $resultType" not in s or "string $fingerprint,string $claimToken): array" not in s: raise SystemExit('round 82 upload transformation incomplete')
p.write_text(s)

# RepairService also uses the shared Idempotency API.
op=ROOT/'sabri-central-media/includes/class-scm-operations.php';o=op.read_text()
o=o.replace("Idempotency::complete('repair',$idempotencyKey,$fingerprint,'repair',$repairId)","Idempotency::complete('repair',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],'repair',$repairId)")
o=o.replace("Idempotency::fail('repair',$idempotencyKey,$fingerprint,$repair['error'])","Idempotency::fail('repair',$idempotencyKey,$fingerprint,(string)$claim['record']['claim_token'],$repair['error'])")
if "Idempotency::complete('repair',$idempotencyKey,$fingerprint,'repair'" in o or "Idempotency::fail('repair',$idempotencyKey,$fingerprint,$repair" in o: raise SystemExit('round 82 repair stale caller remains')
op.write_text(o)

t=ROOT/'tests/review-round-82-idempotency-generation.php';t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$u=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');$o=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r82($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 82 FAIL: $m\n");exit(1);}echo "ROUND 82 PASS: $m\n";}
r82(str_contains($u,"claim_token'=>Utils::id('idem')"),'new claims receive unique generation identity');
r82(str_contains($u,"record['claim_token']??''")&&str_contains($u,"status']??'')!=='claimed'"),'completion rejects stale/non-current claims');
r82(str_contains($u,"r['claim_token']??''"),'failure rejects stale claim generations');
r82(substr_count($u,"['record']['claim_token']")>=5&&substr_count($o,"['record']['claim_token']")>=2,'all upload and repair terminal paths propagate exact claim identity');
echo "REVIEW ROUND 82 IDEMPOTENCY GENERATION: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();a='php "$ROOT/tests/review-round-81-provider-exit-drift.php"\n'
if 'review-round-82-idempotency-generation.php' not in x:
    if a not in x: raise SystemExit('round 82 quality anchor missing')
    q.write_text(x.replace(a,a+'php "$ROOT/tests/review-round-82-idempotency-generation.php"\n',1))

# Fresh Review Round 83 was fully completed before corrections began.
# Defect ledger: Schema::ready trusted only the schema-version option and table names.
# A partial/failed dbDelta could therefore mark an old table shape ready and let runtime
# proceed against missing columns. Require the exact critical column set before ready.
pp=ROOT/'sabri-central-media/includes/class-scm-persistence.php';ps=pp.read_text()
old="global $wpdb; foreach(['records','audit'] as $t){$name=Db::table($t);$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name));if((string)$found!==$name)return false;}return true; }"
new="global $wpdb;$required=['records'=>['record_type','id','actor_id','status','version','expires_at','payload','updated_at'],'audit'=>['id','event_id','event_key','actor_id','previous_hash','event_hash','payload','created_at']];foreach(['records','audit'] as $t){$name=Db::table($t);$found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$name));if((string)$found!==$name)return false;$columns=array_map('strval',(array)$wpdb->get_col('SHOW COLUMNS FROM '.$name,0));foreach($required[$t] as $column)if(!in_array($column,$columns,true))return false;}return true; }"
if old in ps: ps=ps.replace(old,new,1)
if "SHOW COLUMNS FROM '.$name" not in ps or "'records'=>['record_type','id','actor_id','status','version','expires_at','payload','updated_at']" not in ps: raise SystemExit('round 83 schema-shape transformation incomplete')
pp.write_text(ps)

t83=ROOT/'tests/review-round-83-schema-shape.php';t83.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
function r83($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 83 FAIL: $m\n");exit(1);}echo "ROUND 83 PASS: $m\n";}
r83(str_contains($p,"SHOW COLUMNS FROM '.$name"),'schema readiness verifies physical columns');
r83(str_contains($p,"'records'=>['record_type','id','actor_id','status','version','expires_at','payload','updated_at']"),'records critical shape is explicit');
r83(str_contains($p,"'audit'=>['id','event_id','event_key','actor_id','previous_hash','event_hash','payload','created_at']"),'audit critical shape is explicit');
echo "REVIEW ROUND 83 SCHEMA SHAPE: PASS\n";
''')
x=q.read_text();a83='php "$ROOT/tests/review-round-82-idempotency-generation.php"\n'
if 'review-round-83-schema-shape.php' not in x:
    if a83 not in x: raise SystemExit('round 83 quality anchor missing')
    q.write_text(x.replace(a83,a83+'php "$ROOT/tests/review-round-83-schema-shape.php"\n',1))
