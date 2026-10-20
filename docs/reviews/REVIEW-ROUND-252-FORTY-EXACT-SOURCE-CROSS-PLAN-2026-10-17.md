# CF-04 R252 — Forty fresh scoped code/central-plan/companion-module checks (2026-10-17)

## Verified baseline
- GitHub repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Frozen default branch `main` source HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Frozen starting Draft PR #5 source HEAD: `b946569c19e6d971977fad48f2b4c1e2b32fc62f`.
- Corrected source/tests HEAD before documentation commits: `ac488142a3a1b92b73a8ab3746019f7bc6c91b0c`.
- Candidate runtime `1.3.0-rc.1`; source schema/contract `1.5.0 / 1.5.0`; disabled/fail-closed default.
- Re-read **15 source-class files**, ~**567,427 source characters** after corrections and carried out **40 targeted source/contract checks** with explicit recorded dispositions. Each checked implementation location exists. **This is NOT forty independent exhaustive codebase passes, forty database integrations or forty staging/live tests.** Marker presence alone is not evidence of correct implementation.
- Existing `CF-04 v1.0` source-plan excerpts, original user-provided Consolidated Three Central Plans, File 12 v1.1 amended, and File 21 NG30 amended were compared for responsibility and trust boundaries. **The independently amended CF-04 v1.1 Future-40 source Word document was not available**, so exact all-clause document parity remains unverified.

## 40 checked scopes, sources, and outcomes

| Check | Source/plan responsibility | CF04 implementation | Disposition |
|---|---|---|---|
| 01 | Plugin/runtime enablement | `class-scm-plugin.php` | Source guard located; deployed flag unknown |
| 02 | Source schema and index readiness | `class-scm-persistence.php` | Source checked; live DB and options unknown |
| 03 | Durable CAS writes | `class-scm-persistence.php` | Source present; staging concurrency pending |
| 04 | Versioned destructive delete | `class-scm-persistence.php` | Source present; staging race pending |
| 05 | DB keyset scan identity | `class-scm-persistence.php` | DEFECT FIXED R248; non-test-mode regression green |
| 06 | DB bounded multipart batch identity | `class-scm-persistence.php` | DEFECT FIXED R249; non-test-mode regression green |
| 07 | User-scoped/status list identity | `class-scm-persistence.php` | DEFECT FIXED R250; non-test-mode regression green |
| 08 | Oldest-status job lookup identity | `class-scm-persistence.php` | DEFECT FIXED R251; non-test-mode regression green |
| 09 | Audit chain tamper evidence | `class-scm-persistence.php` | Source present; audited staging retention pending |
| 10 | Test-only / live-only boundaries | `class-scm-persistence.php` | Real-DB-mode tests used; production parity unknown |
| 11 | Encrypted object storage | `class-scm-storage.php` | Crypto code present; live KMS unverified |
| 12 | Private-root path containment | `class-scm-storage.php` | Source guard present; host ACL unknown |
| 13 | File00 actor verification authority | `class-scm-contracts.php` | Adapter source present; installed File00 unknown |
| 14 | Canonical owning domain version | `class-scm-contracts.php` | Action-time checks present; actual handshake pending |
| 15 | C0–C5 privacy separation | `class-scm-contracts.php` | Policy source present |
| 16 | Rights/consent/purpose policy | `class-scm-contracts.php` | Policy source present; real owners pending |
| 17 | Owner-bound upload session | `class-scm-upload.php` | Source present |
| 18 | Multipart stream SHA and identity | `class-scm-upload.php` | Source present |
| 19 | Upload finalization/idempotency | `class-scm-upload.php` | Source present |
| 20 | Quota/rate limit and expiry | `class-scm-upload.php` | Source present; real load pending |
| 21 | Owner-bound deduplication | `class-scm-upload.php` | Prior R238 paging fix retained |
| 22 | Expired-upload cleanup | `class-scm-upload.php` | Prior guarded cleanup retained |
| 23 | Magic/MIME and declared data validation | `class-scm-validation.php` | Source present; hostile corpus pending |
| 24 | Malware, archive, decompression bomb | `class-scm-validation.php` | Source present; real scanner pending |
| 25 | Sandboxed execution | `class-scm-processing.php` | Adapters present; real sandbox attestation pending |
| 26 | Job lease, dead-letter and fairness | `class-scm-processing.php` | Source present; concurrency soak pending |
| 27 | Image conversion and validation | `class-scm-processing.php` | Source adapter; production engine pending |
| 28 | Audio/video transcoding | `class-scm-processing.php` | Source adapter; real codec outputs pending |
| 29 | PDF preview/OCR boundary | `class-scm-processing.php` | Source adapter; File12 owner acceptance pending |
| 30 | Derivative/manifest lineage | `class-scm-processing.php` | Source checks present |
| 31 | Signed token and audience grant | `class-scm-delivery.php` | Source checks present |
| 32 | Action-time private serving/revocation | `class-scm-delivery.php` | Source checks present; real browser pending |
| 33 | C0-only public CDN/purge | `class-scm-delivery.php` | Source checks present; real CDN purge acceptance pending |
| 34 | File17 native recipient/transfer | `class-scm-transfer.php` | Canonical owner retained; installed plugin parity unknown |
| 35 | Retention and rights expiry | `class-scm-lifecycle.php` | Source checks present; real backup expiry pending |
| 36 | Legal hold, deletion, tombstone | `class-scm-lifecycle.php` | Source checks present; real provider evidence pending |
| 37 | Provider exit shadow/rollback | `class-scm-operations.php` | OPEN: in-memory globally capped migration plan |
| 38 | Shared ciphertext key rotation | `class-scm-operations.php` | OPEN: rotateAll() globally materializes groups; staging KMS unknown |
| 39 | Restore provenance and consistency | `class-scm-operations.php` | R245 paging fixed earlier; OPEN snapshot-frozen durable restore acceptance |
| 40 | Future40 and cross-plan module integration | `class-scm-future40.php` | R242/R243 source green; independent amended manuscript and deployed owner parity missing |

## Newly proven root cause: indexed SQL row and stored JSON payload were insufficiently reconciled

**Root cause:** Generic production `RecordStore` paths previously fetched only JSON `payload` and allowed the decoded `id`, `actor_id`, `status`, and/or `version` to stand in for authoritative indexed SQL columns without comparing them. A corrupted, stale or wrongly mapped payload could be treated as another row or actor and could poison keyset continuation, multipart part assembly, owner-scoped list results and oldest pending-job selection. These are source-demonstrated risks; **no claim is made that the same attack/error occurred on the live system**.

All new fixes operate against the same existing indexed record table; no schema or migration change was introduced.

### R248 — indexed keyset scan (corrected)
- `RecordStore::scanPage()` now selects indexed `id,actor_id,status,version,payload` and validates indexed identity, actor, status, version and strictly ascending SQL cursor against the decoded payload. NULL DB results fail closed.
- Regression `tests/review-round-248-indexed-db-scan-parity.php` executes the **real production PHP code path** with a mocked SQL store (`SCM_TEST_MODE` deliberately undefined), tests valid rows and cross-ID forgery, out-of-order ID, duplicate row, actor/status/version mismatch and silent null query.
- Exact-head CI: passed PHP 8.1/8.3/8.4.

### R249 — batched native upload part reads (corrected)
- `RecordStore::getMany()` now binds each returned payload to its actual SQL primary key rather than accepting any requested ID asserted inside the payload; validates actor/status/version and null results.
- Regression `tests/review-round-249-indexed-db-batch-parity.php` exercises forged part IDs, duplicates, unsolicited parts and malformed rows using the non-test-mode SQL branch.
- Exact-head CI: passed PHP 8.1/8.3/8.4.

### R250 — owner/status scoped lists (corrected)
- `RecordStore::list()` now checks DB/payload ID, actor, status and version against each other **and** against the explicit requested owner and status filters.
- Regression `tests/review-round-250-owner-scoped-db-list-parity.php` exercises cross-owner and off-status records and null DB query response in real-mode simulation.
- Exact-head CI: passed PHP 8.1/8.3/8.4.

### R251 — oldest pending-job selection (corrected)
- `RecordStore::oldestByStatuses()` now checks the selected DB primary key and metadata against the decoded payload and requested queue statuses, rejects impossible multiple LIMIT-1 results and fails closed on null DB query response.
- Regression `tests/review-round-251-indexed-oldest-status-parity.php` exercises owner/ID/status/version tampering and query failures.
- Exact-head CI: passed PHP 8.1/8.3/8.4.

These four are **four corrected entry paths of one shared SQL/payload integrity root cause**, not four observed production incidents.

## Exact code/test CI evidence
- Source/test SHA `ac488142a3a1b92b73a8ab3746019f7bc6c91b0c`: Push run `38964836014` and PR run `38964841405`, **all six quality jobs SUCCESS** (PHP 8.1, 8.3, 8.4, twice).
- PHP 8.4 logs confirmed `REVIEW ROUND 248`, `249`, `250`, `251` regression PASS markers and `CF-04 QUALITY GATE: PASS`.
- Documentation report and matrix updates add later commits; their own exact HEAD CI must be checked independently before making any statement of final PR-head green.

## Separate canonical owner realities checked
- File 00 retains verification and identity authority; CF-04 accepts only versioned claims, not a second identity database.
- File 10 owns video/live entities, File 11 Reels owns feed/ranking/user watch entities; CF-04 owns shared binary-processing infrastructure only.
- File 12 PDF Library owns legal PDF catalogue/edition/reader/reading-state and rights; PDF byte processing does not confer catalogue ownership.
- File 17 owns message, recipient, relationship, block and private-transfer truth; CF-04 may securely process/serve binary attachments only when native owner approves.
- File 21 owns Home/News feed and publication; File 22 owns editing/composer; File 24 owns audit/assurance oversight. No CF-04 patch modified any companion repository or claimed an owner migration.
- GitHub source presence in another repository does **not** prove installed/deployed parity.

## Known remaining engineering/release gaps (NOT fixed)
1. `ProviderExitService::plan()` still materializes up to 100k assets and 200k derivatives in a single large migration plan; safe completion requires durable segmented item state, indexed provider inventory, resume, rollback and real DB/load acceptance. Do not fake completion by raising a row cap.
2. `KeyRotationService::rotateAll()` still materializes 100k assets and 200k derivatives into in-memory group lists; safe completion requires durable group planning and real KMS/provider shared-ciphertext/old-object recovery proofs.
3. `RestoreService` had earlier R245 capped inventory fix but still needs snapshot-frozen durable restore checkpoints, concurrent-change consistency and real staging recoverability/rollback acceptance.
4. Production `RecordStore` source remains subject to live schema/DB parity verification; these new tests use a mocked WordPress DB, not a real staging MySQL instance.
5. Original amended CF-04 v1.1 Future-40 Word source remains unavailable; full external source-of-truth plan parity is not established.
6. Real File 00/10/11/12/17/21/22/24 companion plugin installed versions, actual provider credentials, malware scanners, sandbox transcoders, CDN purge, 1 GiB File17 transfers, Hostinger staged MySQL and load tests, legal-privacy acceptance, Founder release authorization and live re-test are not complete.

## Live-first ledger
| Distinct reality | Evidence |
|---|---|
| Repository HEAD `main` | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 | Open/unmerged; candidate development source only |
| Candidate runtime version | `1.3.0-rc.1` |
| Source DB schema / API contracts | `1.5.0 / 1.5.0` |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | NOT PERFORMED |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No main merge, provider operation, staging migration, live write, runtime enablement or live incident resolution was asserted. A green repository CI is NOT staging/live acceptance.
