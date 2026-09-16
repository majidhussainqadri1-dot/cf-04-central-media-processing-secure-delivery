#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=ROOT/'sabri-central-media/includes/class-scm-lifecycle.php'
s=p.read_text()

# Fresh Review Round 68 was fully completed before applying this correction.
old="""    $aliases=['delete'=>'deletion','deletion'=>'deletion','deliver'=>'delivery','delivery'=>'delivery','process'=>'processing','processing'=>'processing','repair'=>'reprocess','reprocess'=>'reprocess','provider-exit'=>'provider_exit','provider_exit'=>'provider_exit'];
"""
new="""    $aliases=['delete'=>'deletion','deletion'=>'deletion','expire-derivatives'=>'deletion','expire_derivatives'=>'deletion','deliver'=>'delivery','delivery'=>'delivery','process'=>'processing','processing'=>'processing','repair'=>'reprocess','reprocess'=>'reprocess','provider-exit'=>'provider_exit','provider_exit'=>'provider_exit'];
"""
if old not in s: raise SystemExit('legal-hold alias target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-68-legal-hold-scope.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-lifecycle.php');
function r68($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 68 FAIL: $m\n");exit(1);}echo "ROUND 68 PASS: $m\n";}
r68(str_contains($s,"'expire-derivatives'=>'deletion'")&&str_contains($s,"'expire_derivatives'=>'deletion'"),'derivative expiry operations map to deletion hold scope');
r68(str_contains($s,"LegalHoldService::assertNoHold($assetId,'expire-derivatives')"),'derivative expiry path remains protected by the centralized hold assertion');
r68(str_contains($s,"$allowed=['delivery','processing','deletion','reprocess','provider_exit','all']"),'legal-hold accepted scopes remain canonical and bounded');
echo "REVIEW ROUND 68 LEGAL HOLD SCOPE: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();needle='php "$ROOT/tests/review-round-67-lease-contention.php"\n'
if 'review-round-68-legal-hold-scope.php' not in x:
    if needle not in x: raise SystemExit('quality insertion anchor missing')
    q.write_text(x.replace(needle,needle+'php "$ROOT/tests/review-round-68-legal-hold-scope.php"\n',1))
