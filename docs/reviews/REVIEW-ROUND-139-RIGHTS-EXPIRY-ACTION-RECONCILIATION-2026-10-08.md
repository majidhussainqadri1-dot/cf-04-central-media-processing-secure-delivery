# Review Round 139 — Rights expiry action-time and reconciliation

Review completed read-only before any correction. Baseline PR #5 HEAD: `a9ac0151ee381a148d721fbf60d47b74c0ae5914`.

## Frozen defect ledger

1. `RightsPolicy::assert()` cast `expires_at` to integer, allowing malformed or missing expiry to become an indefinite zero and bypass the normalized rights contract.
2. `RightsRevocationService::reconcileExpired()` repeated unchecked casts during priority sorting and execution, skipping invalid expiry records rather than revoking them.
3. Previously reconciled expired records were repeatedly selected and revoked because no durable rights-state reconciliation fingerprint was persisted; bounded batches could waste capacity on repeat work.

## Unified correction

- Enforce mandatory canonical non-negative expiry at the authorization boundary; zero and canonical decimal strings remain supported.
- Treat malformed/missing persisted rights expiry as revocation-due, prioritize it ahead of valid future/indefinite records, and use `rights_invalid` for audit.
- Persist a fingerprint and reason after successful revocation propagation; skip unchanged reconciled rights, but re-evaluate when rights change.
- Add executable Round 139 regression coverage to the canonical quality gate.

No staging, live deployment or operational acceptance is inferred from repository evidence.
