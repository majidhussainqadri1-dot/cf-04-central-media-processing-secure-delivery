# Review Round 148 — WORM/object-lock and deferred ciphertext cleanup

Baseline exact PR #5 HEAD: `6f74eb332f56f68f92ef31b80b33b446eae88e60`. The read-only review of `ResidencyCryptoService`, `KeyRotationService`, previous Future-40 and key-rotation regressions, the release gate and governing CF-04 v1.1 Future-40 Amended plan was completed before writing corrections.

## Frozen defect ledger
1. Persisted noncanonical lock expiry could cast to zero, bypassing WORM protection.
2. Unexpected lock state or mismatched asset identity could be treated as unlocked.
3. Re-locking could overwrite corrupt prior lock metadata, losing monotonic retention assurance.
4. Deferred old-ciphertext scheduling used lossy lock-expiry casts.
5. Noncanonical or missing deferred `not_before` could become due immediately.
6. Malformed, empty, duplicate or missing deferred parent asset IDs could bypass lock checks.
7. Malformed lock records during deferred reconciliation could permit deletion.
8. Cleanup record state, provider, key, hash or record-ID drift could be normalized or accepted before physical deletion.

## Unified correction
Strict persisted lock validation is shared by object-lock checks, extensions and deferred cleanup. Deferred deletion requires canonical persisted scheduling, parent references, state and physical object identity; malformed records fail closed without deletion and remain auditable. New executable regression covers valid lock, shortening, corrupt metadata, valid cleanup, active lock and adversarial persisted cleanup values. `tools/quality-check.sh` runs Round 148 on each supported PHP matrix version.

## Evidence boundary
Correction is accepted only after commit attachment to the exact PR head and matching quality/CI success. No staging, live deployment or operational claim follows from this source correction.
