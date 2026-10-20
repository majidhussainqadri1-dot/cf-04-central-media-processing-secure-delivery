# CF-04 Round 161

Baseline: c77e213263530b576757fb8d53251ceefd1d9089
Findings: stale pending outbox, absent supersession evidence, reactivation gap, missing regression, policy drift.
Correction: durable supersession, safe reactivation, policy binding and regression.
Acceptance requires exact-head CI.
