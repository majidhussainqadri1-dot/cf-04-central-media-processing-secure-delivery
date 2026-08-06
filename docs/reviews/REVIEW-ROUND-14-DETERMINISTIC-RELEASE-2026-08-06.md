# Review Round 14 — Deterministic Package and Release Evidence

## Checks

- PHP syntax across plugin and tests.
- Contract JSON parsing and local reference resolution.
- Source/bootstrap/integration completeness.
- Full 33-requirement acceptance suite and CHAT-XFER-001.
- Governance, security and adversarial review gates.
- Two independent deterministic builds compared byte-for-byte.
- Sorted ZIP paths, fixed timestamps and stripped ZIP metadata.
- Source/package path, size and SHA-256 parity.
- `MANIFEST.json`, CycloneDX `SBOM.json`, `CHECKSUMS.sha256` and `RELEASE-EVIDENCE.json`.
- Runtime default remains disabled in the packaged bootstrap.

## Result

Deterministic release-candidate gate: **PASS**. Package evidence does not substitute for staging/live acceptance.
