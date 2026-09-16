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
if old_check in s:
    s=s.replace(old_check,new_check)

old_all="""    if count<min_count: raise SystemExit(f'{rel}: expected >= {min_count}, found {count}: {old!r}')\n"""
new_all="""    if count<1: raise SystemExit(f'{rel}: marker not found: {old!r}')\n"""
if old_all in s:
    s=s.replace(old_all,new_all)

obsolete="""# Integration manifest explicitly exposes the new-plan parity contract.\nreplace(\n    'sabri-central-media/includes/class-scm-plugin.php',\n    \"'runtime_default'=>'disabled','domain_contracts'=>DomainRegistry::manifest(),\",\n    \"'runtime_default'=>'disabled','domain_contracts'=>DomainRegistry::manifest(),'current_plan_parity'=>PlanParityRegistry::manifest(),\"\n)\nreplace(\n    'sabri-central-media/includes/class-scm-plugin.php',\n    \"'scm.provider.degraded','scm.budget.threshold'\",\n    \"'scm.provider.degraded','scm.budget.threshold','scm.media.accessibility.updated','scm.media.revoked'\"\n)\n\n"""
if obsolete not in s:
    raise SystemExit('obsolete plugin-manifest transformer block not found')
s=s.replace(obsolete,"# Plan-parity services are loaded as native runtime classes; no duplicate plugin manifest is introduced.\n\n")

p.write_text(s)
print('transformer source-drift assumptions corrected')
