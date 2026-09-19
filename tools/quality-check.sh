#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VERSION="1.3.0-rc.1"
for command in php python3 zip unzip sha256sum rsync; do command -v "$command" >/dev/null || { echo "missing command: $command" >&2; exit 1; }; done
run_php() {
  local output status
  set +e
  output="$(php -d display_errors=stderr -d error_reporting=E_ALL "$@" 2>&1)"
  status=$?
  set -e
  printf '%s\n' "$output"
  if printf '%s\n' "$output" | grep -Eq 'PHP (Warning|Notice|Deprecated):|^(Warning|Notice|Deprecated):'; then
    echo "PHP diagnostic output is a quality-gate failure." >&2
    return 1
  fi
  return "$status"
}
find "$ROOT/sabri-central-media" "$ROOT/tests" -type f -name '*.php' -print0 | LC_ALL=C sort -z | xargs -0 -n1 php -l >/dev/null
python3 "$ROOT/tests/contracts-runtime.py"
python3 "$ROOT/tests/source-integration.py"
run_php "$ROOT/tests/run-all.php"
run_php "$ROOT/tests/new-plan-parity.php"
run_php "$ROOT/tests/future40.php"
run_php "$ROOT/tests/review-round-57-upload-validation.php"
run_php "$ROOT/tests/review-round-59-upload-finalization.php"
run_php "$ROOT/tests/review-round-60-processing-atomicity.php"
run_php "$ROOT/tests/review-round-61-lifecycle-rollback.php"
run_php "$ROOT/tests/review-round-63-upload-create-races.php"
run_php "$ROOT/tests/review-round-64-job-concurrency.php"
run_php "$ROOT/tests/review-round-65-replay-atomicity.php"
run_php "$ROOT/tests/review-round-66-runtime-lock.php"
run_php "$ROOT/tests/review-round-67-lease-contention.php"
run_php "$ROOT/tests/review-round-68-legal-hold-scope.php"
run_php "$ROOT/tests/review-round-69-cdn-policy-reuse.php"
run_php "$ROOT/tests/review-round-70-provider-exit-inventory.php"
run_php "$ROOT/tests/review-round-71-final-adversarial.php"
run_php "$ROOT/tests/review-round-72-record-scan-boundary.php"
run_php "$ROOT/tests/review-round-73-abort-recovery.php"
run_php "$ROOT/tests/review-round-74-range-suffix.php"
run_php "$ROOT/tests/review-round-75-manifest-lineage.php"
run_php "$ROOT/tests/review-round-76-grant-revocation.php"
run_php "$ROOT/tests/review-round-77-retention-isolation.php"
run_php "$ROOT/tests/review-round-78-key-rotation-reference-group.php"
run_php "$ROOT/tests/review-round-79-service-auth-target.php"
run_php "$ROOT/tests/review-round-80-audit-lock-name.php"
run_php "$ROOT/tests/review-round-81-provider-exit-drift.php"
run_php "$ROOT/tests/review-round-82-idempotency-generation.php"
run_php "$ROOT/tests/review-round-83-schema-shape.php"
run_php "$ROOT/tests/review-round-84-upload-terminal-order.php"
run_php "$ROOT/tests/review-round-85-validation-provenance.php"
run_php "$ROOT/tests/review-round-86-processing-review-pause.php"
run_php "$ROOT/tests/review-round-87-storage-boundaries.php"
run_php "$ROOT/tests/review-round-88-delivery-cdn-atomicity.php"
run_php "$ROOT/tests/review-round-89-lifecycle-reauthorization.php"
run_php "$ROOT/tests/review-round-90-operations-recovery.php"
run_php "$ROOT/tests/review-round-91-transfer-action-boundaries.php"
run_php "$ROOT/tests/review-round-92-final-governance.php"
run_php "$ROOT/tests/review-round-93-hold-owner-version.php"
run_php "$ROOT/tests/review-round-94-upload-completion-reauth.php"
run_php "$ROOT/tests/review-round-95-processing-action-time.php"
run_php "$ROOT/tests/review-round-96-cdn-recovery.php"
run_php "$ROOT/tests/review-round-97-deletion-idempotency.php"
run_php "$ROOT/tests/review-round-98-key-rotation-governance.php"
run_php "$ROOT/tests/review-round-99-operations-failclosed.php"
run_php "$ROOT/tests/review-round-100-future40-adversarial.php"
run_php "$ROOT/tests/review-round-101-runtime-release.php"
run_php "$ROOT/tests/review-round-102-evidence-freshness.php"
run_php "$ROOT/tests/review-round-103-private-storage-root.php"
run_php "$ROOT/tests/review-round-104-upload-quota-concurrency.php"
run_php "$ROOT/tests/review-round-105-processing-idempotency.php"
run_php "$ROOT/tests/review-round-106-delivery-revocation.php"
run_php "$ROOT/tests/review-round-107-lifecycle-action-time.php"
run_php "$ROOT/tests/review-round-108-operations-reconciliation.php"
run_php "$ROOT/tests/review-round-109-rest-contract-boundaries.php"
run_php "$ROOT/tests/review-round-110-persistence-runtime.php"
run_php "$ROOT/tests/review-round-111-plan-parity.php"
run_php "$ROOT/tests/review-round-112-rest-error-boundary.php"
run_php "$ROOT/tests/review-round-113-upload-runtime-gate.php"
run_php "$ROOT/tests/review-round-114-processing-runtime-gate.php"
run_php "$ROOT/tests/review-round-115-public-delivery-runtime-gate.php"
run_php "$ROOT/tests/review-round-11-governance.php"
run_php "$ROOT/tests/review-round-12-security.php"
run_php "$ROOT/tests/review-round-13-adversarial.php"
run_php "$ROOT/tests/review-rounds-15-54.php"
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
(cd "$ROOT/dist" && sha256sum RELEASE-EVIDENCE.json > RELEASE-EVIDENCE.sha256 && sha256sum -c RELEASE-EVIDENCE.sha256)
echo "CF-04 QUALITY GATE: PASS ($VERSION $PACKAGE_SHA)"
