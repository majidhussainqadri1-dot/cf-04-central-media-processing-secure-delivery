# CF-04 Runtime Status

## Candidate

- Runtime: `1.3.0-rc.1`
- Schema: `1.5.0`
- Contract: `1.5.0`
- Runtime default: disabled
- Governing CF-04 plan: `v1.1 — Future-40 Amended — 2026-09-16`
- Source implementation: 33/33 CF04 functional requirements plus CHAT-XFER-001, CF04-CEN-01..10, CF04-NJ-01..06 source journeys, and CF04-FUT-001..040
- Automated source acceptance: required on PHP 8.1, 8.3 and 8.4

## Completion boundaries

| Level | Status |
|---|---|
| Specified | Complete |
| Coded | Complete for approved source scope |
| Deterministically packaged | CI-gated |
| Automated QA | Exact-head CI-gated |
| Hostinger staging accepted | Pending |
| Real providers accepted | Pending |
| Live deployed | No |
| Operational | No |

No external-environment claim may be inferred from source completion.

## Current rewritten-plan parity

- CF04-CEN-01 through CF04-CEN-10: implemented and source-tested in candidate `1.3.0-rc.1`.
- CF04-NJ-01 through CF04-NJ-06: source paths/negative gates represented in the automated suite.
- Data constitution: `C0 Public`, `C1 Account`, `C2 Private Communication`, `C3 Professional Evidence`, `C4 Clinical-Sensitive`, `C5 Security Secret`.
- Runtime remains disabled by default. Hostinger/real-provider/browser/accessibility/load/penetration/migration/restore/rollback/live/operational acceptance is still pending.

## Future-40 extension — 2026-09-16

- CF04-FUT-001 through CF04-FUT-040 are implemented in source and covered by `tests/future40.php`.
- External media engines/providers are adapter-gated and fail closed when absent.
- Canonical ownership remains with the existing domain owners; CF-04 owns binary/media infrastructure only.
- This source completion does not claim real C2PA/provider certification, real CDN failover, real regional replication, staging acceptance, live deployment or operational acceptance.
