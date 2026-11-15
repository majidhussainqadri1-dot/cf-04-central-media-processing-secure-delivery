# CF-04 Runtime Status

## Candidate

- Runtime: `1.3.0-rc.1`
- Schema: `1.5.0`
- Contract: `1.5.0`
- Runtime default: disabled
- Governing CF-04 plan: `v1.1 — Future-40 Amended — 2026-09-16`
- Source implementation: 33/33 CF04 functional requirements plus CHAT-XFER-001, CF04-CEN-01..10, CF04-NJ-01..06 source journeys, and CF04-FUT-001..040
- Automated source acceptance: required on PHP 8.1, 8.3 and 8.4

## Completion boundaries

| Level | Status |
|---|---|
| Specified | Complete |
| Coded | Complete for approved source scope |
| Deterministically packaged | CI-gated |
| Automated QA | Exact-head CI-gated |
| Hostinger staging accepted | Pending |
| Real providers accepted | Pending |
| Live deployed | No |
| Operational | No |

No external-environment claim may be inferred from source completion.

## Current rewritten-plan parity

- CF04-CEN-01 through CF04-CEN-10: implemented and source-tested in candidate `1.3.0-rc.1`.
- CF04-NJ-01 through CF04-NJ-06: source paths/negative gates represented in the automated suite.
- Data constitution: `C0 Public`, `C1 Account`, `C2 Private Communication`, `C3 Professional Evidence`, `C4 Clinical-Sensitive`, `C5 Security Secret`.
- Runtime remains disabled by default. Hostinger/real-provider/browser/accessibility/load/penetration/migration/restore/rollback/live/operational acceptance is still pending.

## Future-40 extension — 2026-09-16

- CF04-FUT-001 through CF04-FUT-040 are implemented in source and covered by `tests/future40.php`.
- External media engines/providers are adapter-gated and fail closed when absent.
- Canonical ownership remains with the existing domain owners; CF-04 owns binary/media infrastructure only.
- This source completion does not claim real C2PA/provider certification, real CDN failover, real regional replication, staging acceptance, live deployment or operational acceptance.

## V12.3 checkpoint replay hardening — 2026-11-15 (source-only)

- Scope: defensive replay verification for the final completed physical shadow target in `ProviderExitShadowCopyStage::copySegment()`.
- Before advancing or accepting an idempotent completed journal, the last target object is rehashed and checked against the current authoritative staged identity; missing/corrupt targets fail closed with `provider_exit_shadow_checkpoint_stale`.
- New `tests/review-round-293-shadow-checkpoint-replay.php` covers missing target, corrupt target, non-advancement, recovery, completion, and continued non-destructive owner mapping.
- This bounded replay guard **is not** full segmented cutover, cross-owner write fencing, >2,000 reference migration, real KMS rotation, restore execution, Hostinger staging acceptance, or live deployment. Every external release gate remains mandatory.

## V12.3 read-only deployment freeze utility (R294)

Added a CLI-only non-mutating evidence collector for deployed PHP fingerprints, schema/options, active plugin list and safe table counts. Fixture tests require no data mutation or false claims. **No live evidence was collected**: the WordPress account has no connected WPVibe site, and the attempted connection returned HTTP 530.

## R295 — candidate package PHP source witness

Deterministic manifest and release evidence carry a reproducible PHP-only tree digest matching the CLI freeze collector's algorithm; CI validates ZIP entries against the manifest. This does not establish live deployment parity until observed deployed PHP hashes are collected and matched independently.

## R296 — target/source pre-switch preflight and administrator guard

Source-level hardening checks every planned target and original source before first provider remap, validates target-storage envelopes and enforces media_manage_providers on copy, shadow, switch and purge operations. These checks do not provide native cross-owner write fencing or >2,000 item cutover. The corresponding regression is mandatory in the source quality gate.

## R297 — honest version provenance

Corrected a misleading live-freeze output key: the loaded plugin `SCM_VERSION` constant is now labeled `loaded_plugin_version_constant`, not repository truth. R294 fixture asserts the distinction; no live access or migration was performed.

## R298 — exact branch HEAD CI evidence

The candidate branch is included in the GitHub Actions push matrix in addition to PR validation. Pull-request checkout normally uses a synthetic merge commit; the push workflow explicitly builds/tests the exact source-branch commit for PHP 8.1/8.3/8.4. A green PR merge test alone must never be misreported as a tested deployed package or an exact PR-head execution. Evidence remains bound to the **current** SHA at the time of checking.

## R299 restore physical source audit — 2026-11-15

Read-only bounded encrypted source-object rehashing added for asset/derivative pages with durable CAS journals and final fresh verification. The successful marker is `physical_verified_unfenced` only. No executable restore, snapshot consistency across DB/object/key, KMS, owner write fence, staging or live acceptance has been achieved.

## R300 — physical key identity check for staged candidates

Added optional ObjectCipherKeyInspector and bounded source ciphertext/key-id preflight for candidate key rotation. Real LocalObjectStore encrypted bytes and physical key IDs are checked without rewriting, retiring keys or allowing unfenced cutover. Real KMS/third-party provider integration, durable >2,000-alias group processing and staging acceptance remain open.

## R301 — bounded local ciphertext key-header inspection

Changed the physical key ID reader from unbounded fgets to a 65,536-byte newline-terminated, strict-format, canonical-key envelope check. Added R301 regression including deliberately overlong and forged headers. This prevents an untrusted local object header from being interpreted as valid key identity but does not replace a KMS/HSM provider audit.

## R302 — bounded physical key-rotation shadow re-encryption

Implemented source-only, bounded 1–25 item shadow re-encryption of staged stale-key candidates into separately encrypted new objects, using deterministic target identity, physical source/target key-ID and byte hashes, durable CAS journals, replay and complete segment verification. Original source, key, mapping and serving state stay untouched. This is not a global >2,000-item cutover or approved KMS key retirement. Quality gate must pass at the exact candidate HEAD before source acceptance.

## R303 — cooperative multi-segment shadow key coordinator

Added a durable source-only coordinator across all staged key candidate segments, bounded 1–25 aliases per invocation with CAS checkpoints and complete fresh physical rehash. Test uses >2,600 metadata records plus real encrypted stale candidates, interruption/recovery and no remap. This does not prove performance for >2,000 affected aliases or authorize old-key cutover, KMS retirement, staging or live deployment.

## R304 — exact staged segment catalogue check

Provider-exit, key-rotation and restore source inventories now reject extra segments claiming the same inventory/checkpoint ID, rather than only verifying expected page IDs and chain hashes. Negative tests inject and remove an extraneous segment for each source family. This is source-level fail-closed validation, not concurrent owner write fencing or live acceptance.

## R305 — greater-than-2000 affected alias source regression

Added deterministic in-memory physical provider regression with 2,001 genuinely stale key aliases, ten source inventory segments, bounded resumable shadow copies, one reused target ciphertext, full fresh proof and wrong-physical-key rejection. No real MySQL/provider/KMS or production cutover has been verified; all original mappings stay unchanged.

## R306 — native owner mapping manifest before provider cutover

Added read-only, per-segment CAS-bound native owner mapping manifest verified against real encrypted shadow copy and source DB records. It detects forged references and physical target loss; terminal state is `verified_unfenced` with no permission to remap, purge or revoke credentials. Full-fleet write fencing, approved atomic cutover/rollback, Hostinger and other real-provider acceptance remain unverified.
