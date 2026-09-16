#!/usr/bin/env python3
from pathlib import Path
p=Path(__file__).with_name('apply-current-plan-parity.py')
s=p.read_text()
old="replace_all('tests/bootstrap.php',\"'authorize_hold'=>$ownerDecision,\",\"'authorize_hold'=>$ownerDecision,'authorize_accessibility_metadata'=>$ownerDecision,\",2)"
new="replace_all('tests/bootstrap.php',\"'authorize_hold'=>$ownerDecision,\",\"'authorize_hold'=>$ownerDecision,'authorize_accessibility_metadata'=>$ownerDecision,\",1)"
if old not in s:
    raise SystemExit('transformer callback cardinality marker not found')
p.write_text(s.replace(old,new))
print('transformer callback cardinality corrected')
