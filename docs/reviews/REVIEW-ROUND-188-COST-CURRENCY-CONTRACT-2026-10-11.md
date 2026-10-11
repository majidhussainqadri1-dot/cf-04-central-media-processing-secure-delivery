# CF-04 Review Round 188 — Cost Estimate Contract and Currency Truth

## Frozen source and plan identity
- Base: PR #5 `codex/cf04-three-plan-complete-1.2.0` at `923329a4f6031997f125e19acf2f69569527c0a9`.
- Target: `main` at `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Governing reference: CF-04 v1.1 Future-40 Amended, runtime 1.3.0-rc.1, schema/contract 1.5.0; independent full-plan comparison pending.
- Scope: CF04-FUT-035, DisasterCostRoutingService::costEstimate, current tests/future40.php, round 128 and 146 numeric contracts, quality-check.sh.

## Read-only review completed before changes
Five defects were frozen: (1) unknown price keys silently ignored; (2) unknown plan keys silently ignored; (3) missing currency silently defaults to USD; (4) lower/mixed-case currency coerced instead of rejected; (5) absent targeted negative regressions.

## Joint correction
- Fail closed on unknown provider-price or plan-quantity keys.
- Require explicit three-letter uppercase currency without coercion or default.
- Preserve valid USD estimates and existing decimal bounds.
- Add dedicated adversarial tests and include them in the full quality gate.

## Acceptance
A new exact-head quality/CI success is required before this round is accepted. Repository QA does not prove staging, deployed code, DB migration, or operational acceptance.

Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
