# Review Round 159 — Concurrent rights/owner state and durable dispatch (2026-10-11)

## Frozen exact-head repository truth
- Base `main`: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Draft PR #5 source head: `1ec3b6b594cb3ae2ffa10c763d9076ab017c013c`.
- Governing reference: CF-04 Conditional Complete Master Plan v1.1 Future-40 Amended (2026-09-16); runtime `1.3.0-rc.1`, schema/contract `1.5.0/1.5.0`. Full external governing text has not been independently checked.
- Baseline push/PR CI: `38080734165` and `38080736817`, 6/6 PHP quality jobs green. This is baseline evidence, not correction acceptance.

## Complete read-only review findings frozen before correction
1. `RightsRevocationService::invalidate()` marks the freshly changed rights fingerprint reconciled after hook dispatch, even if the hook changed rights; the original event does not cover that new state.
2. A synchronous hook can change owner identity, policy or terminal state, or remove the asset. The existing post-dispatch code silently returns success or skips the reconciliation marker.
3. Pending `RevocationDispatchService` rows validate owner and projection identity but not current rights fingerprint or terminal asset state, allowing stale retries to emit obsolete notifications.
4. No focused regression test covers these concurrent-state and pending-retry boundaries.

## Coordinated correction
- Check stable asset/owner/policy/rights identity before and after derivative expiry and after local hook dispatch; reject missing/terminal assets.
- Bind reconciliation markers only to the original validated rights fingerprint, using optimistic record-version checks.
- Reject stale pending outbox rows before hook dispatch while retaining them as durable pending evidence.
- Add adversarial hook mutation, missing asset, stale pending and clean-path regression to the quality gate.

## Acceptance boundary
Accept only after exact-new-head PHP 8.1/8.3/8.4 CI. Local hook dispatch is not downstream acknowledgement. No staging/live/operational claim.
