# CF-04 Requirements Completion Matrix

**Audit basis:**

1. Sabri Social Homeopathy Platform Definitive Master Plan 2026 v3.0.
2. Consolidated All-Chats Recovered Directive Register v2.2, including CHAT-XFER-001 and CHAT-QA-001.
3. CF-04 Central Media Processing and Secure Delivery Conditional Complete Master Plan 2026 v1.0.

**Audited source:** `codex/cf04-1.1.0-rc.1-audit-release`

**Audit verdict:** the `1.1.0-rc.3` source is a useful fail-closed foundation, but it is **not a 100% implementation of the three governing plans**. The former statement that all code-level Must requirements were complete was inaccurate and is superseded by this matrix.

Status meanings:

- **Complete:** the complete requirement and its stated acceptance behavior are implemented and evidenced.
- **Partial:** a useful subset exists, but one or more mandatory behaviors or acceptance proofs are absent.
- **Missing:** the required runtime capability is not implemented in this source candidate.
- Staging, live and operational evidence are evaluated separately and cannot be inferred from source or CI.

| Requirement | Status | Current evidence | Mandatory gap / blocker |
|---|---|---|---|
| CF04-FR-001 Asset policy envelope | Partial | Purpose/domain/privacy/media class/size/MIME/scans/delivery modes are validated. | Duration/pages, derivative set, retention, rights, consent and full policy-envelope enforcement are absent. |
| CF04-FR-002 Resumable upload session | Partial | Multipart upload, checksums, expiry, contiguous parts and idempotent create/complete exist. | No upload credential/token, abort workflow, persisted reservation release, or accepted streaming completion above the local memory limit. |
| CF04-FR-003 Source identity and dedupe | Partial | SHA-256, byte size and owner domain/object are recorded. | No media fingerprint or rights/privacy-safe dedupe policy; storage key omits privacy/rights version and can collapse boundaries. |
| CF04-FR-004 Client metadata distrust | Partial | Safe filename, MIME allowlist and magic-signature comparison exist. | No server-side dimensions/duration/pages/EXIF/container/codec structural probe. |
| CF04-FR-005 Quota and purpose controls | Partial | A simple per-user request rate limit exists. | No atomic storage/job/domain/role quotas, reservations, release, burst budgets, abuse scoring or governed exception flow. |
| CF04-FR-006 Private quarantine | Partial | Local encrypted storage is outside the public root and source starts quarantined. | Separate provider credentials/domain, non-executable serving controls and operator/scanner-scope enforcement are not evidenced. |
| CF04-FR-007 Magic/MIME/container validation | Partial | Basic signature/MIME checks exist. | No bounded structural parser, codec/container probe, encrypted-document policy or malicious corpus coverage. |
| CF04-FR-008 Malware and archive defense | Partial | Mandatory fail-closed external hooks exist for malware/archive/decompression inspection. | Provider version, timeout, recursion, macro/script, polyglot depth and manual-review lifecycle are not implemented. |
| CF04-FR-009 Metadata stripping/preservation | Missing | Workspace context requests location stripping. | No actual EXIF/GPS/author stripping or selective preservation pipeline exists. |
| CF04-FR-010 Technical/content-safety signals | Missing | None. | No versioned confidence signal, reviewer provenance or low-confidence publication barrier implementation. |
| CF04-FR-011 Sandboxed workers | Missing | None. | No non-root ephemeral worker, network isolation, resource ceilings, patched-tool inventory or output validation sandbox. |
| CF04-FR-012 Idempotent job graph | Partial | Request idempotency and a processor callback registry exist. | No persisted probe→scan→transform→validate→store→manifest graph, leases, heartbeat, retry/backoff, dead-letter or orphan recovery. |
| CF04-FR-013 Image pipeline | Missing | Only image workspace parameters are normalized. | No orientation/color-profile/pixel-budget transform, thumbnail/srcset, WebP/AVIF/fallback or visual acceptance tests. |
| CF04-FR-014 Audio/video pipeline | Missing | Only audio workspace parameters are normalized. | No codec probe, adaptive ladder, manifest/segments, poster/waveform generation, loudness/caption references or playback validation. |
| CF04-FR-015 Document pipeline | Missing | Basic PDF signature recognition exists. | No structural PDF parser, page limits, preview, authorized text/OCR, active-content suppression or render sandbox. |
| CF04-FR-016 Derivative lineage | Missing | None. | No derivative records/manifests, tool/preset/version/hash metadata, supersession or atomic switch. |
| CF04-FR-017 Encrypted object storage | Partial | AES-256-GCM local encrypted objects with atomic writes and integrity hash exist. | No provider/bucket/region/class/tier/replication lifecycle, managed key IDs, key rotation/re-encryption or cross-provider tests. |
| CF04-FR-018 Delivery grant | Partial | Signed expiring grant, nonce, audience hash, persistent record, revocation and current-domain reauthorization exist. | Claims do not bind complete actor/service/context/session/operation/range/download/policy-version semantics; no use/replay policy. |
| CF04-FR-019 Public CDN policy | Missing | None. | No public-ready derivative CDN route, immutable cache key, headers/CSP/CORS/nosniff, purge adapter or purge SLO. |
| CF04-FR-020 Private/restricted delivery | Partial | Token verification and audience binding exist. | No same-origin HTTP delivery proxy, no-store/referrer headers, byte-range limits or explicit session binding. |
| CF04-FR-021 Download and disposition | Partial | A user-owned download task record exists and the native domain authorizes creation. | No byte delivery, safe Content-Disposition, view/download-mode enforcement, filename response or alternate-endpoint protection. |
| CF04-FR-022 Integrity checks | Partial | Upload, storage and pre-scan size/hash checks exist. | No derivative/download hash verification, periodic sampling, bit-rot quarantine or repair workflow. |
| CF04-FR-023 Rights/consent binding | Partial | Native domain is called at upload/delivery/deletion time. | License, consent, territory, audience and rights-expiry envelope is not stored/enforced as a versioned technical policy. |
| CF04-FR-024 Asset retention policy | Partial | A native-domain retention decision callback exists. | No lifecycle scheduler, class-specific retention execution, boundary jobs, hold exception processing or temp-workspace cleanup reconciliation. |
| CF04-FR-025 Deletion propagation | Partial | Provider deletion, tombstone and active-grant revocation exist. | Required order/reconciliation is incomplete: grants/CDN are not revoked/purged before source deletion; no derivatives, mappings, backup-expiry ledger, retry state or pending alert. |
| CF04-FR-026 Legal/security hold | Partial | A boolean `legal_hold` can block deletion. | No versioned hold entity, scope/authority/reason/review date, access restriction, expiry/review or authorized release workflow. |
| CF04-FR-027 Provider exit | Missing | None. | No inventory/copy/hash/shadow delivery/switch/rollback/purge confirmation/credential-revocation workflow. |
| CF04-FR-028 Queue priorities and fairness | Missing | None. | No queue implementation, security/revoke priority, tenant fairness, starvation bound or load evidence. |
| CF04-FR-029 Provider abstraction | Partial | A minimal object-store interface and health registry exist. | Adapters do not declare/enforce capabilities, region, limits, cost, webhook security, degraded mode or exit contract. |
| CF04-FR-030 Cost attribution and budgets | Missing | None. | No bytes/jobs/minutes/provider-fee ledger, domain-purpose attribution, budgets, alerts or invoice reconciliation. |
| CF04-FR-031 Safe repair/reprocess | Missing | A generic processor callback can be invoked. | No operator preview/reason/target/preset/idempotent reprocess, domain notice, last-valid preservation or rollback workflow. |
| CF04-FR-032 Restore/rebuild | Missing | None. | No database/object/policy/manifest/mapping/tombstone/key restore orchestrator or pre-serve rights/deletion/integrity reconciliation gate. |
| CF04-FR-033 Observability | Partial | Public health reports runtime/provider state; selected actions write audit events. | No full metrics, queue age, scan/transcode/purge/integrity/storage/cost dashboards, synthetic faults, alerts, traces or runbook linkage. |

## Summary count

- Complete: **0 / 33**
- Partial: **20 / 33**
- Missing: **13 / 33**
- Staging accepted: **No**
- Live deployed: **No**
- Operational: **No**

This count evaluates each requirement against its complete wording and acceptance evidence. It does not deny that the candidate contains valuable security foundations; it prevents those foundations from being mislabeled as complete implementation.

## Immediate release blockers

1. Persisted runtime/audit operations must fail closed when the database or required schema is unavailable; test-only memory mode must not become a production fallback.
2. Verified-user transfer must revalidate both sender and recipient (or the authorized group) through File 00/File 17 at action time, including suspension, consent, relationship, confidentiality and abuse policy.
3. Delivery grants need complete operation/session/context/range/download/policy binding and an actual secure delivery endpoint with disposition and cache/referrer controls.
4. Required processing pipelines, derivative lineage and sandboxed execution are absent.
5. CDN, retention execution, legal-hold lifecycle, ordered deletion reconciliation, provider exit, queues, cost controls, repair, restore and full observability are absent.
6. Real provider, migration, backup/restore, rollback, browser/accessibility/load/security and Founder acceptance evidence remain pending.

## Governing release rule

The candidate may be described as **Specified + partially coded + deterministically packaged + automated tests green for the implemented subset**. It must not be described as fully coded, three-plan complete, staging accepted, live or operational until every Must requirement and its evidence gate is closed.
