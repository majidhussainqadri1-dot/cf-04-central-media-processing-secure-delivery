# CF-04 R269 — Forty Executed CI Reviews and Blocking-Work Remediation (2026-10-17)

## 1. Freeze: three separate realities
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Initial `main` source HEAD at audit freeze: `0294442f0fddd1ca5440d9d5ac992ba80aced972` (unchanged from prior freeze).
- Initial open/unmerged Draft PR #5 HEAD: `c07e1c1f937de90817b20ac5aa37b3f8a484becc`.
- Corrected **CODE + REGRESSIONS SHA**: `fb9f64e096ca3412023e7de713558be3b00c0ca7`; later documentation change must not inherit green CI without its own run.
- Runtime candidate version `1.3.0-rc.1`, source DB schema `1.5.0`, source contract version `1.5.0`, default runtime OFF/fail-closed.
- **This audit performed 40 distinct individually executed existing/new regression scripts** at an exact HEAD, targeted fresh manual source inspection, and primary governing-plan/adjacent-module contract comparison. It was **not** 40 exhaustive full-repository manual audits and **not** forty end-to-end production or staging tests.
- CI evidence on the corrected code+test SHA: push run `39034543149`, pull_request run `39034546141`; PHP 8.1, 8.3, 8.4 three each, **6/6 PASS**. PHP 8.4 job log independently verified **40/40 distinct final PASS review markers**, R265/R266/R267/R268 and `CF-04 QUALITY GATE: PASS`.
- An **earlier intermediate PR SHA** `ec177ccf0868118cb6002498fdaed493a964cbaf` initially failed PHP 8.1/8.3/8.4: restore fixture lacked rights and `RestoreService::rights()` raised PHP warning/TypeError instead of persisting a blocked result. Both the newly diagnosed real source failure and the unrelated tombstone fixture isolation were corrected. Final CI is green; no failed run is concealed.

## 2. The forty explicit executable reviews

| # | Distinct script | Source/test subject | Cross-plan owner | Exact-source CI |
|---|---|---|---|---|
| 1 | R70 | Provider-exit derivative eligibility and parent policy | File 17 private media | PASS |
| 2 | R72 | RecordStore complete inventory keyset overrun behavior | Persistence | PASS |
| 3 | R78 | Shared-ciphertext identity consistency | Key Rotation | PASS |
| 4 | R81 | Source-provider drain proof before cutover | Provider Exit | PASS |
| 5 | R103 | Non-public encrypted local storage root | Privacy | PASS |
| 6 | R104 | Concurrent upload reservation and quotas | Files 10/12/17 | PASS |
| 7 | R105 | Replay-safe processing idempotence | Processing | PASS |
| 8 | R106 | Signed private-grant revocation | File 17 | PASS |
| 9 | R107 | Action-time retention/hold conditions | Lifecycle | PASS |
| 10 | R108 | Operational reconciliation and failure paths | Operations | PASS |
| 11 | R109 | REST native-owner API boundaries | File 00 | PASS |
| 12 | R110 | Schema/CAS persistence readiness and status | DB | PASS |
| 13 | R111 | Three-plan contract/requirements evidence | Central governance | PASS |
| 14 | R113 | Upload runtime fail-closed activation gate | Runtime | PASS |
| 15 | R114 | Worker runtime fail-closed activation gate | Runtime | PASS |
| 16 | R115 | Public safe media delivery gate | File 21 | PASS |
| 17 | R118 | File 17 native transfer bridge runtime guard | File 17 | PASS |
| 18 | R119 | Future-40 conditional governance | Scope | PASS |
| 19 | R121 | Contract negotiation and version truth | Cross-File | PASS |
| 20 | R123 | Upload reauthorization at action time | Files 00/17 | PASS |
| 21 | R124 | Dead-letter retry and runtime protection | Operations | PASS |
| 22 | R125 | Scoped download/delivery grant claims | File 17 | PASS |
| 23 | R126 | Job-state invariants | Processing | PASS |
| 24 | R127 | Provider destructive-purge outcome reconciliation | Provider Exit | PASS |
| 25 | R129 | Deployment-schema/status evidence | Runtime | PASS |
| 26 | R143 | Source copied-byte hash/object identity | Provider Exit | PASS |
| 27 | R144 | Encryption key rotation content identity | KMS | PASS |
| 28 | R150 | Retention fail-closed and legal hold | File 24 | PASS |
| 29 | R151 | Deletion state invariants | Privacy | PASS |
| 30 | R152 | Deletion audit recovery | File 24 | PASS |
| 31 | R153 | Tombstone/audit lineage | File 24 | PASS |
| 32 | R184 | Audit custody/recovery | Compliance | PASS |
| 33 | R205 | Expired multipart uploads cleanup | Resource | PASS |
| 34 | R220 | Cron scan cursor fairness | Workers | PASS |
| 35 | R231 | Late authoritative live reference witness | Provider/Rotation | PASS |
| 36 | R245 | Restore scan after 250-page boundaries | Restore | PASS |
| 37 | R265 | NEW: revalidate complete inventory immediately before allowing serving | Restore | PASS |
| 38 | R266 | NEW: verify every rollback original before first remap | Provider Exit | PASS |
| 39 | R267 | NEW: malformed/missing rights persist blocked restore gate | Restore | PASS |
| 40 | R268 | NEW: missing/invalid tombstone asset id blocks restore | Restore | PASS |

Prior R70–R245 scripts were **re-executed**, not newly invented. R265–R268 are new mandatory PHP tests and were registered in `tools/quality-check.sh`. A green source test is not a staging or deployed artifact proof.

## 3. Four newly confirmed implementation defects — all four corrected in source and tested

### Defect R265 — previously reconciled restore inventory could go stale before serving authorization

**Baseline root cause:** `RestoreService::authorizeServe()` trusted persisted `restore.status='reconciled'` and `restore_gate.status='reconciled'` without fresh tombstone, rights, manifest, provider, keyring, schema and sampled-integrity checks. A newly inserted or changed owner/asset record after an earlier successful reconciliation could be missed at the final serve-unlock moment.

**Fix:** `authorizeServe()` now calls `self::reconcile($restoreId)` and reads the current gate again immediately before any `serve_authorized` update. A newly failed check persists `blocked` and yields `restore_gate_blocked`. The existing optimistic version checks and audit event remain. This requires a full synchronous verification and **is not** a stable transactional DB/object snapshot.

**Regression:** `tests/review-round-265-restore-fresh-serve-gate.php` first reconciles an empty environment, creates 2,600 deleted historical assets and a late new ready asset with contradictory tombstone, verifies final serve refusal and stored blocked status, repairs the asset and proves successful fresh revalidation. PASS across the entire PHP matrix.

### Defect R266 — a late missing rollback original could create a partial provider compensation

**Baseline root cause:** `ProviderExitService::rollback()` verified each source object's bytes only when it reached that item. In a multi-item plan with an intact first original and absent second original, the first owner record could be remapped to source before processing the second item's missing original. This permits an avoidable half-rolled-back plan.

**Fix:** Before the first compensating update, an independent preflight now traverses **every planned `switched` item**, checks fresh record/current mapping/previous key/provenance presence, and performs bounded original-source SHA-256 and size verification for records still mapped to target. The original per-item action-time verification remains. No actual source remap begins if any original is missing.

**Regression:** `tests/review-round-266-provider-rollback-all-sources-preflight.php` constructs encrypted local-provider ciphertexts for two planned assets, removes the second original, proves neither mapping changes, then restores the missing bytes and confirms both may be rolled back. PASS across PHP 8.1/8.3/8.4.

**Remaining concurrency limit:** This protects the known multi-item missing-original case; it **does not** constitute an atomic transaction or an owner/provider write fence against mutations after preflight.

### Defect R267 — malformed Rights caused unhandled exception rather than a durable blocked restore

**Baseline root cause:** The `RestoreService::rights()` branch accessed `$a['rights']` and passed it to a strictly typed rights verifier without validating an array. A missing rights payload raised a warning/TypeError, aborting reconciliation without reliably marking the restore/gate `blocked`.

**Fix:** Invalid/non-array rights now yield `false`; unexpected rights-validation throwables are also treated as a failed check. The existing explicitly denied `rights_operation_denied` handling is preserved. This is correct fail-closed behavior when the authoritative policy is malformed.

**Regression:** `tests/review-round-267-restore-rights-malformed-failclosed.php` seeds a missing rights-policy record, verifies persistent `blocked` restore and gate without PHP diagnostics, and validates repaired rights. PASS across matrix.

### Defect R268 — missing Tombstone `asset_id` could be wrongly treated as nonconflicting evidence

**Baseline root cause:** `RestoreService::tombstones()` used `(string)$t['asset_id']` without checking presence or canonical identity. A malformed missing `asset_id` might be cast to an empty ID and accepted when `RecordStore::get()` returned null; PHP warnings also failed the quality gate.

**Fix:** Check tombstone `asset_id` is a present, nonempty, canonical string with max 96 chars. Any malformed value returns `false` so reconciliation persists a blocking status.

**Regression:** `tests/review-round-268-restore-tombstone-identity.php` starts a restore with a tombstone missing `asset_id`, verifies the persisted `blocked` state and inability to authorize serving, repairs it to point to a deleted asset and verifies reconciliation passes. PASS across matrix.

## 4. Actual work accounting

| Metric | Verified result |
|---|---|
| Executed distinct selected Review Scripts | **40** |
| Final PASS markers on exact code+test HEAD | **40/40** |
| Newly confirmed source-level defects | **4** |
| Fixed in Draft PR source | **4/4** |
| New mandatory regressions | **4** (R265–R268) |
| Initial intermediate CI failure | **1 bad HEAD; 3 failed PHP jobs**, investigated and repaired |
| Corrected code+tests GitHub CI | **6/6 SUCCESS** |
| Production/Hostinger deploy and external integration acceptance | **NONE** |

## 5. Governing and native ownership review

Original attached governing documents were read: **Three Central Plans Consolidated Governing Master Plan 2026**, **File 12 PDF Library v1.1 Future24 Amended 2026**, **File 21 Home and News Feed NG30 Amended**, and other available primary owner DOCX files. The independently indexed original **CF-04 Conditional Complete Master Plan v1.0** was found and read (33 pages); the exact separate **CF-04 amended v1.1 Future-40 Word original** was **not found** despite targeted library search.

- The central governing plan requires Requirement ID, canonical owner, affected data, migration, test, rollback, source/package evidence, staging acceptance and explicit founder approval. No staging/live state may be inferred from the open PR.
- CF-04 v1.0 explicitly treats the module as conditional shared binary processing/delivery, with File 10/11 video/reels owners, File 12 PDF owner, File 17 private owner, File 21 publisher/feed and File 22 composer retaining independent canonical entity/business decisions.
- File 12 expressly demands DB/object/key backup coherence, restore proof and local/outbox transaction ownership before shared media is considered migrated.
- File 21 expressly demands native News Feed/Newsroom release and rollback evidence and must not treat media helper output as publishing authority.
- These four fixes remain in CF-04 only; they do not modify any external owner's WordPress rows or grant CF-04 access to private content without an owner decision.
- A read-only `WPVibe::list_sites` connection check returned **NO CONNECTED SITES**. It cannot establish live WordPress/plugin/runtime/DB parity. No host connected or runtime source observed; the user's explicit authorization will be required to attach a site.

### Exact previously checked adjacent repository `main` source HEADs (never claim deployment)
| Module | GitHub source HEAD |
|---|---|
| File 10 | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` |
| File 11 | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` |
| File 12 | `2b369ef2d609247896e1ab32118f1dc498407f51` |
| File 17 | `8ae656e51796d1f05865d8be5dca2480443d79ca` |
| File 21 | `f2eb7e95ddea327af36ea725ffb923b029f885e6` |
| File 22 | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` |

These unchanged sibling source HEADs are reference points only. None proves the version deployed to live/staging.

## 6. Open blockers and exact closure criteria — DO NOT CLAIM ALL WORK COMPLETE

### A. Durable provider exit for more than 2,000 eligible objects — OPEN
**Current source fact:** `ProviderExitService::plan()` uses complete 250-keyset enumeration but explicitly rejects a 2,001st eligible object. This is correct protective behavior, not full high-scale implementation.

**Required implementation still pending:** immutable plan generation and source-provider indexed inventory; persistent item child rows/segment counters, resumable copy/verify/switch/reverse steps with CAS and leases; concurrent new-owner-write barrier; same-physical-object reference cohesion; page/segment digest and checkpoint; crash recovery and rollback across partial segments; verified final source-drain and durable deferred cleanup; signed final credential retirement and evidence. No simple cap increase should masquerade as this redesign.

**Acceptance:** >2,000 objects and duplicates, mixed deleted/legal-hold/region-denied records; crash after each step; simultaneous File17 messages and File12 PDFs; safe replay; no lost or cross-tenant data; real MySQL/provider host tests.

### B. Durable key rotation above 2,000 live record references — OPEN
**Current source fact:** `KeyRotationService::rotateAll()` scans full inventories but safely refuses more than 2,000 active references before crypto mutation.

**Required implementation still pending:** persistent physical `provider/object_key` group state with durable leases, owner/alias reference fencing, chunked and checkpointed KMS rotation, atomic group references/mapping CAS, old-key/ciphertext retention until zero live references, legal-hold-aware cleanup, immutable audit and compensating rollback. No new key can be called universally active until migrated references and envelopes are independently reconciled.

**Acceptance:** >2,000 live aliases across asset and derivative families, process termination at each stage, missing KMS key, wrong key ID, shared-ciphertext drift, rekey collision, read-after-rotate and rollback on actual providers.

### C. Snapshot-consistent, checkpointed, real-world Restore — OPEN
**Current improvement:** full keyset enumeration, R265 action-time fresh recheck, R267 malformed rights gate and R268 malformed tombstone gate. **These are four fixes but do not create a coherent immutable DB+object/key snapshot or a resumable restore executor.**

**Required implementation still pending:** precise snapshot generation ID, DB transaction/snapshot binding to object listing/keyring/policy/rights/tombstones; durable paginated verification checkpoints and checkpoint version fences; freeze/replay of writes across scan; deterministic recovery after interruption; end-to-end media fetch/decrypt and File17 private/file12 PDF access on isolated staging.

**Acceptance:** Hostinger-equivalent MySQL, snapshot manifest SHA and actual restored object hash/key decrypt checks, induced mid-reconcile crash, concurrent mutations, bad backups, legal holds and inability to release the serve gate until all checks are fresh.

### D. Other external blockers — OPEN
1. Independent approved original CF-04 **amended v1.1 Future-40** Word plan is inaccessible; every amended clause cannot yet be verified against primary specification.
2. **No WordPress site connected** in available live-site integration; installed plugin SHA, actual binary/package checksum, active files, DB version/schema/index migration status, `wp_options`, runtime errors/logs, active dependency versions and owner/CF-04 parity are unverified.
3. Real worker/scanner isolation, CDN/signed URL and purge, KMS, object-provider credentials, simulated outage, 1 GiB transfer/load, browser/RTL/WCAG accessibility, privacy owner native flows and detailed performance are unverified.
4. Hostinger staging rehearsal, rollback/restore proof, release authorization and live smoke re-test are absent.

## 7. Mandatory Live-First ledger

| Separate reality | Current evidence |
|---|---|
| Repository `main` exact source HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR exact source + tests HEAD | `fb9f64e096ca3412023e7de713558be3b00c0ca7` (prior to docs amendment) |
| Candidate plugin runtime | `1.3.0-rc.1` |
| Repository schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Source tests pass, but the defects have not been established as present on production, and their fixes have **not** been deployed. No PR merge, activation, runtime plugin update, database modification, live fix or live issue resolution was performed. Final documentation HEAD requires its own separately checked CI run.
