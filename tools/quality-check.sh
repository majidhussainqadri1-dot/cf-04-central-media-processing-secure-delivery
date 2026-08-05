#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
find "$ROOT/sabri-central-media" "$ROOT/tests" "$ROOT/tools" -type f -name '*.php' -print0 | sort -z | xargs -0 -n1 php -l >/dev/null
python3 "$ROOT/tests/contracts-runtime.py"
python3 "$ROOT/tests/source-integration.py"
php "$ROOT/tests/run-unit.php"
php "$ROOT/tests/review-round-8-three-plan-adversarial.php"
rm -rf "$ROOT/dist"; mkdir -p "$ROOT/dist"
php "$ROOT/tools/build-package.php" >/dev/null
cp "$ROOT/dist/cf-04-sabri-central-media-1.1.0-rc.2.zip" /tmp/cf04-first.zip
php "$ROOT/tools/build-package.php" >/dev/null
cmp /tmp/cf04-first.zip "$ROOT/dist/cf-04-sabri-central-media-1.1.0-rc.2.zip"
unzip -t "$ROOT/dist/cf-04-sabri-central-media-1.1.0-rc.2.zip" >/dev/null
(cd "$ROOT/dist" && sha256sum -c CHECKSUMS.sha256)
echo 'CF-04 QUALITY GATE: PASS'
