# CF-04 Runtime Status

## Candidate

- Runtime: `1.3.0-rc.1`
- Schema: `1.5.0`
- Contract: `1.5.0`
- Runtime default: disabled
- Source implementation: 33/33 CF04 functional requirements plus CHAT-XFER-001
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
- Data constitution: `C0 Public`, `C1 Account`, `C2 Private Communication`, `C3 Professional Evidence`, `C4 Financial/Legal`, `C5 Clinical/High Sensitivity`.
- Runtime remains disabled by default. Hostinger/real-provider/browser/accessibility/load/penetration/migration/restore/rollback/live/operational acceptance is still pending.
