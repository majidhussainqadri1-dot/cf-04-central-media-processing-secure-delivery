# C4-A Privacy-Class Mapping Gate

**Status:** Blocked pending domain-owner, privacy and security approval.  
**Runtime authorization:** None.

## Purpose

The source inventory identifies storage families and media classes. It does not by itself establish the approved CF-04 privacy class for any object. File type, storage location, publication state or current URL must never be used as a silent substitute for a domain-approved C1–C5 classification.

## Governing rule

Every future CF-04 asset reference and upload policy requires an explicit privacy class supplied or validated by the canonical domain owner:

- **C1:** public and intentionally cacheable under an approved immutable/public policy;
- **C2:** public or account-related material with bounded metadata and policy-controlled caching;
- **C3:** private user or professional material requiring authenticated, purpose-bound access;
- **C4:** highly restricted identity, communication, financial or equivalent sensitive material;
- **C5:** clinical or comparably critical restricted material requiring the strongest approved controls.

These labels remain planning categories until File 24 and affected domain owners approve their exact definitions and handling rules.

## Prohibited inference

CF-04 and inventory tooling must not infer that:

- an object is C1 merely because it currently has a public WordPress URL;
- an object is C3–C5 merely because it is stored outside the web root;
- every File 10 or File 21 upload shares one privacy class;
- every PDF is public or every File 17 attachment has the same sensitivity;
- a post becoming public permanently makes all source/derivative objects public;
- a stale cache, index or provider URL is authorization evidence.

## Required mapping record

For every domain and purpose, the approved mapping must record:

- canonical domain owner;
- purpose identifier and policy version;
- default privacy class;
- allowed class overrides and authorized approvers;
- source and derivative class rules;
- metadata exposure rules;
- delivery mode and cache policy;
- download/range permission;
- consent and rights requirements;
- minor/guardian restrictions;
- retention, legal hold, revocation and deletion rules;
- click-time authorization source;
- migration and rollback treatment;
- test and approval evidence.

## Domain-specific unresolved decisions

| Domain | Unresolved decision |
|---|---|
| File 10 | Public videos, unpublished/rejected videos, local originals, thumbnails, captions and live/replay assets require separate purpose mappings |
| File 11 | Reel projection must inherit or narrow the File 10 source policy without creating a second source classification |
| File 12 | Public catalog/cover, encrypted source PDF, reader access and downloadable copy require distinct mappings |
| File 17 | Conversation attachment class depends on conversation, participant, purpose, minor status and attachment type; no blanket class is permitted |
| File 21 | Public post media, pending/rejected/private media and failed composer uploads require distinct lifecycle-aware mappings |
| File 22 | Orchestration metadata is not a media privacy decision and cannot assign a broader class than the native owner permits |

## Acceptance gate

Privacy-class mapping remains **Pending** until:

1. File 24 approves the platform class definitions and assurance requirements;
2. each native owner approves purpose-specific mappings;
3. migration and delivery policies consume the same versioned mapping;
4. negative tests prove that stale, absent, unknown or incompatible mappings fail closed;
5. the Founder approves the resulting change-control record.

No inventory count may be labeled C1–C5 before that mapping is approved. Until then, staging evidence must use neutral native states such as public, pending, rejected, private, restricted and deleted, with a separate `privacy_mapping_pending` marker.
