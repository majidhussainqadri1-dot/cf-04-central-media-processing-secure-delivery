#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 89 was fully completed before corrections began.
# Defect ledger:
# 1) Retention scheduling accepted an owner retention decision without requiring or
#    comparing the current canonical object_version, allowing stale owner truth to seed
#    destructive retention timings.
# 2) derivative expiry/CDN purge was irreversible but did not re-authorize deletion
#    against the owner at action time.
# 3) queued/retried deletion processing relied only on authorization captured when the
#    request was created; a later owner-version change could therefore precede physical
#    deletion without a fresh owner authorization check.

p=ROOT/'sabri-central-media/includes/class-scm-lifecycle.php'
s=p.read_text()

old="$decision=DomainRegistry::decision($asset['owner_domain'],'retention_decision',['asset'=>$asset,'policy'=>$asset['policy']['retention']],false);$now=Utils::now();"
new="$decision=DomainRegistry::decision($asset['owner_domain'],'retention_decision',['asset'=>$asset,'policy'=>$asset['policy']['retention']]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Retention decision is stale.',409);$now=Utils::now();"
if old not in s and new not in s: raise SystemExit('round 89 retention-decision anchor missing')
s=s.replace(old,new,1)

old="$asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);LegalHoldService::assertNoHold($assetId,'expire-derivatives');DeliveryService::revokeForAsset($assetId,$reason);"
new="$asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);LegalHoldService::assertNoHold($assetId,'expire-derivatives');$decision=DomainRegistry::decision($asset['owner_domain'],'authorize_deletion',['asset'=>$asset,'actor_id'=>0,'reason'=>$reason,'context'=>['scope'=>'derivatives','phase'=>'expire_derivatives']]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner deletion authorization is stale.',409);DeliveryService::revokeForAsset($assetId,$reason);"
if old not in s and new not in s: raise SystemExit('round 89 derivative-expiry auth anchor missing')
s=s.replace(old,new,1)

old="LegalHoldService::assertNoHold($asset['asset_id'],'delete');$d['attempts']=(int)$d['attempts']+1;"
new="LegalHoldService::assertNoHold($asset['asset_id'],'delete');$decision=DomainRegistry::decision($asset['owner_domain'],'authorize_deletion',['asset'=>$asset,'actor_id'=>(int)($d['actor_id']??0),'reason'=>(string)($d['reason']??'deletion'),'context'=>['deletion_id'=>$deletionId,'phase'=>'process']]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner deletion authorization is stale.',409);$d['attempts']=(int)$d['attempts']+1;"
if old not in s and new not in s: raise SystemExit('round 89 deletion-process auth anchor missing')
s=s.replace(old,new,1)

p.write_text(s)

t=ROOT/'tests/review-round-89-lifecycle-reauthorization.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r89($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 89 FAIL: $m\n");exit(1);}echo "ROUND 89 PASS: $m\n";}
r89(str_contains($s,"retention_decision")&&str_contains($s,"Retention decision is stale."),'retention schedule is bound to the current owner object version');
r89(str_contains($s,"'scope'=>'derivatives'")&&str_contains($s,"'phase'=>'expire_derivatives'"),'derivative expiry performs fresh owner deletion authorization before destructive propagation');
r89(str_contains($s,"'phase'=>'process'")&&str_contains($s,"['deletion_id'=>$deletionId"),'deletion retries re-authorize against canonical owner truth at action time');
r89(substr_count($s,"domain_object_version_stale")>=3,'lifecycle destructive paths fail closed on stale owner object version');
echo "REVIEW ROUND 89 LIFECYCLE REAUTHORIZATION: PASS\n";
''')

q=ROOT/'tools/quality-check.sh';x=q.read_text()
anchor='php "$ROOT/tests/review-round-88-delivery-cdn-atomicity.php"\n'
line='php "$ROOT/tests/review-round-89-lifecycle-reauthorization.php"\n'
if line not in x:
    if anchor not in x: raise SystemExit('round 89 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+line,1))
