# CF-04 Runtime Status

## Current candidate
- Version: `1.1.0-rc.3`
- Schema / contract: `1.3.1 / 1.3.1`
- Provenance: reconstructed 1.1.0-rc.1 source, published to a separate audit/release branch, then corrected through two fresh review/fix rounds.
- Runtime default: disabled and fail closed.

## Evidence status
- Source audit: complete for this release cycle.
- Fresh review/fix round 1: complete.
- Fresh adversarial review/fix round 2: complete.
- Deterministic ZIP, MANIFEST, SBOM, checksums and exact-head GitHub Actions: required release evidence.

## Pending external acceptance
Hostinger staging, real malware/metadata/archive scanners, approved private object provider, streaming assembly/scanning/delivery for objects above the bounded local limit, migration rehearsal, backup/restore, rollback, browser/API integration, load testing, Founder acceptance, live deployment and operational monitoring remain pending. Therefore this is a coded/packaged/automated-QA candidate, not a production-complete claim.
