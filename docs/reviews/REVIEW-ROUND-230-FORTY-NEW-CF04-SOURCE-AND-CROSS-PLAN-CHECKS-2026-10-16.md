# R230 — Forty new CF-04 source/cross-plan checks and critical inventory fixes

**Date:** 2026-10-16. **Repository:** `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`. **Branch:** `codex/cf04-three-plan-complete-1.2.0` (Draft PR #5).

## Exact source-state freeze

- Initial candidate HEAD: `637b7edf8547c0a9b988e68b1d875b7bfb84d383`.
- Initial default `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- New code/test HEAD *before this report*: `38c64df188c8335a83fdcb26b0be282e989a939b`.
- Candidate plugin version `1.3.0-rc.1`, source schema/contract `1.5.0/1.5.0`, runtime default disabled. None is evidence of currently deployed plugin, actual DB migration, or activation.
- All fifteen PHP runtime classes (~555,731 source characters) were inspected in **forty distinct source/contract checkpoints**, using available original governing Word plans plus the repository requirements matrix and companion GitHub trees. This was *not* forty standalone full end-to-end installations or forty independent complete CI executions.

## Governing plans and companion-source comparison

- The **Three Central Plans Consolidated Governing Master Plan** defines WordPress as canonical owner/contract host and approved separate media processing, encrypted storage/CDN, queue/worker services rather than an unbounded PHP request; owner-specific responsibilities must not silently change.
- File 12's amended Word plan requires encrypted restricted binaries, signed time-limited delivery, privacy/residency separation, deletion/cache/provider propagation, tamper-evident audit, measured retention and real backup/restore proof.
- File 21 amended Word plan requires retention/deletion tombstones, ownership of post/feed records, approval-controlled changes and dependent CDN/cache propagation. File 08 explicitly separates clinical/private case data from general media services.
- 2026-10-16 GitHub **main** source tree checks: File 00 has 48 PHP files, File 10 11, File 17 175, File 21 302, File 22 117, File 20 86; File 11 and File 12 **main** each have only README (0 PHP). **This does not prove deployed runtime or other branches lack code.**
- The exact *standalone amended CF-04 v1.1 Future-40 master Word plan* was not present among mounted attachments. Source requirement matrix entries cannot be accepted as an independently verified 1:1 comparison with that missing manuscript.

## All forty distinct scoped inspections

| # | Source/contract question | Outcome |
|---|---|---|
| 01 | Plugin boot/runtime disabled default | Bootstrap guarded; deployed parity unknown |
| 02 | DB schema version and install | Source present; deployed DB unverified |
| 03 | Composite record primary key | Source `(record_type,id)` index present |
| 04 | Record create/update CAS | Source conflict checks retained |
| 05 | Record deletion CAS | Source checks retained |
| 06 | Generic bounded ID-keyset reader | RecordStore::scan present |
| 07 | Primary-key batch lookups | RecordStore::getMany present |
| 08 | Processing cron fairness | R220/R225 fixes retained |
| 09 | Integrity cron fairness | R220/R225 fixes retained |
| 10 | Deletion cron failure isolation | R222 task boundaries retained |
| 11 | Rights expiry ID cursor | R224 retained |
| 12 | Revocation dispatch ID cursor | R223 retained |
| 13 | Pending CDN purge 100k inventory | **Defect R229-A fixed**: pending-only keyset |
| 14 | Per-asset CDN purge 100k inventory | **Defect R229-B fixed**: bounded page processing |
| 15 | CDN publication global mapping reuse | **Defect R229-C fixed**: deterministic exact-ID lookup |
| 16 | Deletion reconciliation 100k inventory | **Defect R227 fixed**: durable keyset |
| 17 | Deferred key rotation 100k inventory | **Defect R228 fixed**: durable keyset |
| 18 | Scope-specific legal hold | Source guard present; actual provider hold untested |
| 19 | Deletion stages/tombstone/ledger | Source present, staging verify pending |
| 20 | Rights revocation outbox | Source and prior regressions present |
| 21 | Append-only audit chain | Source present, live audit persistence unverified |
| 22 | Retention and backup expiry | Source present, provider propagation unverified |
| 23 | Encrypted private storage | Source present, production key custody unverified |
| 24 | Provider health/key versioning | Source present, external provider acceptance pending |
| 25 | Storage root containment | Source guards present, active host unverified |
| 26 | File 00 verified actor boundary | Source adapter present, counterpart approval pending |
| 27 | Canonical owner object identity/version | Source DomainRegistry present, deployed contract unverified |
| 28 | Consent, MIME, upload part policy | Policy checks exist; full v1.1 master source missing |
| 29 | Quota, rate limiting | Source present, live distributed-load unverified |
| 30 | Part digest/replay integrity | R218 source and regressions retained |
| 31 | Scoped multipart enumeration | R211 indexed source lookup retained |
| 32 | Expired upload janitor/quota | R205/R208/R209 cleanup evidence retained |
| 33 | Immutable source hashing and validation | R214/R216 safeguards retained |
| 34 | Malware/archives/sandbox | Source contracts present; real engines unaccepted |
| 35 | Image/audio/video/PDF processing | Source pipelines present; non-root worker unaccepted |
| 36 | Queue leases and orphan recovery | Source present; host soak tests missing |
| 37 | Signed delivery + serve-time authorization | Source checks present; real owner/browser tests missing |
| 38 | File 17 native recipient transfer | Source binding present; 1 GiB staging E2E not performed |
| 39 | Provider exit, backup/restore/rotation | Source present; real rehearsal and data parity pending |
| 40 | REST schemas, Future-40, integration/CI | Source trace and CI, original standalone v1.1 plan unavailable |

## Five confirmed source-level omissions and their corrections

### R227 — DeletionService inventory materialization

**Prior fault:** `DeletionService::reconcile()` needed `RecordStore::all('deletion',0,null,100000)` before a single deletion could be reconciled. As the inventory grew over the hard cap, all completion, retry and audit reconciliation would stop.

**Correction:** `RecordStore::scan('deletion',1000,$after)` with validated, persistent `cron_cursor:deletion-reconciliation`, ID progression, safe wrap and the previous per-record failure/terminal-state checks intact. The new R227 executable regression inserts **2,100 fully schema-valid cancelled deletion records**, proves 1,000 / 1,000 / 100 advance, cursor wrap and corrupt-cursor denial. Important: this is in-memory test-mode evidence; live MySQL query plans remain unverified.

### R228 — Deferred key rotation cleanup inventory materialization

**Prior fault:** `KeyRotationService::reconcileDeferredCleanup()` loaded every old ciphertext cleanup row with a global 100k ceiling before applying its `$limit`, risking indefinite starvation of later pending cleanup entries.

**Correction:** bounded 2,000-row keyset window; preserve `not_before`, object-lock, live-reference and provider deletion checks; validated durable `cron_cursor:rotation-cleanup`; refuse corrupted cursors. R228 creates 2,100 cleanup records, proves the late invalid pending record is eventually reached and rejected, and verifies wrap/corrupt-cursor behavior. Real source/derivative live-reference scans still require staging scale/owner evidence.

### R229-A — Pending public CDN purge inventory materialization

**Prior fault:** `reconcilePublicPurges()` materialized all `purge_pending` mappings up to 100k before applying its 200/2,000 asset budget.

**Correction:** bounded `RecordStore::scan('cdn_mapping',2000,$after,0,'purge_pending')` and durable validated `cron_cursor:cdn-purge-reconciliation`. Keeps mapping/asset rights and actual CDN purge rules.

### R229-B — Per-asset public CDN purge inventory materialization

**Prior fault:** `purgePublicForAsset()` had the same whole-table 100k bound, including mappings for unrelated assets. Deletion or rights revocation could fail even with a single affected mapping.

**Correction:** streaming bounded `RecordStore::scan('cdn_mapping',250,$after)` page enumeration. Per-asset mapping filtering, provider purge confirmation, fail-closed unknown version keys, and pending/degraded-state audit behavior remain. **Remaining operational limitation:** Without an asset-ID secondary index the method can still traverse many unrelated mappings. Large-scale latency/performance has not been accepted; a schema/index change requires deployment-parity and migration authorization.

### R229-C — CDN immutable publication cache lookup

**Prior fault:** `publishPublic()` inspected the entire CDN mapping table (100k bound) to locate an existing mapping, even though `mappingId=sha256('cdn-map|'+cacheKey)` is deterministic and already the persistence key.

**Correction:** derive deterministic mapping ID and use exact `RecordStore::get('cdn_mapping',$mappingId)`, maintaining original privacy-class, rights-hash, policy-hash, asset and derivative checks. Existing R69 source assertion was adjusted to inspect the new `$existing` variable; source privacy fallback was preserved. No unapproved provider or owner cutover.

**R229 executable regression:** 2,100 unrelated historical CDN mappings, one late pending target, successfully confirmed purge, durable mapping cursor, empty-list wrap and corrupt-cursor denial, plus removal of old `RecordStore::all` reads.

## QA regressions and exact-head evidence

- Added executable tests `tests/review-round-227-deletion-keyset-progress.php`, `tests/review-round-228-rotation-cleanup-keyset.php`, `tests/review-round-229-cdn-purge-keyset.php`.
- Added all three to mandatory `tools/quality-check.sh` under PHP 8.1/8.3/8.4 matrix.
- Existing R69 checks initially failed after the CDN immutable lookup refactor due to a source-expression change. They were updated to test actual new current-rights/current-policy/privacy checks, not removed.
- R227's first test fixture was invalid under the project's strict deletion-record validation (missing step list/timestamps). Fixture was repaired to the full cancelled deletion schema, without weakening runtime validation.
- Push CI for source/test commit `38c64df188c8335a83fdcb26b0be282e989a939b` succeeded **3/3 PHP 8.1/8.3/8.4**. PHP 8.4 logs explicitly confirm **R227 PASS, R228 PASS, R229 PASS, CF-04 QUALITY GATE PASS**. Pull-request matrix was still completing at the initial report-write point; check its final result and **this report commit's own exact-head CI** before claiming full 6/6.
- All tests above execute memory-backed store and local test CDN. No real Hostinger provider, network, MySQL optimizer, distributed CDN edge or private KMS test was performed.

## Remaining blockers — never mark Production Resolved

1. No read of installed/staged CF-04 plugin artifact checksum, active plugin list, `wp_options`, live DB schema/data/migrations, provider dependencies and runtime logs; cannot assert deployed exact code or migration parity.
2. Original amended CF-04 v1.1 Word manuscript missing, so exhaustive author's clause-parity is not established.
3. File 17 native transfer and File 21 feed, File 10/11 media, File 12 PDFs, File 00 identity, File 19 notifications, File 20 shell and File 24 security require separately accepted exact deployment artifacts, owner callbacks and migration/cutover agreements.
4. Real private store/CDN edge/KMS/scanner/sandbox workers, 1 GiB File 17 transfer tests, record/index query plans, high-cardinality purge latency, deletion/legal hold/backups, restore/rollback and penetration/accessibility/load tests remain to be performed with an approved staging setup.
5. The still-global inventory inspection in other provider-exit and live-reference gates is not asserted fixed by R227–R229. No speculative schema or production rollout was executed.

## Distinct exact-state ledger

| Field | Evidence |
|---|---|
| GitHub `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| New corrected Draft PR #5 HEAD before this report | `38c64df188c8335a83fdcb26b0be282e989a939b` |
| Source plugin candidate | `1.3.0-rc.1` |
| Source schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Green synthetic source CI does **not** prove real provider integration, complete plan parity, accepted migration or resolved Live defect. This PR remains Draft and must not be merged or activated without Founder/deployment approval.
