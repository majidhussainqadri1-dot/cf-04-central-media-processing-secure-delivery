# CF-04 Round 181 — Offline Grant Integrity

Frozen read-only source 25f4bf1c6a3464dfd2df8afb83794476276e5a29; main 0294442f0fddd1ca5440d9d5ac992ba80aced972; Draft PR #5. Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1, schema/contract 1.5.0.

Findings: issuance silently clamped out-of-contract TTL; owning-domain callback could mutate source before token issuance; persisted grant lacked source/policy/rights/purpose binding; consume checks did not compare record identity and all claims to current asset; no post-authorization source freshness check; negative regressions missing.

Joint correction: strict TTL, fresh source verification, complete grant/claim/record identity binding, consume-time authorization freshness and regression in full quality gate. No staging/live/operational claim. Round acceptance requires exact-head CI.
