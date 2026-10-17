# CF-04 R261 — 40 executed distinct regression reviews; source-safe provider rollback (2026-10-17)

## Initial exact GitHub source freeze

- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- GitHub `main` **source-only truth** at the initial freeze: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Initially checked, open/unmerged Draft PR #5 HEAD: `86eef9dff73e33f53f13e1377c8ca74738805227`.
- Code/test commit after new corrections: `4b46dd5f3fcb20431a9f1515dacf7f3a3175b7bf`.
- Candidate runtime `1.3.0-rc.1`; source schema/contract `1.5.0/1.5.0`; default disabled/fail closed.
- Checks are **40 distinct existing/new executed PHP Regression Review Scripts**, plus targeted manual `ProviderExitService::rollback` source review and plan/owner comparison. They are **NOT** forty exhaustive full-repository rewrites, forty fresh staging environments or forty complete live journeys.

### Latest exact code+tests validation
- GitHub Actions `pull_request` run `39010481054` and `push` run `39010478077`, each with successful PHP 8.1, 8.3, 8.4 quality jobs: **6/6 successful** on code/test SHA `4b46dd5f3fcb20431a9f1515dacf7f3a3175b7bf`.
- Scanned the PHP 8.4 CI log and directly found **40/40 distinct R## final PASS markers**; new `REVIEW ROUND 259 PROVIDER ROLLBACK SHARED-TARGET REFERENCE: PASS`, `REVIEW ROUND 260 PROVIDER ROLLBACK SOURCE-BYTE INTEGRITY: PASS` and `CF-04 QUALITY GATE: PASS` explicitly present.
- Both new tests are registered in mandatory `tools/quality-check.sh`. This is code/test evidence, not deployed/staging MySQL or real external provider evidence.

## 40 distinct executed checks and owner/plan coverage

| No. | Test | Test subject | Master/cross-file duty | Outcome |
|---|---|---|---|---|
| 01 | R70 | Derivative provider inventory and parent/hold linkage | CF04-FR-027; File 17 | PASS |
| 02 | R72 | Bounded RecordStore inventory and overflow | Database integrity | PASS |
| 03 | R78 | Shared ciphertext groups and expected identity | Encryption | PASS |
| 04 | R81 | Source drain before provider activation | Provider exit | PASS |
| 05 | R103 | Private storage root outside public WordPress | File 24 privacy | PASS |
| 06 | R104 | Upload quota reservation under concurrency | Upload/Files 10,12,17 | PASS |
| 07 | R105 | Process replay/idempotency controls | Processing | PASS |
| 08 | R106 | Private delivery token/grant revocation | File 17 | PASS |
| 09 | R107 | Action-time legal-hold and deletion authorization | Lifecycle | PASS |
| 10 | R108 | Operational recovery and reconciliation | Operational acceptance | PASS |
| 11 | R109 | REST input/owner contract boundaries | Files 00 and 20 | PASS |
| 12 | R110 | SQL indexed schema/locking readiness | Persistence | PASS |
| 13 | R111 | Governing-plan cross-contract guards | Central plans | PASS |
| 14 | R113 | Upload runtime off-by-default gating | Release | PASS |
| 15 | R114 | Worker processing runtime gates | Release | PASS |
| 16 | R115 | Public-media C0-only delivery gating | File 21 | PASS |
| 17 | R118 | Canonical transfer runtime gating | File 17 | PASS |
| 18 | R119 | Future-40 governance scope | CF04 future | PASS |
| 19 | R121 | Owner contract version mapping | Cross-module | PASS |
| 20 | R123 | Fresh upload authorizations | File 17/00 | PASS |
| 21 | R124 | Dead-letter and runtime worker gates | Operations | PASS |
| 22 | R125 | Signed scoped delivery grants | Private data | PASS |
| 23 | R126 | Job-state contract invariants | Processing | PASS |
| 24 | R127 | Provider purge outcomes and idempotence | Provider exit | PASS |
| 25 | R129 | Schema/version/status truth | Deployment | PASS |
| 26 | R143 | Provider byte and object-key integrity | Provider exit | PASS |
| 27 | R144 | Encryption rotation safety identity | Rotation | PASS |
| 28 | R150 | Retention/legal-hold fail-closed behavior | Data lifecycle | PASS |
| 29 | R151 | Deletion state integrity | Privacy | PASS |
| 30 | R152 | Deletion audit recovery | File 24 | PASS |
| 31 | R153 | Tombstone/audit linkage | File 24 | PASS |
| 32 | R184 | Audit event binding and traceability | Audit | PASS |
| 33 | R205 | Expired-upload cleanup | Resource lifecycle | PASS |
| 34 | R220 | Durable cursor fairness for cron | Operations | PASS |
| 35 | R231 | Late source-reference guard | Provider exit/rotation | PASS |
| 36 | R245 | Restore keyset inventory and gating | Restore | PASS |
| 37 | R253 | Late source alias before deletion | File 17 | PASS |
| 38 | R254 | Late source alias before credential retirement | Provider exit | PASS |
| 39 | R259 | NEW: shared target reference must survive rollback | File 17 shared attachments | PASS |
| 40 | R260 | NEW: original source bytes verified before remapping | File 17/File 12 | PASS |

## Root cause 1 / R259 — provider rollback deleted a still-referenced target object

**Source evidence:** `ProviderExitService::rollback()` formerly remapped a planned record to `previous_storage` and, unless locked, immediately called `$target->delete($item['target_key'])`. For shared ciphertext referenced by an active sibling or newly introduced File 17 derivative, the first rollback item could delete an object still in active service. This was a proven source-level unsafe deletion path, **not a claim that the live website has actually lost data**.

**Applied fix:** Immediately before any rollback target ciphertext deletion, call `StorageReferenceWitness::first(targetProvider,targetKey)` across both canonical `asset` and `derivative` families. If another live reference exists, **do not delete**; record `target_cleanup_deferred='live_reference'`. Existing legal/WORM lock enforcement and per-record CAS are preserved.

**Executed regression:** `tests/review-round-259-provider-rollback-shared-target.php` creates encrypted local-provider ciphertext, a File 17 asset plus independent derivative alias; proves the alias's physical object survives rollback. Separately validates two planned asset aliases sharing one object: the first cleanup defers while its sibling remains, and physical deletion happens only after the last reference is remapped. PASS under all PHP matrix jobs.

**Remaining condition:** Durably reconciling any `target_cleanup_deferred='live_reference'` target objects left behind by a third-party permanent alias is not fully implemented; cross-domain owner fencing and real concurrent-upload behavior need staged acceptance. There is no atomic database-provider transaction.

## Root cause 2 / R260 — provider rollback trusted previous_storage without re-verifying source bytes

**Source evidence:** `ProviderExitService::rollback()` did not verify `previous_object_key` resolves to an available original source object with the intended SHA-256 and size before changing the active `object_key` and `storage` mapping. A deleted, unavailable, corrupted or changed previous source could turn a valid target into a broken active reference.

**Applied fix:** Before every target-to-original mapping update, `verifyRollbackSource(ObjectStore $source,array $record)` checks canonical expected `sha256/size` and `previous_object_key`, reads the original source object with bounded stream hashing (1 GiB max), and compares both source digest and byte count. Missing source fails closed via the provider's `object_not_found`; mismatched bytes yield `provider_exit_rollback_source_mismatch`. No mapping or target object is changed when the validation fails. This check precedes any destructive target cleanup.

**Executed regression:** `tests/review-round-260-provider-rollback-source-integrity.php` proves that missing original source, forged original source bytes, and intact expected source have distinct safe results, with target ciphertext and active mapping preserved on both failures. PASS under PHP 8.1/8.3/8.4 CI.

**Remaining condition:** This is a before-remap integrity witness, not an atomic no-change guarantee against concurrent source-provider deletion; real lifecycle fencing and staged provider storage tests remain required.

### Change accounting for this turn
- **Two new distinct dangerous source-level implementation omissions** found, both in provider rollback.
- **Two corrected** in Draft PR (R259/R260).
- **Two new PHP tests** and quality-gate registration.
- **40 separately executed selected scripts: 40 PASS**, code+test SHA CI **6/6 SUCCESS**.
- No external companion repository modified; no main merge, staging or live deployment.
- **Three broader existing technical-release blockers remain**: scalable segmented provider migration, durable large-key rotation, snapshot-frozen restore; plus non-technical primary-plan and installed-runtime verification gaps.

## Primary governing document and cross-module comparison

- Actually read the user-supplied **Three Central Plans Consolidated Governing Master Plan 2026** and **File 12 PDF Library v1.1 Future24 Amended** and **File 21 Home/News NG30 Amended** DOCX files. The central plan requires explicit requirement → canonical owner → test → version/package/checksum → staging acceptance → rollback → Founder approval. File 12 assigns PDF rights, source/catalogue, reader state and lifecycle to File 12; File 21 assigns news publishing and feed governance to File 21. In the central plan, CF-04 takes binary processing/delivery only after activation.
- Retrieved available indexed project excerpts for **CF-04 Conditional Complete Master Plan v1.0** describing its functional requirements, transition/recovery, privacy, deployment, rollback and evidence. The exact independent **amended CF-04 v1.1 Future-40 primary Word document is not currently accessible**, so full clause-by-clause amended-source parity is explicitly **NOT VERIFIED**.
- File 17 remains native owner of messages, recipient relationships, blocks and private transfer truth; CF-04 must not delete ciphertext still used by File 17 or File 12 or change those modules' records. R259/R260 address that boundary in CF-04 only. File 00 remains identity owner; File 10 video, File 11 reels, File 21 feed, File 22 composer and File 24 assurance each retain their own canonical ownership.

### Exact sibling GitHub `main` source HEADs at this review — not evidence of deployment
| Owner | Source-repository main HEAD |
|---|---|
| File 10 Video | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` |
| File 11 Reels | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` |
| File 12 PDF | `2b369ef2d609247896e1ab32118f1dc498407f51` |
| File 17 Network | `8ae656e51796d1f05865d8be5dca2480443d79ca` |
| File 21 Home/News | `f2eb7e95ddea327af36ea725ffb923b029f885e6` |
| File 22 Composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` |

## Remaining explicitly open limitations / no unsupported completion claims

1. `ProviderExitService::plan` still limits a single migration manifest to **2,000 eligible items**. At 2,001, it fails closed. It needs durable segmented migration, resumable per-item progress, true owner write fences, independent stale-reference cleanup and provider-level cutover/rollback tests.
2. `KeyRotationService::rotateAll` remains limited to **2,000 active physical record references** per in-memory grouping run. A new persistent grouped-job/lease and real key-custody/provider recovery pipeline is needed for larger inventories.
3. `RestoreService` has keyset enumeration, but still lacks proven immutable database/object snapshot consistency, durable checkpoints, concurrent changes/hold reconciliation, real staging restore and rollback.
4. The exact independently amended CF-04 primary plan v1.1 Future-40 was not available; complete master plan clause parity is unverified.
5. No fresh production/staging plugin version, deployed artifact SHA/checksum, actual installed owner plugin versions, DB schema or `wp_options` migration state, logs, provider/KMS/CDN/worker config, 1 GiB streaming acceptance, legal/rights browser flows, Founder deployment authorization or live verification was obtained.

## Mandatory live-first reality ledger

| Reality | Verified value |
|---|---|
| `main` repository HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR candidate, source + two tests | `4b46dd5f3fcb20431a9f1515dacf7f3a3175b7bf` |
| Runtime candidate version | `1.3.0-rc.1` |
| Source DB schema/API contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No production/staging operations or live bug resolution claimed. The later documentation commit must undergo its own exact-HEAD CI check; a former green SHA cannot be silently transferred.
