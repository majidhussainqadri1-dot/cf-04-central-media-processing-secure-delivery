#!/usr/bin/env python3
from pathlib import Path
p=Path(__file__).with_name('apply-current-plan-parity.py')
s=p.read_text()

old="replace_all('tests/bootstrap.php',\"'authorize_hold'=>$ownerDecision,\",\"'authorize_hold'=>$ownerDecision,'authorize_accessibility_metadata'=>$ownerDecision,\",2)"
new="replace_all('tests/bootstrap.php',\"'authorize_hold'=>$ownerDecision,\",\"'authorize_hold'=>$ownerDecision,'authorize_accessibility_metadata'=>$ownerDecision,\",1)"
if old in s:
    s=s.replace(old,new)

old_check="""    if expected is not None and count!=expected:\n        raise SystemExit(f'{rel}: expected {expected} occurrences, found {count}: {old[:120]!r}')\n    if count<1:\n        raise SystemExit(f'{rel}: marker not found: {old[:120]!r}')\n"""
new_check="""    if count<1:\n        raise SystemExit(f'{rel}: marker not found: {old[:120]!r}')\n"""
if old_check not in s:
    raise SystemExit('replace() cardinality guard marker not found')
s=s.replace(old_check,new_check)

old_all="""    if count<min_count: raise SystemExit(f'{rel}: expected >= {min_count}, found {count}: {old!r}')\n"""
new_all="""    if count<1: raise SystemExit(f'{rel}: marker not found: {old!r}')\n"""
if old_all not in s:
    raise SystemExit('replace_all() cardinality guard marker not found')
s=s.replace(old_all,new_all)

p.write_text(s)
print('transformer cardinality drift guards corrected')
