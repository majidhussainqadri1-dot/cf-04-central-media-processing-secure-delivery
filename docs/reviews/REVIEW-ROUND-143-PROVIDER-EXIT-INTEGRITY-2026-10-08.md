# Review Round 143 — Provider-exit immutable content identity

Baseline exact PR #5 HEAD: `02caf284c1ea8c6302c3d5fbf7bb334e72665ef0`. Complete read-only review preceded this correction.

## Frozen defect ledger
1. Provider-exit inventory accepted missing or noncanonical source/derivative sizes, digests, and object keys.
2. Copy could perform remote side effects before rejecting malformed persisted plan identity; integer casts could hide corrupted sizes.
3. Shadow verification used unchecked casts and unvalidated persisted identity.
4. Switch checked provider/key mapping but did not reject a record whose digest or size had changed since planning.
5. Missing identity fields could emit runtime diagnostics instead of stable fail-closed errors.

## Unified correction
A canonical content-identity guard is used during inventory, before copy side effects, at shadow verification, and before switch mapping changes. Exact source digest and size are compared with current records. Executable regression covers malformed, missing and valid identities; quality gate includes it.

This is repository-only evidence. Exact-head CI must succeed before acceptance. Staging/live/operational states remain unverified.
