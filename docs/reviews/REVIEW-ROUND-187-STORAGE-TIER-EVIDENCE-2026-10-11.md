# CF-04 Review Round 187 — Storage-tier evidence hardening
Date: 2026-10-11 (Asia/Karachi)

## Frozen repository truth
- Target: main at 0294442f0fddd1ca5440d9d5ac992ba80aced972.
- PR #5 head: codex/cf04-three-plan-complete-1.2.0 at 0b2d696aae2576ac198c3f2a829835684452039b.
- Governing: CF-04 Conditional Complete Master Plan v1.1 — Future-40 Amended; runtime 1.3.0-rc.1; schema/contract 1.5.0.
- Last accepted review: 186. Round 187 reviewed read-only before any correction.

## Frozen findings
1. Missing rights expiry was interpreted as unlimited rights.
2. Unknown and unbounded tier metrics were accepted.
3. Malformed retention/privacy metadata was coerced into strings.
4. Adversarial regression coverage did not cover those cases.

## Joint correction
- Require explicit canonical rights expiry, retention policy/class and privacy class.
- Reject unknown metrics and cap metric ranges; preserve explicit-null rejection for each metric.
- Add tier metrics and tier evidence negative regression tests to the quality gate.
- Maintain advisory-only recommendation and fail-closed boundaries.

## Acceptance gate
Round 187 is not accepted until all files are linked in one source commit on PR #5 and exact-head quality/CI evidence is green. No staging, live deployment or operational acceptance inferred.
