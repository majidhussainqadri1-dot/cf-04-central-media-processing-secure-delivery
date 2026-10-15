# R219 — forty new scoped source/plan checks and R218 corrections (2026-10-15)

## Frozen source and plan scope

- CF-04 repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Initial exact candidate `codex/cf04-three-plan-complete-1.2.0` HEAD: `2630119c5ad26744cf53dfe1f4225087710f5126`.
- Default `main` HEAD at review start: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Corrected code+test HEAD **before publishing this audit file**: `bc217a4ca979dc9d3ffc8450d9095f375f4b3d49`.
- Pull request #5 remains **open, draft, unmerged**. Candidate version `1.3.0-rc.1`, source schema/contract `1.5.0/1.5.0`, runtime default disabled/fail-closed.
- Fifteen PHP runtime classes reviewed (~549,637 source characters); forty distinct scoped technical inspections were performed. **These are not forty independent complete end-to-end tests or forty live deployments.**
- Governing plan evidence read in original Word includes the **Three Central Plans Consolidated Governing Master Plan**, **File 12 PDF Library v1.1 Amended** and **File 08 Clinic v1.1 Amended**. The central plan says File 12 owns PDF metadata/rights, File 10/11 own video, File 17 owns attachments/messages, File 00 owns verification, File 20 is shell, File 24 governs privacy/security, and CF-04 conditionally supplies secure binary processing/delivery *after activation*. The File 12 plan requires encrypted private originals and short-lived signed reader/download, revocation, real backups/restore and no dual-write without an approved cutover.
- **Exact independent CF-04 Conditional Master Plan v1.1 Future-40 Word text was not found among available attached/project Word files**. `docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md` traces source clauses but is not independent evidence of authorial manuscript parity.

## All forty checked areas

| Check | Scope | Result |
|---|---|---|
| 01 | Runtime enable/disable guard | Source; deployment unknown |
| 02 | WP bootstrap/cron hooks | Source; actual scheduler unverified |
| 03 | DB version/index definition | Source; deployed DB unverified |
| 04 | Persistence readiness | Source |
| 05 | Record optimistic CAS updates | Source; real concurrency unverified |
| 06 | Versioned record deletion | Source |
| 07 | Bounded keyset maintenance | Source |
| 08 | Upload part keyed batch retrieval | Source; SQL staging load needed |
| 09 | Tamper-evident audit chain | Source |
| 10 | Private object root isolation | Source; hosting path unverified |
| 11 | Encrypted object envelope | Source; KMS/provider acceptance pending |
| 12 | Provider capability/health checks | Source |
| 13 | Keyring identity and rotation interface | Source |
| 14 | File 00 verified actor/role | Source; native callback unaccepted |
| 15 | Owner-domain authorization/version | Source; File 17/12/21 runtime callbacks unaccepted |
| 16 | Rights, scan and media policy | Source; amended plan clause parity blocked |
| 17 | Idempotency claim and fencing | Source |
| 18 | Quota reservation and settlement | Source |
| 19 | Rate limits and abuse controls | Source |
| 20 | First-read part SHA-256 | Source |
| 21 | Second-read stored-byte identity | DEFECT R218-A: fixed + negative regression |
| 22 | Part same-hash replay validity | DEFECT R218-B: fixed + negative regression |
| 23 | Part ordinal/parent indexed listing | Source |
| 24 | Assembled size, order and whole hash | Source |
| 25 | Provider stream never-EOF handling | R213 guard retained |
| 26 | Resume/complete fresh-owner auth | Source |
| 27 | Abort/purge/quota cleanup | Source |
| 28 | Expiry keyset fairness, cleanup | R208/R209 retained |
| 29 | Pre-hash upload size ceiling | R214 retained |
| 30 | Inspection second-pass SHA agreement | R216 retained |
| 31 | Inspection tempfile cleanup | R216 retained |
| 32 | Required malware scanner fail-close | Source; approved engine acceptance pending |
| 33 | Archive traversal and zip limits | Source |
| 34 | Job claim/lease/recovery | Source; staging worker soak missing |
| 35 | Image/AV/document derivatives | Source; real tool workers unaccepted |
| 36 | Grant issue/use and recheck | Source; real browser/session tests pending |
| 37 | File17 recipient/action-time checks | Source; native File17 cutover unaccepted |
| 38 | Hold/retention/erasure | Source; real provider purge not proven |
| 39 | Provider exit/restore/key rotation | Source; real disaster drill pending |
| 40 | Future-40/rights dispatch/REST | Source interfaces; full manuscript parity missing |

Checks 21 and 22 are two distinct root causes identified during the same R218 part-upload review. The other thirty-eight checks confirmed specific source pathways or retained safeguards; none is asserted fully live-verified merely because matching code exists.

## Defect R218-A — uploaded part source hash can diverge from stored bytes

**Evidence:** `PartStore::put()` first computed `Utils::streamHash($stream)` and compared it with expected SHA-256. It then passed the same stream to `ObjectStore::putStream()`, which rewinds/re-reads source bytes. The repository stored the *first* SHA and size without asserting that the **provider-reported committed SHA and size** matched. Mutating provider streams could therefore create incorrect part metadata and storage lineage.

**Correction:** Require exact type/size/SHA-256 parity between provider's committed metadata and verified first-pass source. On mismatch refuse persistence with `part_storage_integrity_invalid` and only attempt deletion when the provider returned the expected scoped object key and did not mark it reused. This does not claim forensic remediation of already-deployed corrupted data, which has not been observed.

**Regression:** `tests/review-round-218-part-provider-replay-integrity.php` uses a stream that flips its bytes between first-pass digest and provider re-read. It proves mismatch is denied, no part row exists and the newly written wrong private object is cleaned.

## Defect R218-B — same-hash replay accepted stale/forged persisted part metadata

**Evidence:** The old `if($existing&&hash_equals(...))return $existing` shortcut checked only the claimed digest. It did not verify recorded upload parent/part number, current actor, stored status/size, required provider/object key fields, or that the referenced object still existed. Hash equality alone was not sufficient evidence of a usable, authorized private part.

**Correction:** A same-hash replay must match canonical record type/ID, upload ID, ordinal, current actor, stored status, exact byte count, and provider/object identifiers. Re-check private object existence before treating a replay as successful. New explicit fail-closed outcomes `part_replay_identity_invalid` and `part_replay_object_missing`; original normal idempotent same-hash replay still succeeds without new record version.

**Regression:** R218 exercises forged parent, missing object, and valid same-hash replay.

## Exact-code CI evidence and safety boundary

- Initial R218 implementation `7bbdfd824984041d38ed20ebc945241c555a1dfa`; regression file `a9404384f14cb210339be5d29d5a2c12dd186000`.
- Source and mandatory quality-gate HEAD `bc217a4ca979dc9d3ffc8450d9095f375f4b3d49`: push and PR each returned **success** on PHP 8.1, 8.3 and 8.4. PHP 8.4 job log confirms `REVIEW ROUND 218 PART STORAGE AND REPLAY INTEGRITY: PASS` and `CF-04 QUALITY GATE: PASS`.
- An extra CI run is required on the **new final report commit** (not yet known here). Do not reuse green earlier-head CI to claim this report-head CI passed.
- Regression outcomes use in-memory `RecordStore` and a local encrypted test provider. They do not prove Hostinger MySQL/storage behavior or an installed WP deployment.

## Cross-module and deployment blockers

1. Exact approved standalone amended CF-04 master-plan document missing for independent clause-by-clause verification.
2. GitHub default `main` trees for File 11 and File 12 each contain only `README.md`; their other branches and **deployed runtime state remain unknown**. File 17 and File 21 `main` have their own substantial native attachment/media paths. A universal CF-04 cutover requires explicit owner/Founder approvals, migration parity and testing; no duplicate backend or silent replacement.
3. Actual live/staging active plugin package checksum, `wp_options`, database schema/rows/version, migration/deferred-upgrade state and runtime logs not inspected.
4. Real object provider contract and KMS keys, malware engine/isolated workers, CDN, signed recipient/rights revocation, File 17 1 GiB transfers, WordPress MySQL scale, live deletion and backup/restore/rollback, security/load/accessibility and owner-consumer acknowledgements need separate acceptance.
5. No Draft PR merge, provider activation or production release was performed.

## Exact-deployed-state ledger

| Required field | Status |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Corrective code+test HEAD before report | `bc217a4ca979dc9d3ffc8450d9095f375f4b3d49` |
| Candidate plugin | `1.3.0-rc.1` |
| Source schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | Unverified |
| DB Version | Unverified |
| Migration State | Unverified |
| Live Verification Status | Not performed |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

Source-level regression success is **not** a claim that the entire project has no missing code, that all forty areas passed real integration acceptance, or that a live defect is resolved.
