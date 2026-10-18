# CF-04 — Fresh Forty-Control Source and Cross-Plan Review (2026-10-18)

## Source freeze and evidence law
- Main branch: `0294442f0fddd1ca5440d9d5ac992ba80aced972` (repository base and not the review candidate).
- Review starting Draft PR #5: `005473de22d61acd71452ea01eb41e5e6fa25de5`.
- Correction/test exact source HEAD: `87eadd9df92122bc764f4c3d036e6367838dbfc5`.
- Runtime candidate: `1.3.0-rc.1`; schema/contract: `1.5.0/1.5.0`; runtime defaults off.
- Current code+test GitHub Actions run: **39096017930**; PHP 8.1 job **117582553512**, PHP 8.3 job **117582553452**, PHP 8.4 job **117582553292**; **3/3 SUCCESS**.
- Quality gate: `ROUND 174 GATE COVERAGE: PASS (194 PHP, 2 Python review tests)`; `R285 PASS`; `R286 PASS`; `CF-04 QUALITY GATE: PASS`.
- The PHP 8.4 raw Actions log contains PASS markers for forty selected distinct existing/new review scripts (R103–R286 selection), counted once per identifier, including R285 and R286.
- Deterministic package SHA256 in PHP 8.4 logs: `e298cdbeb5c190ecf37a3e7bbd5785224d82eb1574d071fd5bafeb2fcd3b2a59`.
- Scope: **40 distinct source-presence/security-boundary assertions** with targeted manual inspection and companion-plan governance comparison, plus 40 selected CI regressions. A source-presence check does not prove full behavior, physical provider acceptance, or clause-by-clause parity.

## Primary source material actually available
- Attached `Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan 2026` (requirement → owner → data → risk → migration → tests → rollback → evidence; separate status levels and explicit staging/Founder approval before live).
- Attached `File 12 PDF Library and Digital Reading v1.1` (PDF bibliographic, reader and rights owner).
- Attached `File 21 Complete Home and News Feed NG30 Amended` (publication, feed, moderation and presentation owner).
- Indexed `CF-04 Conditional Complete Master Plan v1.0` (media binary infrastructure and conditional activation).
- Repository current-plan/Future40 requirements matrix and native owner contracts.
- **Unavailable:** independently amended primary CF-04 v1.1 Future-40 DOCX; complete clause-level proof cannot be given. Existing repository summaries are secondary, not replacements.

## Forty individual source-control checks
| No. | Tested contract | Inspected source | Initially | After correction |
|---|---|---|---|---|
| 1 | Entry disabled by default | `sabri-central-media/sabri-central-media.php` | Present | Source control present; CI green |
| 2 | Runtime disabled gate | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 3 | Persistent schema readiness | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 4 | Keyring readiness | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 5 | Approved object provider | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 6 | File 00 / File 17 owner contract requirement | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 7 | Missing approval hook rejected | `includes/class-scm-contracts.php` | DEFECT | Source control present; CI green |
| 8 | Explicit approved=true claim required | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 9 | Processing cron full activation gate | `includes/class-scm-plugin.php` | DEFECT | Source control present; CI green |
| 10 | Retention cron full activation gate | `includes/class-scm-plugin.php` | DEFECT | Source control present; CI green |
| 11 | Integrity cron full activation gate | `includes/class-scm-plugin.php` | DEFECT | Source control present; CI green |
| 12 | Deletion cron full activation gate | `includes/class-scm-plugin.php` | DEFECT | Source control present; CI green |
| 13 | Runtime/version identity | `sabri-central-media/sabri-central-media.php` | Present | Source control present; CI green |
| 14 | Versioned native owner contracts | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 15 | File 00 verified user assertions | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 16 | Rights/consent normalization | `includes/class-scm-contracts.php` | Present | Source control present; CI green |
| 17 | Resumable upload owner service | `includes/class-scm-upload.php` | Present | Source control present; CI green |
| 18 | Expired upload cleanup | `includes/class-scm-upload.php` | Present | Source control present; CI green |
| 19 | MIME/magic-byte checks | `includes/class-scm-validation.php` | Present | Source control present; CI green |
| 20 | Streaming source digest | `includes/class-scm-validation.php` | Present | Source control present; CI green |
| 21 | Malware scan registry | `includes/class-scm-validation.php` | Present | Source control present; CI green |
| 22 | Isolated processor adapter | `includes/class-scm-validation.php` | Present | Source control present; CI green |
| 23 | Leased processing jobs | `includes/class-scm-processing.php` | Present | Source control present; CI green |
| 24 | Image pipeline | `includes/class-scm-processing.php` | Present | Source control present; CI green |
| 25 | Video/audio processing | `includes/class-scm-processing.php` | Present | Source control present; CI green |
| 26 | Document/PDF processing | `includes/class-scm-processing.php` | Present | Source control present; CI green |
| 27 | Derivative manifest/lineage | `includes/class-scm-processing.php` | Present | Source control present; CI green |
| 28 | Private object store abstraction | `includes/class-scm-storage.php` | Present | Source control present; CI green |
| 29 | Signed expiring grant primitive | `includes/class-scm-storage.php` | Present | Source control present; CI green |
| 30 | Secure delivery service | `includes/class-scm-delivery.php` | Present | Source control present; CI green |
| 31 | Revocation by asset | `includes/class-scm-delivery.php` | Present | Source control present; CI green |
| 32 | Public CDN purge reconciliation | `includes/class-scm-delivery.php` | Present | Source control present; CI green |
| 33 | Retention policy service | `includes/class-scm-lifecycle.php` | Present | Source control present; CI green |
| 34 | Legal/security hold service | `includes/class-scm-lifecycle.php` | Present | Source control present; CI green |
| 35 | Deletion lifecycle and reconciliation | `includes/class-scm-lifecycle.php` | Present | Source control present; CI green |
| 36 | Provider shadow-copy staging | `includes/class-scm-operations.php` | Present | Source control present; CI green |
| 37 | Key-rotation candidate staging | `includes/class-scm-operations.php` | Present | Source control present; CI green |
| 38 | Resumable restore checkpoint | `includes/class-scm-operations.php` | Present | Source control present; CI green |
| 39 | Native transfer ownership boundary | `includes/class-scm-transfer.php` | Present | Source control present; CI green |
| 40 | Mandatory regression test coverage checker | `tests/review-round-174-gate-coverage.py` | Present | Source control present; CI green |

Initial audit: **35 / 40 passed**, with **5 confirmed control gaps**. Two additional initial search-pattern alarms (#1 and #40) were disproved by direct source inspection and excluded from the defect count. Corrected source recheck: **40 / 40 control conditions present**. These conditions are *necessary not sufficient* for full implementation.

## Proven defects, root causes and changes
**DEFECT CF04-R285-A — Cron full-readiness gate omitted in four functions.**
The Plugin cron handlers checked only `RuntimeGuard::enabled()`, then could acquire durable locks and perform operations without checking the approved, current staging/provider/owner activation evidence. This creates a path for work when a runtime constant is enabled prematurely. The source-level defect exists even though no live occurrence is demonstrated.
- **Changed** `sabri-central-media/includes/class-scm-plugin.php` commit `f7c68b17dcb3b9f0f9cff39e897efe0f362af8e0`.
- Before lock acquisition, all four handlers (`cronJobs`, `cronRetention`, `cronIntegrity`, `cronDeletions`) now call `RuntimeGuard::requireReady(['streaming'])` in a guarded block and safely return on denial. A redacted operational alert is attempted; absence of alert infrastructure does not authorize work.
- **Regression** `tests/review-round-285-cron-full-activation-gates.php`: source-bound guard ordering and fail-closed return for all four; mandatory quality script; PHP matrix green. This is static source-structure evidence, not a real WordPress cron/e2e test.

**DEFECT CF04-R286-A — Missing approval filter treated as implicit authorization.**
The non-test runtime guarded evidence validation by `if(function_exists('apply_filters'))`; when that hook was unavailable, the evidence test was skipped without error. Although ordinary WordPress normally supplies this hook, defensive fail-closed logic cannot equate absence with approval.
- **Changed** `sabri-central-media/includes/class-scm-contracts.php` commit `e3b5d872323db668253033ef3a2464f437b74778`.
- In all non-test executions, missing hook now raises `activation_evidence_missing`; present hook must return an explicit `approved === true` claim.
- **Regression** `tests/review-round-286-missing-approval-hook.php`: isolated non-test execution with fully stubbed persistence/provider/owner dependencies but no approval callback; asserts exact denial code. PHP matrix green.

**Assessment:** 2 distinct new source-root-cause defects, 5 affected control checks; source corrections 2/2, mandatory new regressions 2, all three PHP CI jobs pass on exact code/test SHA. No live incident or deployed defect is declared fixed.

## Unfinished requirements and external blockers
1. Provider exit: >2,000 item *actual* native write fencing, transactional authoritative cutover, durable segmented rollback and safe provider retirement remain beyond the bounded, **unfenced** shadow-copy prep.
2. Key rotation: >2,000 live stale aliases need durable grouped physical re-encryption with KMS, locks/fencing, recovery and object cleanup. Current inventory is a preparatory proof.
3. Restore: actual consistent database + object + key snapshot and tested atomic, interruption-safe restore remain absent/unverified. Bounded family inventory is not a restore executor.
4. Independently amended CF-04 v1.1 original not accessible; exact 1.1 line-by-line parity unresolved.
5. Real Hostinger staging, production database/schema/wp_options, deployed artifact checksum, provider/KMS/CDN/worker integrations, native-owner approvals, backup/rollback rehearsal, load/accessibility/penetration, Founder signoff and live re-test not available. WPVibe site inventory: **no connected WordPress sites**.

## Live-first ledger
| Item | Reality |
|---|---|
| Repository main HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Code/test HEAD, pre-report | `87eadd9df92122bc764f4c3d036e6367838dbfc5` |
| Candidate runtime version | `1.3.0-rc.1` |
| Deployed version / package parity | UNVERIFIED |
| DB version | UNVERIFIED |
| Migration state | UNVERIFIED |
| Live verification status | NOT PERFORMED |
| PR | #5 Draft, unmerged |
| Production changes | NONE |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

A subsequent documentation commit requires a new exact-head CI; do not represent this report as a merged, staged, live or operational release. 
