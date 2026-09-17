#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="1.3.0-rc.1"
for command in php python3 zip unzip sha256sum rsync; do command -v "$command" >/dev/null || { echo "missing command: $command" >&2; exit 1; }; done
find "$ROOT/sabri-central-media" "$ROOT/tests" -type f -name '*.php' -print0 | LC_ALL=C sort -z | xargs -0 -n1 php -l >/dev/null
python3 "$ROOT/tests/contracts-runtime.py"
python3 "$ROOT/tests/source-integration.py"
php "$ROOT/tests/run-all.php"
php "$ROOT/tests/new-plan-parity.php"
php "$ROOT/tests/future40.php"
php "$ROOT/tests/review-round-57-upload-validation.php"
php "$ROOT/tests/review-round-59-upload-finalization.php"
php "$ROOT/tests/review-round-60-processing-atomicity.php"
php "$ROOT/tests/review-round-61-lifecycle-rollback.php"
php "$ROOT/tests/review-round-63-upload-create-races.php"
php "$ROOT/tests/review-round-64-job-concurrency.php"
php "$ROOT/tests/review-round-65-replay-atomicity.php"
php "$ROOT/tests/review-round-66-runtime-lock.php"
php "$ROOT/tests/review-round-67-lease-contention.php"
php "$ROOT/tests/review-round-68-legal-hold-scope.php"
php "$ROOT/tests/review-round-69-cdn-policy-reuse.php"
php "$ROOT/tests/review-round-70-provider-exit-inventory.php"
php "$ROOT/tests/review-round-71-final-adversarial.php"
php "$ROOT/tests/review-round-72-record-scan-boundary.php"
php "$ROOT/tests/review-round-73-abort-recovery.php"
php "$ROOT/tests/review-round-74-range-suffix.php"
php "$ROOT/tests/review-round-75-manifest-lineage.php"
php "$ROOT/tests/review-round-76-grant-revocation.php"
php "$ROOT/tests/review-round-77-retention-isolation.php"
php "$ROOT/tests/review-round-78-key-rotation-reference-group.php"
php "$ROOT/tests/review-round-79-service-auth-target.php"
php "$ROOT/tests/review-round-80-audit-lock-name.php"
php "$ROOT/tests/review-round-81-provider-exit-drift.php"
php "$ROOT/tests/review-round-82-idempotency-generation.php"
php "$ROOT/tests/review-round-11-governance.php"
php "$ROOT/tests/review-round-12-security.php"
php "$ROOT/tests/review-round-13-adversarial.php"
php "$ROOT/tests/review-rounds-15-54.php"
"$ROOT/tools/build-package.sh" >/dev/null
FIRST="$(mktemp -d)"; trap 'rm -rf "$FIRST"' EXIT
cp "$ROOT/dist/"* "$FIRST/"
"$ROOT/tools/build-package.sh" >/dev/null
for evidence in "cf-04-sabri-central-media-$VERSION.zip" MANIFEST.json SBOM.json CHECKSUMS.sha256; do cmp "$FIRST/$evidence" "$ROOT/dist/$evidence"; done
unzip -t "$ROOT/dist/cf-04-sabri-central-media-$VERSION.zip" >/dev/null
(cd "$ROOT/dist" && sha256sum -c CHECKSUMS.sha256)
python3 "$ROOT/tests/review-round-14-release.py"
COMMIT="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || printf local-uncommitted)"
PACKAGE_SHA="$(sha256sum "$ROOT/dist/cf-04-sabri-central-media-$VERSION.zip" | awk '{print $1}')"
python3 - "$ROOT" "$VERSION" "$COMMIT" "$PACKAGE_SHA" <<'PY'
import json,pathlib,sys
root=pathlib.Path(sys.argv[1]);version=sys.argv[2];commit=sys.argv[3];sha=sys.argv[4]
evidence={'module':'CF-04','version':version,'source_commit':commit,'package_sha256':sha,'runtime_default':'disabled','source_requirements':'33/33','current_plan_requirements':'CF04-CEN-01..10','native_journeys':'CF04-NJ-01..06','future40_source_capabilities':'CF04-FUT-001..040','cross_plan_directives':['CHAT-XFER-001','CHAT-QA-001'],'quality_gate':'passed','external_acceptance':'pending'}
(root/'dist/RELEASE-EVIDENCE.json').write_text(json.dumps(evidence,sort_keys=True,separators=(',',':'))+'\n')
PY
echo "CF-04 QUALITY GATE: PASS ($VERSION $PACKAGE_SHA)"
