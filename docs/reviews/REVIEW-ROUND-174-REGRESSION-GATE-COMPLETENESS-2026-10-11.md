# CF-04 Review Round 174 — Regression Gate Completeness (2026-10-11)

## Frozen repository truth
Read-only review source HEAD: `263f70a84847aed019b3e6d4d967574d770fd74c`; target main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`; Draft PR #5. Governing repository plan reference: CF-04 v1.1 Future-40 Amended (2026-09-16). Runtime: 1.3.0-rc.1; schema/contract: 1.5.0/1.5.0. No direct deployed-code evidence; full original external plan parity remains unverified.

## Complete read-only findings frozen before correction
1. The runtime CI gate invoked `tools/quality-check.sh`, which omitted all eight committed adversarial PHP regressions for rounds 166–173. Exact-head 6/6 green therefore did **not** establish execution of these eight tests.
2. Round 131 was invoked as raw `php` rather than the `run_php` diagnostic-enforcing wrapper, leaving its PHP warning/notice detection inconsistent with the other tests.
3. No automated invariant required newly committed `review-round-*.php` or `review-round-*.py` tests to be registered in the quality gate, allowing future false-green omissions.

## Joint correction
- Explicitly register review tests 166–173 through `run_php`.
- Route round 131 through the same PHP diagnostic gate.
- Add `tests/review-round-174-gate-coverage.py`, executed before the PHP suite, to fail on missing, stale or duplicate review-test registrations (PHP and Python).
- Retain deterministic packaging and exact-head PHP 8.1/8.3/8.4 CI verification; no production/staging acceptance claim.

## Acceptance boundary
This ledger records source findings and intended correction. Round 174 may be accepted only after the joint correction is committed and exact-head quality/CI passes. The repository is not proof of deployed source.
