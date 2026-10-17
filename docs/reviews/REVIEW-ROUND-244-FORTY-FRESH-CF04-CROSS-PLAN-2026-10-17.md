# CF-04 — Fresh Forty-Scope Source and Cross-Plan Review (R244, 2026-10-17)

## 1. Exact initial source freeze
- Default `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- New session initial Draft PR #5 HEAD: `b9b35ea51166e1a25314dcb253a3f6fa2bc324a3`.
- Runtime candidate `1.3.0-rc.1`; source schema / API contracts `1.5.0 / 1.5.0`; production activation default OFF/fail-closed.
- Fifteen original runtime class source files retrieved from exact PR HEAD and forty different targeted cross-file/source invariants checked. This is **forty scoped source checks**, not forty full independent end-to-end installations, forty comprehensive manual rereads or live production tests.
- The source-marker search found locations for all 40 scopes. **Marker presence is not a claim that code is complete or secure.** Three concrete high-cardinality defects found and fixed in this session are detailed below.
- Original available `CF-04 Conditional Complete Master Plan v1.0`, the consolidated Three Central Plans governing document, and Files 12, 17 (native GitHub repository), 21 and other applicable plans were used as separate boundary evidence. The authoritative independently amended `CF-04 v1.1 Future-40` **original Word document is still unavailable**, so exact one-to-one parity with that amendment remains unverified.

## 2. Forty source checks and recorded dispositions

| Check | Boundary | Inspected source | Finding |
|---|---|---|---|
| 01 | Runtime bootstrap/disabled-default | class-scm-plugin.php, class-scm-core.php | Source guard located; live status unknown |
| 02 | Schema readiness/migration option | class-scm-persistence.php | Source 1.5.0; live DB unknown |
| 03 | Composite record key and index | class-scm-persistence.php | Source key verified; MySQL explain absent |
| 04 | Optimistic CAS writes | class-scm-persistence.php | Source guard verified; DB race test pending |
| 05 | Version-aware deletion | class-scm-persistence.php | Source guard verified; live audit pending |
| 06 | ID-keyset pagination | class-scm-persistence.php | Bounded 250/2000 support located |
| 07 | Batch exact-ID lookup | class-scm-persistence.php | Bounded 250-ID query located |
| 08 | Append-only hash-linked audit | class-scm-persistence.php | Source chain located; live preservation pending |
| 09 | Private object encryption | class-scm-storage.php | Source cryptography present; key provider pending |
| 10 | Keyring and active key policy | class-scm-storage.php, class-scm-operations.php | Source present; bulk rotation unresolved |
| 11 | Path containment/symlink protection | class-scm-storage.php | Source guards present; real host ACL unknown |
| 12 | File 00 identity/verification bridge | class-scm-contracts.php | Source adapter; installed owner unknown |
| 13 | Canonical owner and object-version gate | class-scm-contracts.php, class-scm-delivery.php | Source action checks; counterparts not proven |
| 14 | C0–C5, rights, and purpose envelopes | class-scm-contracts.php | Source and tests present |
| 15 | Upload credentials and reauthorization | class-scm-upload.php | Source and tests present |
| 16 | Upload quota/rate/idempotency | class-scm-upload.php | Source and tests present; load untested |
| 17 | Multipart part identity | class-scm-upload.php | Provider/source hash controls present |
| 18 | Multipart complete assembly | class-scm-upload.php | Byte hash/EOF controls present |
| 19 | Expiry janitor and quota release | class-scm-upload.php | Durable keyset and tests present |
| 20 | Owner-bound deduplication | class-scm-upload.php | R238 previous fix green |
| 21 | MIME/Magic/type validation | class-scm-validation.php | Source exists; hostile corpus pending |
| 22 | Archive/polyglot/bomb protection | class-scm-validation.php | Source exists; real scanners pending |
| 23 | Sandbox worker attestations | class-scm-processing.php | Source contract only; approved workers pending |
| 24 | Job leases, retries, dead-letter | class-scm-processing.php | Source and tests; real scheduling pending |
| 25 | Derivative manifest and lineage | class-scm-processing.php | Source state/hash contracts |
| 26 | Image processing | class-scm-processing.php | Adapter-gated; external engines pending |
| 27 | Audio/video transcoding | class-scm-processing.php | Adapter-gated; real playback pending |
| 28 | PDF/document processing | class-scm-processing.php | Reader owner File 12 not cut over |
| 29 | Signed grants and scoped identity | class-scm-delivery.php | Source issue/serve checks |
| 30 | Expiry/session/use-limit | class-scm-delivery.php | Source checks; real browser pending |
| 31 | C0-only public CDN/caching | class-scm-delivery.php | Source publication/purge; purge SLO pending |
| 32 | Private ranges/secure disposition | class-scm-delivery.php | Source range checks; real 1GiB pending |
| 33 | File 17 recipient/transfer owner gate | class-scm-transfer.php | Source adapters; native live counterpart unknown |
| 34 | Retention/legal hold/deletion | class-scm-lifecycle.php | Source keyset; real provider cleanup pending |
| 35 | Retention cron progress | class-scm-lifecycle.php, class-scm-plugin.php | R235/R236 earlier fixes retained |
| 36 | Provider exit/source-drain | class-scm-operations.php | Source witness improved earlier; plan manifest still globally capped |
| 37 | Bulk key rotation and envelopes | class-scm-operations.php | Source guard present; bulk global scan uncorrected |
| 38 | Cost attribution/provider invoice | class-scm-operations.php | NEW R241 complete keyset ledger (green) |
| 39 | Future40 perceptual duplicate and rescan | class-scm-future40.php | NEW R242/R243 paged and durable (green) |
| 40 | Restore, operational QA, and release | class-scm-operations.php, class-scm-plugin.php | Restore global scan unresolved; stage/live gates pending |

## 3. Proven omissions and correction ledger (this fresh batch)

### R241 — Global cost ledger inventory cap
- Old source: `CostService::checkBudget()` and `CostService::reconcile()` called `RecordStore::all('cost',0,null,500000)`, materializing up to half a million records in PHP and potentially failing for a large ledger unrelated to the requested domain/provider.
- Correction: a stable 250-record `CostService::ledgerRows()` generator over `RecordStore::scan('cost',250,$after)`; preserves provider, domain, date, numeric validation, budget overflow, and fail-closed corrupt cost handling.
- New `tests/review-round-241-cost-keyset-ledger.php` proves late domain/provider record after 2600 unrelated rows still participates, malformed late charges fail closed, and old global cap is gone.
- Boundary: still O(N) synchronous cost analytics and not a substitute for indexed monthly summaries, transactional aggregate ledgers and real MySQL/latency acceptance.

### R242 — Perceptual fingerprint inventory cap
- Old source: `PerceptualMediaService::nearDuplicates()` loaded all active fingerprints with `RecordStore::all('perceptual_fingerprint',0,'active',100000)`.
- Correction: two stable 250-record keyset traversals; select freshest current source (timestamp DESC, ID ASC) without materializing the whole inventory, then compare and independently reauthorize eligible candidate assets.
- New `tests/review-round-242-perceptual-keyset-duplicates.php` proves late matches after 2600 unrelated fingerprints and same-owner privacy/policy/rights enforcement.
- Boundary: exhaustive comparison is still O(N), specialized provider adapters and high-volume performance not accepted.

### R243 — Threat-intelligence rescan starvation on mutable offset pagination
- Old source: `ContentSafetyUpgradeService::scheduleRescan()` used `RecordStore::list('asset',..., $offset)` with a 1,000,000 inventory scan cutoff and no durable continuation. On a continuously updating source, mutable `updated_at` order can skip work or cause repeated scans.
- Correction: persistent operation-keyed `cron_cursor`; immutable-ID `RecordStore::scan('asset',$pageSize,$after)`; at most 2,000 assets inspected per invocation; repeat invocations advance the cursor; strict filter and cursor validation; honest `scan_complete` / `scan_truncated` state; rescan jobs remain idempotent and owner-bound.
- New `tests/review-round-243-rescan-cursor-fairness.php` verifies two sweeps reach 2600 valid records plus one excluded deleted asset, last-asset enqueue, cursor wrap, replay, malformed cursor denial.
- Boundary: actual scheduled continuation, installed native policy hooks, provider scanner acknowledgement, and large-host throughput still unverified.

## 4. Regressions and exact-code CI
- Each of R241, R242 and R243 has a new executable PHP regression, registered in `tools/quality-check.sh` on the Draft PR branch.
- During R243, the older Round 100 test asserted the obsolete offset-based implementation: an intermediate push failed at `ROUND 100 FAIL`. After the completed review, this stale test was corrected for durable pagination.
- The R243 regression also initially checked cursor wrap after an additional replay; corrected it to test the completed sweep first, followed by independent replay behavior.
- Corrected code/test HEAD before this report: `985512e2cd1cb461b19db4a64aa9c989bc22df09`; both Push and PR CI completed **successfully**, PHP 8.1/8.3/8.4 = **6 successful jobs**.
- PHP 8.4 logs explicitly show `REVIEW ROUND 241 COST KEYSET LEDGER: PASS`, `REVIEW ROUND 242 PERCEPTUAL KEYSET DUPLICATES: PASS`, `REVIEW ROUND 243 RESCAN CURSOR FAIRNESS: PASS`, and `CF-04 QUALITY GATE: PASS`.
- Report/matrix commits after this exact code/test SHA require their own **fresh exact-HEAD CI**. Do not carry previous green status forward without verification.

## 5. Native module boundaries independently compared
Native `main` snapshots are source repository truth ONLY; they are not proof of installed plugins:
| Native canonical owner | Observed GitHub main SHA | Source observation |
|---|---|---|
| File 10 Video Wall | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` | 11 PHP files, independent video metadata/operations |
| File 11 Reels | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` | README-only main; installed source/other branches unknown |
| File 12 PDF Library | `2b369ef2d609247896e1ab32118f1dc498407f51` | README-only main; installed source/other branches unknown |
| File 17 Network and Transfers | `8ae656e51796d1f05865d8be5dca2480443d79ca` | 175 PHP; native file-transfer and attachment classes retain authority |
| File 21 Feed | `f2eb7e95ddea327af36ea725ffb923b029f885e6` | 302 PHP; independent native media handler |
| File 22 Post Composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` | 117 PHP; native composition ownership |

No simultaneous deployment parity, schema compatibility, accepted cross-owner contract handshake, or migration/rollback permission is asserted from these snapshots. No changes were made to their native repos.

## 6. Remaining actionable blockers — NOT fixed
1. `ProviderExitService::plan()` still materializes 100k source assets and 200k derivatives into a single migration plan record. The canonical provider-exit child-item/slice representation, integrity, resume/rollback, real MySQL acceptance and migration protocol require controlled design.
2. `KeyRotationService::rotateAll()` still uses globally capped asset/derivative materialization and in-memory groups. Rotation of shared ciphertext references requires a durable indexed group/job design with staging cryptographic drills.
3. `RestoreService` globally enumerates up to 100k tombstones/assets in synchronous checks. A safe resumable staged restore gate with trustworthy snapshot/change consistency is required before removing its cap.
4. Cost and perceptual comparisons, although now bounded-memory, remain linear scans rather than indexed provider/domain/period or similarity-search structures; real latency and concurrency acceptance pending.
5. Original independently amended CF-04 v1.1 Future-40 document unavailable; full clause parity unverified.
6. Exact production plugin versions/checksums, DB version, `wp_options` migration state, approved storage/CDN/scanner/workers, real 1 GiB File 17 transfer, File 12 private PDF readers, File 21 feed cutover, backup/restore/rollback, load, browser, accessibility, Founder approval and live re-test remain unverified.

## 7. Mandatory final-state ledger
| Reality | Verified claim |
|---|---|
| Repository HEAD `main` | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 | Open, not merged, defaults disabled |
| Corrected source and test SHA before documentation | `985512e2cd1cb461b19db4a64aa9c989bc22df09` |
| Candidate runtime | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**No merge, runtime enablement, staging/production migration, or live change was performed.** Exact-head source code/tests green is not production acceptance.
