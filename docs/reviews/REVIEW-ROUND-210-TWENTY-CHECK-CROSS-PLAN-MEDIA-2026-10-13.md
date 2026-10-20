# CF-04 — Twenty-check follow-up review and corrective work (2026-10-13)

## Authority and frozen evidence

- The prior verified PR #5 HEAD was `960507fb2f33967d0c6bfdd68a196834486560f5`; push + PR PHP 8.1/8.3/8.4 all passed. Review was re-opened against that exact immutable snapshot.
- This follow-up does **not** assume GitHub PR HEAD or `main` equals deployed WordPress code.
- Actual supplied governing-source comparison includes **Sabri Social Homeopathy Platform — Three Central Plans Consolidated Governing Master Plan**, especially its WordPress + separated media-worker architecture, canonical ownership, File 10 recorded/live media, File 17 messaging, File 12 library, File 19 notifications, File 20 shell, File 21 feed and File 22 composer rules. It also includes supplied amended File 12, File 21 and File 25 source plans and CF-04 v1.0/source requirements matrix. **The precise amended CF-04 v1.1 Future-40 Word document was not independently available**, so its full 1:1 textual parity cannot be certified.
- Root-cause-first scope is repository-source QA, not a diagnosis of an observed live website failure; no production database, deployment or migration change was authorized.

## Twenty bounded source/contract checks

Each numbered check is a distinct scoped code/source inspection, **not twenty complete independent end-to-end installations or twenty separate full CI executions**.

| Check | Plan-to-code area | Result / independent evidence boundary |
|---|---|---|
| 01 | Governance and runtime default | Plugin bootstrap retains explicit disabled-default gate, schema/contract version; real activation remains external |
| 02 | WordPress schema/readiness | Persistence install and `ready()` exist; deployed DB, options and migrations not inspected |
| 03 | Durable record identity and CAS | Versioned `RecordStore::put/delete` and conflict checks present |
| 04 | Global upload inventory scaling | **R208 defect**: `RecordStore::all('upload',...,100000)` caused whole cleanup to fail above 100,000 records |
| 05 | Maintenance scan fairness | **R208 correction**: bounded, persistent keyset cursor can advance beyond thousands of still-active uploads |
| 06 | Expired upload quota and orphan cleanup | R205 part purge/quota settlement still in expiry path; R208 covers pagination and retry |
| 07 | Upload replay/idempotency | Create/complete state and claim/replay machinery present; external race testing pending |
| 08 | Media source hash/MIME/malware policy | Source validation and quarantine paths present; production providers/corpus unverified |
| 09 | Image/AV/PDF sandboxes and derivatives | Process contracts and tool allowlist exist; actual non-root isolated runner not verified |
| 10 | Async queues, leases, retries | Job queues and recovery implemented; live scheduling/scale unverified |
| 11 | Encrypted private object store | AES-GCM/keyring and private provider abstraction present; real KMS/provider evidence unverified |
| 12 | File 00 verified actor / canonical owners | Versioned domain registry and File 00 assertions; real consumer callback adapters unaccepted |
| 13 | Signed grant replay, expiry, uses | Recent numeric input guards and serve-time persistence checks present |
| 14 | File 17 native transfer / message ownership | Owner re-check and native transfer binding present; no direct parallel message database declared |
| 15 | C0 public delivery versus restricted classes | Delivery/CDN/rights revocation paths present; real purge SLO acceptance pending |
| 16 | Retention dependency isolation | **R209 defect**: an upstream rights cursor failure prevented unrelated upload expiry from running |
| 17 | Legal hold/deletion rights propagation | Source algorithms present; exact staging DB/provider deletion propagation untested |
| 18 | Provider exit, restore and crypto rotation | Source capabilities present; real backup restore and provider-exit drills unverified |
| 19 | Future-40/source SDK and contract trace | Capability-gated code and matrix IDs present, not an independent amended plan comparison |
| 20 | REST canonical boundary and CI | R207 canonical route/part/length tests remain; new R208/R209 negative regressions added to mandatory quality gate |

## Confirmed defects, corrections, and regression evidence

### R208 — Unbounded table enumeration despite bounded cleanup

**Evidence:** `UploadService::cleanupExpired()` called `RecordStore::all('upload',0,null,100000)` before selecting any work, thus a true inventory exceeding 100,000 records raises `record_scan_limit`, blocking expiry and quota cleanup. The later `$limit` did not bound the full inventory read.

**Correction:** `RecordStore::scan()` exposes bounded, ID-ordered pages. Expiry uses a durable `cron_cursor:upload-expiry`, bounded scan budget, persisted progress, strict cursor validation, and retry-safe per-upload steps. The regression seeds 2,100 uploads, proves the last expired record is found after crossing the first 2,000 active records, confirms quota release, cursor wrap and corruption rejection. Updated legacy R84 assertion to require the new stronger cursor-and-budget property rather than the retired 100k full-table read.

**Limits:** This solves the top-level Upload Session enumeration defect. `PartStore::list()` still scans its global part inventory with a hard 100,000 record ceiling, and bulk part indexing/migration design is unverified. Do not claim extreme-scale upload-part cleanup completed.

### R209 — Failure of one maintenance task starved independent tasks

**Evidence:** `Plugin::cronRetention()` called rights expiry, retention and upload cleanup inside one catch block, so rights failure prevented the latter two tasks.

**Correction:** Each task has an independent guarded error boundary, with a specific operational alert. A failure is **preserved and rethrown after other independent maintenance work has been attempted**; lock release stays in `finally`. The R209 regression injects invalid rights-cursor evidence, asserts an explicit error remains visible, proves a stale upload nevertheless expires, confirms release of the cron lock, repairs the cursor fixture and verifies safe subsequent retry.

### R207 — Existing correction confirmed

Strict canonical REST route identities, upload part numbers and Content-Length checks were committed in the preceding continuation and verified against its own exact head `960507fb2f33967d0c6bfdd68a196834486560f5` (both push/PR green).

## Remaining cross-repository and deployment acceptance blocks

1. Exact amended CF-04 v1.1 authoritative source is missing for full textual requirement parity.
2. File 11 and File 12 GitHub `main` were only README at previous verified snapshot; deployed/runtime branch parity and the actual PDF/video/reel contracts are unknown. Their absence from `main` does not prove deployed absence.
3. File 17's native attachment backend and File 21's composer/feed media code still need approved event/data/identity contracts, dry-run migration and authoritative owner cutover before CF-04 can become canonical for their binaries.
4. `PartStore::list()` inventory-wide scan above 100k parts remains an independently identified scalability blocker. No speculative schema patch, migration or cross-owner storage change was made without DB evidence.
5. Private provider/malware/AV tool/CDN/KMS/latency/accessibility/load/penetration evidence and Hostinger staging parity must be obtained before an activation/merge decision.

## Distinct release realities

| Required incident field | Evidence |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 baseline | `960507fb2f33967d0c6bfdd68a196834486560f5` |
| Corrective branch code before this report | `4db02838925e4bb253c29c33c0351b63e2d13e6a` |
| Deployed Version | **Unverified** |
| DB Version | **Unverified** |
| Migration State | **Unverified** |
| Live Verification Status | **Not performed** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Any CI success must be checked on the final exact commit, including this report commit; no inference from earlier green runs. Source QA success does not mean production resolution, staging acceptance or approval to enable CF-04.
