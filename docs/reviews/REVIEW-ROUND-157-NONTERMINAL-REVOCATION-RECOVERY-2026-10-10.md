# Review Round 157 — Nonterminal revocation dispatch and projection recovery (2026-10-10)

## Frozen repository truth (read-only review completed before changes)
- Main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Draft PR #5 branch `codex/cf04-three-plan-complete-1.2.0` HEAD: `4af0aef3a4a157a10c759b1f5dbd866bbb0187b9`.
- Governing reference: CF-04 Conditional Complete Master Plan v1.1 — Future-40 Amended (2026-09-16); runtime `1.3.0-rc.1`; schema/contract `1.5.0/1.5.0`. The full governing external plan text is not independently verified here.
- Baseline exact-head push and PR CI: 38052670374 and 38052674147, six quality jobs green; they establish only the baseline, not the correction.

## Frozen findings
1. `RightsRevocationService::invalidate()` calls `DeletionService::expireDerivatives()`, and both emit `scm.media.revoked` for the same logical rights event.
2. `expireDerivatives()` and `invalidate()` silently omit owner revocation if `do_action` is unavailable.
3. Neither nonterminal path persists a pending outbox before callback dispatch; callback failure/crash can leave destructive work without durable retry evidence.
4. The projection ID depends on `Utils::now()`, creating new identities across retries and weakening audit idempotency.
5. No focused regression/quality evidence covers single logical dispatch, stable projection identity, fault injection, outbox replay and cron recovery.

## One coordinated correction
- Route nonterminal notices through `RevocationDispatchService` with stable identity and fail-closed persisted pending/dispatched states.
- Suppress the duplicate derivative hook only for the enclosing rights invalidation; direct derivative expiry still emits its own durable notice.
- Use a stable rights-fingerprint/owner-version projection key and `Audit::recordOnce` for idempotent propagation evidence.
- Register pending outbox reconciliation in the existing deletion cron, with bounded recovery.
- Add round-157 fault-injection and replay regression to the standard quality gate.

## Acceptance and evidence boundary
Correction acceptance requires a new exact-head PHP 8.1/8.3/8.4 push/PR CI pass and deterministic package evidence. Local WordPress hook dispatch is **not** external receipt, exactly-once delivery, staging acceptance, live deployment or operational proof. Preserve Draft/unmerged, runtime-disabled release gate.
