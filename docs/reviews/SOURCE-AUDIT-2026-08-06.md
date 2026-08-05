# CF-04 Complete Source Audit — 2026-08-06

## Scope and provenance
The reconstructed `1.1.0-rc.1` runtime source was first preserved on the independent branch `codex/cf04-1.1.0-rc.1-audit-release`. The audit covered the plugin bootstrap, authorization, owner adapters, uploads, multipart state, quarantine, validation/scanners, encrypted object storage, processing, signed delivery, downloads, verified-user transfer, retention/deletion, REST, persistence, audit evidence, package builder, tests and GitHub Actions.

## Governing boundaries
CF-04 owns shared binary ingest, quarantine, technical processing, storage and secure delivery only after approved activation. Native content, clinical, message, PDF, video/reel, identity, shell, assurance and visual truth remain with their canonical owners. Runtime presence does not authorize extraction or production activation.

## Material findings and disposition

| Severity | Finding | Correction |
|---|---|---|
| Critical | Delivery trusted caller-supplied asset data and embedded the object key in signed claims. | Canonical asset lookup is mandatory; object keys remain server-side; persisted grants bind asset, audience, version, expiry and token hash. |
| Critical | Grant revocation existed only in process memory. | Grant lifecycle and revocation are persisted in the canonical record store and rechecked on every consumption. |
| High | Audit evidence existed only in memory. | Runtime audit events persist to the dedicated audit table; persistence failure blocks sensitive runtime operations. |
| High | Runtime storage could silently fall back to a temporary local directory. | Production runtime requires an explicitly registered healthy provider and a safely configured absolute private root outside the public WordPress tree. |
| High | Local object writes were non-atomic and envelope validation was weak. | Atomic temporary write/rename, strict object keys, AES-GCM envelope validation and stored hash verification were added. |
| High | Deletion accepted caller state, did not persist a tombstone, and claimed CDN purge without performing it. | Canonical lookup, owner-version authorization, legal-hold check, provider purge, grant revocation and truthful persisted tombstone are enforced; CDN status is explicit. |
| High | Persistent records used replace semantics rather than compare-and-swap. | Insert/update version predicates and conflict detection now enforce optimistic concurrency. |
| High | Idempotency only claimed keys; it could not safely replay completed results. | Claimed/completed/failed lifecycle, fingerprint conflicts, in-progress rejection and result-reference replay were implemented. |
| High | MIME validation defaulted to acceptance for unknown formats. | Signature-first detection, canonical MIME aliases and fail-closed mismatch behavior were implemented. |
| High | Archive, metadata and decompression scanners could report success without a real provider. | Provider-backed scanners now fail closed when required evidence is unavailable. |
| High | A large upload could be assembled entirely in PHP memory. | Part sizes are bounded and local assembly fails closed above the configured safe limit until an approved streaming assembly/scanning/delivery provider is accepted. |
| High | Verified-transfer identity could fall back to a synthetic verified assertion. | File 00 assertion evidence is mandatory; unavailable or incomplete assertions fail closed. |
| Medium | REST could leak an unhandled throwable/fatal path. | Unexpected failures receive a generic incident response and safe audit event. |
| Medium | Scan failure was not retained on the asset. | Failure code/time are persisted while the asset remains quarantined. |
| Medium | Cryptographic grant/envelope parsing lacked complete structural/time validation. | Token size, claim completeness, issued/expiry bounds and envelope fields are strictly validated. |
| Medium | Tests accepted inline assets and did not exercise deletion/replay/provider failure. | Tests now cover canonical state, grants, revocation, deletion, idempotency, scanner failure, upload replay and fail-closed provider behavior. |

## Result
No known unresolved Critical source defect remains in the audited release-candidate scope. The large-object requirement is not falsely claimed: unsafe local processing is blocked, while production activation remains gated on a real streaming provider and staging evidence. Real external scanners/providers, Hostinger migration/rollback and live operations are acceptance dependencies, not proven by repository CI.
