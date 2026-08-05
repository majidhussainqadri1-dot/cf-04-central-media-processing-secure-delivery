from pathlib import Path
r=Path(__file__).resolve().parents[1]
main=(r/'sabri-central-media/sabri-central-media.php').read_text()
assert 'SCM_RUNTIME_ENABLED' in main and "false" in main
required=['class-scm-upload-service.php','class-scm-delivery-service.php','class-scm-transfer-service.php','class-scm-download-manager-service.php','class-scm-workspace-upload-service.php','class-scm-scanner-registry.php','class-scm-record-store.php','class-scm-part-store.php']
for f in required: assert (r/'sabri-central-media/includes'/f).exists(), f
for p in r.rglob('*'):
    if p.is_file() and '.git' not in p.parts and p.name != 'source-integration.py':
        t=p.read_text(errors='ignore')
        assert ('A'+'KIA') not in t and ('BEGIN PRIVATE'+' KEY') not in t
print('source-integration: PASS')
