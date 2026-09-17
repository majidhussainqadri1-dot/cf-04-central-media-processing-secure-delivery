#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 80 was fully completed before corrections began.
# Defect ledger:
# - Audit::record used a MySQL named lock longer than the documented 64-character
#   user-lock identifier ceiling ("scm_audit_chain_" + 64 hex chars = 80).
#   On affected MySQL/MariaDB installations this can make audit serialization fail
#   even though persistence is otherwise healthy. Keep the lock deterministic and
#   table-specific while bounding the complete name to 64 characters.

p=ROOT/'sabri-central-media/includes/class-scm-persistence.php'
s=p.read_text()
old="$lock='scm_audit_chain_'.hash('sha256',Db::table('audit'));"
new="$lock='scm_audit_'.substr(hash('sha256',Db::table('audit')),0,54);"
if old not in s: raise SystemExit('round 80 audit lock target missing')
p.write_text(s.replace(old,new,1))

t=ROOT/'tests/review-round-80-audit-lock-name.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
function r80($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 80 FAIL: $m\n");exit(1);}echo "ROUND 80 PASS: $m\n";}
r80(str_contains($s,"$lock='scm_audit_'.substr(hash('sha256',Db::table('audit')),0,54);"),'audit lock uses a bounded deterministic table-specific identifier');
r80(!str_contains($s,"$lock='scm_audit_chain_'.hash('sha256',Db::table('audit'));"),'legacy 80-character audit lock construction is absent');
r80(strlen('scm_audit_'.substr(hash('sha256','wp_scm_audit'),0,54))<=64,'representative audit lock stays within the 64-character MySQL user-lock ceiling');
echo "REVIEW ROUND 80 AUDIT LOCK NAME: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-79-service-auth-target.php"\n'
if 'review-round-80-audit-lock-name.php' not in x:
    if anchor not in x: raise SystemExit('round 80 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-80-audit-lock-name.php"\n',1))
