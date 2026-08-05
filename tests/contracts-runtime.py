import json, pathlib
root=pathlib.Path(__file__).resolve().parents[1]
for p in (root/'contracts').glob('*.json'):
    d=json.loads(p.read_text())
    assert d.get('$schema') and d.get('$id')
    assert '1.3.1' in d['$id']
print('contracts-runtime: PASS')
