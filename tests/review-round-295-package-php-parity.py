#!/usr/bin/env python3
"""R295: manifest-to-CLI PHP digest parity, with no live deployment inference."""
import hashlib
import json
import pathlib
import zipfile
root = pathlib.Path(__file__).resolve().parents[1]
dist = root / 'dist'
manifest = json.loads((dist / 'MANIFEST.json').read_text())
evidence = json.loads((dist / 'RELEASE-EVIDENCE.json').read_text())
php_files = [f for f in manifest['files']
             if f['path'] == 'sabri-central-media.php'
             or (f['path'].startswith('includes/') and f['path'].endswith('.php'))]
digest = hashlib.sha256()
for item in sorted(php_files,key=lambda x:x['path']):
    digest.update(item['path'].encode('utf-8') + b'\0'
                  + item['sha256'].encode('ascii') + b'\n')
assert len(php_files) >= 16
assert manifest['php_tree_sha256'] == digest.hexdigest()
assert manifest['php_tree_file_count'] == len(php_files)
assert manifest['php_tree_scope'] == 'entrypoint_and_includes_php'
assert evidence['php_tree_sha256'] == digest.hexdigest()
assert evidence['php_tree_file_count'] == len(php_files)
assert evidence['external_acceptance'] == 'pending'
assert evidence['runtime_default'] == 'disabled'
package = dist / f"cf-04-sabri-central-media-{manifest['version']}.zip"
with zipfile.ZipFile(package) as zipped:
    for item in php_files:
        data = zipped.read('sabri-central-media/' + item['path'])
        assert hashlib.sha256(data).hexdigest() == item['sha256']
print('REVIEW ROUND 295 DEPLOYED-PHP SOURCE PARITY WITNESS: PASS')
