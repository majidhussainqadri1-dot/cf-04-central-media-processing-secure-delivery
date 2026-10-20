# CF-04 — R247: Fresh Forty-Aspect Exact-HEAD Source/Cross-Plan Review (2026-10-17)

## 1. Mandatory outcome ledger
- **Forty new targeted Source/Contract inspection scopes:** 40.
- **New confirmed defects discovered in this session:** 2.
- **New source defects corrected:** 2 of 2; each has a new mandatory PHP regression.
- **Known open material architecture/acceptance blockers:** provider-exit plan grouping; whole-fleet key rotation grouping; high-volume restore/consistency and real provider acceptance.
- **Source-code marker inspections:** 40/40 implementation locations identified among 15 runtime PHP classes, ~565,686 fetched characters after corrections. A marker hit is **not** a proof of correct implementation, end-to-end parity, or security.
- **Review scope disclaimer:** These were forty distinct source/contract checks, *not* forty complete, independently installed system reviews or forty repetitions of every test. No such claim is made.

## 2. Exact source and baseline reality
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Default `main` HEAD at start: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Exact Draft PR #5 HEAD at start: `820f8d00a92c6df3f4ba46fabbb6af026a393e9c`.
- Candidate plugin: `1.3.0-rc.1`; source schema/contract `1.5.0 / 1.5.0`; runtime disabled and fail-closed by default.
- Corrected code+test HEAD **before the R247 report and requirements documentation commits**: `d84d556c4e20e57b30a88089fe923f27b7c4a941`.
- At that exact code/test commit, **six GitHub Actions quality jobs passed**, Push and PR matrices covering PHP 8.1, 8.3 and 8.4. Logs explicitly show `REVIEW ROUND 245 RESTORE KEYSET AUDIT: PASS`, `REVIEW ROUND 246 ENVELOPE RECONCILIATION KEYSET: PASS`, `CF-04 QUALITY GATE: PASS`.
- Any later documentation commit must be re-verified at its exact HEAD; green evidence must not be copied across SHAs.

## 3. Governing document and native-owner crosswalk
The governing comparison uses:
1. Original source plan `CF-04 Central Media Processing and Secure Delivery Conditional Complete Master Plan v1.0`, including FR-001…FR-033; this is a **conditional** future infrastructure plan.
2. User-provided `Sabri Social Homeopathy Platform — Three Central Plans Consolidated Governing Master Plan 2026`, protecting canonical owners, versioned interfaces, migration reconciliation, rollback and C0–C5 controls.
3. Supplied File 12 PDF Library v1.1, File 21 Home/News Feed NG30 amended and other domain plans. The exact separately amended CF-04 **v1.1 Future-40** original Word manuscript is **not independently available**; the repository-maintained completion matrix cannot substitute for it.

GitHub native `main` source trees independently re-read on this run (not live deployment evidence):

| Owner | Main HEAD | Source observation |
|---|---|---|
| File 10 Video Wall | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` | 11 PHP source files |
| File 11 Reels | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` | 0 PHP on `main`; other branch/deployed state unknown |
| File 12 PDF Library | `2b369ef2d609247896e1ab32118f1dc498407f51` | 0 PHP on `main`; deployed state unknown |
| File 17 Network/Smail | `8ae656e51796d1f05865d8be5dca2480443d79ca` | 175 PHP files; original attachment/native transfer code retained |
| File 21 Home/News | `f2eb7e95ddea327af36ea725ffb923b029f885e6` | 302 PHP files; separate native media handler |
| File 22 Composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` | 117 PHP files |

**File 17 remains canonical for message/recipient authorization; File 12 for PDF catalogue/rights/reading; File 21 for feed/publication; File 10 for video metadata.** No unapproved source migration or ownership transfer is represented as completed.

## 4. Forty scoped fresh inspections

| No. | Boundary | CF-04 source | Finding / limit |
|---|---|---|---|
| 01 | Runtime default-disabled / plugin bootstrap | class-scm-plugin.php | Located; deployed activation unknown |
| 02 | Schema 1.5.0, readiness, migration gate | class-scm-persistence.php | Located; DB/wp_options unknown |
| 03 | Composite primary key, record IDs | class-scm-persistence.php | Located; real MySQL query plans pending |
| 04 | Atomic CAS writes | class-scm-persistence.php | Located; real concurrency pending |
| 05 | Version-aware CAS deletion | class-scm-persistence.php | Located |
| 06 | Fail-closed database reads | class-scm-persistence.php | Located |
| 07 | Immutable ID-keyset scans | class-scm-persistence.php | Located |
| 08 | Exact-ID batched read | class-scm-persistence.php | Located |
| 09 | Tamper-evident audit events | class-scm-persistence.php | Located; operational durability pending |
| 10 | File 00 canonical identity claims | class-scm-contracts.php | Adapter located; installed counterpart unverified |
| 11 | Native canonical owner and object version | class-scm-contracts.php | Authorization gate located; handshake missing |
| 12 | C0–C5 privacy class control | class-scm-contracts.php | Located |
| 13 | Rights, purpose and lawful-basis envelope | class-scm-contracts.php | Located |
| 14 | Upload credential/session binding | class-scm-upload.php | Located |
| 15 | Quota and abuse controls | class-scm-upload.php | Located; host load pending |
| 16 | Idempotent upload/replay | class-scm-upload.php | Located |
| 17 | Part identity/hash integrity | class-scm-upload.php | Located |
| 18 | Full multipart assembly | class-scm-upload.php | Located |
| 19 | Upload expiry/cleanup and quota release | class-scm-upload.php | Located |
| 20 | Owner/policy-bound dedupe | class-scm-upload.php | Prior R238 pagination fix present |
| 21 | Content sniffing, MIME/magic | class-scm-validation.php | Located; real hostile corpus pending |
| 22 | Malware/archive/polyglot defenses | class-scm-validation.php | Located; real scanner pending |
| 23 | Sandbox worker external attestations | class-scm-processing.php | Located; real worker pending |
| 24 | Processing job leases/recovery | class-scm-processing.php | Located; staging concurrency pending |
| 25 | Derivative manifest lineage | class-scm-processing.php | Located |
| 26 | Image processing | class-scm-processing.php | Located; external engine pending |
| 27 | Audio/video pipeline | class-scm-processing.php | Located; codec provider pending |
| 28 | PDF/document previews and OCR | class-scm-processing.php | Located; File 12 integration pending |
| 29 | Encryption at rest / keyring | class-scm-storage.php | Located; real KMS pending |
| 30 | Private-root containment | class-scm-storage.php | Located; host ACL pending |
| 31 | Signed, scoped delivery grant issue | class-scm-delivery.php | Located |
| 32 | Serving-time permission and grant recheck | class-scm-delivery.php | Located; real browser pending |
| 33 | C0-only public CDN, purge | class-scm-delivery.php | Located; purge SLO pending |
| 34 | File 17 private recipient and transfer | class-scm-transfer.php | Located; owner cutover forbidden without approval |
| 35 | Retention keyset and purge | class-scm-lifecycle.php | Located; real backup expiry pending |
| 36 | Legal hold and deletion | class-scm-lifecycle.php | Located; stage/legal provider evidence pending |
| 37 | Provider exit safety witness and migration | class-scm-operations.php | Witness exists; globally capped migration plan STILL UNRESOLVED |
| 38 | Key rotation/envelope reconciliation | class-scm-operations.php | NEW R246: active-key envelope scan corrected; rotateAll global grouping STILL UNRESOLVED |
| 39 | Restore tombstones/rights/manifests/integrity | class-scm-operations.php | NEW R245: 100k global audit cap corrected; consistent staging snapshot STILL UNVERIFIED |
| 40 | Future-40/re-scan ownership and release gates | class-scm-future40.php | Prior R242/R243 fixes; real providers and complete amended-plan parity pending |

## 5. New confirmed defect / correction A — R245 Restore inventory ceiling

**Exact cause:** `RestoreService::tombstones()`, `rights()`, `manifests()` and `integrity()` used global `RecordStore::all(...,100000)` materializations. With more than 100,000 unrelated items they could fail from the inventory cap or huge PHP memory allocation, jeopardizing legitimate restore verification. No live event is asserted.

**Correction:** Each family now uses `RestoreService::restoreRecords($type)`, a stable `RecordStore::scan($type,250,$after)` generator with full keyspace traversal. Existing rights/manifest/tombstone/integrity decisions, blocked serve gate, and fresh restore authorization remain intact. **This removes the arbitrary global-inventory ceiling and full-array materialization; it does not make a synchronous restore scan into a durable resumable consistency snapshot.**

**New regression:** `tests/review-round-245-restore-keyset-audit.php` creates 2,600 historical deleted assets+tombstones plus a late conflicting tombstone, proves reconciliation blocks serving, repairs the record, proves all checks complete, and proves the serve gate is authorized only after reconciliation. The original test assertion first failed because PHP double-quoted text interpolated `$a`, then a prefix matcher also matched `$asset` from a separate owner path. Both test defects were corrected; negative CI history is retained.

## 6. New confirmed defect / correction B — R246 Key-envelope reconciliation cap

**Exact cause:** `KeyRotationService::reconcileCurrentEnvelopes()` globally loaded up to 100,000 asset rows, potentially blocking otherwise valid late envelope synchronization.

**Correction:** Stable 250-record ID-keyset pages, retaining exact active key, asset SHA, algorithm, cryptographic envelope CAS update and mismatched identity fail-closed. Scope **only** covers the current-key envelope reconciliation helper, **not** the riskier `rotateAll()` global grouping algorithm.

**New regression:** `tests/review-round-246-envelope-reconciliation-keyset.php` creates 2,600 historical records, verifies a late active-key envelope is repaired idempotently, then inserts a late forged envelope and confirms `asset_key_envelope_stale` rather than silently accepting it.

## 7. Remaining known work — never mislabel complete
1. **ProviderExitService::plan()** still enumerates up to 100k source assets and 200k derivatives, building a large in-memory plan embedded in a single JSON record. Correct completion requires indexed source-provider inventory, durable segmented item state, provider credential/key identity, staged cutover/rollback, and real MySQL/Hostinger load evidence. A one-line cap increase is prohibited as a fake fix.
2. **KeyRotationService::rotateAll()** still groups entire active asset and derivative inventories in memory. Shared-ciphertext reference preservation, resumable group leases, crash recovery, key ring rollback, old-object purge and KMS isolation require a separate durable redesign and staging drill.
3. **Restore service**, after R245, still performs a synchronous exhaustive scan rather than immutable snapshot-frozen, durable checkpointed high-cardinality restore reconciliation. Real provider snapshots, concurrent-change consistency, large-scale soak, backup restore and rollback gates remain pending.
4. **Cross-owner acceptance** for File 00/10/11/12/17/20/21/22/24 remains unverified at exact installed versions. GitHub `main` can never prove deployed parity.
5. Real private object storage/KMS, scanner, malware test corpus, sandbox media codecs, CDN purge SLO, 1 GiB File 17 attachment streaming, actual staging MySQL query profiles, international residency/privacy, accessibility browser audits and Founder release signoff remain pending.
6. The original amended CF-04 Future-40 Word manuscript is missing, so exact one-to-one amended-plan completeness remains open.

## 8. Mandatory live-first final ledger
| Item | State |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Source candidate | Draft PR #5, not merged |
| Source plugin version | `1.3.0-rc.1` |
| Source DB schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | **Unverified** |
| DB Version | **Unverified** |
| Migration State | **Unverified** |
| Live Verification Status | **Not performed** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No deployment, live incident fix claim, merge, runtime enablement, or approved source-owner cutover occurred. The two newly proven omissions are corrected at source level and their mandatory CI regressions passed at the cited exact code/test SHA; external deployment parity remains open.
