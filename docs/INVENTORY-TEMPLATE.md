# CF-04 Binary Inventory Template

Complete one row per canonical binary object before ownership extraction. Do not place secrets, full private URLs, tokens, raw clinical data or message content in this public repository.

## Asset inventory

| Field | Required value |
|---|---|
| Inventory record ID | Stable evidence ID |
| Current domain owner | File 10/11/12/17/21/22/other approved owner |
| Canonical domain object ID | Mask or use safe test identifier in public evidence |
| Media class | image/audio/video/document/attachment |
| Purpose | publication/player/reader/message/clinical/other approved purpose |
| Privacy class | C1–C5 |
| Current storage/provider | Safe provider alias only |
| Current object reference | Masked reference; never credential-bearing URL |
| Source hash | Cryptographic digest where policy permits |
| Size/type/duration/pages | Measured technical properties |
| Current derivatives | Variant IDs and safe technical metadata |
| Rights/consent version | Domain policy reference |
| Retention rule | Schedule ID |
| Hold status | none/active/review-required |
| Current deletion state | active/revoked/pending/deleted/unknown |
| Current public exposure | public/restricted/private/unknown |
| Orphan/duplicate/corrupt flag | yes/no/unknown with reason |
| Migration eligibility | eligible/blocked/review-required |
| Reconciliation result | matched/missing/hash-mismatch/access-drift/duplicate |
| Evidence location | Private evidence reference or public-safe artifact |
| Reviewer/date | Named reviewer and timestamp |

## Provider inventory

| Provider alias | Service type | Region | Data classes allowed | Encryption | Export capability | Delete confirmation | Exit tested | Owner |
|---|---|---|---|---|---|---|---|---|
| TBD | object storage/CDN/transcode/scanner | TBD | TBD | TBD | TBD | TBD | No | TBD |

## Route and process inventory

| Existing route/process | Domain owner | Reads/writes | Authentication | Authorization source | Cache behavior | Migration action |
|---|---|---|---|---|---|---|
| TBD | TBD | TBD | TBD | TBD | TBD | retain/adapt/replace/decommission |

## Reconciliation invariants

1. Every canonical domain object has zero or more declared assets; every asset points to exactly one canonical owner/domain relation.
2. Every stored object has a known asset, purpose, privacy class and lifecycle state.
3. Every derivative has a source asset, tool/preset version, hash and status.
4. No restricted object is publicly cached or exposed through an unbounded URL.
5. A deletion marked complete has source, derivative, CDN and provider confirmation or a documented backup-expiry obligation.
6. Unknown records block migration until resolved or Founder-approved as a time-bound residual risk.
