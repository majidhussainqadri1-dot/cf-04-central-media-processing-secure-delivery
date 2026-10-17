# Review Round 146 — Future-40 disaster, tier, cost, routing numeric contracts

Baseline exact PR #5 HEAD: `4da4d61c6e6535c11cd52a286462072d639f6dae`. Read-only review of DisasterCostRoutingService and its Round-128/Future-40 tests completed before correction.

## Frozen defects
1. Disaster recovery RPO/RTO cast malformed, negative or noncanonical values to integers; region types were coerced.
2. Tier-access and age metrics cast invalid values or clamped negatives to zero.
3. Invalid rights expiry cast to zero, misclassifying expired or malformed rights as unlimited.
4. Future-40 price and plan quantities used lossy float casts; explicit null values could become defaults.
5. Persisted asset size used a lossy cast, allowing wrong estimates.
6. Currency identity lacked a strict format.
7. Provider routing scores used lossy casts, and malformed requirement/provider shapes could pass silently.

## Unified correction
Canonical bounded numeric parsing, fail-closed persisted rights/size checks, typed region and currency validation, strict route inputs, regression tests for malformed values, and an exact-head quality gate. No source correction was made during the review phase.

## Acceptance boundary
Repository source and matching exact-head CI only. No staging, live, deployed-source, or operational evidence is claimed.

## Verification-only correction
First exact-head CI exposed a stale Round-119 text assertion for direct rights-expiry access. Updated that assertion to require the new fail-closed rights error guard. Round-146 executable regression tests malformed expiry directly. No additional runtime patch was made.
