# CF-04 Review Round 183 — Crypto-Agility Policy Integrity

## Frozen repository truth
- Read-only baseline: PR #5 head `983cfc57577509a45bc40c23273a9b4589171fbb`; main `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Governing CF-04 v1.1 — Future-40 Amended (2026-09-16); runtime 1.3.0-rc.1; schema and contract 1.5.0.
- Prior accepted review: round 182, matching push/PR CI (PHP 8.1, 8.3, 8.4).
- Review scope: CF04-FUT-032 crypto-agility policy, RecordStore persistence, Utils integer contracts, audit evidence, existing Future-40 tests and quality gate.

## Frozen read-only defect ledger
1. The policy writer omits RuntimeGuard::requireReady despite mutating security policy.
2. Algorithms are cast from arbitrary input, normalized/truncated, deduplicated silently and unbounded; malformed or coerced entries can enter the policy.
3. Numeric security thresholds use lossy integer casts; the current AES-256-GCM algorithm can be removed and weak minimum key sizes accepted.
4. Unknown policy fields are not rejected.
5. A malformed persisted crypto policy can be silently overwritten instead of failing closed.
6. Security-policy changes have no explicit audit event; negative regressions are absent.

## Joint correction and verification contract
Use strict bounded canonical algorithm list, retain the current cipher, canonical bounded integer validation, explicit input keys, runtime guard, fail-closed persisted evidence checks, audit event and an executable adversarial regression in the exact-head quality gate.

## Evidence boundary
This document is source-level review evidence, not staging acceptance, real-provider acceptance, deployment or operational proof. Accept this round only after the full exact-head CI and deterministic packaging checks pass.
