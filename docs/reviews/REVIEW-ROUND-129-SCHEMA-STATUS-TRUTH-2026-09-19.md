# Review Round 129 — Persistence/schema and repository-status truth — 2026-09-19

Review completed in full before correction.

## Findings
1. The production plugin declares schema 1.5.0, but the persistence layer still used 1.4.0 as its fallback install/readiness identity when the constant is unavailable. That is a stale source-level schema identity.
2. The current runtime-status document still described the superseded data constitution as C4 Financial/Legal and C5 Clinical/High Sensitivity. The current governing central/CF-04 plans use C4 Clinical-Sensitive and C5 Security Secret.

## Correction after review
Persistence fallback identity now matches schema 1.5.0, and runtime status evidence now uses the current C0-C5 constitution. Exact-head regressions prevent both forms of stale truth from returning.

Durable CAS, bounded scans, physical schema/index readiness, audit-chain integrity and fail-closed database reads were also reviewed in this pass.
