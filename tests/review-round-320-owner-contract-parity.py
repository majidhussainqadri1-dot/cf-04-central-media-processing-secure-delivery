#!/usr/bin/env python3
"""Review R320: JSON owner contract to PHP preflight parity."""
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
schema=json.loads((root/'contracts/native-owner-provider-exit-v1.schema.json').read_text())
req=schema['$defs']['request']
res=schema['$defs']['response']
expected={'plan_id','asset_id','record_type','record_id','object_version',
    'record_cas','source_provider','target_provider','source_key_sha256',
    'target_key_sha256','phase'}
assert schema['$schema'].endswith('/draft/2020-12/schema')
assert req['additionalProperties'] is False
assert set(req['required'])==expected
assert req['properties']['phase']['const']=='read_only_preflight'
assert set(req['properties']['record_type']['enum'])=={'asset','derivative'}
assert req['properties']['object_version']['minimum']==1
assert req['properties']['record_cas']['minimum']==1
assert res['properties']['allowed']['const'] is True
assert res['properties']['approved_for_provider_exit']['const'] is True
assert {'asset_id','record_id','source_provider','target_provider',
    'record_cas','object_version'}<=set(res['required'])
source=(root/'sabri-central-media/includes/class-scm-provider-cutover-plan.php').read_text()
for literal in ("'authorize_provider_exit'","'source_key_sha256'",
    "'target_key_sha256'","'read_only_preflight'",
    "'provider_owner_subject_mismatch'","'owner_approved_unfenced'",
    "'native_owner_write_fenced'=>false","'authorization_to_switch'=>false"):
    assert literal in source, f'missing owner contract: {literal}'
guide=(root/'docs/integration/NATIVE-OWNER-PROVIDER-EXIT-V1.md').read_text()
assert 'read-only' in guide and 'write fence' in guide
print('REVIEW ROUND 320 OWNER PROVIDER EXIT CONTRACT PARITY: PASS')
