# Review Round 156 — Revocation notice record integrity
Baseline PR #5 `42d263088c1cc00e9efac4e98becd5c1714c0d0a`; main `0294442f0fddd1ca5440d9d5ac992ba80aced972`. CF-04 Conditional Complete Master Plan v1.1 Future-40 Amended, runtime `1.3.0-rc.1`, schema/contract `1.5.0`.

## Read-only findings frozen before any correction
- The existing outbox validates deletion/asset/event identity but not its persisted actor identity, creation timestamp, or positive record version. A forged/malformed outbox can bypass hook dispatch when status is `dispatched`.
- The state validator accepts contradictory timestamp evidence: `pending` can contain a dispatch marker; `dispatched` can retain `delivered_at`; and legacy `delivered` can contain an incompatible `dispatched_at`.
- There is no regression that asserts these corrupt states fail closed, and the quality/README round evidence is missing.

## One coordinated correction
Enforce actor binding, positive integer record metadata and state-specific timestamp exclusivity. Add a focused adversarial regression and quality gate entry, update README. Preserve round-155 dispatch-only semantics and legacy migration. Exact-head PHP 8.1/8.3/8.4 plus deterministic packaging are required for acceptance; external acceptance remains pending.
