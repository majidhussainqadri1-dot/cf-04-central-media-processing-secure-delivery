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

## Current rewritten-plan parity

The 2026-09 current CF-04 plan adds the following source gates. These rows record only CF-04 native implementation/consumer-contract responsibility; domain capabilities remain with their canonical owners.

| Requirement | CF-04 source responsibility | Evidence | Source status |
|---|---|---|---|
| CF04-CEN-01 | Typed owner reference + C0-C5 class + rights/purpose/lawful-basis/retention/revocation envelope | `tests/new-plan-parity.php` | Complete in source |
| CF04-CEN-02 | Quarantine + required scans + fail-closed processing | existing FR-006..011 + new-plan suite | Complete in source |
| CF04-CEN-03 | Versioned/checksum lineage + active-manifest stale-state checks | FR-016/022 + adversarial tests | Complete in source |
| CF04-CEN-04 | Purpose/privacy-bound grants + rights-aware immutable CDN key | new-plan suite | Complete in source |
| CF04-CEN-05 | C0-only public CDN; C2-C5 public delivery denied | new-plan suite | Complete in source |
| CF04-CEN-06 | Accessibility metadata provenance + qualified high-risk review | `AccessibilityMetadataService` | Complete in source |
| CF04-CEN-07 | Explicit low-bandwidth/audio/text selection + resumable transfer | `RenditionSelector`, FR-002/014/021, CHAT-XFER-001 | Complete in source |
| CF04-CEN-08 | Rights/delete propagation through grants/CDN/derivatives + downstream invalidation event | `RightsRevocationService`, `DeletionService` | Complete in source |
| CF04-CEN-09 | Explicit degraded-state evidence; no silent rendition substitution | `DegradedStateService`, `RenditionSelector` | Complete in source |
| CF04-CEN-10 | Allowlisted aggregate operational telemetry; person/content labels denied | `PrivacyTelemetry` | Complete in source |

### Native journeys

| Journey | Source evidence |
|---|---|
| CF04-NJ-01 | upload/quarantine/scan/process/manifest path + owner contracts |
| CF04-NJ-02 | AV adaptive + caption/transcript references + low-bandwidth outputs |
| CF04-NJ-03 | verified 1 GiB resumable transfer + scoped grant/revoke |
| CF04-NJ-04 | rights-expiry/deletion propagation + tombstone/projection evidence |
| CF04-NJ-05 | durable jobs, bounded retry, dead-letter, repair and degraded state |
| CF04-NJ-06 | malware/archive/polyglot/decompression-bomb fail-closed scan path |

External browser/device/accessibility, real-provider, Hostinger staging, migration/restore/rollback, live and operational evidence remain separate gates and are not claimed by this source matrix.


## Approved Future-40 source capabilities

The Founder-approved Future-40 extension is implemented as a fail-closed source layer. Capabilities requiring specialist external engines (for example provenance signing, perceptual hashing, CDR, quality scoring or sensitive-data detection) require an approved adapter and do not silently simulate production success. Staging/live/provider acceptance remains a separate lifecycle gate.

| Requirement | Source responsibility | Evidence | Source status |
|---|---|---|---|
| CF04-FUT-001 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-002 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-003 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-004 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-005 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-006 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-007 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-008 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-009 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-010 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-011 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-012 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-013 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-014 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-015 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-016 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-017 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-018 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-019 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-020 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-021 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-022 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-023 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-024 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-025 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-026 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-027 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-028 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-029 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-030 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-031 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-032 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-033 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-034 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-035 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-036 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-037 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-038 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-039 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
| CF04-FUT-040 | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |
