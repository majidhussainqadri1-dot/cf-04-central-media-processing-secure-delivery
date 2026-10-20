# CF-04 R292 — Forty scoped cross-plan source checks and streaming-stall repair

## Exact reality and boundaries
- Review date: 2026-10-20. Frozen repository `main` SHA `0294442f0fddd1ca5440d9d5ac992ba80aced972`.
- Frozen Draft PR #5 HEAD **before review:** `10f88803e805c0ac86ecd81c8e2af3ed96de4020`. Runtime candidate `1.3.0-rc.1`, schema `1.5.0`, contract `1.5.0`; disabled by default.
- Reviewed fifteen PHP source files (6,804 lines) in the Draft PR, the uploaded amended CF-04 v1.1 Future-40 Word plan (2026-09-16), central project governing plan, and File 11/17 cross-file rules.
- **Methodology:** Forty sequential, individually named **scoped source-symbol/control checks** against that frozen candidate. Thirty-nine source signatures were present; check 26 showed an actual missing fail-closed check. A present class/method does **not** establish that all associated requirements, executable functionality, external adapters, or cross-repository production behavior are complete. These are not forty independent whole-repository manual audits.
- An actual streaming read-path defect was then corrected **after the 40 checks**, not during them. A focused executable PHP regression and mandatory gate invocation were added. No live/staging source was modified.
- CI for the new code/commit is **pending** until GitHub Actions confirms the exact SHA; do not call it green from this document.

## Forty frozen-state source checks
| No. | Review scope | Frozen result |
|---|---|---|
| 01 | Default-disabled runtime | Source symbol present |
| 02 | Founder activation authority | Source symbol present |
| 03 | Schema install safeguards | Source symbol present |
| 04 | Versioned contract transport | Source symbol present |
| 05 | Canonical identity contract | Source symbol present |
| 06 | Owner native adapters | Source symbol present |
| 07 | Versioned owner assertions | Source symbol present |
| 08 | Consent/rights normalization | Source symbol present |
| 09 | C0-C5 policy typing | Source symbol present |
| 10 | Audit records integrity | Source symbol present |
| 11 | Upload session idempotency | Source symbol present |
| 12 | Quota reservation | Source symbol present |
| 13 | Resumable part storage | Source symbol present |
| 14 | Upload expiry janitor | Source symbol present |
| 15 | MIME magic verification | Source symbol present |
| 16 | Archive/decompression defense | Source symbol present |
| 17 | Malware scanner capability | Source symbol present |
| 18 | Safe processing job leases | Source symbol present |
| 19 | Image derivative worker | Source symbol present |
| 20 | Video/audio derivative worker | Source symbol present |
| 21 | Document processing worker | Source symbol present |
| 22 | Derivative manifest lineage | Source symbol present |
| 23 | AES GCM object encryption | Source symbol present |
| 24 | Storage provider abstraction | Source symbol present |
| 25 | Token signing grants | Source symbol present |
| 26 | Streaming stalled-read fail closed | GAP at frozen baseline; code fix and regression added |
| 27 | Delivery grants user scoped | Source symbol present |
| 28 | Revocation and public purge | Source symbol present |
| 29 | Legal hold protections | Source symbol present |
| 30 | Deletion reconciliation | Source symbol present |
| 31 | Provider exit inventory | Source symbol present |
| 32 | Shadow copy durability | Source symbol present |
| 33 | Key rotation read-only staging | Source symbol present |
| 34 | Restore checkpoint staging | Source symbol present |
| 35 | File17 transfer canon | Source symbol present |
| 36 | Workspace upload integration | Source symbol present |
| 37 | Media C2PA metadata adapter | Source symbol present |
| 38 | Media privacy-safe fingerprint | Source symbol present |
| 39 | CDN delivery resilience | Source symbol present |
| 40 | Cross-file rights revocation | Source symbol present |

## Confirmed source finding, root cause and fix
- **Defect R292-01 (medium, source-level):** `StreamingEndpoint::handle()` previously treated any `fread()` returning an empty string as successful end of delivery without checking `feof()`. A nonblocking/transiently dry stream may return an empty string while not at EOF; thus the endpoint could silently exit with incomplete bytes and an already-set success response.
- **Root cause:** The streaming endpoint did not use the fail-closed stalled-stream distinction already present in `Utils::streamHash()` and `Utils::copyStream()`.
- **Fix:** central `readChunk()` rejects empty non-EOF reads with `delivery_stream_stalled` and records a privacy-safe audit code if the HTTP delivery had already begun. True EOF remains valid. The negative regression uses a real nonblocking socket pair to reproduce a temporary empty read; other checks cover successful bytes and legitimate EOF.
- **Release:** This prevents falsely accepting a stalled stream. Headers already transmitted before an error cannot be rewritten; a caller still needs integrity/range validation, retry, and a real HTTP integration acceptance test before production.
- **Accounting:** 1 confirmed source defect / 1 patch / 1 focused test / 1 mandatory quality-gate addition. The frozen 40 checks found 39 present, 1 deficient. Re-review and CI evidence are separately required.

## Incomplete work and external blockers — not falsely resolved
1. Provider exit for large populations: `>2,000` authoritative live references, full owner-write fencing, atomically safe cutover, provider credential retirement, rollback and real-staging acceptance remain incomplete.
2. Large-scale cryptographic key rotation: `>2,000` affected physical-object groups, actual re-encryption, KMS recovery, post-rotation cleanup and real-provider rehearsal remain incomplete.
3. Real restore: no verified consistent DB + object + key snapshot and interruption-safe live-scale restore/reconciliation.
4. Native File 00/10/11/12/17/20/21/22/24/25 plugin version/deployed source/schema parity and contract acceptance cannot be inferred from this repository.
5. Hostinger staging/production snapshots, WordPress `wp_options`, deployed checksums, DB migrations, scanner/CDN/KMS providers, security penetration, load, device/accessibility and rollback acceptance are not available. Founder-approved change control remains a mandatory gate.
6. Revised CF-04 v1.1 Word plan is now uploaded and available in this project; prior reports that it was *absent* should not be repeated as a current missing-document blocker. Its clause-by-clause runtime evidence still needs independent verification.

## Live-first ledger
| Field | Status |
|---|---|
| Repository main HEAD | `0294442f0fddd1ca5440d9d5ac992ba80aced972` |
| PR source HEAD at review start | `10f88803e805c0ac86ecd81c8e2af3ed96de4020` |
| Exact deployed version | UNVERIFIED |
| DB schema version | UNVERIFIED |
| Migration state | UNVERIFIED |
| Live verification status | NOT PERFORMED |
| Production change | NONE; PR #5 stays draft / unmerged |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
