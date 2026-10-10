# Review Round 158 — Projection evidence integrity and fail-closed owner identity (2026-10-11)

## Frozen exact-head repository truth
- Baseline main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Draft PR #5 branch `codex/cf04-three-plan-complete-1.2.0`: `bf39cdd2aa873ec9272fc444e6a68397e11611e6`.
- Governing reference: CF-04 Conditional Complete Master Plan v1.1 Future-40 Amended (2026-09-16); runtime `1.3.0-rc.1`, schema/contract `1.5.0/1.5.0`. The full external governing text has not been independently verified.
- Baseline exact-head push/PR CI: 38080498187 and 38080500929; six quality jobs green.

## Frozen read-only findings
1. The existing projection key includes asset, reason, rights fingerprint and object version but not owner identity. A changed owner with an unchanged object version collides with the prior projection. The conservative contract-safe resolution is to reject this inconsistent ownership transition, not silently issue a second event. A legitimate owner transition must advance the authoritative object version.
2. Persisted projection evidence is insufficiently validated: rights invalidation replay accepts a projection with corrupt actor, metadata, status or hashes; pending outbox reconciliation verifies only asset, reason and object version.
3. Existing projection validation occurs after derivative-expiry side effects, so tampered persisted evidence is rejected too late. No adversarial regression covers these corruption/recovery boundaries.

## Coordinated correction
- Preserve the established stable projection key and fail-closed owner binding; do not introduce a new key that would replay already-dispatched events.
- Validate stored projection identity, immutable owner/rights bindings, actor, numeric metadata and propagation fields before destructive replay.
- Independently validate projection identity and binding before a pending outbox dispatch.
- Add round-158 tamper, no-side-effect, pending-recovery and invalid owner-transition tests, quality gate and README.

## Acceptance and boundary
Accept only after new exact-head push/PR PHP 8.1/8.3/8.4 quality CI passes. Local hook dispatch is not downstream acknowledgement, staging acceptance, live deployment or operational proof.
