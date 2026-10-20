# CF-04 Review Round 162 — Supersession Evidence Integrity

Baseline exact PR HEAD: `9af3bd34d13e40e765a0cbd87eca7ed34da3d9bc`.
Governing source plan: CF-04 v1.1 — Future-40 Amended (2026-09-16), subject to independent original-plan parity verification.

## Frozen read-only findings
1. Supersession-history entries were not individually type/shape validated.
2. Supersession count could be absent, non-integer, negative or inconsistent with bounded history.
3. The active superseded reason/timestamp could disagree with the latest durable history entry.
4. Counter increment could overflow PHP integer bounds.
5. Adversarial regression coverage and quality-gate inclusion were absent.

## Grouped correction
Fail-closed validation enforces history entry keys, types, allowed reasons, monotonic timestamps and record versions; requires a bounded 32-entry suffix consistent with a positive integer total count; binds active supersession evidence to the last history entry; and refuses counter overflow before persistence. The new adversarial regression checks corruption, safe reactivation, pending recovery and overflow without event dispatch. This does not establish external receipt or deployment.

Acceptance gate: the exact new branch/PR HEAD must pass source regression, deterministic packaging and both required CI workflows before round 163 may begin.
