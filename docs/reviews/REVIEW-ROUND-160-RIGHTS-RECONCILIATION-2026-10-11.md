# Review Round 160 — Automatic rights-expiry scheduling and bounded fairness (2026-10-11)

## Frozen exact-head repository truth
- Base main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Draft PR #5 source HEAD: `37ebcf2a9c03220cd0477aae8f36cecc69e98992`.
- Governing reference: CF-04 Conditional Complete Master Plan v1.1 Future-40 Amended (2026-09-16); runtime `1.3.0-rc.1`, schema/contract `1.5.0/1.5.0`. Full external plan unverified.
- Exact baseline push and PR CI: `38091815586` and `38091818743`, PHP 8.1/8.3/8.4 6/6 green.

## Frozen read-only review findings
1. `RightsRevocationService::reconcileExpired()` is called only by tests, not the registered production cron path. Expired/invalid rights may remain unreconciled indefinitely without a manual caller.
2. Fixed sorting plus `array_slice(...,0,$limit)` lets a repeatedly failing first batch starve later actionable assets on every invocation.
3. A failure to write degraded-state evidence inside the per-asset catch can abort the remaining batch.
4. No regression tests prove production cron wiring, durable fairness after a failed asset, or later-asset progress.

## Coordinated correction
- Wire rights reconciliation into the existing runtime-gated, locked hourly retention cron.
- Filter actionable assets, rotate by a stable expiry/id cursor and persist cursor progress after a bounded batch.
- Isolate secondary degraded-state reporting failures from the per-asset reconciliation loop.
- Add focused regression test and quality-gate registration.

## Acceptance boundary
Accept only on exact-new-head quality CI for PHP 8.1/8.3/8.4. Runtime remains disabled by default; staging/live/operational acceptance is not evidenced.
