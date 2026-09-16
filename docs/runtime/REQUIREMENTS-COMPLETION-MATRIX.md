# CF-04 Requirements Completion Matrix

**Governing plans:** Definitive Master Plan 2026 v3.0; Consolidated All-Chats Directive Register; CF-04 Conditional Complete Master Plan v1.1 — Future-40 Amended (2026-09-16).

**Candidate:** `1.3.0-rc.1`  
**Schema / contract:** `1.5.0 / 1.5.0`  
**Runtime default:** disabled and fail-closed.

This matrix records repository source-code completion and automated source-level acceptance only. Hostinger staging, real providers, migration/backup/restore/rollback rehearsal, browser/device/accessibility/load/penetration acceptance, Founder release approval, live deployment and operational acceptance remain separate evidence gates.

## Base CF-04 functional requirements

| Requirement | Mandatory capability | Source ownership | Acceptance evidence | Source status |
|---|---|---|---|---|
| CF04-FR-001 | Asset policy envelope | `Policy, RightsPolicy` | `tests/run-all.php` | Complete in source |
| CF04-FR-002 | Resumable upload session | `UploadService, PartStore, Idempotency` | `tests/run-all.php` | Complete in source |
| CF04-FR-003 | Source identity and dedupe | `UploadService::complete/dedupe, Validator` | `tests/run-all.php` | Complete in source |
| CF04-FR-004 | Client metadata distrust | `Validator::inspectStream` | `tests/run-all.php` | Complete in source |
| CF04-FR-005 | Quota and purpose controls | `QuotaService, RateLimiter` | `tests/run-all.php` | Complete in source |
| CF04-FR-006 | Private quarantine | `UploadService, ObjectStore` | `tests/run-all.php` | Complete in source |
| CF04-FR-007 | Magic/MIME/container validation | `Validator, ArchiveInspector, ToolRunner` | `tests/run-all.php` | Complete in source |
| CF04-FR-008 | Malware/archive/polyglot/bomb defense | `ScannerRegistry, ArchiveInspector` | `tests/run-all.php` | Complete in source |
| CF04-FR-009 | Metadata stripping/preservation | `MetadataPolicy, ToolRunner` | `tests/run-all.php` | Complete in source |
| CF04-FR-010 | Technical/content-safety signals | `SafetySignalService` | `tests/run-all.php` | Complete in source |
| CF04-FR-011 | Sandboxed workers | `ToolRunner` | `tests/run-all.php` | Complete in source |
| CF04-FR-012 | Idempotent job graph | `JobService, ProcessingService` | `tests/run-all.php` | Complete in source |
| CF04-FR-013 | Image pipeline | `ImagePipeline` | `tests/run-all.php` | Complete in source |
| CF04-FR-014 | Audio/video pipeline | `AvPipeline` | `tests/run-all.php` | Complete in source |
| CF04-FR-015 | Document pipeline | `DocumentPipeline` | `tests/run-all.php` | Complete in source |
| CF04-FR-016 | Derivative lineage | `DerivativeService` | `tests/run-all.php` | Complete in source |
| CF04-FR-017 | Encrypted object storage | `Keyring, Crypto, ObjectStore, KeyRotationService` | `tests/run-all.php` | Complete in source |
| CF04-FR-018 | Delivery grant | `DeliveryService, Crypto` | `tests/run-all.php` | Complete in source |
| CF04-FR-019 | Public CDN policy | `CdnRegistry, DeliveryService::publishPublic` | `tests/run-all.php` | Complete in source |
| CF04-FR-020 | Private/restricted delivery | `DeliveryService::serve` | `tests/run-all.php` | Complete in source |
| CF04-FR-021 | Download/disposition | `DownloadManagerService, DeliveryService` | `tests/run-all.php` | Complete in source |
| CF04-FR-022 | Integrity checks | `IntegrityService` | `tests/run-all.php` | Complete in source |
| CF04-FR-023 | Rights/consent binding | `RightsPolicy, DeliveryService` | `tests/run-all.php` | Complete in source |
| CF04-FR-024 | Retention policy | `RetentionService` | `tests/run-all.php` | Complete in source |
| CF04-FR-025 | Deletion propagation | `DeletionService` | `tests/run-all.php` | Complete in source |
| CF04-FR-026 | Legal/security hold | `LegalHoldService` | `tests/run-all.php` | Complete in source |
| CF04-FR-027 | Provider exit | `ProviderExitService` | `tests/run-all.php` | Complete in source |
| CF04-FR-028 | Queue priorities/fairness | `JobService` | `tests/run-all.php` | Complete in source |
| CF04-FR-029 | Provider abstraction/webhooks | `ObjectStore, ProviderRegistry, WebhookService` | `tests/run-all.php` | Complete in source |
| CF04-FR-030 | Cost attribution/budgets | `CostService` | `tests/run-all.php` | Complete in source |
| CF04-FR-031 | Safe repair/reprocess | `RepairService` | `tests/run-all.php` | Complete in source |
| CF04-FR-032 | Restore/rebuild | `RestoreService` | `tests/run-all.php` | Complete in source |
| CF04-FR-033 | Observability/audit | `Observability, Audit` | `tests/run-all.php` | Complete in source |

## Cross-plan directives

| Directive | Source | Evidence | Status |
|---|---|---|---|
| CHAT-XFER-001 | `TransferService`, `Auth::transferParties`, upload/delivery services | `tests/run-all.php` | Complete in source |
| CHAT-QA-001 | quality gate, sequential review evidence, deterministic double build | exact-head CI | Complete in source |

## Current rewritten-plan parity

| Requirement | CF-04 source responsibility | Evidence | Source status |
|---|---|---|---|
| CF04-CEN-01 | Typed owner reference + C0-C5 class + rights/purpose/lawful-basis/retention/revocation envelope | `tests/new-plan-parity.php` | Complete in source |
| CF04-CEN-02 | Quarantine + required scans + fail-closed processing | base suites + new-plan suite | Complete in source |
| CF04-CEN-03 | Versioned/checksum lineage + stale-state blocking | base/adversarial suites | Complete in source |
| CF04-CEN-04 | Purpose/privacy-bound grants + rights-aware CDN identity | `tests/new-plan-parity.php` | Complete in source |
| CF04-CEN-05 | C0-only public CDN; restricted classes denied public delivery | `tests/new-plan-parity.php` | Complete in source |
| CF04-CEN-06 | Accessibility provenance + qualified high-risk review | `AccessibilityMetadataService` + tests | Complete in source |
| CF04-CEN-07 | Low-bandwidth/audio/text alternatives + resumable transfer | `RenditionSelector`, transfer suites | Complete in source |
| CF04-CEN-08 | Rights/delete propagation through grants/CDN/derivatives/consumer projection | `RightsRevocationService`, `DeletionService` | Complete in source |
| CF04-CEN-09 | Explicit degraded state; no silent widening/substitution | `DegradedStateService`, rendition/provider tests | Complete in source |
| CF04-CEN-10 | Privacy-minimal allowlisted operational media telemetry | `PrivacyTelemetry` | Complete in source |

### Native journeys

| Journey | Source evidence | Status |
|---|---|---|
| CF04-NJ-01 | upload → quarantine → scan/type/rights → derivatives → owner review/publish-ready | Covered in source |
| CF04-NJ-02 | AV transcode → accessibility metadata → low-bandwidth/playback fallback | Covered in source |
| CF04-NJ-03 | private 1 GiB chunk/resume → scan → scoped delivery → revoke/purge | Covered in source |
| CF04-NJ-04 | rights expiry/source deletion → revoke delivery/cache/derivatives → evidence | Covered in source |
| CF04-NJ-05 | provider/queue outage → retry/dead-letter → repair without duplicate derivative | Covered in source |
| CF04-NJ-06 | malware/polyglot/archive-bomb containment without preview/index leakage | Covered in source |

## Approved Future-40 source capabilities

The Future-40 layer is fail-closed. Capabilities that require specialist engines/providers require an approved adapter and do not simulate real-provider success when that adapter is absent.

| Requirement | Capability | Evidence | Source status |
|---|---|---|---|
| CF04-FUT-001 | C2PA/content-credential provenance envelope | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-002 | Synthetic-media declaration | same | Complete in source |
| CF04-FUT-003 | Perceptual fingerprint registry | same | Complete in source |
| CF04-FUT-004 | Privacy-safe near-duplicate detection | same | Complete in source |
| CF04-FUT-005 | Storage-level safe deduplication | same | Complete in source |
| CF04-FUT-006 | CDR safe reconstruction | same | Complete in source |
| CF04-FUT-007 | Threat intelligence re-scan | same | Complete in source |
| CF04-FUT-008 | Emergency format/codec kill switch | same | Complete in source |
| CF04-FUT-009 | Content-aware encoding plan | same | Complete in source |
| CF04-FUT-010 | Objective quality scoring gate | same | Complete in source |
| CF04-FUT-011 | Codec capability negotiation | same | Complete in source |
| CF04-FUT-012 | Color/HDR/ICC lineage | same | Complete in source |
| CF04-FUT-013 | Audio quality control | same | Complete in source |
| CF04-FUT-014 | Smart poster/storyboard | same | Complete in source |
| CF04-FUT-015 | Chapter/timecode infrastructure | same | Complete in source |
| CF04-FUT-016 | Rich caption tracks | same | Complete in source |
| CF04-FUT-017 | Audio-description tracks | same | Complete in source |
| CF04-FUT-018 | Sign-language media track | same | Complete in source |
| CF04-FUT-019 | OCR confidence/coordinate map | same | Complete in source |
| CF04-FUT-020 | Accessibility manifest | same | Complete in source |
| CF04-FUT-021 | Sensitive-data technical detection | same | Complete in source |
| CF04-FUT-022 | Governed redaction derivatives | same | Complete in source |
| CF04-FUT-023 | Multi-CDN failover | same | Complete in source |
| CF04-FUT-024 | Origin shield/cache protection | same | Complete in source |
| CF04-FUT-025 | Edge authorization profile | same | Complete in source |
| CF04-FUT-026 | Network-adaptive upload | same | Complete in source |
| CF04-FUT-027 | Encrypted offline package/grant | same | Complete in source |
| CF04-FUT-028 | Cross-session/device resumable download | same | Complete in source |
| CF04-FUT-029 | Regional residency pinning | same | Complete in source |
| CF04-FUT-030 | WORM/object lock | same | Complete in source |
| CF04-FUT-031 | Per-asset envelope encryption metadata | same | Complete in source |
| CF04-FUT-032 | Crypto-agility policy | same | Complete in source |
| CF04-FUT-033 | Multi-region disaster-recovery plan | same | Complete in source |
| CF04-FUT-034 | Storage-tier optimization | same | Complete in source |
| CF04-FUT-035 | Pre-processing cost estimate | same | Complete in source |
| CF04-FUT-036 | Approved-provider auto-routing | same | Complete in source |
| CF04-FUT-037 | Privacy-minimal QoE telemetry | same | Complete in source |
| CF04-FUT-038 | Staging-only chaos/fault injection | same | Complete in source |
| CF04-FUT-039 | Signed migration/export bundle | same | Complete in source |
| CF04-FUT-040 | Versioned media SDK/contract kit | same | Complete in source |

## Release boundary

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed` and `Operational` are distinct statuses. This matrix can support only repository/source claims. No staging, live or operational claim is made here.
