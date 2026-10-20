# CF-04 R258 — Forty Independent CI Reviews, Complete-Inventory Source Corrections, Cross-Plan Audit (2026-10-17)

## Exact source-reality freeze and review scope

- Repository: `majidhussainqadri1-dot/cf-04-central-media-processing-secure-delivery`.
- Main HEAD at starting freeze: `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Starting Draft PR #5 HEAD: `764037757dbbd15bddb76906740f91582c229328`.
- **Corrected code and tests HEAD before documentation updates**: `7f2f7160d3f132b4b6fd3d7177fbb41449b4c9d1`.
- Runtime candidate `1.3.0-rc.1`, source schema/contracts `1.5.0/1.5.0`, default runtime disabled.
- **40 genuinely separately executed regression review scripts** across upload, private serving, schema, owner contracts, retention, key rotation, restore, provider exit and safety; 40/40 review markers explicitly confirmed from **GitHub Actions PHP 8.4 job logs on the cited source/test HEAD**, **not** a simulated 40-fold manual audit.
- On exact source/test SHA: push run `38969890186`, pull_request run `38969895857`; six PHP quality jobs on PHP 8.1, 8.3, 8.4 **SUCCESS**. Logs explicitly show `REVIEW ROUND 256 PROVIDER-EXIT BOUNDED KEYSET PLANNING: PASS`, `REVIEW ROUND 257 KEY ROTATION BOUNDED INVENTORY: PASS` and `CF-04 QUALITY GATE: PASS`.
- A test marker shows that script's checks passed; it does **not** prove comprehensive implementation, production readiness, a staging provider run, or exact live installed-code parity.

## 40 distinct passed review scripts

| Review | CI script ID | Tested source / owner boundary | Plan relation | Result |
|---|---|---|---|---|
| 1 | R70 | Independent derivative provider-exit inventory | Source provider and parent/hold policy | PASS |
| 2 | R72 | RecordStore bounded-scan overflow semantics | No hidden partial inventories | PASS |
| 3 | R78 | Physical-key group identity across shared references | Shared ciphertext and references | PASS |
| 4 | R81 | Source drain before provider activation | Cutover drift gate | PASS |
| 5 | R103 | Private object storage root boundary | File 17 private media | PASS |
| 6 | R104 | Concurrent upload quota reservation | Multi-tenant abuse | PASS |
| 7 | R105 | Processing replay idempotency | Jobs and derivatives | PASS |
| 8 | R106 | Private serving and grant revocation | File 17 recipient | PASS |
| 9 | R107 | Legal hold/deletion action-time checks | CF-04 lifecycle | PASS |
| 10 | R108 | Operations repair/reconciliation | Disaster recovery | PASS |
| 11 | R109 | REST API envelope authorization | Owner-facing APIs | PASS |
| 12 | R110 | SQL/schema/CAS persistence readiness | Live DB parity | PASS |
| 13 | R111 | Cross-plan requirements traceability | Governing master | PASS |
| 14 | R113 | Upload runtime default-deny gate | Conditional activation | PASS |
| 15 | R114 | Processing runtime fail-closed | Conditional activation | PASS |
| 16 | R115 | Public CDN C0 gate | File 21 news feed | PASS |
| 17 | R118 | Native file17 transfer delivery gate | File 17 owner | PASS |
| 18 | R119 | Future40 source governance | Scope unapproved capabilities | PASS |
| 19 | R121 | Contract version negotiation | File 00/owner APIs | PASS |
| 20 | R123 | Fresh owner consent on upload | File 00/17 | PASS |
| 21 | R124 | Dead-letter retry on runtime faults | Operations | PASS |
| 22 | R125 | Signed delivery grant bound to owner | Private attachments | PASS |
| 23 | R126 | Job state lineage | Processing graph | PASS |
| 24 | R127 | Object-source purge reconciliation | Provider exit | PASS |
| 25 | R129 | Schema status evidence | Runtime release gate | PASS |
| 26 | R143 | Provider copy content hash and identity | Provider exit | PASS |
| 27 | R144 | Rotation content identity checks | Encryption keys | PASS |
| 28 | R150 | Retention fail-closed | Backup expiry | PASS |
| 29 | R151 | Deletion evidence state integrity | Data lifecycle | PASS |
| 30 | R152 | Audit-deletion recovery | File 24 | PASS |
| 31 | R153 | Audit tombstone binding | Proof of erasure | PASS |
| 32 | R184 | Audit event custody | Observability | PASS |
| 33 | R205 | Expired upload janitor | Resource cleanup | PASS |
| 34 | R220 | Maintenance job keyset fairness | Background ops | PASS |
| 35 | R231 | Source-reference witness for cutover/cleanup | New aliases | PASS |
| 36 | R245 | Restore keyset completeness and serve gate | Recovery | PASS |
| 37 | R253 | Source purge guards late live aliases | Safety regression | PASS |
| 38 | R254 | Credential retirement guards late aliases | Safety regression | PASS |
| 39 | R256 | NEW: bounded provider-exit keyset inventory | Safe full enumeration | PASS |
| 40 | R257 | NEW: bounded key-rotation keyset grouping | Safe full enumeration | PASS |

## New finding 1 — R256 global plan inventory was materially oversized and capped

**Confirmed source cause:** `ProviderExitService::plan()` previously invoked `RecordStore::all('asset',0,null,100000)` and `RecordStore::all('derivative',0,null,200000)`. This materializes every asset and derivative **globally** (including large volumes of unrelated or deleted history) before filtering by source provider. The generic all() method explicitly fails closed when records exceed its cap. Thus a source with a single eligible source-provider object can still fail to plan once unrelated history grows, and large arrays stress PHP memory. Not asserted as a live event.

**Implemented correction:** Private `ProviderExitService::inventoryRows(string $type): Generator` iterates **entire** asset and derivative keyspaces in 250-ID pages using `RecordStore::scan()`, retaining the original legal hold, residency, provider, parent and content-identity checks. Only source-provider candidate entries are appended to the **single-row** migration plan. Before appending item number 2,001, it now **rejects the plan** with explicit `provider_exit_plan_capacity_exceeded` (503); this avoids silent incomplete plans or unbounded serialized single-record manifests. **It deliberately does not claim support for unlimited 2,001+ item migrations.** They need durable segmented plans.

**Tests:** R256 new test seeds 2,600 irrelevant historical deleted assets and one late active File17 asset; proves full inventory enumeration and inclusion, then adds 2,000 further eligible items and confirms fail-closed before a new plan ledger row is stored. Rejects invalid inventory types. Existing R70/R81 source expectations were updated to the new safe keyset boundary. Full previous provider exit e2e CI suite remains passed.

## New finding 2 — R257 key rotation globally materialized unrelated inventory before grouping

**Confirmed source cause:** `KeyRotationService::rotateAll()` independently invoked globally capped `RecordStore::all('asset',0,null,100000)` and `RecordStore::all('derivative',0,null,200000)`, then built shared-physical-object `$groups` entirely in memory before its key-change phases. Unrelated historical rows could prevent access to late active references. This is a source-level scaling failure, not evidence of a live malfunction.

**Implemented correction:** Private `KeyRotationService::rotationRows(string $type): Generator` streams both authoritative families across all pages, preserving **grouping by provider and physical object key** and the same shared-ciphertext identity checks. The current single-run algorithm now rejects the 2,001st live physical reference with `key_rotation_capacity_exceeded` (503) **before** creating the rotation run or starting crypto writes. The bounded memory ceiling explicitly keeps whole-fleet 2,001+ reference key rotation blocked pending a staged durable group protocol and real KMS/provider drills.

**Tests:** R257 new test seeds 2,600 historical deleted rows followed by a live asset, proves late keyset coverage, adds 2,000 additional live references and confirms no partial key_rotation run is persisted on capacity violation. Rejects invalid inventory type. Existing R78/R98 shared-ciphertext identity/cleanup tests remain passed.

## Cross-plan primary document and owner audit
- Compared supplied `Sabri Social Homeopathy Platform Three Central Plans Consolidated Governing Master Plan 2026` (explicit source truth, canonical owner, acceptance/evidence chain, privacy, deployment, rollback, Founder approval), `File 12 PDF Library v1.1 Amended`, `File 21 Complete Home/News NG30 Amended` and retrievable source descriptions/excerpts from original `CF-04 Conditional Complete Master Plan v1.0`.
- **Primary amended CF-04 `v1.1 Future-40` Word file not accessible**. Consequently exact clause-by-clause full compliance with that amended primary is **not verified**. GitHub maintained source matrix is not a substitute for the missing approved master document.
- The changes touch only **CF-04** and CI regression files. File 00 retains verified identity, File 10 video entities, File 11 reels/feed ranking, File 12 PDF catalogue/reader and rights, File 17 recipient and messaging/transfer authority, File 21 news publishing, File 22 editing/composer, File 24 system security/assurance. No owner database was modified or claimed migrated.

### Exact checked sibling GitHub `main` source-repository HEADs (NOT deployed parity)
| Owner | Source SHA and observation |
|---|---|
| File 10 Video | `4b8a3dda140186c01b05d998e729cb1aeeed2cf3`; 11 PHP files on main |
| File 11 Reels | `c2df8e72bad76dc1a7fb83cbe039541f7aa1fd8b`; no PHP on main |
| File 12 PDF | `2b369ef2d609247896e1ab32118f1dc498407f51`; no PHP on main |
| File 17 Network/Private Transfer | `8ae656e51796d1f05865d8be5dca2480443d79ca`; 175 PHP files |
| File 21 Home/News | `f2eb7e95ddea327af36ea725ffb923b029f885e6`; 302 PHP files |
| File 22 Composer | `b7a7f2e69411cbd32f0574fd12d766fb70c01b7a`; 117 PHP files |

Do not infer that an empty `main` PHP tree means another branch or installed package has no implementation.

## Corrected count and remaining blockers

- **40 distinct selected automated review scripts**: 40 PASS in exact-source PHP8.4 quality log (not exhaustive system passes).
- **New defects identified**: 2 source-level global inventory/cap/memory problems.
- **New defects corrected at source level**: 2 / 2.
- **New regressions**: R256 and R257, mandatory in quality-check.sh.
- **Existing tests changed to reflect valid keyset source**: R70 and R81.
- **CI**: 6/6 green at exact source+tests SHA before this documentation commit; final documentation SHA needs its own checks.

### STILL OPEN (blocking whole-module completion)
1. `ProviderExitService`: current single-row JSON plan can contain **at most 2,000 eligible source objects**. Plans above that explicitly return 503, not silent partial success. Requires provider-indexed segmented durable plan items, CAS page work, owner-write fencing, copy/shadow/cutover atomicity, rollback and provider acceptance.
2. `KeyRotationService::rotateAll`: current grouped rotation can process **at most 2,000 live physical record references** in one run. Needs durable grouped work queue, leases/CAS, atomic shared-physical-object lifecycle, key-recovery escrow, KMS proof and >2000 scale staging evidence.
3. `RestoreService`: prior R245 keyset fix corrects global materialization but not immutable snapshot-frozen, checkpointed, concurrent-update verified restoration or real staging rollback.
4. CF04 exact amended source Word `v1.1 Future-40` parity remains unknown; all clauses not attestable.
5. Live site deployed code, plugin checksum, source parity, DB `wp_options`, schema and migration, actual installed owners, external CDN/KMS/object scanner/sandbox, 1GiB transfer soak, accessibility/performance, Founder approval and **live re-test** are absent.

## Mandatory Live Reality Freeze reporting
| Reality | Evidence |
|---|---|
| Repository `main` HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| GitHub Draft PR | Open, not merged |
| Candidate runtime | `1.3.0-rc.1` |
| Source schema/contract | `1.5.0 / 1.5.0` |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **NOT PERFORMED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

No migration, activation, deployment, live write, staging provider cutover, PR merge or resolved live-incident assertion occurred.
