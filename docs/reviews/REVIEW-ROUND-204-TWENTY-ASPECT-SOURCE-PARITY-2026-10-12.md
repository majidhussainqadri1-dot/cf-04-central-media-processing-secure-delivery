# CF-04 Review Round 204 — Twenty-aspect source and cross-plan completeness audit

**Date:** 2026-10-12  
**Frozen starting PR #5 HEAD:** `b404ac5ae84e7a362c9f4ab7a1c25721ff581788`  
**Base branch:** `main` at `0294442f0fddd1ca5440d9d5ac992ba80aced972`  
**Reviewed runtime:** 1.3.0-rc.1; schema/contract 1.5.0/1.5.0.  
**Scope:** 15 PHP runtime includes (~540,942 characters), source matrix, governing CF-04 v1.0, central plan v3.0, available companion contracts and latest GitHub Actions evidence.

## Evidence method and limits

Twenty **distinct structural source-review aspects** were inspected against the central master-plan ownership constitution, CF-04 functional requirements, the source-matrix Future-40 reconciliation, and File 00/10/11/12/17/19/20/21/22/24/25 dependencies. Presence of identifiers or source-level test coverage is not proof of correct behavior, independent security acceptance, twenty full end-to-end executions, or successful live deployment. The *full amended CF-04 v1.1 Word source* was not independently available for clause-by-clause verification; repo-authored v1.1 parity evidence is not a substitute for its primary text.

Read-only review and defect recording preceded any correction. The two newly identified source-level defects were corrected only after the structural review was complete.

## Twenty inspection aspects

| # | Review subject | Source/evidence checked | Result |
|---|---|---|---|
| 01 | Native canonical ownership | `class-scm-contracts.php`, DomainRegistry and adapters | Source identifiers present; domain acceptance external |
| 02 | Actor and File 00 verification | Auth and owner assertion paths | Source identifiers present; real File 00 contract acceptance external |
| 03 | Upload/create/resume/completion | UploadService lifecycle | Source identifiers present; CI regression suite exists |
| 04 | Quota, rate, replay prevention | QuotaService, Idempotency, RateLimiter | Source identifiers present; CI regression suite exists |
| 05 | Private quarantine and validation | Validator, ScannerRegistry, quarantine | Source identifiers present; real provider security tests external |
| 06 | Archive, MIME, polyglot and bomb defenses | ArchiveInspector and scanner abstractions | Source identifiers present; malicious real-provider corpus external |
| 07 | Sandboxed processing and job state | ToolRunner, ProcessingService, JobService | Source identifiers present; worker sandbox attestation external |
| 08 | Image/AV/PDF derivatives and lineage | ImagePipeline, AvPipeline, DocumentPipeline, DerivativeService | Source identifiers present; real transcoder verification external |
| 09 | Encryption and key rotation | Keyring, encrypted object store, KeyRotationService | Source identifiers present; managed-key recovery drill external |
| 10 | Issued, expiring and revoked grants | DeliveryService::issue/serve and grant persistence | **Defect R204-B:** zero TTL/use count silently coerced; fixed in source, pending exact-head retest |
| 11 | Private ranges and authenticated transport | StreamingEndpoint, DeliveryService::sliceStream | Source identifiers present; device/network staging external |
| 12 | Public CDN and revocation | CdnRegistry, purge and reconciliation | Source identifiers present; real CDN purge-SLO external |
| 13 | Rights/consent, purpose and C0–C5 | RightsPolicy and RightsRevocationService | Source identifiers present; approved privacy-class mapping external |
| 14 | Retention, holds, deletions | RetentionService, LegalHoldService, DeletionService | Source identifiers present; live-data restore/reconciliation external |
| 15 | File 17 verified 1 GiB transfer | TransferService, native readiness and serve-time binding | Source identifiers present; real File 17 authorization external |
| 16 | File 10/11/12/17/21/22 consumers | Companion-domain adapter registrations | Source identifiers present; cross-repository runtime parity external |
| 17 | Provider exit, restore, repair | ProviderExitService, RestoreService, RepairService | Source identifiers present; provider-exit rehearsal external |
| 18 | Fairness, cost and rate controls | JobService, CostService and quotas | Source identifiers present; load/cost measured staging external |
| 19 | Audits, metrics and privacy-safe telemetry | Observability, PrivacyTelemetry, Audit | Source identifiers present; operational SLOs external |
| 20 | Future-40 / ingress and fail-closed adapters | FutureAdapterRegistry, DegradedStateService, Rest::providerWebhook | **Defect R204-A:** webhook timestamp coerced at REST boundary; fixed in source, pending exact-head retest |

**All 20 aspects had the expected source-level entry points.** This is a twenty-aspect *static coverage* assessment, not an assertion of twenty full independent security/functional test runs.

## Frozen defects and post-review corrections

- **R204-A — Provider webhook timestamp alias:** `Rest::providerWebhook` previously coerced `x-event-timestamp` with `(int)`, allowing malformed strings such as `1712345678suffix` to be treated as the canonical timestamp during MAC verification. Replaced with `Utils::integer(..., 'webhook_timestamp_invalid', 1)`. Regression checks malformed suffix, leading-zero alias and a legitimate canonical signed request.
- **R204-B — Nonpositive delivery grant parameters:** `DeliveryService::issue` previously used `max(1,...)` on TTL and use count, silently creating a one-second or one-use grant from a zero request. Added explicit rejection of `ttl < 1` and `maxUses < 1`, preserving otherwise approved upper-bound narrowing. Regression checks both forbidden zero cases.
- **R204-C — Regression fixture escaping:** the first newly authored PHP fixture contained doubled global namespace separators; corrected the fixture **before** claiming CI success. This is a test-fixture authoring defect, not evidence of a deployed flaw.

All changes remain only in the **open draft PR #5 branch**; no live, staging or main-branch deployment is authorized.

## Required post-fix verification

The final patched **exact PR head** must pass push and PR runs on PHP 8.1, 8.3 and 8.4, with `tests/run-all.php`, complete regression gate, deterministic double ZIP build, checksums and required release evidence. Earlier green runs on `b404ac5` apply **only** to that earlier code, not the new corrected head.

## Open acceptance gates (not automatically fixable by a GitHub commit)

1. Founder-approved development/cutover controls and domain-owner signatures.
2. Independent receipt of the amended CF-04 v1.1 Word plan for complete text-level verification.
3. Hostinger staging exact package, installed runtime, DB/schema/migration evidence and checksum parity.
4. Real malware/archive/sandbox/object-storage/CDN provider tests, backup/restore and provider-exit drills.
5. Real File 00/10/11/12/17/19/20/21/22/24/25 contract tests and least-privilege production checks.
6. Browser/accessibility/load/penetration acceptance and actual monitored live re-test after separately approved deployment.

## Mandatory final incident/status fields

- Repository HEAD: re-read PR #5 branch after final commit.
- Deployed Version: **unverified**.
- DB Version: **unverified**.
- Migration State: **unverified**.
- Live Verification Status: **not performed**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No claim of `Resolved in production`, `Staging-Accepted`, `Live-Deployed`, or `Operational` is made here.
