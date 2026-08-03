# CF-04 Activation Gates

Runtime extraction, implementation, staging or production activation is blocked until every mandatory gate below has explicit evidence and approval.

## Gate A — Founder change control

- [ ] Dated Founder-approved extraction decision.
- [ ] Old owner rule, new owner rule and affected files recorded.
- [ ] Security, privacy, Sharīʿah, medical, cost and operational impact recorded.
- [ ] Migration, rollback, tests and residual risks approved.
- [ ] Permanent numbered-module decision recorded if CF-04 is promoted beyond its conditional identifier.

## Gate B — Canonical ownership inventory

- [ ] File 10 video/live binary stores inventoried.
- [ ] File 11 Reel binary stores inventoried.
- [ ] File 12 PDF/document stores inventoried.
- [ ] File 17 message-attachment stores inventoried.
- [ ] Files 21/22 publication-upload paths inventoried.
- [ ] Provider IDs, raw URLs, storage paths, hashes and object states mapped.
- [ ] Duplicate, orphaned, missing and corrupt assets reconciled.
- [ ] Existing deletion, retention, consent and rights states reconciled.

## Gate C — Versioned domain contracts

- [ ] Asset-reference schema frozen.
- [ ] Upload purpose and policy envelope frozen.
- [ ] Privacy class and field-level access contract frozen.
- [ ] Allowed media types, sizes, durations, pages and derivative sets frozen.
- [ ] Retention, hold, revocation and deletion ownership frozen.
- [ ] File 00 capability and entitlement assertions accepted.
- [ ] File 20 route/shell placement accepted.
- [ ] File 24 assurance manifest accepted.
- [ ] File 25 visual/component contract accepted.

## Gate D — Threat and privacy architecture

- [ ] Data-flow diagram approved.
- [ ] Threat model covers upload abuse, parser exploits, SSRF/RCE, token leakage, stale CDN access, metadata disclosure, cross-domain dedupe and provider compromise.
- [ ] Privacy impact assessment completed for C1–C5 assets.
- [ ] Minor/guardian, clinical and message-attachment restrictions approved.
- [ ] Retention schedule and legal-hold procedure approved.
- [ ] Provider register, regions, subprocessors and exit obligations approved.

## Gate E — Economic and capacity justification

- [ ] Current volume measured by domain and media class.
- [ ] Storage growth measured.
- [ ] Processing queue demand measured.
- [ ] Reliability and failure rates measured.
- [ ] Current and projected provider cost measured.
- [ ] Shared extraction is demonstrably safer, more reliable or more economical than current ownership.

## Gate F — Migration safety

- [ ] Discovery inventory can be reproduced.
- [ ] Copy/hash verification is idempotent.
- [ ] Dual-read or shadow mode is specified.
- [ ] Final-delta and write-fencing strategy approved.
- [ ] Reconciliation detects missing, corrupt, duplicate and access-drift cases.
- [ ] Rollback restores previous delivery without data loss or privilege broadening.
- [ ] Provider-exit drill defined.

## Gate G — Independent security acceptance

- [ ] Malware corpus tests pass.
- [ ] MIME/magic/polyglot tests pass.
- [ ] Archive/decompression/pixel-bomb limits pass.
- [ ] Sandboxed worker SSRF/RCE tests pass.
- [ ] Encryption, key rotation and key recovery tests pass.
- [ ] Signed/expiring delivery authorization tests pass.
- [ ] CDN purge and stale-access SLO tests pass.
- [ ] Tenant/domain/object/field authorization tests pass.
- [ ] Deletion and provider-confirmation tests pass.
- [ ] Independent penetration assessment accepted.

## Gate H — Operational readiness

- [ ] Named service owner and domain owners assigned.
- [ ] Security/privacy operator assigned.
- [ ] Queue and provider on-call ownership assigned.
- [ ] Backup/restore owner assigned.
- [ ] Incident escalation and support path approved.
- [ ] SLOs, alerts, dashboards and runbooks approved.
- [ ] Founder and affected domain owners sign off.

## Gate decision

| Field | Value |
|---|---|
| Current gate status | Blocked — evidence not yet assembled |
| Permitted work | Documentation, inventory templates, contracts, threat-model preparation and non-runtime CI |
| Forbidden work | Live credentials, provider integration, media ingestion, runtime route activation, data migration and production deployment |
