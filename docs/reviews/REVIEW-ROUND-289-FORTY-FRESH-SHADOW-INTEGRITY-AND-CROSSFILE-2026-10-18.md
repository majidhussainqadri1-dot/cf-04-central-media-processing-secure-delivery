# CF-04 R289 — 40 Fresh Source/Cross-Plan Checks, Shadow-Ledger Fix and Exact-Head QA (2026-10-18)

## Governing truth and frozen state
- Repository `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Base `main`: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Initial Draft PR #5 HEAD: `d61c617c2135ad7f03c368789cdc88f5527ef082`.
- Source+regression HEAD before this documentation addition: `863a2d38f331add484ed7e32c4fb98e0007a25c7`.
- Candidate runtime `1.3.0-rc.1`, source schema and API contracts `1.5.0/1.5.0`; disabled/fail closed unless approved.
- Exact code+test CI run **39115863429**, PHP 8.1 job **117641428996**, PHP 8.4 job **117641429091**, PHP 8.3 job **117641429124**: **3/3 SUCCESS**.
- Mandatory `ROUND 174 GATE COVERAGE: PASS (195 PHP, 2 Python review tests)`, `REVIEW ROUND 288 SHADOW-COPY JOURNAL IDENTITY: PASS`, `CF-04 QUALITY GATE: PASS` on all three PHP versions.
- Deterministic ZIP digest on this code+test SHA: `221303ab2f5b5ecc724a993de8319af295c8c65d960901522a4c2c164e89666c`.
- Scope: **40 individually named targeted source/contract checks**, with exact source inspection, plus mandatory automated suites on three PHP versions. These are not 40 independent complete codebase audits, not 40 live runs, and static source presence is not real provider interoperability proof.

## Original material / evidence limits
Read locally available **Three Central Plans Consolidated Governing Master Plan**, **File 12 PDF Library amended v1.1**, **File 21 Home/News Feed amended NG30**, and the previously indexed **CF-04 Conditional Complete Master Plan v1.0**. Governing ownership: CF-04 binary infrastructure only after approved extraction; File00 identity, File10 video, File11 Reels, File12 PDF library/reader/rights, File17 conversations, File21 publisher, File22 composer, File20 shell, File24 assurance, File25 visuals. Technical screening is not editorial or clinical approval.
The **independently amended CF-04 v1.1 Future-40 primary Word file is unavailable**. Repository traceability or old v1.0 cannot prove complete clause-by-clause parity with the independent v1.1. No production context was available.

## Exactly 40 reviews (pre-fix 36 PASS, 4 explicit proof gaps; post-fix 40 source controls present)
| # | Reviewed safeguard/contract | Source/evidence | Result after fix |
|---|---|---|---|
| 01 | Runtime/spec status remain distinct | Repository matrix and external gates | PASS |
| 02 | Four cron workflows require approved runtime | class-scm-plugin.php; previous R285 | PASS |
| 03 | File 00 remains verified identity owner | DomainRegistry::call('file00',...) | PASS |
| 04 | File 10 owns video and player truth | Central plan and File10 domain delegation | PASS: owner boundary; live integration unverified |
| 05 | File 11 Reel entity/feed remains native | No duplicate Reel owner in CF04 | PASS: source boundary |
| 06 | File 12 PDF catalog, rights, reader remains native | File12 Word plan; RightsPolicy/DocumentPipeline | PASS: source boundary |
| 07 | File 17 messaging and recipient truth remains native | Native transfer file17 contract | PASS: source boundary |
| 08 | File 21 publishing and moderation remains native | File21 Word plan; domain callbacks | PASS: source boundary |
| 09 | File 22 composer remains orchestration owner | Domain contract; no second composer | PASS: source boundary |
| 10 | File 24 privacy/security assurance remains separate | Central plan; native enforcement | PASS: source boundary |
| 11 | File 20 shell/File25 visual-system remain external | Central/owner plan | PASS: source boundary |
| 12 | Provider inventory requires elevated admin | ProviderExitInventoryStage::begin | PASS |
| 13 | Source and destination must differ | provider_exit_identity_invalid | PASS |
| 14 | Source+target require copy/delete/streaming | ProviderExitInventoryStage capability loop | PASS |
| 15 | Inventory scans 250-row keyset pages | RecordStore::scan | PASS |
| 16 | Asset and derivative inventories distinct | ProviderExitInventoryStage families | PASS |
| 17 | Derivative requires non-deleted parent | provider_exit_derivative_orphaned | PASS |
| 18 | Provider inventory checks legal holds | LegalHoldService::assertNoHold | PASS |
| 19 | Provider inventory checks residency | ResidencyCryptoService::assertProviderRegion | PASS |
| 20 | Provider inventory validates SHA/object identity | provider_exit_identity_invalid | PASS |
| 21 | Inventory end-to-end source revalidation | ProviderExitInventoryStage::verifyComplete | PASS |
| 22 | Staged manifest exactly equals current source | canonicalJson(expected/items) | PASS |
| 23 | Shadow worker requires admin authorization | ProviderExitShadowCopyStage::loaded | PASS |
| 24 | Shadow copy only after complete inventory | inventory_complete_unfenced | PASS |
| 25 | Shadow rechecks authoritative owner mapping | ProviderExitShadowCopyStage::expected | PASS |
| 26 | Copy checks source and target actual hashes | hashObject(source/target) | PASS |
| 27 | Each verified copy has durable CAS cursor | RecordStore::put shadow segment | PASS |
| 28 | Copy batch limited to 25 | copySegment limit guard | PASS |
| 29 | Cooperative shadow copy supports resumable progress | advanceInventory | PASS |
| 30 | Shadow path never activates target provider | No ProviderRegistry::activate in stage | PASS |
| 31 | Shadow path never purges source ciphertext | No source->delete in stage | PASS |
| 32 | Verification status remains unfenced | shadow_verified_unfenced | PASS |
| 33 | Source and target proof content bound | hash equality on actual bytes | PASS |
| 34 | Stored copy proof status validated | R288 source correction and regression | PASS after fix |
| 35 | Saved inventory ID bound to copy journal | R288 source correction and regression | PASS after fix |
| 36 | Saved segment ID bound to copy journal | R288 source correction and regression | PASS after fix |
| 37 | Saved actor identity validated | R288 source correction and regression | PASS after fix |
| 38 | Key rotation candidate stage is not rekey cutover | KeyRotationInventoryStage | PASS: preparatory |
| 39 | Restore checkpoint is not restore execution | RestoreCheckpointStage and gates | PASS: preparatory |
| 40 | R288 incorporated into mandatory quality gate | tools/quality-check.sh + CI R174 | PASS |

The first automated search produced 5 apparent cross-file missing labels (#4,#8,#9,#10,#11), but those were **false alarms caused by literal human-readable name matching**. The actual native owner names are lower-case canonical domain IDs (`file10`, `file21`, etc.) in code and the Word governing boundary is explicit. They are excluded from proven defect totals. Owner-implementation parity against installed sibling modules is still unverified.

## Confirmed source defect — R288 (one root, four affected controls)
The final verification of a completed provider shadow-copy segment compared owner item IDs, source key, destination key, SHA256 and size and actually rehashed source and target ciphertexts, but it did **not check four integrity fields of saved evidence**:
1. Per-copy `status === copied_unfenced`.
2. Saved `inventory_id` matches verified inventory.
3. Saved `segment_id` matches source inventory segment.
4. Saved `actor_id` is a valid positive integer.

**Root cause:** Canonical record key lookups and byte matching were incorrectly treated as sufficient evidence of the entire saved proof envelope. The consequence was acceptance of altered audit metadata, not proven external data exposure or live migration. This is a GitHub source defect; live occurrence unverified.

**Correction** in `sabri-central-media/includes/class-scm-operations.php` commit `ef8247223d65434c5ecd95ac14fddbdec79213d4`: both `copySegment()` (including completed idempotent replay) and `verifyComplete()` now validate the binding of inventory, segment, actor, list structure and per-item proof status/source identity. The system still cannot cut over, alter native owners, purge originals or retire credentials from the unfenced stage.

**New adversarial regression** `tests/review-round-288-shadow-copy-journal-identity.php` commit `8fc1c7e4b9941f771b494e32efbcb44d8a96d5de`: seeds actual encrypted private test object, completes shadow copy, corrupts saved proof five ways (invalid per-copy status; changed inventory ID; changed segment ID; zero actor ID; sparse/non-list copies). Replays and full verification reject each mutation, restore genuine proofs and confirm no switch or delete. Mandatory CI registration commit `863a2d38f331add484ed7e32c4fb98e0007a25c7`.

**Accounting:** New confirmed source root defects **1**; source fixes **1/1**; affected source control gaps **4/4**; new mandatory regression tests **1**; CI PHP 8.1/8.3/8.4 **3/3 successful on exact code+test HEAD**. This excludes historical defects and false-alarm search labels.

## Current source-only sibling freeze (not cross-deployment parity)
| Native owner | Repository | `main` HEAD observed | Interpretation |
|---|---|---|---|
| File10 | `10-video-wall-and-educational-broadcasting-foundation` | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3` | CURRENT SOURCE ONLY; deployed/contract parity unverified |
| File11 | `11-reels-and-short-video-discovery-foundation` | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b` | CURRENT SOURCE ONLY; deployed/contract parity unverified |
| File12 | `12-pdf-library-and-digital-reading-foundation` | `2b369ef2d609247896e1ab32118f1dc498407f51` | CURRENT SOURCE ONLY; deployed/contract parity unverified |
| File17 | `17-sabri-network` | `8ae656e51796d1f05865d8be5dca2480443d79ca` | CURRENT SOURCE ONLY; deployed/contract parity unverified |
| File21 | `sabri-complete-home-news-feed` | `f2eb7e95ddea327af36ea725ffb923b029f885e6` | CURRENT SOURCE ONLY; deployed/contract parity unverified |
| File22 | `sabri-universal-post-composer` | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a` | CURRENT SOURCE ONLY; deployed/contract parity unverified |

Some earlier CF-04 inventories cite older inspected sibling SHAs. Updating their `inspected_ref` to the above without reviewing exact files would falsely imply contract inspection, so historical inventory is not rewritten. These fresh refs are an **evidence snapshot only**. Need contract-by-contract comparison, parity tests and domain-owner sign-off at fixed refs. File11/File12 current main trees were minimal in the observed source trees; no deployed inference.

## Remaining unimplemented / not independently accepted (not declared Resolved)
1. Large provider exit: all-owner write fencing, >2,000 authoritative mapping cutover/rollback, provider purge and credential retirement still not proved at scale. Current stage remains `unfenced`.
2. Large KMS key rotation: per-physical-object aliases, transactional durable 2,000+ re-encryption with provider/KMS acceptance, rollback and cleanup not completed.
3. Restore: durable inventory and verification exist, but consistent database+object+key snapshot and executable cross-resource restore with interruption recovery and real MySQL tests are not completed.
4. Latest native File10/11/12/17/21/22 contract parity and independent amended CF-04 v1.1 primary source remain outstanding.
5. Real Hostinger staging, PHP extensions/WordPress stack, provider/KMS/CDN integrations, privacy/RTL/accessibility/load/penetration, migration/restore/rollback rehearsal, Founder signoff and live deployment/retest remain outstanding.

## Required Live-First Ledger
| Field | Status |
|---|---|
| Repository main HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 code/test HEAD before report | `863a2d38f331add484ed7e32c4fb98e0007a25c7` |
| Candidate runtime/schema/contract | `1.3.0-rc.1 / 1.5.0 / 1.5.0` |
| Deployed version / installed package checksum | **UNVERIFIED** |
| DB version | **UNVERIFIED** |
| Migration state | **UNVERIFIED** |
| Live verification status | **NOT PERFORMED** |
| Production changes | **NONE** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

A report-only commit changes HEAD and must have its own exact-head green CI before publication as reviewed.
