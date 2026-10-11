# CF-04 Review Round 167 — Rich Track Metadata Contracts (2026-10-11)

Read-only source: `5669ac120528f5c9d3ebc09d13e79d8bd7d0413d`; target main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`. Governing CF-04 v1.1 Future-40 Amended, runtime 1.3.0-rc.1, schema/contract 1.5.0. Complete read-only review preceded correction.

## Frozen findings
1. Arbitrary extra metadata keys could be persisted in rich tracks.
2. Caption provenance source was unrestricted.
3. Hash/locale/reference coercion could emit diagnostics and hash truncation could accept an invalid suffix.
4. Audio-description and sign-language track references admitted untrusted external/path-like references.
5. Smart-preview adapter selection was unbounded arbitrary nested metadata.
6. Negative regression coverage was missing.

## Joint correction
Enforce explicit per-track field allowlists, bounded provenance vocabulary, exact typed SHA-256, typed locale, internal asset/derivative references and bounded preview selection. Add `tests/review-round-167-track-metadata-contracts.php` to the quality gate via `tests/source-integration.py`.

Repository CI is not evidence of staging acceptance, deployment or operational monitoring.
