# CF-04 — Forty Review/Fix Rounds 15–54

**Candidate after corrections:** `1.2.0-rc.2`
**Scope:** source code, contracts, persistence, security, privacy, processing, delivery, transfers, lifecycle, operations, REST integration, WordPress scheduling, deterministic packaging, and regression evidence.
**Method:** each round was completed as review → defect correction where required → focused regression check → full-suite regression.

| Round | Review focus | Defect/correction result | Regression evidence |
|---:|---|---|---|
| 15 | Stream writes | Partial/stalled writes made fail-closed through complete-write helpers. | `review-rounds-15-54.php` + lint |
| 16 | Path containment | Canonical containment and traversal/symlink boundaries hardened. | Round 16 gate |
| 17 | Secret redaction | Credential/token/key-like fields expanded in recursive redaction. | Round 17 gate |
| 18 | Private root | Storage root must be absolute and outside public paths. | Round 18 gate |
| 19 | Object I/O | Locks, bounded streaming, and safe deletion verified. | Round 19 gate |
| 20 | Signed claims | Caller-supplied reserved claims can no longer override trusted values. | Round 20 gate + runtime crypto tests |
| 21 | Actor identity | Requested actor must match authenticated identity. | Round 21/44 gates |
| 22 | Domain contracts | Silent contract replacement/conflicting registration blocked. | Round 22 gate |
| 23 | Clinical privacy | Confidential media cannot acquire public audience/CDN rights. | Round 23 gate |
| 24 | Policy envelope | Future-issued policy and non-C1 public CDN are denied. | Round 24 gate |
| 25 | Pagination | Critical scans use complete bounded pagination rather than first-page lists. | Round 25 gate |
| 26 | Persistence identity | Corrupt or mismatched record identities fail closed. | Round 26 gate |
| 27 | Audit chain | Verification follows hash links and supports concurrent persistence. | Round 27/53 gates |
| 28 | Upload replay | Idempotent replay returns the same session but rotates credentials. | Round 28/43 gates |
| 29 | Quotas | Client limit elevation and over-settlement are denied. | Round 29 + FR-005 tests |
| 30 | Multipart expiry | Expired sessions purge parts, clear credentials, and release quota. | Round 30 gate |
| 31 | Multipart providers | Parts retain provider identity; assembly reads the correct provider. | Round 31 gate |
| 32 | MIME/magic | Declared metadata is distrusted and disguised files fail closed. | Round 32 + FR-004 tests |
| 33 | Archive defense | Depth, entry count, ratio, polyglot, and decompression controls verified. | Round 33 + FR-008 tests |
| 34 | Sandbox | Non-root, isolated, ephemeral worker attestation remains mandatory. | Round 34 + FR-011 tests |
| 35 | Job generation | Reprocessing now creates a new generation and cannot reuse stale completed jobs. | Round 35 + targeted repair test |
| 36 | Job leases | Raw lease secrets are not stored; expired leases cannot complete. | Round 36 gate |
| 37 | Derivative MIME | Each derivative has validated explicit MIME in storage, record, manifest, and delivery. | Round 37/46 gates |
| 38 | Targeted repair | Requested derivative is regenerated while non-target derivatives remain active. | Round 38/48 gates |
| 39 | Grant binding | Actor, service, audience, context, session, range, operation, and state are bound. | Round 39 + FR-018 tests |
| 40 | Active manifest | Superseded or non-active derivatives cannot be served. | Round 40 gate |
| 41 | HTTP delivery | Safe MIME, range, disposition, CSP, no-store, and nosniff headers verified. | Round 41 + FR-020/021 tests |
| 42 | Verified transfer | Sender/recipient checks and the 1 GiB cap remain enforced. | Round 42 + CHAT-XFER-001 |
| 43 | Replay credential | Live regression confirms credential rotation. | Round 43 runtime gate |
| 44 | Cross-actor action | Live regression confirms actor mismatch denial. | Round 44 runtime gate |
| 45 | End-to-end pipeline | Encrypted ingest → scan → process → active manifest completes. | Round 45 runtime gate |
| 46 | Manifest lineage | MIME and lineage are present for preview/text/OCR. | Round 46 runtime gate |
| 47 | Scoped holds | Deletion-only holds no longer incorrectly block delivery. | Round 47 runtime gate |
| 48 | Repair isolation | Targeted repair replaces preview only and preserves text/OCR. | Round 48 runtime gate |
| 49 | Financial period | Invalid month and normalized duplicate cost units are rejected. | Round 49 + hardening regression |
| 50 | Metrics | NaN/INF and malformed labels are rejected. | Round 50 runtime gate |
| 51 | Webhook replay | Same event ID with changed body is a conflict, not an idempotent replay. | Round 51 runtime gate |
| 52 | Restore safety | Grant issuance remains blocked until reconciliation and explicit authorization. | Round 52 runtime gate |
| 53 | Restore/audit reopening | Explicit serve authorization reopens delivery and audit chain stays valid. | Round 53 runtime gate |
| 54 | WordPress/release | Background cleanup/processing, disabled default, and deterministic packaging verified. | Round 54 + full quality gate |

## Corrected source areas

The forty-pass cycle corrected or strengthened: stream I/O, storage containment, crypto claim ownership, actor binding, policy/rights invariants, persistence pagination, audit verification, upload replay and expiry, multipart provider affinity, derivative MIME, processing generations, targeted manifest merging, delivery binding, scoped holds, deletion idempotency, provider exit, key rotation, cost validation, repair/rollback, restore pre-serve blocking, chunked REST uploads, background cron execution, webhook replay conflicts, observability validation, tests, and deterministic release evidence.

## Completion boundary

These rounds establish **source-coded, packaged, and automated-QA completion** for candidate `1.2.0-rc.2`. They do not constitute Hostinger staging acceptance, real-provider acceptance, migration/backup/restore/rollback rehearsal in the real environment, browser/accessibility/load/penetration acceptance, Founder sign-off, live deployment, or operational monitoring acceptance.
