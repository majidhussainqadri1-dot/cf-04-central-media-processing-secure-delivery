# CF-04 — 40 Executed Source Regressions and CI Repair (2026-10-18)

## Exact evidence and scope
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Frozen main HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972` (governance-only tree).
- Draft PR #5 code/test HEAD **before this report**: `5e5020475f6f038bb38a84ca832aed4d319f5fd7`.
- Candidate runtime `1.3.0-rc.1`; schema `1.5.0`; contract `1.5.0`. Runtime disabled by default.
- Current code+test CI: pull-request workflow run **39094385761**, jobs **117577676417** (PHP 8.1), **117577676354** (PHP 8.3), **117577676273** (PHP 8.4). **3/3 successful**.
- Raw PHP 8.4 log independently checked for exactly the forty distinct selected R-numbered PASS markers below; all present. PHP 8.1/8.3 jobs also green with mandatory quality gate.
- `ROUND 174 GATE COVERAGE`, `REVIEW ROUND 283`, and `CF-04 QUALITY GATE` succeeded. Deterministic package digest in all three jobs: `0d31e2eff2f1158f7c86ca45fe11493702783f323e3952a326079fede9f3153e`.
- **Scope declaration:** 40 separate executed regression scripts/check categories plus targeted source review. This is **not** 40 independent end-to-end full-codebase audits, 40 production runs, real-provider acceptance or proof of missing-free code.

## Forty distinct checks
| No. | Executed test | Checked contract | Cross-file owner | Verified |
|---|---|---|---|---|
| 01 | R103 | Private object root outside public web root | CF-04 / File 24 | PASS (PHP 8.1/8.3/8.4) |
| 02 | R104 | Concurrent upload quota/CAS | CF-04 / Files 10,12,17 | PASS (PHP 8.1/8.3/8.4) |
| 03 | R105 | Processing idempotency and replay | CF-04 workers | PASS (PHP 8.1/8.3/8.4) |
| 04 | R106 | Private delivery-grant revocation | CF-04 / File 17 | PASS (PHP 8.1/8.3/8.4) |
| 05 | R107 | Deletion and hold authorization at action time | CF-04 / File 24 | PASS (PHP 8.1/8.3/8.4) |
| 06 | R108 | Operational reconciliation | CF-04 operations | PASS (PHP 8.1/8.3/8.4) |
| 07 | R109 | REST contract and authorization boundaries | File 00 / consumers | PASS (PHP 8.1/8.3/8.4) |
| 08 | R110 | Persistence and runtime record checks | CF-04 DB | PASS (PHP 8.1/8.3/8.4) |
| 09 | R111 | Master-plan source parity contract | Central plan | PASS (PHP 8.1/8.3/8.4) |
| 10 | R115 | Public delivery fail-closed gate | File 21 | PASS (PHP 8.1/8.3/8.4) |
| 11 | R118 | Transfer adapter runtime gate | File 17 | PASS (PHP 8.1/8.3/8.4) |
| 12 | R123 | Upload action reauthorization | Files 00/12/17 | PASS (PHP 8.1/8.3/8.4) |
| 13 | R124 | Dead-letter and retry authorization gate | CF-04 workers | PASS (PHP 8.1/8.3/8.4) |
| 14 | R125 | Signed delivery-grant contract | File 17 | PASS (PHP 8.1/8.3/8.4) |
| 15 | R126 | Processing job-state contract | Files 10/12 | PASS (PHP 8.1/8.3/8.4) |
| 16 | R127 | Provider purge reconciliation | CF-04 storage | PASS (PHP 8.1/8.3/8.4) |
| 17 | R129 | Schema/deployment status truth | Live-first governance | PASS (PHP 8.1/8.3/8.4) |
| 18 | R143 | Provider exit source/target integrity | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |
| 19 | R144 | Cryptographic key rotation integrity | CF-04 keys | PASS (PHP 8.1/8.3/8.4) |
| 20 | R150 | Retention fail-closed enforcement | File 24 | PASS (PHP 8.1/8.3/8.4) |
| 21 | R151 | Deletion ledger integrity | CF-04 / File 24 | PASS (PHP 8.1/8.3/8.4) |
| 22 | R152 | Deletion audit recovery | CF-04 / File 24 | PASS (PHP 8.1/8.3/8.4) |
| 23 | R153 | Audit/tombstone binding | CF-04 lifecycle | PASS (PHP 8.1/8.3/8.4) |
| 24 | R184 | Audit record integrity | File 24 | PASS (PHP 8.1/8.3/8.4) |
| 25 | R205 | Expired upload cleanup | CF-04 cron | PASS (PHP 8.1/8.3/8.4) |
| 26 | R220 | Cron keyset progress/fairness | CF-04 operations | PASS (PHP 8.1/8.3/8.4) |
| 27 | R231 | Current authoritative storage-reference witness | Files 12/17 | PASS (PHP 8.1/8.3/8.4) |
| 28 | R245 | Restore inventory keyset integrity | CF-04 restore | PASS (PHP 8.1/8.3/8.4) |
| 29 | R265 | Fresh pre-serve restore authorization | File 00 / CF-04 | PASS (PHP 8.1/8.3/8.4) |
| 30 | R266 | Provider rollback checks all originals | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |
| 31 | R267 | Malformed restore rights fail closed | Files 12/17 | PASS (PHP 8.1/8.3/8.4) |
| 32 | R268 | Restore tombstone identity validation | CF-04 lifecycle | PASS (PHP 8.1/8.3/8.4) |
| 33 | R270 | Fresh target integrity at destructive source purge | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |
| 34 | R271 | Restore inventory fingerprint drift blocks action | CF-04 restore | PASS (PHP 8.1/8.3/8.4) |
| 35 | R272 | Select only stale key rotation workload | CF-04 keys | PASS (PHP 8.1/8.3/8.4) |
| 36 | R273 | Durable segmented provider inventory | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |
| 37 | R277 | Resumable checkpoint dry-run for restore | CF-04 restore | PASS (PHP 8.1/8.3/8.4) |
| 38 | R279 | Encrypted segmented provider shadow copy | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |
| 39 | R280 | Complete key candidate verification | CF-04 keys | PASS (PHP 8.1/8.3/8.4) |
| 40 | R283 | Cooperative segmented shadow-copy recovery and tamper denial | CF-04 provider | PASS (PHP 8.1/8.3/8.4) |

## New confirmed defects and corrections (this review)
1. **D-01 — CI test omission.** At initial PR HEAD `f7f88bb9a607d895a3712bff55676cfe9a15ab15`, PHP 8.1/8.3/8.4 CI run `39093933862` failed because `tests/review-round-283-cooperative-segmented-shadow-copy.php` was not in `tools/quality-check.sh`. Root cause confirmed by strict R174 quality-gate evidence. **Fixed** in commit `d9227976af3cc851d908d69f4843ab71b475c51e` by adding this mandatory regression.
2. **D-02 — Regression fixture assumed segment ordering.** After D-01, exact commit `d9227976...` passed PHP 8.3/8.4 but failed on PHP 8.1 because R283 chose `$copies[0]['copies'][0]` without verifying that the first segment contained a copy. **Fixed** in commit `5e5020475f6f038bb38a84ca832aed4d319f5fd7` by selecting an actual target across segments, asserting existence before corruption and preserving the tamper-detection test.
- New confirmed defects: **2**. Corrected in PR source: **2/2**. Exact corrected code/test SHA had **3/3 green PHP jobs**. No live correction claim.

## Outstanding code/capability and external dependencies (NOT resolved)
1. **Large provider cutover** — bounded inventory and shadow copying exist, but >2,000-object authoritative switch, global native writer fencing, atomic owner mapping updates, safe provider retirement and destructive source purge orchestration remain unproved/unimplemented at scale.
2. **Large cryptographic key rotation** — candidate staging/verifying exists; >2,000 affected references require durable per-physical-object alias group leases, real KMS transactional re-encryption, recovery and cleanup.
3. **Restore execution** — nine-family bounded inventory/checkpoint exists, but no verified atomic DB+object+KMS snapshot and real interruption-safe restore execution with staging/owner write fences.
4. **Amended primary plan** — independently amended CF-04 v1.1 Future-40 DOCX was not accessible; v1.0 and repo matrices are not substitutes for complete clause-by-clause v1.1 parity.
5. **External acceptance** — Hostinger staging and real scanner/transcode/CDN/object/KMS providers, migration/backup/rollback, browser/RTL/accessibility/load/penetration, owner contract acceptance and Founder approval remain unverified. WordPress connection check: **no registered sites**.

## Reality separation and release status
| Reality field | Status |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Corrected source candidate | `5e5020475f6f038bb38a84ca832aed4d319f5fd7` (prior to this documentation commit) |
| PR #5 | Draft, unmerged |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT PERFORMED |
| Production changes | NONE |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔** A documentation-only commit after the code/test SHA requires independent exact-HEAD CI. Do not merge, deploy or call CF-04 operational until the release gates are evidenced.
