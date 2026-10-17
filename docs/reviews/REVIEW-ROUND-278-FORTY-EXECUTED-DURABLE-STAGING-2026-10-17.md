# CF-04 R278 — 40 Executed Checks, Durable Staging Completion Review (2026-10-17)

## Evidence and baseline (do not conflate three realities)
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Starting repository `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Starting open Draft PR #5 HEAD: `3f9694393be83324005eaf2b8dd0bfbfe4cc878d`.
- New **code and mandatory test** HEAD: `2323aef78b929b021c3f7ed15e18e68bbcd6706c`. A later matrix/report commit has its own distinct SHA and needs separate exact-HEAD CI.
- Candidate runtime version: `1.3.0-rc.1`; source schema and API contracts `1.5.0/1.5.0`; candidate plugin remains fail-closed/off by default.
- CI at `2323aef7...`: pull_request run `39055420550`, push run `39055417079`. **6/6 quality jobs success** (PHP 8.1/8.3/8.4 on both runs). PHP 8.4 raw Actions logs independently confirmed **40/40 distinct final R## PASS markers**, including R275/R276/R277 and `CF-04 QUALITY GATE: PASS`.
- This means forty **selected individually executed PHP regression scripts**, not 40 comprehensive independent manual audits, 40 current live checks or confirmation of production deployment.
- Fresh read-only WPVibe site inventory: **No WordPress sites connected**. Exact deployed source, active plugins, DB, migration, checksum and runtime logs were not collected.

## The 40 executed code/contract review scripts
| Review no. | Unique script | Specific assertion domain | Native owner / master boundary | CI log |
|---|---|---|---|---|
| 1 | R81 | Provider source drain before cutover | Provider Exit | PASS |
| 2 | R103 | Storage stays outside public WP root | File24 privacy | PASS |
| 3 | R104 | Quota/CAS simultaneous uploads | File10/12/17 | PASS |
| 4 | R105 | Processing idempotency and replay | Workers | PASS |
| 5 | R106 | Private grant revocation | File17 | PASS |
| 6 | R107 | Hold checks at action time | Retention | PASS |
| 7 | R108 | Repair and reconciliation | Operations | PASS |
| 8 | R109 | REST/API owner contract | File00 | PASS |
| 9 | R110 | Schema/persistent SQL CAS | Database | PASS |
| 10 | R111 | Central requirements and contract governance | Governing plan | PASS |
| 11 | R115 | Public-safe serving boundaries | File21 | PASS |
| 12 | R118 | Private file transfer native bridge | File17 | PASS |
| 13 | R123 | Fresh native owner upload grant | File00/17 | PASS |
| 14 | R124 | Dead letter handling | Workers | PASS |
| 15 | R125 | Signed scoped download grant | File17 | PASS |
| 16 | R126 | Job lifecycle invariants | Processing | PASS |
| 17 | R127 | Provider source cleanup idempotency | Provider Exit | PASS |
| 18 | R129 | Schema/runtime deployment evidence | Release | PASS |
| 19 | R143 | Copied byte SHA integrity | Provider Exit | PASS |
| 20 | R144 | Rotation physical-key identity | Key Rotation | PASS |
| 21 | R150 | Legal holds and retention rules | File24 | PASS |
| 22 | R151 | Deletion state versioning | Privacy | PASS |
| 23 | R152 | Deletion event recovery | Audit | PASS |
| 24 | R153 | Tombstone audit linkage | Restore | PASS |
| 25 | R184 | Audit custody and traceability | Governance | PASS |
| 26 | R205 | Expired upload cleanup | Operations | PASS |
| 27 | R220 | Persistent cursor fairness | Workers | PASS |
| 28 | R231 | Late live source alias witness | Provider/Rotation | PASS |
| 29 | R245 | Complete restore keyset enumeration | Restore | PASS |
| 30 | R265 | Fresh validation before serve | Restore | PASS |
| 31 | R266 | Source preflight before rollback | Provider Exit | PASS |
| 32 | R267 | Malformed rights fail-closed | File12/Restore | PASS |
| 33 | R268 | Malformed tombstone ID fail-closed | Restore | PASS |
| 34 | R270 | Target rehash at source purge | Provider Exit | PASS |
| 35 | R271 | Restore fingerprint drift and gate fence | Restore | PASS |
| 36 | R272 | Select actually stale crypto workload | Key Rotation | PASS |
| 37 | R273 | 2301-item durable provider inventory stages | Provider Exit | PASS |
| 38 | R275 | NEW completed inventory/source and tampered replay verification | Provider Exit | PASS |
| 39 | R276 | NEW 2302 stale references durability/key epoch checkpointing | Key Rotation | PASS |
| 40 | R277 | NEW 2601 asset/nine-family restore source checkpoint staging | Restore | PASS |

## A. R275 — one confirmed new source-level defect, corrected

**Baseline root cause:** `ProviderExitInventoryStage::advance()` checked the persisted segment's page hash, source cursor and endpoints on crash replay, but **not the saved `items` list itself**. A changed/replaced staged media key/sha/size could survive even when the original source page hash remained unchanged. Additionally, a completed unfenced inventory lacked a full read-only cross-check against current source rows.

**Code fix:** Crash replay now compares the journaled `items` to freshly selected canonical items and verifies inventory ID, stage status, raw count and paging metadata; `provider_exit_inventory_drift` is raised on disagreement. New administrative read-only `ProviderExitInventoryStage::verifyComplete()` replays every asset and derivative 250-row source page, enforces current parent/hold/region policies, and validates selected items, segment hashes, chained stage hashes, counters and completeness. It returns only `verified_unfenced` — **never an executable authorization or write fence**.

**Regression:** New mandatory `tests/review-round-275-provider-inventory-complete-revalidation.php` verifies 525 asset records plus one derivative, catches forged saved SHA, forged object key on simulated partial-checkpoint replay, a newly introduced live File 17 object and false stage totals. No provider switch or destructive migration. PASS on all PHP matrix jobs.

## B. R276 — pre-existing missing capability partially implemented (not counted as a new defect)

**Prior gap:** Large actual stale-key inventories over the executable 2,000-alias limit had no durable source candidate discovery/checkpoint workflow; R272 only fixed false blocking from already-current unrelated references.

**New code:** Independent admin-only **`KeyRotationInventoryStage::begin()/advance()`** stages one 250-ID canonical asset or derivative page per call, journals raw page hash, selected stale reference identity and cursor in durable `key_rotation_inventory_segment` rows, then advances parent cursor using version/CAS. On replay it compares identity/payload and rejects drift. It blocks changing `Keyring::activeId()` mid-stage. Completed stage is `inventory_complete_unfenced` and **does not drive `rotateAll()`, edit ciphertext or grant migration authority**.

**Regression:** New mandatory `tests/review-round-276-key-rotation-staged-candidates.php` has 3,000 assets and a derivative with 2,302 stale references, confirms all candidate data is journaled in bounded pages, simulates crash/replay and forged segment item, and checks active-key epoch change blocks continuation. PASS.

**Open:** Transactional physical-key **grouping of all aliases** (including currently active aliases to the same physical ciphertext), durable lock/leases, KMS provider recovery, 2,001+ **executable** rekey jobs, checkpoint replay and safe deletion of original ciphertext remain unimplemented/unverified.

## C. R277 — pre-existing Restore durability prerequisite partially implemented

**Prior gap:** `RestoreService::reconcile()` holds a blocking gate and compares source fingerprints but performs long synchronous scans. No resumable, durable, page-based inventory/checkpoint preparation of the nine record families existed.

**New code:** Separate administrative, **non-destructive `RestoreCheckpointStage`** maintains `restore_checkpoint` state with snapshot descriptor hash, family cursor and chain hash. Its `advance()` persists **one source-family 250-row page per invocation** in a versioned `restore_checkpoint_segment`, checks saved page identity on interrupted replay, and tracks all nine relevant source families. `verifyComplete()` re-reads source pages, compares their digests and totals. Status remains `verified_unfenced`; this feature **does not restore an external backup, own the serve gate or create an atomic DB+object snapshot**.

**Regression:** New mandatory `tests/review-round-277-restore-resumable-checkpoint-dryrun.php` checks 2,601 assets plus eight additional families, interrupted checkpoint replay, source mutation after stage complete and forged parent totals; proves no `restore_gate` opening or `RestoreService` operation. PASS.

**Open:** Stable transactional DB snapshot consistent with media/object/KMS references, resumable and actually executing **restore** (not merely inventory), cross-resource reconciliation, owner writes pause/fence, real staging data replay and 1GiB performance/rollback drills.

## Completed defects, new capabilities and evidence
| Item | Verified |
|---|---|
| New source-code defects discovered | **1**: incomplete source/segment selected-manifest parity |
| New code defects corrected | **1/1** (R275) |
| Previously unimplemented prerequisite capability now added | **2**: Key Rotation candidate stage and Restore checkpoint dry-run (R276/R277) |
| New source regressions | **3** (R275–R277), mandatory in `tools/quality-check.sh` |
| Forty distinct CI review scripts | **40/40 PASS** at exact code+test SHA |
| PHP push and PR CI | **6/6 success** at exact code+test SHA |
| Production plugin, data, migration or external provider changes | **NONE** |
| Remaining high-scale, transactional, production-grade modules closed | **NONE** |

## Cross-plan sources, ownership and exact limitations
- Primary material: attached **Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan 2026**; File 12 PDF Library and Digital Reading amended Word plan; File 21 Complete Home/News Feed NG30 amended Word plan; indexed **CF-04 Conditional Complete Master Plan v1.0**. Central plan requires every implementation to trace requirement ID, canonical owner, affected data, migration, tests, rollback and evidence and demands staging/backups/founder approval before live change.
- **The independently amended CF-04 v1.1 Future-40 Word primary remains unavailable.** The GitHub requirements matrix mentioning v1.1 is repository prose, **not verification against the original approved amended document**. Thus full clause-by-clause plan completeness cannot be certified.
- File 00 retains authentication truth; File 10 video, File 11 reels, File 12 PDF catalogue/reader/rights, File 17 private messaging/recipient/transfer, File 21 Home/News publishing and feed, File 22 composer, File 24 security and File 25 visual UI remain distinct native owners. CF-04 changes only its own source record staging, and do not mutate any sibling DB or installed code.
- Fresh independent sibling repository `main` **source** heads (never deployed-parity evidence):
  - File 10: `4b8a3dda140186c01b05d998e729cb1aeeed2cf3`
  - File 11: `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b`
  - File 12: `2b369ef2d609247896e1ab32118f1dc498407f51`
  - File 17: `8ae656e51796d1f05865d8be5dca2480443d79ca`
  - File 21: `f2eb7e95ddea327af36ea725ffb923b029f885e6`
  - File 22: `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a`

## Remaining blockers and exact next acceptance gates
1. **Provider Exit >2,000 eligible items:** R273/R275 complete non-destructive inventory journaling and validation, but real segmented copy/shadow, owner write fences, checkpointed switch/rollback and destructive purge/credential retirement still need safe transactional architecture, tests and real object store/staging proof. Existing executable `ProviderExitService::plan()` still rejects 2,001 eligible items.
2. **Key Rotation >2,000 affected aliases:** R276 adds durable discovery, not atomic physical ciphertext grouping/rekey execution, KMS escrow and safe old-key/object cleanup. Existing `KeyRotationService::rotateAll()` still fails closed on >2,000 affected references.
3. **Restore:** R277 prepares durable read-only inventory checkpoints, not immutable object/DB backup or actual resumable restore execution. Existing `RestoreService::reconcile()` and serving gates remain separate.
4. **Live/staging parity:** No WordPress site is connected; installed plugin checksum, active version, `wp_options`, DB/schema/migration, logs, external providers, CDN/KMS, native plugin versions, privacy/legal-hold flows, 1GiB streaming/load, accessibility, acceptance and re-test are unknown.
5. **Specification parity:** amended CF04 v1.1 Future-40 original DOCX not available; no approved clause-by-clause matrix can be completed without it.

## Mandatory Live-First reality ledger

| Reality | Status |
|---|---|
| Repository `main` HEAD at freeze | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 fixed code/test HEAD (before report) | `2323aef78b929b021c3f7ed15e18e68bbcd6706c` |
| Candidate runtime version | `1.3.0-rc.1` |
| Repository DB schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No PR merge, Hostinger deploy, staged/live write, plugin activation, DB upgrade or real-provider cutover took place. No live incident was declared resolved. This report's new documentation SHA requires its own CI (not inherited from the earlier green code SHA).
