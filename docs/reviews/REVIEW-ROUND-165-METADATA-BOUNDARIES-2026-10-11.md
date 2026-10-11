# CF-04 Review Round 165 — Future-40 Metadata Boundaries (2026-10-11)

## Exact repository truth and review discipline

Read-only review completed before any correction. Frozen source HEAD: `a1974358028d4c0be69b3153ddd8e409892ae74d`; target `main`: `0294442f0fddd1ca5440d9d5ac992ba80aced972`; PR #5 is Draft and unmerged. Governing repository reference: CF-04 v1.1 Future-40 Amended (2026-09-16); runtime 1.3.0-rc.1, schema and contract 1.5.0. Original external master plan not independently available for full parity comparison.

## Frozen read-only findings

1. `smartPreview` invoked the future_014 adapter before runtime, actor and owner-domain authorization.
2. `putTrack` checked actor identity but did not require owning-domain reprocess approval. Preview adapter results could be persisted against a changed asset snapshot.
3. `accessibilityManifest` and `AccessibilityMetadataService::forAsset` read metadata without runtime, authenticated-user and owning-domain authorization gates.
4. Both projections included revoked or stale metadata after policy, rights or owner object version changed.
5. Manifest evidence hash included volatile generation time and did not explicitly bind current policy and rights hashes.
6. Negative/regression coverage for these cases was absent.

## Joint correction and evidence

- Authorize before adapter execution and before track persistence; recheck asset identity after external work.
- Require authenticated owner-approved reads and filter records to current approved/active policy-rights-version envelope.
- Bind manifest to policy and rights; hash deterministic projection payload without `generated_at`.
- Add `tests/review-round-165-metadata-boundaries.php` to the full quality gate.
- Keep external staging, provider, live deployment and operations gates separate. A repository-green check alone cannot establish them.

## Scale note

Cross-asset record scans remain capped at 100000 records and need an indexed query design at larger volumes. This source-level review does not assert actual production scale or an operational failure.

## Acceptance

Round 165 remains pending until the complete joint correction is committed and exact-HEAD quality/CI passes.
