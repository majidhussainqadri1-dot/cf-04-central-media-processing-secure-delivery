# CF-04 — New twenty-pass code completeness and cross-plan review (2026-10-12)

## Frozen evidence and scope

- Initial **read-only** PR #5 baseline: `54504ce840652b976ad286cbd47cf67a0309ab76`.
- Repository baseline: `main` `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Candidate: `1.3.0-rc.1`; runtime schema/contract `1.5.0 / 1.5.0`.
- Comparison: current accessible *Sabri Social Homeopathy Three-Central-Plans Consolidated Governing Master Plan*, available original CF-04 v1.0 plan, PR source requirements/traceability for amended v1.1 and Future-40, and live GitHub directory inventories of companion repositories.
- **Primary-source limitation:** the exact independently readable amended CF-04 v1.1 Word plan is not available in the supplied plan material. In-repository v1.1 source claims are evidence of implementation intent only, not independent clause-by-clause parity.
- Method: twenty independently scoped read-only source examinations, then one coordinated post-review correction and adversarial regression, then twenty independent post-fix static-source invariants. These are **not** a claim of twenty full end-to-end CI-suite executions or independently approved security tests.

## Twenty distinct inspections

| Review | Source boundary inspected | Finding / status |
|---|---|---|
| 01 | Plugin entrypoint / version constants / fail-closed default | Required bootstrap/version guard in source |
| 02 | Persistent WP schema, readiness and compare-and-swap | Source paths present; deployed DB unverified |
| 03 | Canonical owner registry and contract dispatch | Domain callbacks and fail-closed decisions present; real owner agreement pending |
| 04 | File 00 identity assertions | Verified, eligible, non-suspended actor guard present; exact live assertion provider unverified |
| 05 | Upload creation, owner-version and idempotency | Source validation/replay paths present |
| 06 | Multipart limits, checksums and assembly | Source checks and negative regression paths present |
| 07 | Expired resumable upload cleanup | **DEFECT R205:** cleanup existed but was **not scheduled** by any CF-04 cron hook |
| 08 | Magic/MIME/archive, malware/quarantine defense | Source engines and fail-closed provider adapters present; real malware corpus external |
| 09 | Sandboxed image/audio/video/PDF processing | Source pipeline paths present; real worker sandbox external |
| 10 | Job graph leases, retries, dead letters | Source paths present; deployment load/fault acceptance external |
| 11 | AEAD object encryption and provider abstraction | Source and test providers present; real managed keys/regions external |
| 12 | Signed grant TTL/use-count validation | Prior R204 fixes still in source and regression suite |
| 13 | Grant serving, range, action-time owner check | Source guards present; real File 17 and CDN provider acceptance external |
| 14 | Public CDN derivative policy, revoke/purge | Source paths present; real purge SLO external |
| 15 | File 17 one-GiB transfer identity, eligibility and revocation | Source paths and R203 regression present; real File 17 contract unaccepted |
| 16 | Retention, legal hold, deletion and tombstones | Source paths present; live deletion/restore evidence external |
| 17 | Provider exit, key rotation and restoration | Source paths present; real provider exit/restore drill external |
| 18 | Future-40 adapter governance and degraded operation | Source capabilities adapter-gated; real external engines not asserted |
| 19 | 33 FR, 10 current-plan, 6 native journeys, 40 Future identifiers | All enumerated in source traceability; an identifier is not an independent acceptance proof |
| 20 | CI, regression inclusion, deterministic package and deployment truth | Quality gate includes current correction; real staging/live parity unavailable |

**Post-fix independent static checks:** all twenty listed boundaries had expected identifiers/guard paths. This means source-level coverage was found; it does not prove behavioral completeness for every provider or all unknown threats.

## Confirmed root cause and completed correction (R205)

The method `UploadService::cleanupExpired()` already performed safe expired-session state transitions, `PartStore::purge()`, quota reservation settlement and bounded error alerts. However, inspection of `Plugin::cronJobs`, `cronRetention`, `cronDeletions` and `cronIntegrity` found **no scheduled invocation**. Consequently abandoned uploads would not be automatically reclaimed by the scheduled maintenance flow despite having a manual cleanup implementation.

After completing the read-only review ledger:

1. `Plugin::cronRetention()` now calls `UploadService::cleanupExpired()` after rights and retention processing. It retains the existing lock, failure alerting, and fail-closed runtime gate.
2. `tests/review-round-205-expired-upload-janitor.php` supplies an executable in-memory regression for expired and paused sessions, active session preservation, stored-part deletion, quota release, and repeat-cron idempotency.
3. `tools/quality-check.sh` makes the new regression mandatory. The existing Round-174 gate-coverage test also requires all new review-test files to be included exactly once.

**Corrected functional source and mandatory test gate:** commit `9634f7861f8aa0d115d12078e9de4508aee1ee34`. At that exact commit, push and PR GitHub Actions each reported three successful PHP quality jobs: PHP 8.1, 8.3 and 8.4. Future documentation commits must be checked against their own exact HEAD; older CI does not prove those later commits.

## Actual cross-repository deployment/parity blockers

GitHub `main` branch trees were inspected for File 10, File 11, File 12, File 17, File 00, File 20, File 21 and File 22. Files 11 and 12 `main` each contained only `README.md` at inspection, although other development/review branches exist. File 17 and File 21 retain their own native binary and content workflows. **There is no evidence that the CF-04 adapter callbacks have been agreed, loaded and exercised against the actual deployed companion builds.** Migration and canonical owner transfer therefore remain blocked. Do not patch another domain's storage directly from CF-04.

## Deployment discipline — separate realities

- Repository source branch is a **draft, unmerged PR** and runtime defaults disabled.
- No Hostinger/staging plugin fingerprint, deployed artifact SHA-256, PHP/plugin runtime evidence, live WP DB schema version, options/migration ledger or live trace was supplied.
- **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
- Real-provider malware/transcoder/object storage/CDN/CDR/adaptive pipelines, accessibility, load, penetration, backup/restore, migration, rollback and active File 00/10/11/12/17/19/20/21/22/24/25 contracts require separate independent acceptance and Founder change control.

## Required final status fields

| Item | Current verified value |
|---|---|
| Repository main HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| PR candidate corrected code SHA | `9634f7861f8aa0d115d12078e9de4508aee1ee34` (subject to later doc commits) |
| Runtime candidate | `1.3.0-rc.1`, disabled by default |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

Do not merge, enable or deploy solely because GitHub CI is green. Do not claim a live incident resolved without live retest and deployed/source parity.
