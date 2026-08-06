# CF-04 Requirements Completion Matrix

**Governing plans:** Definitive Master Plan 2026 v3.0; Consolidated All-Chats Directive Register; CF-04 Conditional Complete Master Plan v1.0.

**Candidate:** `1.2.0-rc.2`

The matrix records source-code completion and automated source-level acceptance. External staging/provider/live/operational evidence remains separate and is not represented as complete.

| Requirement | Mandatory capability | Source ownership | Acceptance evidence | Source status |
|---|---|---|---|---|
| CF04-FR-001 | Asset policy envelope | `Policy, RightsPolicy` | `tests/run-all.php FR-001` + four review gates | Complete in source |
| CF04-FR-002 | Resumable upload session | `UploadService, PartStore, Idempotency` | `FR-002` + four review gates | Complete in source |
| CF04-FR-003 | Source identity and dedupe | `UploadService::complete/dedupe, Validator` | `FR-003` + four review gates | Complete in source |
| CF04-FR-004 | Client metadata distrust | `Validator::inspectStream` | `FR-004` + four review gates | Complete in source |
| CF04-FR-005 | Quota and purpose controls | `QuotaService, RateLimiter` | `FR-005` + four review gates | Complete in source |
| CF04-FR-006 | Private quarantine | `LocalObjectStore, UploadService` | `FR-006` + four review gates | Complete in source |
| CF04-FR-007 | Magic/MIME/container validation | `Validator, ArchiveInspector, ToolRunner` | `FR-007` + four review gates | Complete in source |
| CF04-FR-008 | Malware and archive defense | `ScannerRegistry, ArchiveInspector` | `FR-008` + four review gates | Complete in source |
| CF04-FR-009 | Metadata stripping/preservation | `MetadataPolicy, ToolRunner` | `FR-009` + four review gates | Complete in source |
| CF04-FR-010 | Technical/content-safety signals | `SafetySignalService` | `FR-010` + four review gates | Complete in source |
| CF04-FR-011 | Sandboxed workers | `ToolRunner` | `FR-011` + four review gates | Complete in source |
| CF04-FR-012 | Idempotent job graph | `JobService, ProcessingService` | `FR-012` + four review gates | Complete in source |
| CF04-FR-013 | Image pipeline | `ImagePipeline` | `FR-013` + four review gates | Complete in source |
| CF04-FR-014 | Audio/video pipeline | `AvPipeline` | `FR-014` + four review gates | Complete in source |
| CF04-FR-015 | Document pipeline | `DocumentPipeline` | `FR-015` + four review gates | Complete in source |
| CF04-FR-016 | Derivative lineage | `DerivativeService` | `FR-016` + four review gates | Complete in source |
| CF04-FR-017 | Encrypted object storage | `Keyring, Crypto, LocalObjectStore, KeyRotationService` | `FR-017` + four review gates | Complete in source |
| CF04-FR-018 | Delivery grant | `DeliveryService, Crypto` | `FR-018` + four review gates | Complete in source |
| CF04-FR-019 | Public CDN policy | `CdnRegistry, DeliveryService::publishPublic` | `FR-019` + four review gates | Complete in source |
| CF04-FR-020 | Private/restricted delivery | `DeliveryService::serve` | `FR-020` + four review gates | Complete in source |
| CF04-FR-021 | Download and disposition | `DownloadManagerService, DeliveryService` | `FR-021` + four review gates | Complete in source |
| CF04-FR-022 | Integrity checks | `IntegrityService` | `FR-022` + four review gates | Complete in source |
| CF04-FR-023 | Rights/consent binding | `RightsPolicy, DeliveryService` | `FR-023` + four review gates | Complete in source |
| CF04-FR-024 | Asset retention policy | `RetentionService` | `FR-024` + four review gates | Complete in source |
| CF04-FR-025 | Deletion propagation | `DeletionService` | `FR-025` + four review gates | Complete in source |
| CF04-FR-026 | Legal/security hold | `LegalHoldService` | `FR-026` + four review gates | Complete in source |
| CF04-FR-027 | Provider exit | `ProviderExitService` | `FR-027` + four review gates | Complete in source |
| CF04-FR-028 | Queue priorities and fairness | `JobService` | `FR-028` + four review gates | Complete in source |
| CF04-FR-029 | Provider abstraction | `ObjectStore, ProviderRegistry, WebhookService` | `FR-029` + four review gates | Complete in source |
| CF04-FR-030 | Cost attribution and budgets | `CostService` | `FR-030` + four review gates | Complete in source |
| CF04-FR-031 | Safe repair/reprocess | `RepairService` | `FR-031` + four review gates | Complete in source |
| CF04-FR-032 | Restore/rebuild | `RestoreService` | `FR-032` + four review gates | Complete in source |
| CF04-FR-033 | Observability | `Observability, Audit` | `FR-033` + four review gates | Complete in source |

## Cross-plan directive

| Directive | Source | Evidence | Status |
|---|---|---|---|
| CHAT-XFER-001 | `TransferService`, `Auth::transferParties`, `UploadService`, `DeliveryService` | `tests/run-all.php` transfer checks | Complete in source |
| CHAT-QA-001 | `tools/quality-check.sh`, review rounds 11–14, deterministic double build | exact-head CI | Complete in source |

## Release boundary

All 33 source requirements and the transfer directive have implementation paths and automated source evidence. Hostinger staging, real provider acceptance, migration, backup/restore, rollback, browser/accessibility/load/security acceptance, Founder approval, live deployment and monitored operations remain pending external gates.
