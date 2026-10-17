# R221 — Fresh forty-scope CF-04 audit: processing/integrity cron fairness (2026-10-15)

## Immutable repository and evidence freeze

- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Initial exact open Draft PR #5 HEAD: `06e0eb28c1904d46e5418e138916682dcf229c6e`.
- Initial repository `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Corrective code + regression HEAD **prior to this report commit**: `1d4c4b3b078fa7bbad155abdba35191ef4fe2973`.
- Source: 15 PHP runtime files plus requirement trace and mandatory CI gate, totaling ~574k fetched characters. Forty separately scoped read-only checks preceded correction. **These are forty distinct source/contract inspections, not forty full end-to-end suite executions or staging deployments.**
- Code candidate: `1.3.0-rc.1`; schema `1.5.0`, contract `1.5.0`; disabled/fail-closed default; PR remains draft and unmerged.

## Source hierarchy and cross-plan alignment

1. Supplied *Three Central Plans Consolidated Governing Master Plan* (Word), technical architecture and canonical owners, including File 17 communication/attachments, File 10 recorded/live media, File 12 original PDFs/reader and File 00 verification. The central plan calls for bounded processing, indexed cursor operations, independent ownership and versioned approval before any cutover.
2. Supplied amended File 12 PDF Library (Word): protected original bytes, canonical rights/reader metadata, encrypted private storage, signed access, lifecycle and restore tests, no unapproved dual-truth.
3. Supplied amended File 08 Worldwide Clinic and File 21 Home/News Feed plans (Word): owner-specific operations, private clinical separation, deliberate handoff of media/provider storage without direct cross-module writes.
4. CF-04 repository `docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md` and current source/test contracts.
5. Companion GitHub `main` branch inspection: File 17 native private transfers report specifies `1073741824` byte maximum, File 21 still has native WordPress media handler, File 11 and File 12 `main` each only README. None proves their deployed state or mutual runtime parity.

**Independent exact standalone amended CF-04 v1.1 Future-40 Word plan is not among the available source files**. Its exact requirement-by-requirement completeness cannot be independently certified from a repository-authored matrix.

## Forty independent scoped checks

| Check | Investigated boundary | Status |
|---|---|---|
| 01 | Plugin boot + default-disabled runtime | Source/guard found; deployed runtime unknown |
| 02 | Schema install, DB indexes | Source exists; exact DB schema unknown |
| 03 | Migration option/schema readiness | Source exists; wp_options and migration not read |
| 04 | Atomic persistence CAS | Source conflict logic exists; real MySQL races untested |
| 05 | Versioned delete and audit hash chain | Source exists; production audit evidence missing |
| 06 | Stable RecordStore ID-keyset pages | Source available; stage SQL performance pending |
| 07 | Processing cron fairness beyond offset ceiling | **Confirmed R220-A: corrected** |
| 08 | Integrity cron changing-order and offset ceiling | **Confirmed R220-B: corrected** |
| 09 | Cron lock acquisition/release | Source and R220 failure-lock regression passed |
| 10 | Independent retention cron failure boundaries | R209 source/CI protections retained |
| 11 | Deletion, revocation and CDN reconciliation hook | Source exists; live queues unverified |
| 12 | File 00 verified identity boundary | Source callback defined; active owner integration unverified |
| 13 | Owner rights and object-version auth | Source DomainRegistry; counterpart signoff missing |
| 14 | Upload policy MIME, rights, part ceiling | Normalization present; exact standalone CF-04 v1.1 text missing |
| 15 | Quota and abuse rate limits | Source present; host-scale concurrent tests missing |
| 16 | Idempotent session and replay | Source present; distributed race acceptance pending |
| 17 | First-vs-second stored upload part digest | R218 safeguard preserved |
| 18 | Part replay parent/actor/storage identity | R218 safeguard preserved |
| 19 | Indexed upload-scoped multipart lookup | R211 safeguard preserved |
| 20 | Part assembly hash/size/EOF liveness | R213 safeguard preserved |
| 21 | Pause, resume, abort and fresh authorization | Source present; browser+API staging pending |
| 22 | Upload expiry cursor and quota release | R208/R209 safeguards preserved |
| 23 | Pre-hash size gate, source-copy equivalence | R214/R216 protections preserved |
| 24 | Tempfile cleanup on copy exception | R216 safeguard preserved |
| 25 | MIME, scanner registry and fail-closed malware | Source present; real scanner unavailable for acceptance |
| 26 | PDF scan, ZIP traversal and decompression limits | Source present; hostile file corpus unrun |
| 27 | ToolRunner isolation/provenance contract | Source present; real isolated worker unaccepted |
| 28 | Worker job leases and retries | Source present; real concurrency and rollback unverified |
| 29 | Derivatives, output validation, manifest | Source present; native owners not accepted |
| 30 | Image, AV and PDF processing pipelines | Source present; approved codecs/providers missing |
| 31 | Authenticated private object encryption | Source present; real storage/KMS unverified |
| 32 | Provider health and versioned keyring | Source present; real provider health unverified |
| 33 | Public CDN gating and invalidation | Source present; purge-time SLO unverified |
| 34 | Scoped signed delivery grants | Source present; browser/service recheck unverified |
| 35 | Action-time File 00/17 native authorizations | Source exists; installed File 17 parity unknown |
| 36 | Byte-range serving and incomplete reads | Source guard present; 1 GiB streaming not live-tested |
| 37 | File 17 canonical recipient/transfer contract | Source present, counterpart integration unapproved |
| 38 | Retention, legal hold, deletion tombstones | Source present; provider deletion drills pending |
| 39 | Provider exit, rotation, backup and restore | Source present; staging rehearsal missing |
| 40 | Future-40, REST canonical ingress, rights dispatch | Source paths present; source plan parity incomplete |

## Proven new defects and fixes — R220

### A: processing backlog starvation from fixed offset ceiling

**Exact code evidence:** `Plugin::cronJobs()` looped through `RecordStore::list('asset',...,500,$offset)`, restarted at offset 0 on every invocation, and stopped after `$scanned<1000000`; the persistence list also clamped offset to 1,000,000. A queued record located past the first million objects could remain unvisited across every processing cron. The existing `processing_inventory_scan_truncated` warning did **not** advance inventory traversal.

**Fix:** bounded (max 2000 records/call) ID-ordered `RecordStore::scan('asset',...,$after)`, persistent `cron_cursor:processing` with exact-ID validation, incremental scan progress and at most five processing candidates. Keeps runtime lock, per-asset fault alerts, and existing processing dispatch. Progress persists between cron invocations and wraps to the beginning at end of keyspace.

### B: integrity sampling uses unstable offset pagination, with the same hard ceiling

**Exact code evidence:** `Plugin::cronIntegrity()` stored only an integer `offset` with `RecordStore::list` sorted by `updated_at DESC`. Updated assets could reorder between runs, and the 1,000,000 offset ceiling could prevent later records ever being checked.

**Fix:** ID-keyset cursor with max 2,000 scanned and 20 selected assets/invocation. Strictly validate stored cursor; corrupted cursor produces `maintenance_cursor_invalid`. Explicit compatibility path migrates existing offset-only `integrity` cursor to ID progress by rescanning from the beginning once instead of treating the old offset as an ID.

### New executable regression / compatibility protection

- `tests/review-round-220-cron-keyset-fairness.php` creates 2,100 asset records. Proves first scan stops after 2,000, next scan reaches a queued/ready asset at position 2,100, correct durable `last_id` and selected count, and safe wrap after end.
- Proves corrupted stored `integrity` cursor fails closed while releasing its runtime lock, and pre-existing offset-only evidence migrates without a schema rewrite.
- Both crons now use `RecordStore::scan` rather than the offset-based `RecordStore::list('asset'...)`.
- Mandatory `tools/quality-check.sh` executes the new regression.
- Existing `tests/review-round-101-runtime-release.php` initially failed because it explicitly required the **removed** offset-based behavior. Updated the legacy assertion to require stronger new ID-keyset processing and integrity semantics.
- Corrected implementation/test/legacy gate HEAD `1d4c4b3b078fa7bbad155abdba35191ef4fe2973` passed all six GitHub Actions jobs (push + pull request, PHP 8.1/8.3/8.4). Direct PHP 8.4 job logs show `REVIEW ROUND 220 CRON KEYSET FAIRNESS: PASS` and `CF-04 QUALITY GATE: PASS`.

**Performance scope:** The synthetic test used 2,100 memory-backed assets, **not one million actual MySQL objects**. Starvation of the former algorithm is proven from the source's hard cap; real provider/database load and scheduling must be separately verified. This improves fairness but does not certify all job/asset scheduling under host load.

## Remaining production and cross-domain blockers

1. Exact deployed plugin binary, active WordPress plugin versions, checksum, active provider configuration, schema version, migration option and runtime logs **not inspected**.
2. The standalone authoritative amended CF-04 master-plan v1.1 manuscript is unavailable for independent full textual clause parity.
3. File 11 and File 12 GitHub main are not code-complete baselines; actual active branch and installed package unknown. File 17 and File 21 have own native paths and require separately approved, live-tested owner/CF-04 contracts, migration parity, dual-read reconciliation, rollback and Founder signoff before transfer of binary responsibility.
4. Staging MySQL query plans and load tests, File 17 real 1GiB streamed transfers, malware corpus and approved scanner, containerized multimedia workers, private storage/KMS/CDN, revocation purge, retention/deletion, real backup restore and full rollback remain acceptance gates.
5. No merge of Draft PR #5, live activation, staging/production upload, data schema migration or unapproved owner cutover was performed.

## Live-first exact-state ledger

| Field | Status |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Corrected Draft PR HEAD before report | `1d4c4b3b078fa7bbad155abdba35191ef4fe2973` |
| Plugin candidate | `1.3.0-rc.1` |
| Source DB schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

This source audit proves two identified pagination defects have a tested repository correction. It does not prove the deployed service contains that correction nor that all forty areas have completed external acceptance. After adding this report, exact-head CI must be checked again.
