# CF-04 Delivery Roadmap

## C4-A — Activation foundation

**Goal:** prove that extraction is justified and safe before runtime coding.

Deliverables:

- canonical charter and ownership matrix;
- activation-gate register;
- current binary/provider/route inventory;
- policy-envelope and asset-reference contract drafts;
- threat-model and data-flow preparation;
- volume, reliability and cost evidence;
- migration, shadow-mode, reconciliation and rollback design;
- Founder change-control decision.

**Exit gate:** Founder-approved extraction change control with affected domain-owner and security acceptance.

## C4-B — Low-risk image foundation

- purpose-bound upload sessions;
- private quarantine;
- hash, magic-byte, MIME and image-header validation;
- malware scan adapter;
- metadata stripping, orientation and bounded responsive derivatives;
- deny-by-default delivery.

**Exit gate:** malicious corpus, authorization, metadata-leakage and resource-exhaustion tests pass.

## C4-C — Processing graph and lineage

- idempotent job graph;
- leases, heartbeats, retries, cancellation and dead-letter handling;
- image/audio/video/document workers;
- source-to-derivative lineage and immutable manifests;
- partial-failure compensation.

**Exit gate:** sandbox, idempotency, quality, performance and fault tests pass.

## C4-D — Storage and secure delivery

- encrypted object storage abstraction;
- provider mappings and integrity verification;
- short-lived delivery grants and range delivery;
- public immutable cache keys;
- restricted no-store delivery;
- CDN purge, revocation, holds and deletion ledger.

**Exit gate:** leakage, stale-cache purge, key recovery, integrity and deletion tests pass.

## C4-E — Domain adapters in shadow mode

Adapters for Files 10, 11, 12, 17, 21 and 22 consume versioned contracts without transferring domain truth.

**Exit gate:** parity, ownership, authorization and reconciliation contracts pass with no user-visible cutover.

## C4-F — Operations

- quotas and resource classes;
- queue priorities and backpressure;
- provider health, cost attribution and budget alerts;
- repair tools and dead-letter operations;
- observability, SLOs, alerts, runbooks and support ownership.

**Exit gate:** load, fault-injection, incident and operational acceptance pass.

## C4-G — Migration and provider exit

- discovery and dry run;
- copy/hash verification;
- dual-read/shadow delivery;
- final delta and write fencing;
- cutover, purge, reconciliation and rollback;
- provider export/exit drill.

**Exit gate:** zero unexplained missing, corrupt, duplicate or authorization-drift records.

## C4-H — Staging acceptance

- independent penetration review;
- backup/restore and key-recovery drill;
- accessibility, browser, device, RTL and weak-connection acceptance;
- canary/limited rollout and monitored rollback window.

**Exit gate:** Founder, domain-owner, privacy/security and operational acceptance.
