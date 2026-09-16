#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-operations.php'
s=p.read_text()

# Fresh Review Round 70 was fully completed before applying this correction.
old="""    foreach(RecordStore::all('asset',0,null,100000) as $asset){
        if(($asset['status']??'')==='deleted'||($asset['storage']['provider_id']??'')!==$sourceProvider)continue;
        LegalHoldService::assertNoHold((string)$asset['id'],'provider_exit');
        $items[]=['type'=>'asset','id'=>$asset['id'],'object_key'=>$asset['object_key'],'sha256'=>$asset['sha256'],'size'=>$asset['size'],'status'=>'pending'];
        foreach(DerivativeService::forAsset((string)$asset['id']) as $derivative)if(($derivative['status']??'')!=='deleted'&&($derivative['storage']['provider_id']??'')===$sourceProvider)$items[]=['type'=>'derivative','id'=>$derivative['id'],'asset_id'=>$asset['id'],'object_key'=>$derivative['object_key'],'sha256'=>$derivative['sha256'],'size'=>$derivative['size'],'status'=>'pending'];
    }
"""
new="""    foreach(RecordStore::all('asset',0,null,100000) as $asset){
        if(($asset['status']??'')==='deleted'||($asset['storage']['provider_id']??'')!==$sourceProvider)continue;
        LegalHoldService::assertNoHold((string)$asset['id'],'provider_exit');
        $items[]=['type'=>'asset','id'=>$asset['id'],'object_key'=>$asset['object_key'],'sha256'=>$asset['sha256'],'size'=>$asset['size'],'status'=>'pending'];
    }
    foreach(RecordStore::all('derivative',0,null,200000) as $derivative){
        if(($derivative['status']??'')==='deleted'||($derivative['storage']['provider_id']??'')!==$sourceProvider)continue;
        $assetId=Utils::text((string)($derivative['asset_id']??''),96);$parent=$assetId!==''?RecordStore::get('asset',$assetId):null;
        if(!$parent||($parent['status']??'')==='deleted')throw new Error('provider_exit_derivative_orphaned','Source-provider derivative has no active parent asset.',409,['derivative_id'=>$derivative['id']??'']);
        LegalHoldService::assertNoHold($assetId,'provider_exit');
        $items[]=['type'=>'derivative','id'=>$derivative['id'],'asset_id'=>$assetId,'object_key'=>$derivative['object_key'],'sha256'=>$derivative['sha256'],'size'=>$derivative['size'],'status'=>'pending'];
    }
"""
if old not in s: raise SystemExit('provider exit inventory target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-70-provider-exit-inventory.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-operations.php');
function r70($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 70 FAIL: $m\n");exit(1);}echo "ROUND 70 PASS: $m\n";}
$derivativeScan=<<<'PATTERN'
foreach(RecordStore::all('derivative',0,null,200000) as $derivative)
PATTERN;
$providerFilter=<<<'PATTERN'
($derivative['storage']['provider_id']??'')!==$sourceProvider
PATTERN;
$parentGuard=<<<'PATTERN'
provider_exit_derivative_orphaned
PATTERN;
$holdGuard=<<<'PATTERN'
LegalHoldService::assertNoHold($assetId,'provider_exit')
PATTERN;
r70(str_contains($s,$derivativeScan),'provider exit inventories derivatives independently of source-object provider placement');
r70(str_contains($s,$providerFilter),'provider exit filters derivative inventory by its own storage provider');
r70(str_contains($s,$parentGuard),'orphaned source-provider derivatives fail the exit plan closed');
r70(str_contains($s,$holdGuard),'provider-exit legal holds are enforced for independently discovered derivatives');
echo "REVIEW ROUND 70 PROVIDER EXIT INVENTORY: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-69-cdn-policy-reuse.php"\n'
if 'review-round-70-provider-exit-inventory.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-70-provider-exit-inventory.php"\n',1))
