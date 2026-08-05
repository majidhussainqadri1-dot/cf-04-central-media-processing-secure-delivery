#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="1.1.0-rc.3"
ZIP="$ROOT/dist/cf-04-sabri-central-media-$VERSION.zip"
for command in php python3 zip unzip sha256sum; do command -v "$command" >/dev/null || { echo "missing command: $command" >&2; exit 1; }; done
find "$ROOT/sabri-central-media" "$ROOT/tests" "$ROOT/tools" -type f -name '*.php' -print0 | LC_ALL=C sort -z | xargs -0 -n1 php -l >/dev/null
python3 "$ROOT/tests/contracts-runtime.py"
python3 "$ROOT/tests/source-integration.py"
php "$ROOT/tests/run-unit.php"
php "$ROOT/tests/review-round-8-three-plan-adversarial.php"
php "$ROOT/tests/review-round-9-source-audit.php"
php "$ROOT/tests/review-round-10-fresh-adversarial.php"
rm -rf "$ROOT/dist"; mkdir -p "$ROOT/dist"
php "$ROOT/tools/build-package.php" >/dev/null
FIRST="$(mktemp -d)"
trap 'rm -rf "$FIRST"' EXIT
cp "$ROOT/dist/"* "$FIRST/"
php "$ROOT/tools/build-package.php" >/dev/null
for evidence in "cf-04-sabri-central-media-$VERSION.zip" MANIFEST.json SBOM.json CHECKSUMS.sha256; do cmp "$FIRST/$evidence" "$ROOT/dist/$evidence"; done
unzip -t "$ZIP" >/dev/null
(cd "$ROOT/dist" && sha256sum -c CHECKSUMS.sha256)
python3 - "$ROOT" "$VERSION" <<'PY'
import hashlib,json,pathlib,sys,zipfile
root=pathlib.Path(sys.argv[1]); version=sys.argv[2]
zip_path=root/'dist'/f'cf-04-sabri-central-media-{version}.zip'
manifest=json.loads((root/'dist'/'MANIFEST.json').read_text())
expected={f"sabri-central-media/{item['path']}":item for item in manifest['files']}
with zipfile.ZipFile(zip_path) as archive:
    names=archive.namelist()
    assert names==sorted(names), 'ZIP entries are not sorted'
    assert all(name.startswith('sabri-central-media/') and not name.startswith('/') and '..' not in pathlib.PurePosixPath(name).parts for name in names)
    assert set(names)==set(expected), 'source/package path parity failed'
    for name in names:
        data=archive.read(name); item=expected[name]
        assert len(data)==item['size'] and hashlib.sha256(data).hexdigest()==item['sha256'], name
assert manifest['version']==version and manifest['runtime_default']=='disabled'
print('deterministic package parity: PASS')
PY
COMMIT="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || printf unknown)"
PACKAGE_SHA="$(sha256sum "$ZIP" | awk '{print $1}')"
cat > "$ROOT/dist/RELEASE-EVIDENCE.json" <<JSON
{
  "module": "CF-04",
  "version": "$VERSION",
  "source_commit": "$COMMIT",
  "package_sha256": "$PACKAGE_SHA",
  "runtime_default": "disabled",
  "quality_gate": "passed",
  "php": "$(php -r 'echo PHP_VERSION;')"
}
JSON
echo "CF-04 QUALITY GATE: PASS ($VERSION $PACKAGE_SHA)"
