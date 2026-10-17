# CF-04 R274 — Forty Executed Reviews and Real Work on Remaining Blockers (2026-10-17)

## Source freeze and truth boundary
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Initial `main` source HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972` (not known deployed).
- Initial open/unmerged Draft PR #5 source HEAD: `83c1d1157bbf8f92886d522d9301546d6e122ab6`.
- **Implemented code + mandatory regression HEAD before documentation:** `9731235f32d7c8cfe4d87adab02d0d1019194c9f`.
- Candidate plugin/runtime `1.3.0-rc.1`; repository schema `1.5.0`, API contract `1.5.0`; disabled/fail-closed by default.
- Exact source+tests CI: pull_request run `39039030686` and push run `39039027173`; **all six quality jobs SUCCESS** (PHP 8.1/8.3/8.4 on both events). Independently filtered the PHP 8.4 job log and verified the distinct **40/40 R## review PASS markers**, including R270–R273 and `CF-04 QUALITY GATE: PASS`.
- These are **40 separately executed scoped PHP regression review scripts**, plus targeted source/manual and governing/sibling plan inspection. Do not represent them as 40 independent exhaustive audits, 40 actual live/staging deployments, or a proof of complete implementation.

## The forty distinct executed code checks
| No. | Review | Source/contract subject | Governing or native owner | Outcome |
|---|---|---|---|---|
| 1 | R70 | Independent derivative and parent policy | File 17/CF04 FR027 | PASS |
| 2 | R72 | Large keyset bounded record scans | DB | PASS |
| 3 | R78 | Shared ciphertext group equality | Key rotation | PASS |
| 4 | R81 | Source drain before provider activation | Provider Exit | PASS |
| 5 | R103 | Private object storage filesystem | File24 privacy | PASS |
| 6 | R104 | Concurrent upload/quota CAS | Files10/12/17 | PASS |
| 7 | R105 | Idempotent media processing | Media workers | PASS |
| 8 | R106 | Private delivery revocation | File17 | PASS |
| 9 | R107 | Action-time legal holds | Retention | PASS |
| 10 | R108 | Operations reconciliation | Incident recovery | PASS |
| 11 | R109 | REST request contract | File00/20 | PASS |
| 12 | R110 | Durable schema/SQL CAS | MySQL | PASS |
| 13 | R111 | Central-plan contract evidence | Governing plans | PASS |
| 14 | R115 | Public safe C0 serving | File21 | PASS |
| 15 | R118 | File17 transfer runtime | File17 | PASS |
| 16 | R123 | Fresh owner upload authorization | File17/00 | PASS |
| 17 | R124 | Dead-letter worker gate | Operations | PASS |
| 18 | R125 | Scoped delivery grants | File17 | PASS |
| 19 | R126 | Job state machine | Processing | PASS |
| 20 | R127 | Provider exit purge reconciliation | FR027 | PASS |
| 21 | R129 | Schema/run-time truth | Release | PASS |
| 22 | R143 | Provider source/target identity | Provider exit | PASS |
| 23 | R144 | Rotation identity proof | Encryption | PASS |
| 24 | R150 | Retention fail-closed | File24 | PASS |
| 25 | R151 | Deletion lifecycle correctness | Privacy | PASS |
| 26 | R152 | Deletion audit recovery | Audit | PASS |
| 27 | R153 | Tombstone and audit binding | Restore | PASS |
| 28 | R184 | Audit evidence recovery | Compliance | PASS |
| 29 | R205 | Expired upload cleanup | Workers | PASS |
| 30 | R220 | Background keyset fairness | Operations | PASS |
| 31 | R231 | Source-reference live witness | Provider and KMS | PASS |
| 32 | R245 | Restore complete keyset | FR032 | PASS |
| 33 | R265 | Latest inventory before reopening serve | Restore | PASS |
| 34 | R266 | All-source preflight before rollback changes | Provider Exit | PASS |
| 35 | R267 | Malformed rights fail-closed | Restore | PASS |
| 36 | R268 | Malformed tombstone asset ID fail-closed | Restore | PASS |
| 37 | R270 | NEW purge rechecks target SHA/size | Provider Exit | PASS |
| 38 | R271 | NEW blocking restore gate and version-drift fingerprint | Restore | PASS |
| 39 | R272 | NEW skip unrelated current-key aliases from selected work | Key Rotation | PASS |
| 40 | R273 | NEW 2301-item durable staged inventory/checkpoint replay | Provider Exit | PASS |

## Three new confirmed implementation defects fixed

### R270 — deleted original provider ciphertext based on a stale target verification
**Baseline:** `ProviderExitService::shadowVerify()` checks copied target bytes, but `purgeSource()` could later delete the original source ciphertext based only on a target mapping and prior shadow check. If target storage lost/corrupted its bytes after shadow verification, the source purge was destructive with no new byte-validity proof.
**Fix:** `purgeSource()` now reacquires the target provider and decrypts/hashes **actual live target bytes at the destructive boundary**; verifies SHA-256 and size against planned original content; missing bytes stop with provider `object_not_found`, changed bytes fail `provider_exit_purge_target_integrity_failed` and **source ciphertext remains**. Existing live source-reference and legal hold gates are preserved.
**Executed test:** `tests/review-round-270-provider-purge-live-target-integrity.php` exercises copy→shadow→switch, then missing target, corrupt target, and restored correct target in encrypted local object storage. The first two do not purge original; final correct target may complete. PASS on PHP matrix.

### R271 — restore reconciliation had no pre-scan blocking fence or source-version drift witness
**Baseline:** A new `RestoreService::reconcile()` could examine records while a formerly `serve_authorized` gate stayed authorized until the end, and it could not detect a concurrent asset/manifest/tombstone etc change during its own long multi-pass scan.
**Fix:** Reconciliation uses optimistic CAS to persist `restore_gate.status='rebuilding'` **before** validating data; a source fingerprint SHA-256 over authoritative record-type/payload sequences is computed before and after verification. If fingerprints differ, `checks.inventory_stable=false` and status `blocked`; the fingerprint is recorded both in the restore row and gate. Malformed scan/provider checks are handled fail-closed. A check exception leaves the already-blocking gate in place.
**Executed test:** `tests/review-round-271-restore-inventory-fingerprint-fence.php` registers a provider that observes the gate and inserts a late asset during verification. It proves gate is blocking *before* provider health checks, changes produce a blocked restore, stale serving is denied, and stable subsequent reconciliation yields a new fingerprint and can allow serving. PASS.
**Limit:** Fingerprint checks are **not an immutable atomic DB+object snapshot**, cannot prove snapshot-generation cross-resource parity or prevent mutation after the final check.

### R272 — a >2,000-record already-current library blocked rotation of one stale ciphertext
**Baseline:** `KeyRotationService::rotateAll()` counted all active asset/derivative records toward the 2,000-reference in-memory rotation limit **before** filtering groups that actually needed re-encryption. A 2,600-object all-current site could refuse even a no-op or one-stale-object rotation.
**Fix:** Two full streamed bounded-keyset inventory passes: first identify the physical provider/object-key groups that actually need rotation; second collect **all aliases within only those groups**, including already-current aliases sharing the same physical key. Keep the 2,000 **affected alias/group** safety cap and whole-group SHA/size consistency checks. The code does NOT claim it can rotate >2,000 affected aliases atomically.
**Executed test:** `tests/review-round-272-selective-key-rotation-capacity.php` creates 2,600 already-current asset records and one real old-key encrypted object, verifies one actual successful rotation and subsequent no-op without capacity error. Prior R257 deliberately >2,000 affected work rejection remains green.

## One pre-existing incomplete high-scale capability materially advanced, NOT fully closed

### R273 — durable segmented provider exit inventory foundation
**Previous blocker:** Executable `ProviderExitService::plan()` still serializes <=2,000 eligible objects into a single plan record. Above that it intentionally fails closed; no durable segmented inventory existed.
**New code:** Separate **`ProviderExitInventoryStage`** includes:
- `begin(source,target,actor)`: admin-only capability/provider validation; creates durable `provider_exit_inventory` staging parent with cursor, chain hash, phase and counters.
- `advance(id,actor)`: exactly **one** 250-ID source page per call, asset then derivative family; performs parent/hold/region/object identity checks; persists an independent bounded `provider_exit_inventory_segment` item journal with page SHA before CAS-advancing the parent cursor and chained digest.
- Recovery: if a segment exists but parent checkpoint did not commit, verify its page fingerprint, reuse without duplication; changed source page is refused with `provider_exit_inventory_drift`.
- The stage status expressly ends in `inventory_complete_unfenced` and each item remains `planned_unfenced`. **It cannot copy, switch, purge, revoke credentials, or assert an immutable/fenced migration snapshot**. It is intentionally not wired as a destructive execution API.
**Executed test:** `tests/review-round-273-provider-exit-durable-segmented-inventory.php` builds 2,301 source-provider assets (more than legacy 2,000), journals and counts all eligible entries, verifies every page <=250, demonstrates segment replay after a simulated parent-checkpoint crash, denies a subsequently edited source page, and ensures no source switching, key rotation or executable provider_exit plan was created. PASS.

## What was actually completed in this turn?
| Item | Finding | Result |
|---|---|---|
| New dangerous/incorrect code paths | **3** (R270, R271, R272) | **3 corrected + verified on source/CI** |
| Previously missing stage architecture | **1 partially addressed** (R273) | Durable 250-row non-destructive staging implemented, tested **2,301 entries**, NOT an executable cutover |
| New mandatory tests | **4** (R270–R273) | **4 PASS** |
| Independently executed selected regression review scripts | **40** | **40/40 PASS** |
| Exact final source+test PHP matrix | **6 jobs** | **6/6 SUCCESS** |
| Live plugin/DB/provider configuration changes | **0** | Not attempted |

## Relationship to central and independent file specifications
- Primary materials actually consulted: attached **Sabri Three Central Plans consolidated governing master** DOCX; File 12 PDF Library amended DOCX; File 21 Home/News Feed NG30 amended DOCX; accessible CF-04 conditional **v1.0** master plan in the Library. The central plan requires canonical owner, staged release, parity evidence, backup/restore, rights, explicit authorization, change control and rollback proof.
- File 00 remains user/authentication source; File 10 owns video; File 11 reels; **File 12 remains PDF content, rights and reader owner**, **File 17 remains messages/recipients and private transfer owner**; File 21 publishing/feed, File 22 composer; File 24 security and privacy. CF-04 owns shared binary processing/secure delivery **only on approved activation**, never these native owners' canonical database truth.
- New R270 protects File17 and File12 referenced bytes during an exit. R271 protects restoration serving after native owner record changes. R272 does not alter owner semantics. R273 inventories CF-04 records only and has **no permission to consume or migrate sibling native records directly**.
- **Exact amended CF-04 v1.1 Future-40 original Word file remains missing** from current available attachments/Library despite search. A source matrix naming its version is not source-document parity proof.

### Adjacent GitHub `main` HEADs freshly checked (source facts, NOT live parity)
| Owner | HEAD |
|---|---|
| File 10 Video | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` |
| File 11 Reels | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` |
| File 12 PDF | `2b369ef2d609247896e1ab32118f1dc498407f51` |
| File 17 Network | `8ae656e51796d1f05865d8be5dca2480443d79ca` |
| File 21 Home/News | `f2eb7e95ddea327af36ea725ffb923b029f885e6` |
| File 22 Composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` |

## Remaining blockers — precise closure criteria

**A. Provider exit >2,000 object migration execution — STILL OPEN.** R273 closes an initial inventory journaling prerequisite, *not* the actual 2,001+ per-item copy/shadow/switch/purge, durable CAS leases, reference/owner-write fencing, rollback recovery and final credential retirement. Implement segmented work-step executor, role approval/four-eyes authorization, retry/checkpoint and full source+target dry-run; prove real MySQL/object-store staging & 1GiB load.

**B. Key Rotation >2,000 actually stale references — STILL OPEN.** R272 eliminates false blocking by unrelated no-op records but does not implement a durable grouped work queue/leases, KMS envelope provenance and staged >2,000 physical references shared among owner domains. Existing affected group cap remains intentional. Require real provider recovery, interruption, retained old-key test.

**C. Restore immutable consistent backup with durable checkpoints — STILL OPEN.** R271 protects an observed during-scan mutation and records a per-run fingerprint; it does not freeze original snapshot generation across DB/object/KMS, persist page-by-page resume state, or support verified isolated restore, concurrent writes, cross-resource integrity proofs and rollback rehearsal.

**D. Remaining external release acceptance — STILL OPEN.** No WordPress site is connected in live-site connector. Deployed artifact/version/checksum, DB schema & wp_options migration, native plugin version parity, runtime errors, scanner/KMS/CDN, 1GiB load, privacy RTL/WCAG, staging rollback and founder release approval remain unverified. No live re-test.

## Live-first mandatory ledger
| Reality | Status |
|---|---|
| GitHub `main` source HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR code+test SHA | `9731235f32d7c8cfe4d87adab02d0d1019194c9f` (prior to this report commit) |
| Runtime candidate | `1.3.0-rc.1` |
| Repository DB schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No merge to main, no staging/live changes, no DB migration and no actual server defect closure occurred. After this report commit, separately obtain green CI at its exact resulting SHA; previous code SHA results cannot substitute for latest HEAD checks.
