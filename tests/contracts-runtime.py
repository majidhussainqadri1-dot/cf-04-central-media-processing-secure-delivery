import json, pathlib, sys
root=pathlib.Path(__file__).resolve().parents[1]
contracts=root/'contracts'
files=sorted(contracts.glob('*.json'))
assert len(files)>=9, 'contract family incomplete'
ids=set()
for path in files:
    data=json.loads(path.read_text())
    assert data.get('$schema')=='https://json-schema.org/draft/2020-12/schema', path
    assert data.get('$id','').endswith('.json'), path
    assert data['$id'] not in ids, path
    ids.add(data['$id'])
    assert data.get('type')=='object', path
for path in files:
    data=json.loads(path.read_text())
    def walk(v):
        if isinstance(v,dict):
            if '$ref' in v and not v['$ref'].startswith('http'):
                assert (contracts/v['$ref']).is_file(), (path,v['$ref'])
            for x in v.values(): walk(x)
        elif isinstance(v,list):
            for x in v: walk(x)
    walk(data)
print('CONTRACT RUNTIME: PASS')
