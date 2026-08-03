# CF-04 Evidence Package Index

This file records evidence references without exposing secrets or restricted data.

| Evidence ID | Requirement/decision | Artifact type | Environment/version | Public-safe location | Private evidence custodian | Reviewer/date | Result |
|---|---|---|---|---|---|---|---|
| CF04-EV-001 | CF04-A-001 | Repository governance baseline | Phase C4-A | README, charter, activation gates | N/A | Pending PR review | Pending |

## Required evidence families

1. Approved governing plan and Founder change-control records.
2. Requirements traceability matrix.
3. Architecture, data-flow and threat-model records.
4. Domain contracts, schemas and compatibility matrices.
5. Binary, provider, route and orphan inventory.
6. Source repository, branch, commit, tag and package identity.
7. First review and fresh adversarial review records.
8. Static, unit, integration, contract, migration, security, privacy, accessibility and load reports.
9. SBOM, license/provenance, secret-scan and checksum/signature evidence.
10. Migration dry run, hash reconciliation, shadow mode, cutover and rollback evidence.
11. Staging screenshots/logs, browser/device matrix and weak-connection results.
12. Backup/restore, key-recovery, CDN purge, deletion and provider-exit evidence.
13. Runbooks, training, service ownership and operational acceptance.
14. Founder approval and post-release monitoring snapshot.

## Evidence handling law

- Public evidence must be redacted and synthetic where necessary.
- Private evidence is referenced by custodian-controlled identifier, not copied into this repository.
- A skipped, failed or flaky mandatory test cannot support a green status.
- Evidence must identify the exact version, commit, environment, reviewer and timestamp.
- Superseded evidence remains traceable and is never silently overwritten.
