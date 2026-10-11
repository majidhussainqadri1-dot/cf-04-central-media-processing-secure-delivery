import hashlib,json,pathlib,subprocess,sys,zipfile
root=pathlib.Path(__file__).resolve().parents[1]
manifest=json.loads((root/'dist/MANIFEST.json').read_text())
zip_path=root/'dist'/f"cf-04-sabri-central-media-{manifest['version']}.zip"
assert zip_path.is_file()
with zipfile.ZipFile(zip_path) as z:
    names=z.namelist(); assert names==sorted(names)
    expected={f"sabri-central-media/{x['path']}":x for x in manifest['files']}
    assert set(names)==set(expected)
    for name in names:
        raw=z.read(name); item=expected[name]
        assert len(raw)==item['size']
        assert hashlib.sha256(raw).hexdigest()==item['sha256']
assert manifest['runtime_default']=='disabled'
assert manifest['requirements_complete_in_source']==33
print('REVIEW ROUND 14 RELEASE: PASS')
