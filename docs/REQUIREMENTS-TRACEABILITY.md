# CF-04 Requirements Traceability Register

This register establishes stable Phase C4-A identifiers. Runtime development, extraction and release remain subject to their respective authorization gates.

| ID | Requirement | Owner | Current evidence | Status |
|---|---|---|---|---|
| CF04-A-001 | Preserve conditional status and truthful completion labels | Repository governance | README and charter | Implemented in docs |
| CF04-A-002 | Record Founder-approved C4-A change control before bounded runtime development | Founder | Development Gate A | Blocked |
| CF04-A-003 | Inventory File 10/11/12/17/21/22 binaries, providers and routes | Domain owners | Inventory template | Pending evidence |
| CF04-A-004 | Draft and version canonical asset-reference and policy-envelope contracts before development authorization | CF-04 + domain owners | Development Gate C | Pending |
| CF04-A-005 | Prove volume, reliability and cost justification | Founder/operations | Development Gate E | Pending evidence |
| CF04-A-006 | Approve preliminary threat model, data flow, privacy classes, retention and migration architecture | Security/privacy/domain owners | Development Gate D | Pending |
| CF04-A-007 | Define idempotent migration, shadow, reconciliation and rollback before development; prove them before extraction | CF-04 + domain owners | Development Gate D / Release Gate F | Pending |
| CF04-A-008 | Independently test quarantine, parser, worker, delivery, deletion, restore and provider-exit controls before extraction/release | Security operator | Release Gate G | Future implementation evidence required |
| CF04-A-009 | Assign service ownership, on-call, SLO, support and release responsibilities | Founder/operations | Release Gate H | Pending |
| CF04-A-010 | Prevent secrets and restricted data from entering the public repository | All contributors | SECURITY.md and .gitignore | Implemented in docs |
| CF04-A-011 | Preserve native domain ownership and prohibit direct writes | CF-04 + consumers | Ownership matrix | Implemented in docs |
| CF04-A-012 | Require review, correction, fresh adversarial review, correction and retest for every coding or contract batch | Maintainers | CONTRIBUTING.md and PR template | Implemented in docs |
| CF04-A-013 | Keep development authorization distinct from real-data extraction and production release | Repository governance | README and activation gates | Implemented in docs |

## Evidence law

A requirement is not complete merely because prose exists. Each completed item must record:

- requirement ID;
- branch, commit and release version;
- environment and dependency versions;
- test or review IDs;
- expected and actual result;
- evidence artifact location;
- reviewer and timestamp;
- defect links and retest result;
- approval status.

Mandatory tests that fail, are skipped, or are flaky cannot support a green release claim.
