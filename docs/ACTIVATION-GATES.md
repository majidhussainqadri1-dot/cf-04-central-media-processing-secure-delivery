# CF-04 Activation Gates

CF-04 uses two decision gates:

- **Development Authorization Gate:** permits bounded isolated implementation after C4-A evidence is approved.
- **Extraction and Release Gate:** permits real-data migration, provider cutover, staging acceptance or production activation only after implementation and independent acceptance evidence exist.

This separation prevents the logically circular requirement that implementation tests must pass before any implementation may begin.

# Part I — Development Authorization Gate

## Gate A — Founder change control

- [ ] Dated Founder-approved decision authorizes bounded C4-B onward development.
- [ ] Old owner rule, proposed new owner rule and affected files are recorded.
- [ ] Security, privacy, Sharīʿah, medical, cost and operational impact is recorded.
- [ ] Preliminary migration, rollback, test and residual-risk strategy is approved.
- [ ] Permanent numbered-module decision is recorded if CF-04 is promoted beyond its conditional identifier.

## Gate B — Canonical ownership inventory

- [ ] File 10 video/live binary stores and delivery paths are inventoried.
- [ ] File 11 Reel binary stores and delivery paths are inventoried.
- [ ] File 12 PDF/document stores and delivery paths are inventoried.
- [ ] File 17 message-attachment stores and delivery paths are inventoried.
- [ ] Files 21/22 publication-upload paths are inventoried.
- [ ] Provider IDs, safe path references, hashes and object states are mapped.
- [ ] Duplicate, orphaned, missing and corrupt assets are identified.
- [ ] Existing deletion, retention, consent and rights states are identified.

## Gate C — Draft versioned domain contracts

- [ ] Asset-reference schema is drafted and versioned.
- [ ] Upload purpose and policy envelope are drafted and versioned.
- [ ] Privacy class and field-level access contract are drafted.
- [ ] Allowed media types, sizes, durations, pages and derivative sets are drafted.
- [ ] Retention, hold, revocation and deletion ownership is drafted.
- [ ] File 00 capability and entitlement assertion dependencies are identified.
- [ ] File 20 route/shell placement is identified.
- [ ] File 24 assurance-manifest dependency is identified.
- [ ] File 25 visual/component dependency is identified.

## Gate D — Preliminary threat, privacy and migration architecture

- [ ] Preliminary data-flow diagram is approved for isolated development.
- [ ] Threat model covers upload abuse, parser exploits, SSRF/RCE, token leakage, stale CDN access, metadata disclosure, cross-domain dedupe and provider compromise.
- [ ] Privacy impact scope is identified for C1–C5 assets.
- [ ] Minor/guardian, clinical and message-attachment restrictions are identified.
- [ ] Retention schedule and legal-hold responsibilities are identified.
- [ ] Provider classes, regions, subprocessors and exit obligations are identified.
- [ ] Shadow, reconciliation, rollback and provider-exit strategy is designed.

## Gate E — Economic and capacity justification

- [ ] Current volume is measured by domain and media class.
- [ ] Storage growth is measured.
- [ ] Processing demand is measured.
- [ ] Reliability and failure rates are measured.
- [ ] Current and projected provider cost is measured.
- [ ] Shared extraction is demonstrably safer, more reliable or more economical than current ownership.

## Development decision

| Field | Current value |
|---|---|
| Development authorization | Blocked — Founder-approved C4-A decision not yet recorded |
| Permitted work now | Documentation, inventory templates, contract drafts, threat-model preparation, migration design and non-runtime repository quality |
| Forbidden work now | Live credentials, provider integration, active ingest/delivery routes, real-data migration, staging activation and production deployment |

# Part II — Extraction and Release Gate

The following gates are evaluated after bounded implementation exists in an isolated test environment.

## Gate F — Final contracts and migration safety

- [ ] Domain contracts are frozen and accepted by all affected owners.
- [ ] Discovery inventory is reproducible.
- [ ] Copy/hash verification is idempotent.
- [ ] Dual-read or shadow mode passes.
- [ ] Final-delta and write-fencing strategy is approved.
- [ ] Reconciliation detects missing, corrupt, duplicate and access-drift cases.
- [ ] Rollback restores previous delivery without data loss or privilege broadening.
- [ ] Provider-exit drill passes.

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
- [ ] Independent penetration assessment is accepted.

## Gate H — Operational and release readiness

- [ ] Named service owner and domain owners are assigned.
- [ ] Security/privacy operator is assigned.
- [ ] Queue and provider on-call ownership is assigned.
- [ ] Backup/restore owner is assigned.
- [ ] Incident escalation and support path are approved.
- [ ] SLOs, alerts, dashboards and private runbooks are approved.
- [ ] Staging, browser, accessibility, RTL, weak-connection, restore and rollback acceptance passes.
- [ ] Founder and affected domain owners sign off on extraction or release.

## Extraction and release decision

| Field | Current value |
|---|---|
| Real-data extraction/cutover | Blocked |
| Staging/live activation | Blocked |
| Production claim | None |
