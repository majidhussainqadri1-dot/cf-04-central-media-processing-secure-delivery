# R215: Forty distinct CF-04 cross-plan code and safety inspections — 2026-10-14

## Scope, source truth and evidence freeze

This review follows R212. The unchanged starting Draft PR #5 code was `4cf680453e3e833a92e055ea3037fedbf9cef82f`; the default `main` branch was `0294442f0fddd1ca5440d9d5ac992ba80aced972`. All 15 current PHP runtime classes (~548k source characters) were independently reread against the available Three Central Plans Consolidated Governing Master Plan, the repository's CF-04 functional/central/future requirements matrix and companion-source repository checks.

These were **forty distinct, bounded source/contract inspections**, not forty whole-installation regressions and not forty claims of production runtime proof. An identifier/function being present alone does not certify functional correctness. Detailed corrective evidence below is from executable adversarial regressions.

Source governing commitments retained: WordPress and native-domain canonical ownership, external workers/CDN only through approved adapters, fail-closed security and rights, File 00 verified identity, File 17 native private transfers up to **1,073,741,824 bytes**, File 10 recorded/live media ownership, File 12 document metadata and rights, File 19 event notifications, File 20 unified shell, File 21 feed, File 22 composer, and File 24 assurance.

**Not independently available**: the exact CF-04 Conditional Master Plan v1.1 Future-40 Amended full standalone Word manuscript. The in-repository requirements matrix is trace evidence rather than independent proof of exact-plan textual parity.

## Forty specific inspected boundaries

| # | Technical checkpoint | Source evidence and conclusion |
|---|---|---|
| 01 | Plugin runtime disabled/initialization | RuntimeGuard and version gate; exact deployed plugin unverified |
| 02 | Required providers and runtime guards | Fail-closed adapters; live provider readiness not measured |
| 03 | Schema/option migration readiness | Schema::install/ready and `scm_schema_version`; DB truth unverified |
| 04 | Records composite key/index and pagination | `(record_type,id)` keyset access; actual query plans unmeasured |
| 05 | Atomic CAS writes/deletes | `RecordStore::put/delete` expected versions; concurrency staging pending |
| 06 | Scoped indexed batch retrieval | R211 `RecordStore::getMany`; no broad 100k scan |
| 07 | Upload credential and actor binding | Upload credential issue/authentication; live File 00 adapter pending |
| 08 | Upload authorization and owner version | `Auth::verifiedUser`, owner decision; cross-module handshake pending |
| 09 | Upload-policy size, MIME, part limits | Normalized and numerically bounded policy; owner profile fit pending |
| 10 | Quota reservation/settlement | Quota source states; real concurrent billing/storage reconciler pending |
| 11 | Rate and burst throttles | `RateLimiter`; distributed abuse/load tests absent |
| 12 | Idempotency claim and replay | Claim/complete fencing; external race testing pending |
| 13 | Per-part SHA and duplicate protection | Part checksum checks; real provider retries pending |
| 14 | Per-upload part enumeration | R211 scoped hash-ID batches + parent/ordinal check; staging DB performance pending |
| 15 | Upload-part stream liveness | **R213 defect corrected:** empty reads before EOF previously busy-looped |
| 16 | Assembly gap, full hash, byte limit | Complete/gap checks present; 1 GiB end-to-end staging pending |
| 17 | Session pause/resume/abort | Authenticated state transitions; user/device tests pending |
| 18 | Expired upload/quota/part janitor | Prior R205/R208/R209 source protections retained |
| 19 | Source MIME, magic, container inspection | `Validator::inspectStream`; **R214 oversized hash defect corrected** |
| 20 | PDF streaming examination | **R213 defect corrected:** empty before-EOF PDF reads previously busy-looped |
| 21 | ZIP bomb/archive constraints | `ArchiveInspector`; full malicious corpus not run on approved tools |
| 22 | Required malware scan dispatch | ScannerRegistry::scan consumes policy's `required_scans`; scanner adapters unaccepted |
| 23 | Isolated tool runner | `ToolRunner` approved adapter contract; host sandbox unverified |
| 24 | Image derivative processing | ImagePipeline and derivative owner; real codec workers unverified |
| 25 | AV transcode/a11y derivatives | AvPipeline and derivative owner; real runner and subtitle quality pending |
| 26 | PDF/document derivative processing | DocumentPipeline; large library staging acceptance pending |
| 27 | Jobs and dead letter recovery | JobService lease, retry, dead-letter machinery; soak/race pending |
| 28 | Manifest lineage and generation | Manifest switch and processing generation; owner cutover parity unproven |
| 29 | Private authenticated chunk encryption | Crypto/Keyring and AES authenticated storage; real key custody unverified |
| 30 | Storage provider health/capabilities | ProviderRegistry fail-closed contracts; actual approved provider unaccepted |
| 31 | Grant signing/expiry/scope | Signed issue/grant state; browser/API hand-off pending |
| 32 | Action-time owner authorization | DeliveryService::serve; live File 00/17/12 assertions pending |
| 33 | Byte-range private serving | Range and incomplete-read guards; real 1 GiB streaming pending |
| 34 | C0-only public CDN and revocation | CDN policy/purge source gates; measured purge SLO pending |
| 35 | File 17 transfer native recipient binding | TransferService version/recipient checks; File 17 dual-read/cutover unaccepted |
| 36 | File 00 identity/native owners | Auth and DomainRegistry with explicit adapters; live callback verification pending |
| 37 | Legal hold, retention and deletion | Core lifecycle services; backup/provider deletion acceptance pending |
| 38 | Revocation/reconciliation event dispatch | RightsRevocationService and durable cron cursors; File 19/26 consumer acks unaccepted |
| 39 | Provider exit, key rotation and restore | Source state machines; operator rehearsal and recovery-point objectives pending |
| 40 | Future-40, versioned REST and cron | Source feature registry, canonical ingress and scheduled hooks; independent external acceptance pending |

### Inspection 22 follow-up

The string `required_scans` appears in the policy normalized by `class-scm-contracts.php`, not necessarily in ScannerRegistry itself. `ScannerRegistry::scan($stream,array $required,array $context)` accepts this required-scanner set; absent exact substring in one file is **not evidence of omitted malware enforcement**. This false-positive source-marker concern was reexamined, not patched.

## Proven new defect R213 — stalled streams

**Root cause.** `PartStore::assemble()` and the PDF scan loop used `if($chunk==='')continue` even while `feof($resource)===false`; a provider or stream wrapper returning repeated empty chunks could keep the worker spinning.

**Correction.** Explicit `stream_stalled` exception for `''` before EOF in both routines, consistent with existing `Utils::streamHash` and local object-storage guards.

**Test.** `tests/review-round-213-stalled-media-source.php`: adversarial readable stream wrapper that never reaches EOF and returns empty chunks; checks both private-part assembly and PDF scan reject promptly. Added to mandatory `tools/quality-check.sh`. Exact code HEAD `5d7504e633273c4519591291b3bba139566d2a8b` passed push + PR CI with PHP 8.1, 8.3 and 8.4. Job logs explicitly show `REVIEW ROUND 213 STALLED MEDIA SOURCE: PASS`.

## Proven new defect R214 — unbounded source hash before policy rejection

**Root cause.** `Validator::inspectStream()` first called `Utils::streamHash($stream)` **without a maximum**, then checked against `policy['max_size_bytes']`. An oversized stream could be fully read and hashed before returning HTTP 413; other validation steps were correctly blocked, but time/I/O consumed needlessly.

**Correction.** Validate the policy size canonically and pass it as the upper bound to `Utils::streamHash` before reading the whole file. Translate `stream_size_exceeded` to the existing caller-facing `source_size_invalid` code and 413 status. No schema, API contract or default runtime enablement change.

**Test.** `tests/review-round-214-bounded-source-hash.php` provides an 8 MiB counting stream with a 1 KiB maximum, expects immediate 413-coded rejection after no more than a 1 MiB input chunk; rejects malformed declared maximum; required quality gate includes this test. Check CI at the **final exact report commit** before claiming successful closure.

## Cross-domain source reality

- File 17 `main` has its own native encrypted transfer flow with exactly 1 GiB/files, 1–16 MiB chunks, max 1024 parts, and an owner-side recipient policy. Its completion report explicitly conditions production acceptance on File 00/02/19/20/24/25/CF-04 integration. This existing owner source is not evidence that CF-04 has actually replaced its binary storage.
- File 21 `main` still has a WordPress-media `includes/class-media-handler.php` path and File 10 has its own video/media implementation. No direct source-to-deployment proof of universal CF-04 canonical cutover exists.
- File 11 and File 12 **GitHub `main`** trees each still contain only README.md; other branches or deployed files may exist. Do not infer their live status from these main trees.
- Source aliases and per-domain callbacks require live/staging exact-owner contract testing rather than automatic GitHub changes that would create duplicate truth.

## Remaining non-source gates

1. Exact deployed artifact/version/checksum, active plugins/dependencies, DB/schema/migration and operational logs must be frozen before live defect diagnosis or cutover.
2. Missing exact standalone amended CF-04 v1.1 manuscript and authoritative source for 1:1 requirement parity.
3. Real File 00/17/10/12/21/22 owner adapters and native File 19/24 governance acceptance.
4. Hostinger staging deployment, approved malware/worker/private-storage/CDN/KMS providers, 1 GiB transfer trials, rights expiry/deletion and restore/rollback drills.
5. Full MySQL-scale, race, threat, load, browser/device/accessibility and independent security tests, Founder approvals.

## Release-reality ledger

| Field | Status at start of R215 |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 initial HEAD | `4cf680453e3e833a92e055ea3037fedbf9cef82f` |
| Corrected implementation + tests before report | `c2186e72e2ffbaf40a197af15e698c6fe9889a5a` |
| Plugin candidate | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Important:** Do not mark final CI `success` until both push and PR exact report HEAD runs have completed green. Do not merge Draft PR #5, switch on CF-04 in production, or call the original live defect resolved based only on synthetic tests.
