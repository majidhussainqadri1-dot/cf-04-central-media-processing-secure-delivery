# CF-04 — R217: Forty-source-check cross-plan audit (2026-10-15)

## Authority, exact evidence and method

This is a **new** repository review following R215, not a restatement of R215's fixes.

- Initial exact Draft PR #5 HEAD: `59c613737e283e8f00a17578b93f00be46f94cf2`.
- Initial repository `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Latest verified corrective code/test HEAD **before this report**: `21439bfe93aa65069bcdec66cf36211150d16aca`.
- Plugin candidate `1.3.0-rc.1`, source Schema/Contract `1.5.0 / 1.5.0`, runtime default disabled/fail-closed, PR open/Draft/unmerged.
- Full set of 15 PHP runtime include classes reread (about 549,637 characters) and **40 distinct targeted source/contract inspection boundaries** checked against supplied governing master plan, available File 12, File 21 and File 25 plans, repository requirement matrix and companion GitHub source trees.
- **These are 40 scoped inspections, not 40 complete, independent staging installations, penetration tests, or end-to-end test runs.** The presence of a class or string alone does not prove production behavior.
- The precise amended standalone CF-04 Future-40 master-plan Word source remains independently unavailable for verbatim clause-by-clause comparison. Source matrix entries alone do not establish full plan parity.

## Forty inspected boundaries

| Round | Concern and source area | Source finding / unresolved external proof |
|---|---|---|
| 01 | Plugin controlled bootstrap | `Plugin::boot()` verified; initial string-check expected `init()` and was corrected as an audit false positive |
| 02 | Runtime disabled/fail-closed provider requirements | `RuntimeGuard` and provider checks in source; exact live configuration unknown |
| 03 | Schema version / install readiness | `Schema`, option version, `dbDelta`; real DB version/migrations unknown |
| 04 | DB composite primary key and scan | `(record_type,id)` composite key, bounded pagination; real query plan unmeasured |
| 05 | Compare-and-swap persistence | source-level versioned CAS; production MySQL concurrency rehearsal absent |
| 06 | Upload-specific part batch retrieval | `RecordStore::getMany` indexed lookup, R211 regression present |
| 07 | Durable keyset janitor scan | `RecordStore::scan`, bounded limit, R208 regression present |
| 08 | Critical audit event chain | versioned hash-chain machinery present; production audit volume/persistence untested |
| 09 | Object store root containment | reject public web-root and symlink roots; deployed storage path unverified |
| 10 | Encrypted private chunks | Crypto/Keyring authenticated encryption source present; real KMS custody unverified |
| 11 | Provider health and capabilities | source fail-closed checks; approved third-party provider readiness pending |
| 12 | CDN publish and purge adapter | `CdnAdapter` and purge contract; real purge SLO measurement pending |
| 13 | User and doctor verification | `Auth::verifiedUser` present; exact File 00/09 runtime callback not confirmed |
| 14 | Canonical owner/domain authority | `DomainRegistry::decision`, version binding; counterpart acceptance missing |
| 15 | Rights/purpose/scan policy binding | Policy normalization, required scanner set, policy hash in source; exact v1.1 manuscript missing |
| 16 | Max upload size/chunks | numeric bounds/parts; File 17's exact 1-GiB workflow requires real integrated test |
| 17 | Replay/idempotency claims | `Idempotency` present; distributed database races untested |
| 18 | Abuse throttle and quota | `RateLimiter`, `QuotaService`; provider quota reconciliation unverified |
| 19 | Upload session credential/expiry | credential hash and actor binding; cross-device runtime untested |
| 20 | Per-part digest and integrity | `PartStore` hash checks; external object provider untested |
| 21 | Upload part identity/ordinal filter | scoped part-list and rejected forged identities, R211 regression |
| 22 | Upload assembly liveness | previous R213 stall defense still present |
| 23 | Resume/pause/abort state | explicit states and credentials; browser/mobile coverage pending |
| 24 | Expired-upload quota and part janitor | R205/R208/R209 tests retained; scheduler reliability in live unknown |
| 25 | Malware scan and adapter failure | `ScannerRegistry` fail-closed; actual malware corpus, version/timeout acceptance missing |
| 26 | Bounded initial hash | R214 prevents reading whole oversized source before rejection |
| 27 | Consistency between source hash and copied file | **New R216 proven defect**: missing exact second-pass digest/size comparison; corrected |
| 28 | Temporary validation workspace cleanup | **New R216 proven defect**: copy exception occurred before cleanup `finally`; corrected |
| 29 | PDF scan liveness | R213 stalled-read denial present |
| 30 | ZIP/archive-bomb protections | `ArchiveInspector` policy; approved real-world malicious test corpus pending |
| 31 | Image sandbox processing | `ImagePipeline`, `ToolRunner`; real non-root worker not accepted |
| 32 | Audio/video transcode lineage | `AvPipeline` / derivative lineage; production codecs unverified |
| 33 | PDF and document pipeline | `DocumentPipeline`, manifest; File 12 live owner unverified |
| 34 | Processing queues and leases | `JobService`; multi-worker long-running soak missing |
| 35 | Signed grants and action-time checks | `DeliveryService`; counterpart auth and session rechecks unaccepted |
| 36 | Restricted byte-range delivery | partial-range and grant invalidation paths present, end-to-end tests pending |
| 37 | File 17 native transfer | `TransferService` owner/version binding; File 17 native backend acceptance missing |
| 38 | Legal hold, lifecycle, deletion | three service layers present; actual encrypted backup erasure untested |
| 39 | Provider exit, key rotation, restore | source recovery workflows; restoration and rollback drills missing |
| 40 | Rights revocation, Future-40 and REST ingress | versioned source paths exist; real consumers/provider approvals not proved |

## R216 root cause and completed repository changes

### Defect A: source integrity TOCTOU during inspection

`Validator::inspectStream()` computed `Utils::streamHash($stream,policy.max_size_bytes)`, rewound the source and copied it into a temporary inspection file. If the provider-backed stream changed between passes, the returned `sha256` and `fingerprint` remained bound to the **first** bytes even though MIME/structure inspections analyzed the **second** bytes.

**Fix:** capture the copied byte count; hash the temporary copy with SHA-256; reject if either size or digest differs from first-pass source stats, using explicit `source_changed_during_inspection` (422). Test uses a stream wrapper that changes its bytes after rewind, without causing EOF-based errors.

### Defect B: inspection tempfile persisted on failed copy

The original temporary-file `unlink` was in a `try/finally` entered **after** `Utils::copyStream()` succeeded, so a failed or stalled copy could leave `scm-inspect-* ` files behind.

**Fix:** move the create/copy/verify/inspect path into one outer `try/finally` that always unlinks the temporary file, even on a thrown mid-copy exception.

### Regression, test failure and correction

- New executable `tests/review-round-216-inspection-replay-integrity.php` with dynamic provider-like mutable and stalled stream scenarios, including temporary-file inventory before/after, and mandatory `tools/quality-check.sh` gate.
- First implementation and test gate HEAD `e043b3b54a2681a7a5e7c1b6e660d1162930b19e` **FAILED CI on all three PHP versions**, due to the test itself interpolating `$copiedHash` and `$temp` in a source-code assertion string. Functional adversarial test scenarios had passed, but that did **not** make the full gate green.
- Fixed test-only source assertion to use literal, non-interpolating PHP strings; final tested corrective HEAD `21439bfe93aa65069bcdec66cf36211150d16aca` achieved **six successful CI jobs** (push and pull_request on PHP 8.1, 8.3, 8.4). Exact PHP 8.4 log explicitly confirms both mutable-source and failed-copy-tempfile scenarios passed and `REVIEW ROUND 216 INSPECTION REPLAY INTEGRITY: PASS`.
- **Next:** check the CI outcome on this report commit itself before concluding exact-head QA success.

## Companion module source reality

The 2026-10-15 `main` tree inventory shows File 00 48 PHP files, File 10 11 PHP files, File 17 175 PHP files, File 21 302 PHP files, File 22 117 PHP files, File 20 86 PHP files. File 11 and File 12 default `main` trees only have `README.md`; this is not evidence that other branches, staging packages or live plugins are absent.

The consolidated governing master plan establishes File 12 as owner of books/PDF rights, File 10 as owner of video, File 17 as owner of messages/attachments, File 00 as identity authority, File 20 as shell and CF-04 as **conditional secure binary processing/delivery after activation**. No source-only inspection grants permission to replace a native owner, install an unapproved adapter or duplicate canonical records.

## External blockers and safe release limits

1. Exact amended CF-04 standalone Future-40 Word plan for independent, full textual parity still unavailable.
2. File 00, 10, 12, 17, 19, 21, 22 and 24 adapter/binary ownership contract acceptance, and the exact File 11/12 branch-to-deployment status, remain open.
3. Hostinger staging runtime checksum, active plugin versions, schema data, `wp_options`, migrations, DB load, MySQL query plans and cron execution still need live or staging evidence.
4. Approved private object, malware, isolated workers, CDN and KMS providers; large-file 1 GiB transfer, security and backup/restore/rollback drills; accessibility/device tests; Founder release approval are outstanding.
5. Do not merge Draft PR #5 or deploy/enable CF-04 on these source results alone.

## Exact deployment-reality ledger

| Required field | Verified or unknown |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Corrective Draft PR #5 HEAD before report | `21439bfe93aa65069bcdec66cf36211150d16aca` |
| Plugin candidate | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Do not confuse forty inspected source boundaries with independent complete integration, production acceptance, or forty fully executed end-to-end test cycles.
