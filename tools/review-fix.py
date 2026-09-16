#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-delivery.php'
s=p.read_text()

# Fresh Review Round 69 was fully completed before applying this correction.
old="""    $existing=array_values(array_filter(RecordStore::all('cdn_mapping',0,null,100000),fn($mapping)=>($mapping['asset_id']??'')===$assetId&&($mapping['derivative_id']??'')===$derivativeId&&($mapping['status']??'')==='published'&&($mapping['sha256']??'')===$derivative['sha256']));
"""
new="""    $existing=array_values(array_filter(RecordStore::all('cdn_mapping',0,null,100000),fn($mapping)=>($mapping['asset_id']??'')===$assetId&&($mapping['derivative_id']??'')===$derivativeId&&($mapping['status']??'')==='published'&&($mapping['sha256']??'')===$derivative['sha256']&&($mapping['privacy_class']??'')===$asset['privacy_class']&&hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])));
"""
if old not in s: raise SystemExit('public CDN reuse target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-69-cdn-policy-reuse.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r69($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 69 FAIL: $m\n");exit(1);}echo "ROUND 69 PASS: $m\n";}
r69(str_contains($s,"($mapping['privacy_class']??'')===$asset['privacy_class']"),'public CDN mapping reuse is privacy-class bound');
r69(str_contains($s,"hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])"),'public CDN mapping reuse is current upload/delivery-policy bound');
r69(str_contains($s,"hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])"),'public CDN mapping reuse is current rights-policy bound');
r69(str_contains($s,"$asset['rights']['policy_hash']).'/'.$derivative['sha256']"),'new CDN cache keys remain rights-aware');
echo "REVIEW ROUND 69 CDN POLICY REUSE: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-68-legal-hold-scope.php"\n'
if 'review-round-69-cdn-policy-reuse.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-69-cdn-policy-reuse.php"\n',1))
