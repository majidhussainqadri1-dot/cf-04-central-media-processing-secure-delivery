# Review Round 119 — Future-40 governance and plan parity — 2026-09-19

Review completed in full before correction.

## Findings

1. FUT-007 system-wide threat re-scan scheduling was a mutating queue operation with no runtime gate, actor/capability authorization, or actor provenance on the queued records. The current plan requires capability-based authorization for actions and records reason/timestamps for threat-driven re-scan.

2. FUT-034 storage-tier optimization considered access age and WORM lock only. It omitted legal-hold state, retention class, privacy class, rights expiry and cost/residency context, yet returned `automatic_move=true` for ordinary recommendations. The plan defines this as policy-aware across hold/retention/privacy/rights/cost and forbids cost optimization from overriding hold/lock.

## Corrections after review

- Threat re-scan scheduling now requires runtime readiness plus `media_reprocess` actor authorization; actor identity is persisted and audited.
- Storage-tier recommendations now expose hold/lock, retention, privacy, rights, cost and residency context; expired rights/holds constrain the recommendation.
- Tier optimization is explicitly advisory (`automatic_move=false`) and requires canonical-owner authorization before any actual transition.
- Exact-head regressions were added to the quality gate.

Emergency format kill-switch behavior remains deliberately available as an authorized containment action. Staging-only chaos remains separately environment-gated.

No staging/live/provider acceptance is inferred.
