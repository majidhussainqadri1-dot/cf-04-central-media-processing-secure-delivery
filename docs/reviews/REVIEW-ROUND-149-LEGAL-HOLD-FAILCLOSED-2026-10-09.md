# Review Round 149 — Legal hold persistence and authorization

Baseline exact PR #5 HEAD: `de374e7107bbe08cf06482248f34a1807d78056b`. Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1; schema and contract 1.5.0. Read-only review of LegalHoldService, DomainRegistry, RecordStore, tests and exact-head CI was completed before any correction.

## Frozen defect ledger

1. Placement accepted lossy review timestamps.
2. Placement accepted lossy, negative or malformed expiry timestamps.
3. Scope normalization silently coerced nonlists, duplicate and noncanonical scopes.
4. Access restriction silently normalized unknown policy values.
5. Active holds used lossy persisted expiry casts.
6. Active holds trusted malformed persisted scope.
7. Unknown persisted hold states could silently stop blocking operations.
8. Persisted hold ID, asset ID and record identity were not verified.
9. Hold review did not validate persisted review and expiry timestamps.
10. Hold review trusted unsafe persisted version counters.
11. Review did not enforce the owner's explicit hold_allowed decision.
12. Unknown operation names could bypass scoped holds.

## Unified correction and regression

Canonical integer and scope validation is shared across placement, active checks and review. Persisted identity, state and restriction metadata fail closed. Unsupported operation names fail closed. Owner hold authorization is required on placement and review. Review counters are bounded and expiry-aware. Executable regression tests cover valid behavior, malformed inputs, persisted corruption, unknown operations and explicit owner veto. Quality gate includes the new test.

## Evidence boundary

Round 149 is accepted only after its commit is attached to the exact PR head and matching exact-head CI passes. Staging acceptance, deployed code, live operation and external gates remain unverified.
