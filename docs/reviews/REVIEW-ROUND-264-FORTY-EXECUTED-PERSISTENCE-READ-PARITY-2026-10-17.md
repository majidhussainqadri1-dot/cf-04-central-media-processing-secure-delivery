# CF-04 R264 — Forty distinct CI regression reviews, indexed record-get and status-count integrity (2026-10-17)

## Exact source-reality freeze
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- **Initial `main` HEAD:** `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- **Initial unmerged Draft PR #5 HEAD:** `ddad70b7f9ed7c1f5e841c2659783bdab37a906c`.
- **Fixed code+regressions HEAD:** `48657a9a8048339dcd58c8e3d83948f01b3171c0`.
- Candidate runtime `1.3.0-rc.1`; source schema/contract `1.5.0/1.5.0`; plugin runtime default disabled/fail-closed.
- This round uses **40 individually executed, distinct PHP regression scripts** with explicit log-level PASS evidence, a targeted persistence source review, and governing/adjacent-owner plan comparison. It is not forty full production deployments, complete independent repository audits or 40 end-to-end host acceptance runs.
- **CI exact fixed source+test HEAD:** PR run `39018126797`, push run `39018124292`; all PHP 8.1/8.3/8.4 quality jobs `SUCCESS` (**6/6**). The PHP 8.4 job log was checked for each of the forty separate R## PASS lines (**40/40**), including the newly added `REVIEW ROUND 262 SQL RECORD GET INDEX PARITY: PASS`, `REVIEW ROUND 263 STATUS COUNT FAIL-CLOSED: PASS` and `CF-04 QUALITY GATE: PASS`.
- All automated checks were repository/source tests. No real WordPress+MySQL production request, remote provider/KMS/CDN, installed sibling integration, or live deployed parity was executed.

## Forty reviewed executable tests and their scope

| # | Unique review | Distinct safety/behavior subject | Governing or sibling relation | Exact-source CI |
|---|---|---|---|---|
| 1 | R70 | Derivative inventory and lawful parent linkage | File 17; CF04 FR027 | PASS |
| 2 | R72 | RecordStore complete bounded inventory semantics | DB and recovery | PASS |
| 3 | R78 | Shared ciphertext group consistency | Key rotation | PASS |
| 4 | R81 | Provider source drain before cutover | CF04 FR027 | PASS |
| 5 | R103 | Private object root outside WordPress public root | File 24 | PASS |
| 6 | R104 | Upload quota/concurrent reservations | Files 10/12/17 | PASS |
| 7 | R105 | Processing idempotence and replay | Worker media processing | PASS |
| 8 | R106 | Private delivery grant revocation | File 17 | PASS |
| 9 | R107 | Action-time hold and deletion checks | Privacy lifecycle | PASS |
| 10 | R108 | Operational reconciliation and failure recovery | Operations | PASS |
| 11 | R109 | REST request and contract validation | Files 00/20 | PASS |
| 12 | R110 | Schema/persistence CAS + fail-closed checks | SQL records | PASS |
| 13 | R111 | Governing requirements and contract governance | Central plans | PASS |
| 14 | R113 | Upload runtime activation guard | Default disabled | PASS |
| 15 | R114 | Processing worker activation guard | Default disabled | PASS |
| 16 | R115 | Public-safe media-only serving gate | File 21 | PASS |
| 17 | R118 | Native File 17 transfer bridge safeguards | File 17 | PASS |
| 18 | R119 | Future-40 feature governance | Conditional scope | PASS |
| 19 | R121 | Versioned owner contract binding | Cross-file | PASS |
| 20 | R123 | Fresh upload owner authorization | Files 00/17 | PASS |
| 21 | R124 | Dead-letter queue reactivation protection | Operations | PASS |
| 22 | R125 | Scoped signed delivery grants | File 17 | PASS |
| 23 | R126 | Job-state transition invariants | Processing | PASS |
| 24 | R127 | Provider purge reconciliation | CF04 FR027 | PASS |
| 25 | R129 | Schema/runtime status evidence | Live-first release | PASS |
| 26 | R143 | Provider-exit object integrity guard | CF04 FR027 | PASS |
| 27 | R144 | Key rotation source/object integrity | KMS | PASS |
| 28 | R150 | Retention and hold fail closed | File 24 | PASS |
| 29 | R151 | Deletion state consistency | Lifecycle | PASS |
| 30 | R152 | Audit-bound deletion recovery | File 24 | PASS |
| 31 | R153 | Tombstone linkage | File 24 | PASS |
| 32 | R184 | Audit event recovery and custody | Observability | PASS |
| 33 | R205 | Expired-upload janitor | Cleanup | PASS |
| 34 | R220 | Cron cursor fairness and keyset progress | Operations | PASS |
| 35 | R231 | Late source-reference witness | Provider exit/rotation | PASS |
| 36 | R245 | Restore keyset traversal | Recovery | PASS |
| 37 | R253 | Source deletion prevents late alias removal | File 17 | PASS |
| 38 | R254 | Credential retirement prevents late alias revocation | Provider exit | PASS |
| 39 | R262 | NEW indexed record get parity and null SQL result | RecordStore SQL read | PASS |
| 40 | R263 | NEW COUNT(*) null/malformed/overflow failures | Health/operations aggregate | PASS |

The baseline source review additionally inspected the actual `RecordStore::get()`, `countStatuses()`, `getMany()`, `scanPage()`, `list()`, `oldestByStatuses()` and `Db::assertRead()` implementations. The R248–R251 production SQL fixture was re-used to test actual production-mode methods without enabling `SCM_TEST_MODE`. Review-script success is not proof that every possible pathway is defect-free.

## Defect R262 — `RecordStore::get()` trusted payload ID and version but not indexed row metadata

**Cause proved from baseline source:** Single-item lookup used `SELECT payload` and decoded `record_type/id/version` but did **not** verify the indexed SQL `actor_id`, `status`, `version` or physical SQL primary `id` against that payload. Moreover, a null/non-string raw response could be treated as `null` (missing record) even if the query result itself was malformed or missing.

**Implemented correction:** The exact-key query now uses `SELECT id,actor_id,status,version,payload ... LIMIT 1`, treats only a genuine empty result array as missing, refuses null result sets/malformed/impossible multi-row results, validates canonical JSON, and demands exact agreement between DB id/actor/status/version and decoded payload before returning an asset. This extends the R248–R251 indexed parity invariant to single-record reads used across upload, delivery, File 17 private media and other owner-scoped workflows.

**Regression:** New mandatory `tests/review-round-262-record-get-indexed-parity.php` executes the **non-test-mode production branch** against fake WordPress indexed rows, covering absent records, valid rows, wrong SQL ID, forged payload ID, actor/status/version drift, malformed/null JSON, duplicate rows and invalid/null results. CI passed PHP 8.1/8.3/8.4.

## Defect R263 — `RecordStore::countStatuses()` silently converted untrustworthy SQL counts into zero

**Cause proved from baseline source:** After SQL `SELECT COUNT(*)` and `Db::assertRead()`, the method returned `max(0,(int)$value)`. WordPress `get_var()` can return null on failed/absent results without necessarily providing trustworthy `last_error` evidence; malformed strings and huge values also cast silently to misleading integers. Zero is material to health, backlogs, operations, provisioning, cleanup and release decisions.

**Implemented correction:** Count aggregation now demands a valid string/int canonical **non-negative decimal integer** within PHP integer bounds. A genuine `0` is accepted; null, empty, malformed, negative, scientific notation, floats encoded as text, leading zeros, overflow or a DB error are rejected with `persistence_read_failed` (503), rather than claiming no outstanding work.

**Regression:** New mandatory `tests/review-round-263-status-count-failclosed.php` uses the real production SQL branch of the shared R248 fake `$wpdb`; confirms valid positive/zero/exclusion counts and rejects null, invalid, huge and database-error results. Fake DB was extended with a controllable `statusCount` result, without bypassing production `RecordStore` code. PHP 8.1/8.3/8.4 CI passed.

## Source status accounting

| Quantity | Evidence-bound outcome |
|---|---|
| Distinct scoped executable review scripts selected | **40** |
| PASS markers from exact code/test SHA CI log | **40/40** |
| New confirmed **source-code** defects this turn | **2** |
| New source-code defects fixed in Draft PR | **2/2** |
| Added mandatory production-branch PHP regressions | **2** (R262/R263) |
| Additional obsolete/malformed requirements-matrix addendum | **1 documentation correction**, separate from 2 code defects |
| CI jobs at corrected source+test commit | **6/6 success** |
| PR merged, plugin activated, migrated or live deployment | **No** |

The requirements matrix was updated to register R262/R263 and to correct older R246 inventory wording following R256/R257: there are now full bounded keyset traversals, but there is **not** an unlimited durable staged-provider/key-rotation implementation. Incorrect literal document spacing in the addenda was also cleaned. The follow-on report/documentation commit still requires a fresh exact-HEAD CI check; green checks on prior SHA do not automatically certify later commits.

## Governing plans and canonical owner relationship

**Primary evidence accessed:** `CF-04 — Central Media Processing and Secure Delivery Conditional Complete Master Plan v1.0` (indexed 33-page original), consolidated `Sabri Social Homeopathy Platform Three Central Plans 2026 v3.0` original DOCX, File 12 PDF Library amended DOCX, File 21 Complete Home/News amended DOCX, and File 08 Clinic amended DOCX. These explicitly distinguish a coding-ready plan, source implementation, reproducible package, staging acceptance, live deployment and operations. Central master imposes canonical ownership, schema/versioned contracts, change control, rollback and Founder authorization for live changes.

**Owner boundaries:** CF-04 handles shared binary/media processing and secure delivery **only when approved and activated**, not File 10 video entity authority, File 11 reels, File 12 PDF bibliographic/reader/rights truth, File 17 canonical private messaging and recipient ownership, File 21 news/feed publication truth, File 22 composer authority, File 08 appointment truth, File 00 authentication or File 24 security governance. R262/R263 are changes to CF-04 shared persistence only, not native owner DB tables.

**Independent latest sibling repository `main` HEAD checks (source facts, never deployment proof):**
- File 10 video: `4b8a3dda140186c01b05d998e729cb1aeeed2cf3`.
- File 11 reels: `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b`.
- File 12 PDF: `2b369ef2d609247896e1ab32118f1dc498407f51`.
- File 17 private Network: `8ae656e51796d1f05865d8be5dca2480443d79ca`.
- File 21 Home/News: `f2eb7e95ddea327af36ea725ffb923b029f885e6`.
- File 22 composer: `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a`.

**Primary-document verification gap:** The independently amended **CF-04 v1.1 Future-40** original Word plan is not among currently accessible documents; the GitHub requirements matrix names it but is not a substitute for the approved amended primary. Full clause-by-clause parity remains **unverified**. The older v1.0 conditional original is available and supports the general conditional architecture and release rules.

## Remaining blockers requiring separate implementation/proof

1. ProviderExitService uses complete keyset enumeration but its one-record manifest has a **2,000 eligible object** ceiling; bigger exits correctly fail closed. Durable segmented inventory/migration, provider/owner write fences, concurrent reference freeze, interrupted-state recovery and production-grade provider rollback remain missing or unverified.
2. KeyRotationService likewise bounds the in-memory physical-reference grouping at **2,000 live references**. Safe durable group queue/leases, interrupted rotations, real key custody/escrow, concurrent alias fencing and KMS acceptance remain open.
3. RestoreService keyset scans were previously improved but snapshot-frozen DB/object copies, durable checkpoint/replay, real MySQL consistency, concurrent changes and actual staging restore/retest are not proven.
4. Real WordPress SQL provider response formats, schema migration version and `wp_options`, plugin runtime/installed versions, File 00/10/11/12/17/21/22/24 owner compatibility, 1 GiB media soak, external scanning/cipher/CDN/KMS, package-deploy checksum parity, rollback drill and Founder approval have not been observed.
5. Amended CF-04 v1.1 Future-40 primary plan still needed for complete precise specification comparison.

## Live-first mandatory reality ledger

| Category | Status |
|---|---|
| Repository `main` source HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Open Draft PR #5: corrected code/test SHA | `48657a9a8048339dcd58c8e3d83948f01b3171c0` |
| Source candidate runtime | `1.3.0-rc.1` |
| Source schema version | `1.5.0` |
| Source contract version | `1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No merge to main, live/staging writes, migration, native owner change or live defect resolution occurred. All corrections remain confined to the Draft PR until separately approved.
