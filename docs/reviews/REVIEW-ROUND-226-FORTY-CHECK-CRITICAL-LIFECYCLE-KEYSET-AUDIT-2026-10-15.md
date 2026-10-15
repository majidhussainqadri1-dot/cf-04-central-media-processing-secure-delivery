# CF-04 R226 — Forty scoped cross-plan inspections and high-risk lifecycle keyset corrections

**Review date:** 2026-10-15  
**Repository:** `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`  
**Branch:** `codex/cf04-three-plan-complete-1.2.0` — Draft PR #5, **open and unmerged**  
**Baseline candidate HEAD:** `12d71bb7e186ea3234f30b821d6f80f9d365c8e0`  
**Fixed code/CI HEAD before publishing this audit:** `accb6a71197ae8a1018e9680567f845d67cc3432`  
**Default `main` HEAD:** `0294442f0fddd1ca5440d9d5ac992ba80aced972`  
**Candidate plugin:** `1.3.0-rc.1`; source DB schema/contract `1.5.0/1.5.0` (not deployed DB evidence)

## Governing evidence and limits

The review compares fifteen complete PHP runtime classes (~553,418 source characters) and the requirements matrix with the supplied **Sabri Social Homeopathy Platform — Three Central Plans Consolidated Governing Master Plan** and related available plans (File 12 PDF Library, File 08 Clinic, File 21 Home/News Feed). The central plan explicitly calls for stable cursor pagination and bounded operations at scale, owner-domain canonical identity, separate approved storage/processing/CDN workers and event-driven cross-module coordination; File 00 retains identity, File 17 remains native chat/transfer owner, File 10 video owner, File 12 library/reader rights owner, File 19 notification owner, File 20 shell owner.

**Limit:** The exact standalone **CF-04 amended Future-40 Word master-plan** was not independently available among provided files; the repository-authored traceability matrix is not proof of complete 1:1 source-plan parity. No live/staging artifact, checksum, actual DB, migration state, runtime error log or active provider was accessed. `main` and Draft PR are different source realities; neither proves deployed code.

The forty checks below are **distinct source/contract inspections**, NOT forty complete independent PHP suite executions, staging installations, or production end-to-end tests. Presence of a method alone does not establish runtime integration.

## Forty inspected technical and module boundaries

| # | Technical area | Finding / verification limit |
|---|---|---|
| 01 | GitHub Draft PR vs production artifact | Repository HEAD verified; live artifact not verified |
| 02 | WordPress runtime plugin/bootstrap | Source plugin hooks present; deployed plugin status unknown |
| 03 | Schema and migration version | Source 1.5.0; DB schema and wp_options unknown |
| 04 | Records composite PK and CAS insert/update | Source present; MySQL concurrency test pending |
| 05 | Versioned deletion and audit hash chain | Source present; production audit evidence pending |
| 06 | Bounded keyset record scanning and getMany | Source present; actual MySQL query plans pending |
| 07 | Processing cron stable cursor and bounded budget | R220 retained; R225 short-page defect fixed |
| 08 | Integrity cron keyset and stale schema cursor | R220 retained; R225 short-page defect fixed |
| 09 | Retention cron isolated subtask failures | Prior R209 source/test protections retained |
| 10 | Deletion/revocation/CDN/key-rotation cron fault isolation | CONFIRMED R222 defect corrected, tested |
| 11 | Revocation dispatch global pending-list limit | CONFIRMED R223 defect corrected, tested |
| 12 | Rights expiry million-asset global scan | CONFIRMED R224 defect corrected, tested |
| 13 | CDN pending purge inventory-wide query | Open scaling gate: still uses a 100k global limit |
| 14 | DeletionService reconciliation inventory-wide query | Open scaling gate: still uses a 100k global limit |
| 15 | Deferred key-rotation cleanup inventory-wide query | Open scaling gate: still uses a 100k global limit |
| 16 | Provider exit/purge source authorization | Source present; real provider rehearsal pending |
| 17 | Restore/rollback and serving hold | Source present; actual staging restore pending |
| 18 | Private AES-GCM object storage and Keyring | Source present; KMS and real provider unverified |
| 19 | Storage root/public-web confinement | Source present; deployed paths unchecked |
| 20 | Provider region/capability checks | Source present; real provider infrastructure unaccepted |
| 21 | File 00 verified actor and role | Source Auth adapter; deployed File 00 parity unknown |
| 22 | Canonical owner authorization and object versions | Source DomainRegistry; counterpart owner acceptance pending |
| 23 | Purpose, rights, MIME and scanning policy | Source Policy; exact amended CF-04 manuscript missing |
| 24 | Upload idempotency claim and fencing | Source exists; distributed DB race/load pending |
| 25 | Upload quotas, throttling and abuse budget | Source exists; live accounting unverified |
| 26 | Source-vs-provider upload-part digest/replay integrity | R218 corrective gate retained |
| 27 | Upload part keyed retrieval, order and cap | R211 corrective gate retained |
| 28 | Upload expiry janitor, quota and orphan part purge | R205/R208 regressions retained |
| 29 | Multipart assembly full SHA and stalled stream detection | R213 correction retained |
| 30 | Immutable two-pass source validation | R214/R216 protections retained |
| 31 | Malware scanner, archive/polyglot defense | Source hooks present; real scanner/corpus unaccepted |
| 32 | Safe temp-file cleanup and EOF errors | R213/R216 protections retained |
| 33 | Job leases, retries and dead-letter states | Source exists; multi-worker chaos/soak pending |
| 34 | Image/audio/video tool sandbox pipelines | Source exists; real isolated AV/image codecs pending |
| 35 | PDF derivative/document provenance | Source exists; File 12 owner live integration pending |
| 36 | Signed delivery scope, expiry, action-time owner checks | Source exists; real service/browser verification pending |
| 37 | Byte-range private serving and File 17 transfer linkage | Source exists; actual 1GiB File 17 test pending |
| 38 | Legal hold, deletion, retention and backup tombstones | Source exists; provider deletion/backups unverified |
| 39 | Rights event dispatch, future adaptive features | Source exists; native File 19/21/24 consumers unaccepted |
| 40 | Versioned REST/SDK contracts, release/CI and module parity | Source gate green on tested HEAD; production acceptance unverified |

## Four proven new root causes, fixes and regression evidence

### R222: A single reconciliation failure blocks independent, safety-critical maintenance

**Before:** `Plugin::cronDeletions()` ran `DeletionService::reconcile()`, `RevocationDispatchService::reconcile()`, `DeliveryService::reconcilePublicPurges()` and `KeyRotationService::reconcileDeferredCleanup()` sequentially inside a single catch. A corrupt revocation cursor halted CDN purge work that is unrelated to the corrupt cursor.

**After:** independent task-level exception handling and specific alerts; preserve and rethrow first failure **after** attempting other tasks; always release the cron lock in `finally`. R222 adversarial regression introduces a corrupt revocation cursor and a pending public CDN purge: confirms original cursor error survives, CDN map becomes purged, lock is released, and full retry succeeds after repair.

### R223: Unbounded pending revocation enumeration fails above a 100k global threshold

**Before:** `RevocationDispatchService::reconcile()` materialized `RecordStore::all('revocation_dispatch',0,'pending',100000)` before applying its per-run `$limit`. More than 100k pending records raises a hard scan-limit error, obstructing even the first page.

**After:** query directly with `RecordStore::scan('revocation_dispatch',page,after,0,'pending')`; maintain a validated 64-hex ID cursor, per-run bounded work, one-time safe wrap, immediate retry from the front when a cursor was at the final pending key. Existing R158 recovery semantics remain. R223 exercises 210 pending items, pagination through three runs, once-only wrap, replay of failures, invalid cursor rejection, and source absence of the old global 100k enumeration.

### R224: Rights expiry reconciler could not reach more than a million retained assets

**Before:** `RightsRevocationService::reconcileExpired()` enumerated `RecordStore::all('asset',0,null,1000000)`, built a complete eligible array, and sorted it; the per-run action limit only applied **after** the global inventory scan. Any larger inventory blocks reconciliation and consumes excessive request resources.

**After:** bounded immutable-ID keyset scan (maximum `min(20000,max(1000,$limit*4))` inspected records per run), durable `last_id` progression, strict cursor input validation, while retaining the existing `last_key` evidence for backward compatibility. Legacy expiry-key-only cursors migrate using the embedded old asset ID and can safely wrap once from final asset. R224 test uses 2,100 assets, proves the late expired entry is reached on the next run, cursor wrap, corrupt new cursor denial and valid old-cursor migration. Previous R160 and R163 runtime regressions also pass.

### R225: Cursor incorrectly reset when work quota is met mid-final-page

**Before:** `Plugin::cronJobs()` and `cronIntegrity()`, even after prior R220 corrections, could reset their cursor upon detecting that a page returned fewer than the requested 200/100 rows, **even if** they had stopped processing the page early on selecting 5/20 work items. Unvisited tail records could be skipped.

**After:** reset only when the returned short page has actually been consumed without hitting the selected work ceiling. The same half-page reset condition in R224 was fixed to preserve the ID after a work-budget stop. R225 executable fixture verifies five queued assets leave three to the next processing cron, and twenty ready assets leave four to the next integrity cron. Test fixture uses an explicit missing job ID to cause a controlled processing exception rather than PHP undefined-key warnings.

## Regression and CI outcomes

- Regression files added: `tests/review-round-222-deletion-cron-failure-isolation.php`, `tests/review-round-223-revocation-keyset-fairness.php`, `tests/review-round-224-rights-expiry-keyset.php`, and `tests/review-round-225-final-page-cursor-fairness.php`.
- All four are mandatory in `tools/quality-check.sh`.
- Legacy `tests/review-round-71-final-adversarial.php` source assertion updated from a 1m full-table scan to explicit bounded keyset scanning. Existing runtime tests R158, R160, R163 remained mandatory and exposed genuine compatibility edge cases; those were fixed in source rather than disabling or rewriting those test expectations.
- Intermediate exact commits **failed** during development, for documented reasons: old R71 static requirement; recovery cursor delayed wrap (R158); rights mid-page reset (R160); legacy rightmost cursor needing wrap (R163); minimal synthetic R225 fixture emitting missing-policy warnings. Each was followed by an explicit corrective commit and full CI rerun.
- The last tested corrective code/test HEAD `accb6a71197ae8a1018e9680567f845d67cc3432` passed **6/6** GitHub Actions jobs: Push and Pull Request on PHP **8.1, 8.3, 8.4**. PHP 8.4 log confirms `REVIEW ROUND 222 ... PASS`, `223 ... PASS`, `224 ... PASS`, `225 ... PASS`, and `CF-04 QUALITY GATE: PASS`.
- **This report commit must receive its own exact-HEAD CI confirmation**. Previous green CI does not automatically certify a later commit.

## Unresolved and risk-accepted only after real evidence

1. Other global-inventory calls remain in `DeliveryService::reconcilePublicPurges()` / `purgePublicForAsset()`, `DeletionService::reconcile()` and `KeyRotationService::reconcileDeferredCleanup()`. Their current hard bounds could become a scalability blocker at large cardinalities. Do **not** label them clean or fabricate a migration-free schema solution without production DB/query and exact ownership evidence.
2. Real File 11 and File 12 `main` branches contain README-only scaffolds in previous exact source checks, while File 17 and File 21 have their own native transfer/media PHP. This does **not** establish what is deployed or merged in their other branches; exact version parity and owner approval are needed before moving binary responsibility to CF-04.
3. Real WordPress/MySQL cursor performance, cron scheduling in the active site, cache/CDN purge timing, malformed content security scans, 1GiB File17 resumable transfers, non-root media workers, KMS/private object provider, deletion/backup restore/rollback, red-team review and penetration/load/accessibility suites have **not** been executed here.
4. Founder-controlled acceptance and deployment policy remain in force. Do not merge the Draft PR, enable production runtime, or report a live incident resolved without exact deployed artifact/DB evidence and a live retest.

## Live-first exact-deployed-state ledger

| Required reality | Evidence |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Corrective PR HEAD (before this report) | `accb6a71197ae8a1018e9680567f845d67cc3432` |
| Candidate plugin | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

A source fix, static inspection and green synthetic CI are **not** real provider integration, staged approval, migration acceptance or Live resolution.
