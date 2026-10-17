# CF-04 — R212 twenty-pass cross-plan source audit, 2026-10-14

## Evidence freeze and authorization

- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Frozen initial `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Frozen pre-review candidate Draft PR #5 HEAD: `12820537aa5d7bed35ba816158df2528483abad0`.
- Fixed and quality-tested source HEAD **before this audit record**: `0ce78e2d882b404fa79f7a48c0d5582de1eae680`.
- PR #5 remains open, draft, and unmerged. Candidate `1.3.0-rc.1`; source schema/contract `1.5.0/1.5.0`; runtime default disabled and fail-closed.
- Source checks and regression tests do **not** prove production deployment, DB migration state or staging acceptance.

## Governing source crosswalk

Compared the available **Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan (2026)** with the CF-04 source matrix, the full fifteen-class CF-04 source and relevant individual plans available locally:
- Central: WordPress canonical owners, separate approved media processing/storage services, no duplicate truth store; File 17 private message/transfer ownership and 1 GiB file limit, File 00 identity, File 10 recorded/live video, File 12 PDF library, File 19 events, File 20 shell, File 21 feed, File 22 composer, File 24 assurance.
- File 12 PDF Library amended plan: restricted originals private/encrypted; short-lived signed access, owner authorization at action time, native document metadata and lifecycle, backup/restore proof rather than merely having backup files.
- File 08 Clinic plan: private patient intake remains a dedicated encrypted clinical domain; appointment owners provide context, not ownership of calls/messages/binaries.
- CF-04 source `docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md`: enumerates functional 001–033, central 01–10, native journeys 01–06, future 001–040, and deferred external acceptance gates.
- **Limitation:** Independent final amended CF-04 v1.1 Future-40 Word source was not accessible among the current source attachments; a clause-perfect comparison against that particular manuscript was not possible. The source matrix is not proof of such parity.

## Twenty distinct scoped review passes

These are twenty substantive targeted source-and-contract inspections across the fifteen-class source snapshot, **not twenty independently executed full end-to-end test suites or a live runtime investigation**.

| Pass | Boundary and evidence checked | Outcome |
|---|---|---|
| 01 | Plugin start, disabled-default runtime contract | Source guard exists; deployed artifact and activation remain unverified |
| 02 | Schema install, index/readiness, versioned DB state | Source install/ready guards exist; production DB and migrations unknown |
| 03 | Atomic record identity, persistent compare-and-swap and deletion | Source CAS checks present; live database concurrency unverified |
| 04 | Upload PartStore enumeration and access pattern | **Confirmed omission R211:** full `RecordStore::all('upload_part',...,100000)` used before per-upload filtering |
| 05 | Resumable upload part bounds, checksum and size enforcement | Source guards exist; real provider/network stress unverified |
| 06 | Expired temporary upload purge and quota settlement | Prior R205/R208 protections retained; R211 removes part-scan bottleneck |
| 07 | Rate limits, quotas, idempotency/replay | Source controls present; external abuse/stress evaluation unverified |
| 08 | MIME/container validation, malware and archive/polyglot detection | Source-gated providers present; real scanner/provider acceptance pending |
| 09 | Image/audio/video/PDF processing and sandbox interfaces | Source pipeline exists; real isolated worker and tool versions pending |
| 10 | Derivative provenance and owner/object version lineage | Source derivatives exist; exact binary lineage in real storage not accepted |
| 11 | Async job leases, retry, dead letter and fair scheduling | Source queue present; operational cron/worker throughput pending |
| 12 | Encrypted private object storage, provider/keyring abstraction | Source crypto/storage present; independent KMS/provider acceptance pending |
| 13 | File 00 identity and canonical owner-domain authorization | Registry and authorization code present; counterpart integration unverified |
| 14 | Signed scoped grants, private range/stream delivery | Source path present; device/browser/edge checks pending |
| 15 | File 17 1 GiB transfer, message/revocation boundaries | Native transfer binding present; no proof of accepted counterpart callbacks |
| 16 | C0 public CDN publication versus restricted classes | Source policy present; real CDN purging and revocation SLO pending |
| 17 | Retention, legal hold, deletion propagation | Source state machinery present; live provider/archive deletion unverified |
| 18 | Provider exit, storage restore, key rotation | Source flows present; external migration/restore/rollback rehearsal absent |
| 19 | Cross-plan rights reconciliation, dispatch and cursor behavior | Previous R205–R209 fixes present; native owners' event consumption unverified |
| 20 | REST canonical ingress, Future-40 and required CI | R207–R211 automated checks present; exact amended Future-40 manuscript parity unverified |

## Confirmed source defect R211 and root cause

`PartStore::list($uploadId)` previously called `RecordStore::all('upload_part',0,null,100000)` and **then** filtered rows for one upload. `RecordStore::all` deliberately raises `record_scan_limit` when more than 100,000 rows exist. Thus part listing, assembly, abort/completion purge and R205/R208 expired-upload cleanup could fail for one upload because *unrelated users' parts* exceeded the global ceiling.

### Corrective changes

1. `RecordStore::getMany($type,$ids)`: bounded (250 identifiers), exact-identity validated batched lookup through the pre-existing `(record_type,id)` primary key, with production SQL `WHERE record_type=? AND id IN (...)`, and test-memory equivalent. No table/index/version/migration modification.
2. `PartStore::list($uploadId)`: derives only `sha256(uploadId|partNumber)` for up to the session's validated `max_upload_parts` (policy limit maximum 20,000). Reads batches in ascending part order; checks row parent identity, ordinal, record type, key, version; rejects contamination. For old synthetic/unversioned-policy records with missing policy, a bounded 20,000-ordinal fallback preserves cleanup without global scans.
3. `tests/review-round-211-scoped-upload-part-lookup.php`: asserts own-part ordering, 2,400 unrelated parts excluded, batch ceiling/canonical ID rejection, own objects and metadata deleted, unrelated rows preserved, forged parent rejected, and former global scan removed.
4. `tools/quality-check.sh`: adds mandatory new regression alongside prior R207–R209 tests.

**Exact-head evidence:** Push and PR workflows at `0ce78e2d882b404fa79f7a48c0d5582de1eae680` passed on PHP 8.1, 8.3, 8.4. Logs include `REVIEW ROUND 211 SCOPED PART LOOKUP: PASS` and `CF-04 QUALITY GATE: PASS`.

### Remaining evidence limits, not falsely closed

- The CI test mode uses synthetic memory-backed records and a local test object store; production `$wpdb` behavior, database optimizer plans and indexes' real performance have not been observed. The new SQL read path requires staging MySQL QA and load/soak profiling with large volumes. No approval has been assumed.
- File 11 and File 12's **default main** trees each contained only `README.md` in today's public GitHub tree check. This is source-main evidence alone; it does not prove other branches, installed plugins or live owners are absent. Their native contracts must be checked against exact deployed artifacts before cross-module acceptance.
- File 17 `main` and File 21 `main` have substantial PHP source trees, but no deployed-version parity proof, approved runtime adapters or File 17 owner acceptance is on record in this review.
- Signed grant revocation, real provider malware processing, 1 GiB streaming, CDN purge, private clinical data boundaries, backup restore, migration/rollback, usability, accessibility, independent security review, penetration/load testing and Founder-controlled release approval remain distinct gates.

## Final state required by live-first discipline

| Reality | Value |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 corrective source before this report | `0ce78e2d882b404fa79f7a48c0d5582de1eae680` |
| Candidate plugin version | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

After this report commit, verify its **own exact HEAD** push + PR quality outcomes; the earlier green results are not evidence for a changed HEAD. Do not mark live incident resolved, merge the draft PR or enable runtime solely because source tests are green.
