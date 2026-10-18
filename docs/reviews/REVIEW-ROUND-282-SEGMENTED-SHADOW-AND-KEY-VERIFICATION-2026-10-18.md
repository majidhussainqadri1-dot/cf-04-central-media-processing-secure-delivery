# CF-04 R282 — Remaining-work implementation and exact-HEAD verification (2026-10-18)

## Governing source and separate realities

Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`. Initial source-only `main` HEAD `0294442f0fddd1ca5440d9d5ac992ba80aced972`. Initial open/unmerged Draft PR #5 HEAD `56929a3af8542d61eb67c9a44e322d180f5041d0`. Code, three new PHP tests and mandatory quality-gate HEAD `18a7f50c47b4c3a76eb6ec250e64715a78d57d3f`. Later report and matrix commits are separate and require independent CI results. Runtime candidate `1.3.0-rc.1`, repository schema/contract `1.5.0/1.5.0`, default runtime disabled.

Primary plan checks used the attached **Three Central Plans Consolidated Governing Master Plan 2026** (canonical owner; trace requirement→affected data→migration→test→rollback→evidence; staging acceptance and Founder authorization before live change), **File 12 PDF Library v1.1 amended** (canonical PDF owner, migration/rollback/acceptance), and **File 21 Complete Home & News Feed amended** (publishing/owner and deployment/rollback). Independent CF-04 **amended v1.1 Future-40 primary DOCX** remains unavailable. The original v1.0 plan was indexed in earlier project reviews; a requirements matrix is not a replacement for the approved amended primary. No claim of complete clause-by-clause parity is warranted.

## New work 1 — bounded segmented provider shadow copying (R279)

**Previous missing capability:** `ProviderExitInventoryStage` could record and revalidate 2,301+ source records but had no executable physical shadow-copy function. `ProviderExitService::plan/copy/switch` still limits execution to a single <=2,000 object manifest.

**Implemented:** New `ProviderExitShadowCopyStage` with:
- `copySegment(inventoryId,segmentId,actor,limit)` accepting 1–25 items per invocation, requiring admin authorization and a fully revalidated unfenced inventory.
- Fresh per-item source DB identity, legal-hold and residency checks; actual original object rehash; encrypted object-store `copyTo()` into deterministic target keys; actual copied target rehash; **durable per-item versioned CAS progress**.
- A missing/corrupt next source leaves previously verified copies and their cursor available for retry. It never deletes source ciphertext, updates active owner mappings, changes the active provider or revokes credentials.
- `verifyComplete(inventoryId,actor)` independently rechecks every original and target object with the saved item evidence. The strongest result is deliberately `shadow_verified_unfenced`, not a ready-to-cutover status.
- Tests `tests/review-round-279-segmented-provider-shadow-copy.php`: two assets and a derivative (File 17/File 12 owner boundary), actual encrypted local source/target objects, missing second source interruption, resumed segment, full shadow rehash, corrupt target and forged saved proof, plus explicit no-delete/no-switch assertions. PHP CI PASS.

**Remaining:** Owner write fencing and atomic cross-owner reference transaction, durable phase/lease coordination, exhaustive shadow verification across **2,001+ physically copied objects under real providers**, safe segmented switch, rollback, source purge and credential retirement; real staging/DB/provider recovery before production.

## New work 2 — completed key candidate proof (R280)

**Previous missing capability:** `KeyRotationInventoryStage` staged 2,302 stale candidates in bounded durable journals but did not independently verify the entire completed staging inventory against current source and active-key epoch before any later phase.

**Implemented:** `KeyRotationInventoryStage::verifyComplete(inventoryId,actor)` rewalks full 250-row keyset pages for assets and derivatives, checks saved selected items, per-page hash, last ID, cursor and raw row counts, aggregate chain/counters, and active key epoch. Returns only `verified_unfenced`. Test R280 validates 261 stale source references and catches forged saved key, changed authoritative key ID and epoch change. PHP CI PASS.

**Remaining:** No durable atomic physical-object group executor, all alias write leases/fencing, 2,001+ actual encryption, KMS key escrow/retention and old-object cleanup, real recovery/provider acceptance.

## New confirmed source defect — R281

**Cause:** Key candidate discovery formerly skipped assets declaring the current active key ID *before* validating their SHA/object/provider identity. A malformed asset tagged already-current could be silently overlooked, even though actual `KeyRotationService::rotateAll()` considers invalid current-key identity exceptional. This was a source-level omission; live occurrence unverified.

**Correction:** Validate the full canonical content identity **before** any current-key skip. A purported current-key record with bad SHA must fail closed via `key_rotation_identity_invalid`. New mandatory R281 test seeds malformed digest and proves no checkpoint/segment is committed; after source repair it verifies zero stale candidates and safe completion. PHP CI PASS.

## Exact CI evidence

On `18a7f50c47b4c3a76eb6ec250e64715a78d57d3f`:
- GitHub Actions `pull_request` run **39090239492**: PHP 8.1, 8.3, 8.4 successful.
- GitHub Actions `push` run **39090236660**: PHP 8.1, 8.3, 8.4 successful.
- **6/6 success**. PHP 8.4 logs examined for 40 distinct selected Review PASS messages:
1. R103: PASS
2. R104: PASS
3. R105: PASS
4. R106: PASS
5. R107: PASS
6. R108: PASS
7. R109: PASS
8. R110: PASS
9. R111: PASS
10. R115: PASS
11. R118: PASS
12. R123: PASS
13. R124: PASS
14. R125: PASS
15. R126: PASS
16. R127: PASS
17. R129: PASS
18. R143: PASS
19. R144: PASS
20. R150: PASS
21. R151: PASS
22. R152: PASS
23. R153: PASS
24. R184: PASS
25. R205: PASS
26. R220: PASS
27. R231: PASS
28. R245: PASS
29. R265: PASS
30. R266: PASS
31. R267: PASS
32. R268: PASS
33. R270: PASS
34. R271: PASS
35. R272: PASS
36. R273: PASS
37. R277: PASS
38. R279: PASS
39. R280: PASS
40. R281: PASS

The forty script PASS markers are **selected re-executed automated regression reviews**, not 40 fresh independent complete manual security/source audits and not a claim of live production readiness.

## Work accounting

| Category | Newly evidenced result |
|---|---|
| New confirmed source defects | **1** (R281) |
| Defects corrected in source | **1/1** |
| Incomplete capabilities materially advanced | **2** (segmented shadow copy R279; key-stage complete verification R280) |
| New mandatory tests | **3** (R279/R280/R281) |
| Selected executable script markers | **40/40 PASS** at code+test HEAD |
| PHP CI jobs | **6/6 SUCCESS** at code+test HEAD |
| Main merge, live deployment, DB migration, real host change | **None** |

## Remaining explicit blockers

1. **Provider exit >2,000 item executable migration:** inventory and shadow copies are now staged, bounded and resumable; however safe `switch` of all 2,001+ authoritative owner references, global owner writer fencing, provider activation, compensation/rollback across partial batches, legal-hold-aware purge, credentials and KMS/CDN acceptance are still OPEN. Do not make the shadow copy stage a cutover endpoint without these.
2. **Key rotation >2,000 actually affected references:** current candidate inventory and checks are non-destructive; robust per-physical-object alias group coordinator and durable real-key cryptographic executor are still OPEN.
3. **Restore:** nine-family resumable inventory dry-run is already available (R277). Actual **immutable and shared-consistency DB/object/key snapshot** and execution of restore, interruption/rollback recovery and real MySQL/provider rehearsal remain OPEN.
4. **Source plan:** independent amended CF-04 v1.1 Future-40 original is not among accessible uploads; precise clause-by-clause compliance remains unverified.
5. **External deployment parity:** no WordPress site is connected through current WordPress integration as last checked. No exact installed plugin/package checksum, real DB schema/version, `wp_options` migration state, provider/KMS/worker configuration, 1GiB throughput, installed sibling module contracts, staging/live run logs, acceptance proof, founder release permission or live re-test is available.

## Live-First required ledger

| Field | Verified result |
|---|---|
| Repository `main` HEAD at initial freeze | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| Draft PR #5 (code/test SHA) | `18a7f50c47b4c3a76eb6ec250e64715a78d57d3f` (before docs) |
| Candidate runtime | `1.3.0-rc.1` |
| Source schema / contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No live bug declared fixed, no host changes made and no draft PR merged. The report and matrix commits must have their own green exact-HEAD CI to be considered source validated.
