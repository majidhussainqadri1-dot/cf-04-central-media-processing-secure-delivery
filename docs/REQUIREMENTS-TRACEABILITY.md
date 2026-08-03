# CF-04 Requirements Traceability Register

This register establishes stable Phase C4-A identifiers. Runtime requirements remain blocked until activation approval.

| ID | Requirement | Owner | Current evidence | Status |
|---|---|---|---|---|
| CF04-A-001 | Preserve conditional status and truthful completion labels | Repository governance | README and charter | Implemented in docs |
| CF04-A-002 | Record Founder-approved extraction change control before runtime work | Founder | Activation gate A | Blocked |
| CF04-A-003 | Inventory File 10/11/12/17/21/22 binaries and routes | Domain owners | Inventory template | Pending evidence |
| CF04-A-004 | Freeze canonical asset-reference and policy-envelope contracts | CF-04 + domain owners | Activation gate C | Pending |
| CF04-A-005 | Prove volume, reliability and cost justification | Founder/operations | Activation gate E | Pending evidence |
| CF04-A-006 | Approve threat model, data flow, privacy classes and retention | Security/privacy/domain owners | Activation gate D | Pending |
| CF04-A-007 | Define idempotent migration, shadow, reconciliation and rollback | CF-04 + domain owners | Activation gate F | Pending |
| CF04-A-008 | Independently test quarantine, parser, worker, delivery and deletion controls | Security operator | Activation gate G | Pending |
| CF04-A-009 | Assign service ownership, on-call, SLO and support responsibilities | Founder/operations | Activation gate H | Pending |
| CF04-A-010 | Prevent secrets and restricted data from entering the public repository | All contributors | SECURITY.md | Implemented in docs |
| CF04-A-011 | Preserve native domain ownership and prohibit direct writes | CF-04 + consumers | Ownership matrix | Implemented in docs |
| CF04-A-012 | Require review, correction, fresh adversarial review and retest for every coding batch | Maintainers | CONTRIBUTING.md | Planned |

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
