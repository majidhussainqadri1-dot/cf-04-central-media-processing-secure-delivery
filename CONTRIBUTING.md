# Contributing to CF-04

## 1. Scope discipline

CF-04 is conditional. Before activation approval, contributions are limited to governance, inventory, contracts, threat modeling, migration design, evidence templates and non-runtime quality checks.

Do not add live provider credentials, runtime ingest, active delivery routes, migrations against production data, or claims that the module is deployed.

## 2. Branch law

Use a dedicated branch for every change. Recommended pattern:

`codex/cf-04-<phase>-<purpose>`

Direct feature work on `main` is prohibited. Each pull request must identify the applicable roadmap phase and requirement IDs.

## 3. Canonical ownership law

- Do not write directly into another module’s tables, metadata or state machine.
- Do not create duplicate content, video, Reel, PDF, message, clinical, entitlement, shell or visual truth.
- Use versioned commands, queries and events.
- Treat feature availability as distinct from authorization.

## 4. Mandatory review cycle

Every meaningful coding or contract batch follows this sequence:

1. Implement the bounded batch.
2. First review: requirements, logic, security, privacy, migration, performance, accessibility, documentation and integrations.
3. Correct every discovered defect and add regression evidence.
4. Fresh adversarial review: authorization bypass, replay, stale state, race conditions, partial failure, rollback failure, leakage, RTL/mobile and degraded dependencies.
5. Correct every newly discovered defect.
6. Rerun all affected tests and rebuild the artifact.

A pull request cannot be called final while a known unresolved defect remains, unless the Founder explicitly accepts a documented, time-bound residual risk.

## 5. Pull-request evidence

Each pull request must include:

- purpose and roadmap phase;
- requirement IDs;
- ownership and data-class impact;
- security/privacy impact;
- migration and rollback impact;
- tests run and evidence;
- first-review findings and corrections;
- second-review findings and corrections;
- remaining risks;
- truthful completion status.

## 6. Public repository hygiene

Follow `SECURITY.md`. Use synthetic fixtures only. Never commit secrets, private URLs, unredacted user data, raw incident details or reusable signed delivery grants.

## 7. Commit style

Use clear conventional prefixes where practical:

- `docs:` documentation and governance;
- `feat:` approved runtime capability;
- `fix:` defect correction;
- `test:` test or fixture work;
- `security:` security hardening;
- `refactor:` behavior-preserving restructuring;
- `chore:` repository maintenance.
