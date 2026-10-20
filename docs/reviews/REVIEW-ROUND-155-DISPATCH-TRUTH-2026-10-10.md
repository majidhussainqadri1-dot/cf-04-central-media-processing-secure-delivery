# Review Round 155 — Local dispatch versus external delivery
Baseline PR #5 `4d1c6709641bdd84949ef4fedc2a96be7ce3305d`; main `0294442f0fddd1ca5440d9d5ac992ba80aced972`. Governing CF-04 Conditional Complete Master Plan v1.1 — Future-40 Amended (2026-09-16), runtime `1.3.0-rc.1`, schema/contract `1.5.0 / 1.5.0`.

## Frozen read-only findings (before correction)
1. `DeletionService::notifyCompleted()` labels a successful local WordPress `do_action()` callback `delivered`, although it cannot prove an external receiver accepted the notification.
2. The round-154 regression asserts the same inaccurate delivery claim, and no dedicated negative/legacy transition regression exists.
3. Quality-gate and README evidence must explicitly reflect the corrected dispatch-only contract.

## Coordinated correction
Use `pending -> dispatched` and `dispatched_at` only after successful local hook execution. Normalize legacy `delivered` records to `dispatched` without re-sending or fabricating external acknowledgements. Preserve callback failure as `pending`. Add round-155 replay, legacy migration and invalid-evidence tests; update round-154 assertion, quality gate and README.

## Acceptance boundary
The event is AT LEAST ONCE when callbacks fail or a crash occurs between hook return and the durable marker; consumer deduplication must use `event_id`. Exact-head PHP 8.1/8.3/8.4 quality and package CI must pass before this round is accepted. External owner receipt, staging, live deployment and operations are not established by this repository correction.
