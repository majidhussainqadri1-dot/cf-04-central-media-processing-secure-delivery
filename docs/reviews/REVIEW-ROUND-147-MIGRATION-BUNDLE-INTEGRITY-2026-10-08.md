# Review Round 147 — signed migration-bundle identity and verification

Baseline exact PR #5 HEAD: `c2494846a1b27f071de948babcc3fb3dbb201711`. The full read-only review of migrationBundle, verifyMigrationBundle, deletion tombstones, existing Future-40 tests, and Round-100 governance evidence preceded all corrections.

## Frozen defect ledger
1. Migration asset identifiers were cast from arbitrary types and an unbounded array could exhaust resources.
2. Object versions were cast to integers, allowing malformed identity metadata to become zero.
3. Active asset digests and policy/rights identity hashes could be missing or noncanonical in a signed export.
4. Deleted assets could be exported without a tombstone; tombstone timestamps were not validated.
5. Bundle verification ignored the declared bundle_hash and accepted its mutation/removal despite an otherwise valid signature.
6. Bundle verification coerced malformed signature/key types and lacked basic format/item bounds.

## Unified correction
Canonical bounded asset set and identities, strict object-version/digest/policy/tombstone checks, signed payload hash verification, strict signature and bundle structure, and executable regression covering tampering, malformed inputs and valid tombstone export. Quality gate includes Round 147.

## Evidence boundary
Only exact source and matching CI can establish automated quality. No staging, deployment or operational acceptance is claimed.
