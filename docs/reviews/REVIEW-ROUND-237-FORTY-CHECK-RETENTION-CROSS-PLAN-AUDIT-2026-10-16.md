# CF-04 R237: Forty Scoped Cross-Plan Source Checks and Retention Safety Fixes

**Date:** 2026-10-16.  
**Repository:** `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.  
**Draft branch / PR:** `codex/cf04-three-plan-complete-1.2.0`, Draft PR #5, open/unmerged.  

## Immutable source reality

- Start-of-review PR HEAD: `098acc69893ad2f58373fa5eaef43fd7acd53a82` (already contained prior R231–R234 fixes).
- Current code/test HEAD *before this report commit*: `3db24a6e198d31e867f873c1651b755d0eda0ec6`.
- Repository `main` HEAD: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Candidate plugin: `1.3.0-rc.1`, source schema `1.5.0`, source contract `1.5.0`, disabled/fail-closed default.
- Fifteen PHP runtime source classes (~562,016 characters) read for **forty distinct scoped Code/Contract reviews**. These are **not** forty separately executed complete system-level tests, independent staging installations, penetration tests or live release checks. Source-marker checks indicate candidate implementation locations, not guaranteed correctness or full plan parity.

## Governing original Word documents and companion-module truth

The original supplied **Three Central Plans Consolidated Governing Master Plan** requires stable cursor pagination at scale, bounded limits, no unbounded PHP admin work, versioned interfaces, strict authorization, privacy/retention lifecycle, canonical native owners and separate approved object/CDN/workers. The supplied **File 12 PDF Library v1.1 amended** plan requires private encrypted originals, signed document access, legal hold/retention, deletion propagation, recovery and staging/restore evidence. The supplied **File 21 News Feed amended** plan requires its own post/feed ownership, stable pagination, deletion/tombstones, viewer-aware caching and approval-controlled cutover. These are original plan evidence, not assumptions that the plans are deployed.

Companion repository default `main` trees on 2026-10-16: File 00 48 PHP files; File 10 11; File 17 175 with its native transfer classes; File 21 302 with a separate media handler; File 22 117; File 20 86; File 11 and File 12 `main` each README-only, no PHP. **Other branches and installed site packages remain unknown.** File 17 and File 21 cannot be silently replaced as canonical owners.

The standalone **amended CF-04 Future-40 Word master plan** was not independently available among mounted originals; exact authorial requirement-by-requirement parity is **unverified**. A repository-maintained matrix is not a substitute for the missing original.

## Forty distinct inspected Code/Contract boundaries

| Check | Boundary | Result |
|---|---|---|
| 01 | Runtime disabled-by-default and protected bootstrap | Specific source boundary located; real deployed integration not established |
| 02 | WordPress schema readiness and wp_options migration contract | Specific source boundary located; real deployed integration not established |
| 03 | Durable composite (record_type,id) primary-key identity | Specific source boundary located; real deployed integration not established |
| 04 | Atomic optimistic-CAS record writes | Specific source boundary located; real deployed integration not established |
| 05 | CAS record deletes and append-only audit | Specific source boundary located; real deployed integration not established |
| 06 | Validated stable keyset cursor and bounded page sizes | Specific source boundary located; real deployed integration not established |
| 07 | Indexed exact-ID record batches | Specific source boundary located; real deployed integration not established |
| 08 | Private encrypted object storage | Specific source boundary located; real deployed integration not established |
| 09 | Storage provider health and cryptographic keyring | Specific source boundary located; real deployed integration not established |
| 10 | Private object path containment | Specific source boundary located; real deployed integration not established |
| 11 | File 00 verified actor boundary | Specific source boundary located; real deployed integration not established |
| 12 | Native canonical owner decisions and object versions | Specific source boundary located; real deployed integration not established |
| 13 | MIME/consent/residency scanner policy | Specific source boundary located; real deployed integration not established |
| 14 | Upload quota, rate-limit and idempotency | Specific source boundary located; real deployed integration not established |
| 15 | Part source-to-provider byte identity and safe replay | Specific source boundary located; real deployed integration not established |
| 16 | Upload-scoped part storage enumeration | Specific source boundary located; real deployed integration not established |
| 17 | Expired-upload janitor cursor and cleanup | Specific source boundary located; real deployed integration not established |
| 18 | Multipart assembly hash/EOF integrity | Specific source boundary located; real deployed integration not established |
| 19 | Source inspection two-pass digest and temporary-file cleanup | Specific source boundary located; real deployed integration not established |
| 20 | Malware scanner, ZIP/PDF inspection and fail-closed behavior | Specific source boundary located; real deployed integration not established |
| 21 | Background processing leases/recovery | Specific source boundary located; real deployed integration not established |
| 22 | Image/audio/video/PDF pipeline boundary | Specific source boundary located; real deployed integration not established |
| 23 | Derivative manifests and source lineage | Specific source boundary located; real deployed integration not established |
| 24 | Scoped signed delivery grant issue and serve checks | Specific source boundary located; real deployed integration not established |
| 25 | Active-only grant revocation across large inventories | Specific source boundary located; real deployed integration not established |
| 26 | CDN mapped publication and bounded purge | Specific source boundary located; real deployed integration not established |
| 27 | File 17 native recipient transfer scope | Specific source boundary located; real deployed integration not established |
| 28 | Action-time File 17 owner access recheck | Specific source boundary located; real deployed integration not established |
| 29 | Legal hold enforcement beyond full-inventory thresholds | Specific source boundary located; real deployed integration not established |
| 30 | Retention schedule policy/version and backup expiry | Specific source boundary located; real deployed integration not established |
| 31 | Retention backlog keyset beyond 100,000 rows — NEW R235 FIX | **R235 proven defect fixed + regression green** |
| 32 | Retention direct-call expiry-janitor compatibility — R236 | Backward-compatible optional cleanup switch, tested |
| 33 | Cron duplicate janitor invocation — NEW R236 FIX | **R236 proven defect fixed + regression green** |
| 34 | Retention independent operation failure boundaries | Specific source boundary located; real deployed integration not established |
| 35 | Deletion reconciliation and CDN purge isolation | Specific source boundary located; real deployed integration not established |
| 36 | Provider exit/drain witness source-only safety | Repository source present; real provider/owner integration unverified |
| 37 | Provider exit migration/source ownership | Repository source present; real provider/owner integration unverified |
| 38 | Key rotation old/new ciphertext reference safety | Repository source present; real provider/owner integration unverified |
| 39 | Rights outbox and expiry keyset | Repository source present; real provider/owner integration unverified |
| 40 | Future-40/REST canonical ingress + companion owner contracts | Repository source present; real provider/owner integration unverified |

## Proven new omission R235 — bounded retention reconciliation

**Root cause:** `RetentionService::run()` materialized the entire `retention` record family through `RecordStore::all('retention',0,null,100000)` before processing a single schedule. More than 100,000 retained schedules would trigger `record_scan_limit` and prevent expiration/deletion scheduling even for one eligible record; a cron-held PHP process could also exhaust large memory.

**Fix:** validated persistent `cron_cursor:retention-reconciliation`, `RecordStore::scan('retention',1000,$after)`, progress across immutable IDs with eventual wrap and fail-closed corrupted cursor. Existing retention-policy binding, derivative expiry, legal hold, canonical deletion request, backup expiry, degraded-state errors and direct-call output fields are retained. No database/schema migrations, owner-contract changes or production deployment performed.

**Test:** `tests/review-round-235-retention-keyset-backlog.php` seeds 2,100 terminal records plus a last still-live schedule, checks progress at 1,000 then 2,000, verifies the last schedule is actually processed and cursor wraps, and rejects a malformed stored cursor. The previously separate upload janitor still functions.

## Proven new omission R236 — duplicate upload-expiry work in a single cron

**Root cause:** `Plugin::cronRetention()` invoked `RetentionService::run()` and `UploadService::cleanupExpired()` as separate tasks, but `RetentionService::run()` itself invoked `UploadService::cleanupExpired()` unconditionally. Thus a single WordPress cron swept upload expiry **twice**. This wastes work, increments its maintenance cursor twice and needlessly couples upload failures to the retention operation.

**Fix:** add optional backwards-compatible `bool $cleanupUploads=true` to direct `RetentionService::run()`; the cron calls `RetentionService::run(0,false)` and invokes upload cleanup exactly once under its own independently guarded task/error boundary. Direct callers retain their previous default upload expiry behavior.

**Test:** `tests/review-round-236-single-expiry-sweep.php` verifies an expired upload is cleaned by cron, expiry cursor advances once rather than twice, disabling nested cleanup leaves cursor unchanged, and direct retention callers still trigger it by default. No temporary quota-releasing behavior was removed.

## Exact-head regression/CI evidence

- Both R235 and R236 executable regression suites have been added to mandatory `tools/quality-check.sh`.
- Corrective code/test HEAD `3db24a6e198d31e867f873c1651b755d0eda0ec6`: **both Push and Pull Request workflows succeeded, PHP 8.1, 8.3, and 8.4, six successful jobs**.
- The PHP 8.4 job log explicitly includes `REVIEW ROUND 235 RETENTION BACKLOG KEYSET: PASS`, `REVIEW ROUND 236 RETENTION SINGLE JANITOR: PASS`, and `CF-04 QUALITY GATE: PASS`.
- Earlier intermediary commit adding R235 test but not yet its mandatory-gate registration failed an existing test-discovery rule R174; adding the mandatory gate corrected this. Preserve such negative history; do not misreport every intermediary commit as green.
- **This report file adds a further commit: re-verify its own exact-head CI before declaring final full 6/6 success.**

## Remaining source and external proof limits

1. `ProviderExitService::plan()` still fully enumerates 100k assets/200k derivatives and materializes its entire migration manifest; `KeyRotationService::rotateAll()`, current-envelope reconciliation and some provider audits/dashboards still do global-list work. The prior R231 witness fix does **not** close these more complex migration/operations gates. A redesign requires approved schema/job representation, compatibility/rollback and real staging DB evidence.
2. The R235 cursor removes a global-memory/limit defect but has no validated live high-cardinality MySQL `EXPLAIN`/latency/soak or cron lease acceptance. The R236 direct-call contract remains source/CI proof only.
3. Installed plugin package/version/checksum, active providers and dependencies, `wp_options`, actual DB schema/rows/version, deferred migrations and current runtime logs are **unverified**.
4. Real File 00 identity, File 10/11 video, File 12 PDFs, File 17 native transfer, File 21 feed, File 22 composer, File 19 notification and File 24 governance acknowledgements still require exact deployed artifact parity and owners' accepted integration tests.
5. Hostinger staging/provider security, real 1 GiB transfers, KMS/AV sandbox, CDN purge, deletion/hold/backup restore, disaster drills, load/security/accessibility tests and Founder-controlled release approval remain outstanding.
6. Do **not** merge Draft PR #5, enable runtime or declare a live incident resolved on source evidence alone.

## Live-first exact-state ledger

| Required separate reality | Evidence |
|---|---|
| GitHub repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR corrected code/test HEAD *before report* | `3db24a6e198d31e867f873c1651b755d0eda0ec6` |
| Candidate plugin version | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

A green synthetic PHP quality gate certifies neither complete user-plan parity nor real production/staging migration or observed live operation.
