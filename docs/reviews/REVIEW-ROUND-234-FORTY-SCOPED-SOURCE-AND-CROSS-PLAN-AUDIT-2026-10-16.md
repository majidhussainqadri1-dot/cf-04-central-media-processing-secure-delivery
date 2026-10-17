# R234 — Forty new scoped CF-04 source/cross-plan checks, critical storage reference safeguards

**Date:** 2026-10-16  
**Repository:** `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`  
**Branch:** `codex/cf04-three-plan-complete-1.2.0` (Draft PR #5, open/unmerged)

## Immutable source freeze

- Baseline `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Starting corrective PR HEAD: `2970237959026c68f1db41c2f0ad3b0b5d26773e`.
- Final code+tests HEAD **before this report**: `de0aa19fd3c75df770523498d157e4aea5a6cb1f`.
- Candidate plugin: `1.3.0-rc.1`, source schema/contract `1.5.0 / 1.5.0`, runtime off by default, fail closed.
- All fifteen current PHP runtime classes, approximately **559,347** source characters, inspected under **forty different source and contract checkpoints**. These are not forty separately executed full end-to-end suites, staging installations, live tests or independent complete security audits.
- Full latest amended CF-04 standalone v1.1 Future-40 Word master manuscript is not independently available in mounted/project files. Repository requirement matrices cannot independently prove one-to-one authorial plan parity.

## Original controlling documents and companion realities

Reviewed original mounted Word sources:
- `Sabri-Social-Homeopathy-Platform-Three-Central-Plans-Consolidated-Governing-Master-Plan-2026۔۔۔4۔۔۔۔۔.docx`: canonical owner controls (File 00 identity; File 10 video; File 12 PDF metadata/reader; File 17 native messaging/attachments; File 20 shell; File 21 feed); signed, rights-bound access, auditable provider separation and explicit change control for cross-owner transfers.
- `12-File-12-PDF-Library-and-Digital-Reading-Complete-Master-Plan-2026-v1.1-Future-24-Amended-2026-08-11 (1).docx`: protected originals, legal holds, retention, signed access and deletion propagation; explicit DoD says staged/live code requires separate evidence.
- `21-Sabri-Complete-Home-and-News-Feed-Final-Encyclopedic-Master-Plan-2026-NG30-Amended.docx`: File 21 canonical publication ownership, auditable deletion and versioned deployment/cutover requirements.

Companion GitHub **main** source-tree snapshots observed: File 00 48 PHP, File 10 11 PHP, File 17 175 PHP with native transfer files, File 21 302 PHP with native media handler, File 22 117 PHP, File 20 86 PHP, File 11 and File 12 **main** README-only (zero PHP). These facts **do not prove** live or staging deployed artifacts, other branches or actual approved runtime adapters.

## Forty individually inspected source boundaries

| Check | Boundary | Evidence / limitation |
|---|---|---|
| 01 | Default-disabled runtime / bootstrap | Guard inspected; actual deployed activation unknown |
| 02 | WordPress Cron registration | Source hooks inspected; live execution not proven |
| 03 | Schema/version migration | Source 1.5.0; DB/wp_options unknown |
| 04 | Record primary key and indexed lookup | Composite PK in source; MySQL EXPLAIN pending |
| 05 | Atomic CAS record updates | Source version conflicts retained |
| 06 | Versioned deletion | Delete/CAS guards retained |
| 07 | ID-keyset scan + validation | RecordStore::scan has bounded pages |
| 08 | Direct ID batch lookup | RecordStore::getMany caps batch |
| 09 | Audit event hash chain | Source present; live durability unknown |
| 10 | Private at-rest encryption | Source present; real KMS/provider unverified |
| 11 | Storage path containment | Public-path and symlink guards present |
| 12 | Provider/keyring identity, health | Source interfaces present; real host unaccepted |
| 13 | C0 CDN/secure purge owner boundaries | Source checks present; measured purge SLO pending |
| 14 | Upload credential and actor binding | Source checks present |
| 15 | File00 verified identity | Source adapter; live owner contract unaccepted |
| 16 | Owner object version authority | DomainRegistry checks retained |
| 17 | MIME/consent/rights policy | Source checks present |
| 18 | Quota/throttle | Source controls present; load untested |
| 19 | Idempotent upload/resume/replay | Claim fences present |
| 20 | Part two-pass integrity | R218 regression retained |
| 21 | Scoped multipart ID-batch | R211 regression retained |
| 22 | Full-hash assembly/stream stalls | R213 safeguard retained |
| 23 | Expired uploads/quota janitor | R205/R208 protections retained |
| 24 | Mutable source validation | R214/R216 protections retained |
| 25 | Malware scanner/archive constraints | Source fail-close; real engines unapproved |
| 26 | PDF structural checks and EOF | Source checks retained |
| 27 | Image/AV/PDF derivative worker contracts | Source present; isolation acceptance pending |
| 28 | Job lease/recovery | Source and CI; real soak pending |
| 29 | Derivative manifest atomicity | Source guards present |
| 30 | Grant issue/recheck AND revoke all | **R233**: global 100k grant list replaced with active-keyset pages |
| 31 | CDN mappings/immutable indexed lookup | R229 retained |
| 32 | File17 recipient authorization | Source adapter; counterpart parity unknown |
| 33 | Legal Hold, retention, deletion | **R232**: legal holds global 100k list replaced by bounded pages |
| 34 | Deletion cursor/backup ledger | R227 retained |
| 35 | Deferred rotation cleanup cursor | R228 retained |
| 36 | Provider Exit source-drain witness | **R231-A**: full-table 100k/200k read replaced with paged fail-closed witness |
| 37 | Rotation source/destination live refs | **R231-B**: full-table 100k/200k read replaced with paged witness |
| 38 | Provider-exit plan and rotations | Still global plan/group inventory and packaging gate; not accepted at scale |
| 39 | Rights/outbox dispatch | R223/R224 regressions retained |
| 40 | Future40, REST, CI, integration crosswalk | Source matrix present; amended CF04 v1.1 manuscript absent |

## Four newly proven coding omissions and tested corrections

### R231-A — Provider-exit cutover can fail on global inventory size

**Root cause:** `ProviderExitService::assertSourceDrained()` used two global `RecordStore::all` reads, capped at 100,000 assets and 200,000 derivatives. Even when all objects were migrated correctly, a high-cardinality unrelated inventory caused an error before confirming the source provider was drained. Materializing all mappings also increased PHP memory pressure.

**Fix:** Shared `StorageReferenceWitness::first(provider, optional object key)` checks canonical asset then derivative records through 250-row immutable-ID keyset pages, returning the first live reference and preserving fail-closed DB read errors. A single live witness blocks cutover; a complete absence check is needed before activation. Error context explicitly reports `remaining_count_at_least`, not a fabricated exact global count. No schema version change.

### R231-B — Key-rotation reference safety can fail before checking old/new ciphertext

**Root cause:** `KeyRotationService::liveReferences()` used the same global 100,000/200,000 materializations before checking whether old ciphertext still had a reference. This could obstruct cleanup/rollback at scale or lead operators to bypass the gate.

**Fix:** Reuse the paged `StorageReferenceWitness`. Do not delete any old ciphertext when a matching live asset or derivative witness exists. Wrong object-key identity cannot be accepted. Three existing call sites use existence, not an exhaustive ref inventory. The key-rotation drift exception reports a proven lower bound rather than implying an exact count.

### R232 — Legal hold checking can block on unrelated hold inventory

**Root cause:** `LegalHoldService::active()` required `RecordStore::all('hold',0,null,100000)` before filtering to a specific asset and operation. An unrelated historic hold backlog could stop an asset's legal-hold decision. Since this is a privacy/safety boundary, silently skipping the check is forbidden.

**Fix:** Enumerate all hold IDs in 250-row pages, preserving canonical record validation, active/released/expiry filtering, operation scope and `asset_on_hold` fail-closed exception.

### R233 — Global grant inventory prevents targeted revocation

**Root cause:** `DeliveryService::revokeForAsset()` materialized all historic grants to find active grants for one asset, with a hard 100,000 cap.

**Fix:** Scan 250-row keyset pages filtered by `status=active`, preserve asset match, CAS retry/conflict checking, CDN purge and idempotent revocation. An unrelated active grant must not be affected.

## New mandatory regressions and actual CI results

- `tests/review-round-231-storage-reference-witness.php`: 2,520 asset rows plus 2,520 derivative rows; active live reference at late IDs, derivative-only witness, different ciphertext denial, proof of drained source, malformed provider/key rejection. **PASS**.
- `tests/review-round-232-hold-keyset-enforcement.php`: 2,530 holds, late legal hold blocks deletion, released holds cease to block, corrupt asset identity still fails closed. **PASS**.
- `tests/review-round-233-delivery-grant-keyset.php`: 2,530 grant rows, late active grant revoked, unrelated active grant untouched, repeat idempotent. **PASS**.
- All three are included in `tools/quality-check.sh` and the PHP 8.1/8.3/8.4 GitHub Actions matrix.
- Two intermediate full CI runs failed solely in R231 source-inspection assertions due to PHP double-quoted interpolation of `$type`, `$after`, and `$record`; all R231 functional assertions were already green. Replaced with literal, noninterpolating source assertions; never disabled any production security check.
- **Final code+test HEAD** `de0aa19fd3c75df770523498d157e4aea5a6cb1f`: exact-head Push and Pull Request matrix both **success**, 3/3 PHP versions each, 6/6 jobs total. PHP 8.4 logs explicitly include `REVIEW ROUND 231 ... PASS`, `232 ... PASS`, `233 ... PASS`, and `CF-04 QUALITY GATE: PASS`.
- **Important:** this audit report itself changes the branch HEAD. Verify its new exact-head Push/PR matrix before claiming 6/6 success at report HEAD.

## Remaining code limitations and deployment blockers

1. Large `ProviderExitService::plan()` creates full in-memory items and still scans `RecordStore::all('asset',...,100000)` and `all('derivative',...,200000)`; `KeyRotationService::rotateAll()`, current-envelope reconciliation, `RetentionService::run()`, provider reference grouping and some dashboards/cost lists still have whole-record inventory limits. These are **not** fully corrected by R231–R233. A scalable, migrated plan/job representation needs design and staging MySQL approval, not an indiscriminate limit increase.
2. The paged witness is memory bounded and accurate for presence/absence, but absent a suitable provider/object-key secondary index it can still read many pages. Staging `EXPLAIN`, cardinality/load tests and index/migration signoff remain.
3. Exact deployed plugin version/package/checksum, active dependencies/providers, actual `wp_options`, database version/schema, migration/deferred-upgrade state and runtime logs were not accessed.
4. File 00/17/10/12/21/22 integration contracts, approved owner callbacks, native binaries, real 1GiB transfer workflow, CDN purge, provider key custody, real malware scanner, sandbox, immutable backup/restore, GDPR/retention/legal-hold, accessibility and independent security tests are not accepted by this source review.
5. Missing independent amended CF-04 Word plan prevents a final clause-complete implementation verdict. Keep PR #5 draft and runtime disabled; **no live or staging deployment was performed**.

## Exact-deployed-state ledger

| Required evidence | Actual state |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR code+test HEAD before this report | `de0aa19fd3c75df770523498d157e4aea5a6cb1f` |
| Candidate plugin version | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Green source tests are not evidence of staging approval, deployment parity, full cross-plan completeness, nor production-resolution of a live incident.
