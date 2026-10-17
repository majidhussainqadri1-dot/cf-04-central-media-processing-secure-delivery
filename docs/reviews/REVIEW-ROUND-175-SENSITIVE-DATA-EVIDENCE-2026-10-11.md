# CF-04 Round 175 — Sensitive-Data Evidence

Frozen review source: 69467bd12229ddb954e5220e2766c3d0900d6ee3; main: 0294442f0fddd1ca5440d9d5ac992ba80aced972; Draft PR #5. Governing plan: CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1, schema and contract 1.5.0.

Read-only findings frozen before correction: (1) detector adapter invoked without owning-domain authorization; (2) classes and signals unbounded/untyped with possible sensitive-value persistence; (3) detector evidence not source/policy/rights-bound and no post-adapter state check; (4) redaction regions and output hashes inadequately validated; (5) redaction had no post-adapter state/authorization check and no policy/rights binding; (6) adversarial regressions missing.

Joint correction: bounded metadata contracts; authorization before and after provider calls; source identity freshness checks; strict output hashes; lineage-bound evidence; regression integrated into quality gate. No external staging/live/operational acceptance claim. Acceptance pending exact-head CI.
