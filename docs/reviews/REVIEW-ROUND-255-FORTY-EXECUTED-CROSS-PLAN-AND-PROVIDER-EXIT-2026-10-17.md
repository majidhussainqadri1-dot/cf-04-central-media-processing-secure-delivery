# CF-04 R255 — Forty Distinct Executed Regression Reviews, Source Cross-Plan Audit, and Corrections (2026-10-17)

## Freeze and scope
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Frozen initial `main`: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Frozen initial Draft PR #5: `f6281ed9291fbc28793b1d6f0711f141e4d32ebe`.
- Verified corrected **code and tests**, before documentation amendments: `9c0353a65ea78d347a9d58cc6612bce55fa92b62`.
- Current code candidate: `1.3.0-rc.1`; source schema/contracts `1.5.0 / 1.5.0`; runtime disabled/fail-closed by default.
- Checked `class-scm-operations.php` ProviderExitService::plan, copy, shadowVerify, switch, rollback, purgeSource, finalizeCredentials, source-reference witness, KeyRotationService::rotateAll, existing deferred cleanup and restore with 40 existing/new **separately executed regression test scripts** covering the forty distinct lines below.
- This is a **forty-test execution/inspection** with different contracts, plus targeted source review and governing-plan comparison. It is **NOT forty full-codebase exhaustive manual rereads, forty live end-to-end deployments, or forty full CI pipeline repeats**. The counts are intentionally non-inflated.
- Evidence: GitHub Actions `pull_request` run `38967578505` and `push` run `38967571750` on exact code/test SHA: PHP 8.1, PHP 8.3, PHP 8.4 all passed on both events (**6/6 jobs**). PHP 8.4 log was independently filtered for all forty distinct R## PASS labels; **40/40 found**, including `REVIEW ROUND 253 PROVIDER EXIT PURGE LIVE-REFERENCE GATE: PASS`, `REVIEW ROUND 254 CREDENTIAL EXIT LATE REFERENCE: PASS` and `CF-04 QUALITY GATE: PASS`. These logs attest scripted regressions, not live provider behavior.

## Exactly forty verified script-level checks

| No. | Executed review | Checked behavior | Owner/plan boundary | CI |
|---|---|---|---|---|
| 01 | R103 | Private-storage root vs public web root | File 24 assurance; private delivery | PASS in exact-source CI |
| 02 | R104 | Concurrent uploads and quota reservation | CF04 upload; File 17 attachments | PASS in exact-source CI |
| 03 | R105 | Processing replay and idempotency | CF04 processing; Files 10/12/17 | PASS in exact-source CI |
| 04 | R106 | Signed-delivery grant revocation | CF04 serving; File 24 privacy | PASS in exact-source CI |
| 05 | R107 | Action-time deletion and hold authorization | CF04 lifecycle; owners | PASS in exact-source CI |
| 06 | R108 | Operations/reconciliation state integrity | CF04 operations | PASS in exact-source CI |
| 07 | R109 | REST API contract boundaries | File 00 and owner integrations | PASS in exact-source CI |
| 08 | R110 | Persistence/index/schema/lock safety | Shared SQL runtime | PASS in exact-source CI |
| 09 | R111 | Master-plan source parity diagnostics | Central governing plan | PASS in exact-source CI |
| 10 | R112 | REST error/fail-closed boundary | File 20 UI / consumers | PASS in exact-source CI |
| 11 | R113 | Upload enabled/runtime gate | CF04 conditional activation | PASS in exact-source CI |
| 12 | R114 | Processing enabled/runtime gate | CF04 conditional activation | PASS in exact-source CI |
| 13 | R115 | Public C0 serving gate | File 21 public feed / CF04 | PASS in exact-source CI |
| 14 | R118 | Native File 17 transfer gate | File 17 canonical recipient | PASS in exact-source CI |
| 15 | R119 | Future-40 source governance | Future-40 conditional features | PASS in exact-source CI |
| 16 | R120 | Evidence freshness | Central live/source separation | PASS in exact-source CI |
| 17 | R121 | Versioned contract interface | Central cross-file owner versions | PASS in exact-source CI |
| 18 | R122 | Redacted error disclosure | File 24 security and support | PASS in exact-source CI |
| 19 | R123 | Upload fresh owner reauthorization | Files 00/17/12 owners | PASS in exact-source CI |
| 20 | R124 | Dead-letter retry runtime gate | CF04 operations | PASS in exact-source CI |
| 21 | R125 | Signed delivery grant envelope | File 17 private attachments | PASS in exact-source CI |
| 22 | R126 | Processing job-state transitions | Files 10/12/17 jobs | PASS in exact-source CI |
| 23 | R127 | Provider purge reconciliation | CF04 FR-027 destructive cleanup | PASS in exact-source CI |
| 24 | R128 | Future provider cost and route bounds | CF04 conditional costs | PASS in exact-source CI |
| 25 | R129 | Runtime schema/status truth | Production parity discipline | PASS in exact-source CI |
| 26 | R130 | Release/evidence final gate | Central governing release law | PASS in exact-source CI |
| 27 | R143 | Provider-exit byte/object identity | CF04 FR-027 copying | PASS in exact-source CI |
| 28 | R144 | Key-rotation identity integrity | CF04 key-management | PASS in exact-source CI |
| 29 | R150 | Retention failure isolation | File 24/legal retention | PASS in exact-source CI |
| 30 | R151 | Deletion evidence and state integrity | Files 12/17/21 erasure | PASS in exact-source CI |
| 31 | R152 | Deletion audit recovery | CF04 + File 24 audit | PASS in exact-source CI |
| 32 | R153 | Audit-to-tombstone bindings | Deletion proof / privacy | PASS in exact-source CI |
| 33 | R184 | Audit event binding | Compliance evidence | PASS in exact-source CI |
| 34 | R205 | Expired upload janitor idempotency | CF04 session cleanup | PASS in exact-source CI |
| 35 | R220 | Background cron keyset fairness | Shared worker operations | PASS in exact-source CI |
| 36 | R231 | Late authoritative source-reference witness | Provider exit and key rotation | PASS in exact-source CI |
| 37 | R238 | Rights-and-owner-bound asset dedupe | File 17/File 12 no cross-owner reuse | PASS in exact-source CI |
| 38 | R245 | Restore inventory/serve gate | CF04 FR-032 | PASS in exact-source CI |
| 39 | R253 | NEW: late reference rejects provider source purge | Provider exit FR-027 | PASS in exact-source CI |
| 40 | R254 | NEW: late reference rejects provider credential exit | Provider exit FR-027 | PASS in exact-source CI |

Each named R## refers to the corresponding unique existing `tests/review-round-R##-*.php` script. R253/R254 were newly created, all added to mandatory `tools/quality-check.sh`. R103–R245 are existing historical tests rerun at the exact current source commit, not new defects found in this turn.

## New confirmed source-level defects and actual corrections

### Defect R253 — provider-source destructive purge did not repeat its live-reference proof at purge time
**Root cause:** `ProviderExitService::switch()` already called `assertSourceDrained()` before activating the target provider, but the subsequent independent `purgeSource()` could begin destructive source-object deletion after provider/owner records had changed. Existing per-item checks protected legal/WORM locks and target mappings, but omitted fresh provider-level and per-object live-source-reference witnesses. A new authoritative File 17 asset or derivative referencing the old object after switch could be endangered.

**Implemented correction:** `purgeSource()` rechecks `assertSourceDrained($plan)` immediately after loading the pending plan, and additionally checks `StorageReferenceWitness::first(sourceProvider,oldObjectKey)` for every item **immediately before source deletion**. A live alias rejects deletion with explicit `provider_exit_source_reference_live`; global drift yields `provider_exit_inventory_drift`.

**Executed regression:** `tests/review-round-253-provider-exit-purge-live-reference.php`: 2,600 historical asset rows and a late active File 17 derivative source alias; source purge refuses with `provider_exit_inventory_drift`, plan remains pending, and authoritative mappings remain unchanged. A static assertion additionally requires the per-object witness at the destructive boundary. PHP 8.1/8.3/8.4 CI passed.

**Limit:** The per-item witness narrows the race window but does not prove an atomic provider-write freeze; full live migration still needs a proper cutover fence or owner-domain write barrier and staging/provider drill.

### Defect R254 — provider credential retirement lacked an independent last-moment source-drain check
**Root cause:** `ProviderExitService::finalizeCredentials()` was able to call `revokeExitCredentials()` and mark the provider exit completed without its own authoritative source inventory witness. Newly appearing source aliases between earlier purge checks and final credential revocation would not be independently stopped by this terminal function.

**Implemented correction:** `finalizeCredentials()` calls `assertSourceDrained($plan)` immediately before revoking credentials and again before persisting `completed`. The failed witness leaves the plan pending, rather than inventing revocation/completion evidence.

**Executed regression:** `tests/review-round-254-credential-exit-late-reference.php`: Directly exercises the real private terminal method with a live source derivative, requires `provider_exit_inventory_drift`, verifies that no completed status or credential-revocation evidence is recorded, and proves completion only after the last alias is marked deleted. PHP 8.1/8.3/8.4 CI passed.

**Limit:** This is defense in depth, not a transactional guarantee against a simultaneous new source reference during an external credential revocation. Provider-level fencing, transaction/lock and rollback acceptance remain required.

### Change accounting
- **New independent root-cause manifestations:** two destructive boundary omissions (R253, R254), closely related to one common source-reference drift risk.
- **Corrected in Draft PR source:** 2/2.
- **New PHP regressions:** 2/2, registered as mandatory tests.
- **40 selected distinct executable QA reviews:** 40/40 PASS in PHP 8.4 CI log. Six overall PHP-matrix jobs green at code/test commit.
- No native File 10/11/12/17/21/22 code, plugin activation, deployment or database migration was modified.

## Source-driven governance / integration observations
1. The original CF-04 Conditional Master Plan v1.0 FR-027 specifies provider inventory, copy, hash verification, shadow delivery, rollback, source purge and credential revocation proof. CF-04 source retains those stages. The new live-reference checks specifically protect the **source purge and credentials** stages; they do **not** complete a whole-fleet migration redesign.
2. The supplied consolidated Three Central Plans specify canonical owner, proof-before-release, cross-file contract versioning, privacy, audit, staged migration and backup/rollback acceptance. File 17 remains canonical for private messages, recipient and relationship truth; CF-04 owns binary processing and secure delivery, not File 17's native transfer record master.
3. File 12 retains PDF bibliographic, rights and reader ownership. File 21 retains News Feed and publishing decisions. File 00 retains user verification; File 10 owns video entities; File 11 owns Reel entities; File 24 retains security oversight. No source references here grant CF-04 rights to take over another module's database.
4. The independently amended exact CF-04 v1.1 Future-40 original Word file is **not available** in the accessible supplied docs; repository matrix is not a substitute for primary amended-plan clause-by-clause comparison.
5. All forty passing automated scripts attest development-source regression properties; they do not by themselves prove live installed native module compatibility.

## Unresolved material blockers — explicitly not fixed
1. `ProviderExitService::plan()` still relies on `RecordStore::all('asset',0,null,100000)` plus `all('derivative',0,null,200000)`, assembling the entire cross-provider inventory into one JSON plan record. Before attempting large-scale/provider cutover, a new source-indexed durable child-state/sliced plan, explicit owner write freeze, restart and rollback protocol, and staging MySQL/provider tests are required.
2. `KeyRotationService::rotateAll()` still globally materializes 100k assets and 200k derivatives in memory to build shared-ciphertext groups. Safe redesign needs durable group identity/leases, physical reference fencing, key custody and real KMS provider recovery drills.
3. R245 improved `RestoreService` by removing the capped memory enumeration, but real snapshot-frozen consistent backup verification, resumable checkpointed reconstruction, concurrency and rollback tests remain unverified.
4. Real database/schema version, `wp_options` migrations, active plugin checksum, installed File 00/10/11/12/17/21/22/24 versions, scanner/container/KMS/CDN and File 17 1-GiB attachments have not been observed in production/staging. No provider acceptance or founder signoff.
5. Initial repository `main` and Draft PR are separate source realities, neither is evidence of deployed parity. Large-scale MySQL query plans/latency, privacy accessibility browser acceptance and successful live re-test remain pending.

## Live-first mandatory ledger

| Reality | Status |
|---|---|
| Repository `main` HEAD at audit freeze | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR initial HEAD | `f6281ed9291fbc28793b1d6f0711f141e4d32ebe` |
| Corrected source+tests SHA | `9c0353a65ea78d347a9d58cc6612bce55fa92b62` |
| Candidate runtime version | `1.3.0-rc.1` |
| Source DB Schema / Contracts | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No PR merge, live deploy, staging migration, runtime enablement, production data modification or live defect closure occurred. A latest documentation-commit exact SHA requires an independent fresh CI check; the earlier green result cannot automatically be applied to a new HEAD.
