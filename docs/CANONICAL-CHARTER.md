# CF-04 Canonical Charter

## 1. Constitutional identity

**Working identifier:** CF-04  
**Title:** Central Media Processing and Secure Delivery  
**Status:** Conditional future shared-infrastructure module  
**Current authorization:** Phase C4-A governance and evidence preparation only

CF-04 becomes the canonical owner of shared binary-asset infrastructure only after the activation gates and ownership-transfer migration are approved. Planning completeness does not authorize runtime extraction.

## 2. Canonical ownership after activation

CF-04 will own:

1. Media asset and source-object identity.
2. Purpose-bound upload sessions and multipart/resumable parts.
3. Quarantine, validation and scan state.
4. Processing jobs, retries, checkpoints and dead-letter state.
5. Derivatives, manifests, hashes and lineage.
6. Storage-object mappings, encryption/key metadata and lifecycle tiers.
7. Delivery grants, expiry, revocation and CDN purge evidence.
8. Quotas, resource controls, provider health, cost attribution and exit readiness.
9. Binary retention, holds, deletion stages and provider confirmations.
10. Processing and delivery audit evidence.

## 3. Canonical non-ownership

CF-04 will not own:

- editorial, medical, Sharīʿah or publication approval;
- post, article, lesson, video, Reel, PDF, message or clinical-record domain truth;
- video channels, playlists, live-event state, player or replay truth;
- feed ranking, watch history, PDF reader state or bibliographic rights;
- general content moderation decisions;
- identity, role, entitlement, guardian or verification truth;
- global shell, navigation, theme, public profile or timeline rendering;
- raw private originals in the public WordPress Media Library;
- secrets, stream keys or provider credentials in browser-visible or ordinary WordPress option storage.

## 4. Architectural invariants

1. Create, update and delete operations occur only through the canonical owner command.
2. Read contracts use versioned DTO/query interfaces; internal tables are not public contracts.
3. Events are past-tense facts and consumers must be idempotent.
4. Every result retains canonical owner ID and version; provider IDs are mappings only.
5. Authorization and visibility are rechecked at action time and delivery time.
6. No silent fallback may broaden access, publish content, alter money, or mutate clinical/support decisions.
7. A failed or partial derivative never appears Ready.
8. Unscanned, invalid, malicious or unauthorized media is never deliverable.
9. Domain policy controls derivative eligibility, retention and deletion; CF-04 executes the binary lifecycle without seizing domain authority.
10. CF-04 failure must degrade safely and must not turn a private object public.

## 5. Core state families

### Asset lifecycle

`Reserved → Uploading → Uploaded → Quarantined → Validating → Scanning → Processing → Ready`

Exceptional states include `Rejected`, `Failed`, `Revoked`, `DeletionPending`, `Deleted`, `Held` and `MigrationPending`.

### Job lifecycle

`Queued → Leased → Running → Succeeded | Retrying | DeadLetter | Cancelled`

### Delivery-grant lifecycle

`Issued → Active → Expired | Revoked | Exhausted`

Every transition must carry actor/service, source state, target state, record version, reason, idempotency key, timestamp, policy version, trace ID and audit record.

## 6. Privacy classes

Media inherits the owning domain’s classification, ranging from public C1 through highly restricted C5. CF-04 must never reduce classification. Private and restricted assets require encrypted storage, minimum-field logs, no public caching, short-lived delivery and purpose-bound authorization.

## 7. Truthful status law

The following statuses are distinct and must be reported separately:

- Specified
- Coded
- Packaged
- Automated-QA Green
- Staging-Accepted
- Live-Deployed
- Operational

This repository currently claims only **Specified** and **Phase C4-A repository initialized**.
