# CF-04 R240 — Forty Scoped Code/Cross-Plan Inspections with R238/R239 Fixes (2026-10-16)

## Evidence freeze and scope
- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Repository `main`: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Starting open Draft PR #5 HEAD before these new fixes: `b52e200d86e1c6e505992809f1e7248d59c19a78`.
- PHP production source inspected: **15 source classes, 562,030 characters** on the starting PR HEAD.
- Code+test+requirements-matrix HEAD before this report: `f9d5cdd7bcd8c3d5b21774ab930289df16a53c6d`.
- Software candidate: runtime `1.3.0-rc.1`, source schema `1.5.0`, contract `1.5.0`, default disabled/fail-closed.
- **Review granularity:** Forty different focused **source/contract inspections**, not forty independent exhaustive codebase passes, forty complete CI executions, or forty production/staging checks. Presence of a code marker is evidence of an implementation site, not proof of requirement completeness.
- Original supplied consolidated master plan and Files 12/21 govern source ownership and secure media handoff. The original amended **CF-04 v1.1 Future-40** Word manuscript was **not available for independent full text-clause parity**; an earlier CF-04 v1.0 manuscript exists, but cannot replace its amendment. This repository-authored requirements matrix is not that independent source.

## Forty inspected boundaries

| Scope | Control / owner interface | Primary source | Current review conclusion |
|---|---|---|---|
| 01 | Runtime default-off / activation | plugin, core | source present; deployed state unverified |
| 02 | Source schema 1.5.0 / wp_options parity | persistence, plugin | source present; live DB unknown |
| 03 | Composite primary key and index readiness | persistence | source present; staging EXPLAIN pending |
| 04 | Atomic optimistic CAS writes / deletes | persistence | source present; host load pending |
| 05 | Stable ID-keyset scan and batch lookup | persistence | source present; real DB load pending |
| 06 | Tamper-evident audit chain | persistence | source present; provider acknowledgment pending |
| 07 | Source provider ciphertext/keyring | storage | source present; KMS/provider unapproved |
| 08 | Canonical private path containment | storage, core | source present; host file ACL pending |
| 09 | File 00 verified identity | contracts | source contract; actual installed owner unknown |
| 10 | Native owner object/version authority | contracts | source contract; File 10/11/12/17/21/22 parity unknown |
| 11 | Policy/rights/privacy classes C0–C5 | contracts, plan-parity | source-level tests; real owners pending |
| 12 | Resumable upload credentials | upload | source-level tests |
| 13 | Quota/rate limits and racing reservation | upload | source-level tests; load pending |
| 14 | Multipart part source/provider integrity | upload | source-level tests |
| 15 | Upload completion and idempotency | upload | source-level tests |
| 16 | Upload duplicate content identity / >100k inventory | upload | DEFECT R238 FIXED; new mandatory regression green |
| 17 | Expired upload janitor and quota release | upload, lifecycle | source-level tests |
| 18 | Private quarantine and release gate | validation, processing | source-level tests; real scanner pending |
| 19 | Magic/MIME and declared-type distrust | validation | source-level tests |
| 20 | Archive, polyglot and bomb defense | validation | source-level tests; malicious corpus pending |
| 21 | Scanner/sandbox provenance and fail-closed workers | validation, processing | source contracts; real worker pending |
| 22 | Processing queues/leases/cron fairness | processing, plugin | source-level tests; soak pending |
| 23 | Derivative immutable lineage and manifest switch | processing | source-level tests |
| 24 | Image resize/multi-format integrity | processing | source adapters; real engine pending |
| 25 | Audio/video codec/transcode control | processing | source adapters; real engine pending |
| 26 | PDF document preview/OCR rights | processing | source-level tests; File 12 reader parity pending |
| 27 | Future-40 caption/chapters/OCR metadata | future40 | source-level tests; owner acknowledgment pending |
| 28 | Accessibility manifest late-track inventory | future40 | DEFECT R239 FIXED; new mandatory regression green |
| 29 | Signed scope-bound grants and action-time checks | delivery | source-level tests |
| 30 | Private same-origin delivery and range | delivery | source-level tests; real 1 GiB transfer pending |
| 31 | Public C0-only CDN / purge | delivery | source-level tests; purge SLO pending |
| 32 | File 17 native recipient / transfer authorization | transfer | source contract; File 17 deployed parity pending |
| 33 | Retention schedules / audit / keyset cursor | lifecycle | source-level tests; real MySQL pending |
| 34 | Hold / deletion / revocation outbox | lifecycle | source-level tests; real provider rollback pending |
| 35 | Provider exit plan/source-drain evidence | operations | remaining global-inventory scalability risk |
| 36 | Key rotation/reference cleanup | operations | remaining global-inventory scalability risk |
| 37 | Cost/budget ledger / provider invoice | operations | remaining 500k global-inventory scalability risk |
| 38 | Future-40 dedupe and threat re-scan | future40 | remaining global inventory/offset-scale risk |
| 39 | Restore integrity / failure isolation | operations | remaining 100k inventory acceptance risk |
| 40 | CI package/release and companion owners | quality-check, runtime docs | CI green; staging/live approval NOT performed |

## NEW confirmed defect A: R238 deduplication inventory cap — fixed and tested
- **Source before fix:** `UploadService::dedupe()` enumerated `RecordStore::all('asset',0,null,100000)`.
- **Failure cause:** The global 100,000-row cap can throw `record_scan_limit` for an otherwise valid upload completion when the asset inventory exceeds the cap. It also materialized unrelated assets in PHP memory.
- **Correction:** Stable 250-row `RecordStore::scan('asset',250,$after)` keyset pages, retaining exact source SHA, policy, privacy class, owner domain/object, rights fingerprint, and eligible status requirements.
- **New test:** `tests/review-round-238-dedupe-keyset.php` seeds 2,600 unrelated assets and checks later matching, same-owner/right matches, terminal exclusion and source-level removal of the hard cap. Prior source expectation Round 31 changed to match stronger algorithm.
- **Limit:** The late-match synthetic test does not emulate >100,000 actual MySQL objects; cross-plan owner and performance gates remain.

## NEW confirmed defect B: R239 accessibility manifest global cap — fixed and tested
- **Source before fix:** `RichMediaMetadataService::accessibilityManifest()` loaded six entire 100,000-row track families and then filtered by asset.
- **Failure cause:** A large unrelated track inventory can make a valid asset manifest fail with `record_scan_limit`, waste PHP memory, or delay delivery.
- **Correction:** Six separate 250-row immutable-ID keyset traversals, appending only owner/version/policy/rights/status matching tracks. Existing reauthorization and asset-state recheck preserved.
- **New test:** `tests/review-round-239-accessibility-manifest-keyset.php` seeds 2,600 unrelated tracks plus stale/retracted and valid late records; checks manifest integrity and exclusion.
- **Limit:** Synthetic fixture coverage does not establish MySQL throughput or external owner acceptance.

## Validation after corrections
- Both new PHP regression files registered in mandatory `tools/quality-check.sh`, which enforces test discovery, PHP lint, static/security/contract suites, deterministic repeated package build, and checksums.
- PHP **8.1 / 8.3 / 8.4** push and pull_request CI jobs for code/test commit `54ed27d8094409052877ece23b545b27387a275c` all succeeded (6/6 jobs). The PHP 8.4 log expressly contains `REVIEW ROUND 238 DEDUPLICATION KEYSET: PASS`, `REVIEW ROUND 239 ACCESSIBILITY MANIFEST KEYSET: PASS`, and `CF-04 QUALITY GATE: PASS`.
- An intermediate code/test commit caused legacy Round 31 to fail because it asserted that the old global-scan method *must* exist. This was a test stale-assertion, not a reason to revert the safer paging change; updated assertion and all six CI jobs passed.
- The document/matrix commits after the exact code/test commit require their own fresh exact-HEAD CI check; never infer the final status from previous commit alone.

## Known **remaining** unclosed risks — do not misrepresent as fixed
1. Provider exit plan still enumerates up to 100,000 asset and 200,000 derivative records and builds a whole-object migration manifest: redesign requires segmented durable plan, rollback and staging DB evidence.
2. Key rotation `rotateAll()` and current-key envelope reconciliation still materialize high-cardinality source inventories; staged crypto-key/backup recovery proofs absent.
3. Cost-budget and provider-invoice reads still materialize up to 500,000 records; reconciliation and cost correctness under load unverified.
4. Future-40 near-duplicate inspection and threat-rescan include global-list/offset ceilings, requiring safety-preserving pagination and native publisher approval.
5. Restore authority/audit checks read capped global inventories; source-level scale and reproducible recovery remain unaccepted.
6. The actual amended CF-04 Future-40 Word source, exact counterpart plugin build versions, integration provider credentials, real Hostinger staging, backups/restore, database rows/migration state, and production-state evidence are missing. The external acceptance gates are not waived.

The first five entries are **confirmed source-level scale exposure points / incomplete acceptance**, not five demonstrated live incidents. They must not be labeled corrected, green, or approved.

## Live-first exact-state ledger
| Reality | Status |
|---|---|
| GitHub `main` | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 | Open, unmerged; corrected source branch only |
| Candidate version | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Release decision
Do not merge Draft PR #5, enable runtime, migrate native module media, or deploy until independent plan parity, external real-provider and staging tests, restore/rollback rehearsal, Founder approval, and exact deployed parity all succeed.
