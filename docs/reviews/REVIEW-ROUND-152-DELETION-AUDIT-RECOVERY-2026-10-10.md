# Review Round 152 — Terminal deletion crash recovery and audit continuity
Frozen baseline: PR #5 head 9c4a24a21aa88582d4bc4f58d8745c3e6908ad8e; main 0294442f0fddd1ca5440d9d5ac992ba80aced972.
Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1; schema/contract 1.5.0.
The read-only review was completed before correction. Frozen findings:
1. Tombstone crash window: asset status is persisted as deleted before tombstone and final deletion state; a crash can strand retries under fresh owner authorization.
2. Durable request is saved before a non-idempotent deletion_requested audit; retry returns existing request without healing missing audit.
3. Completed deletion audit is non-idempotent; process/reconcile on a completed record never repairs missing audit evidence.
4. Round-151 executable regression is present but absent from tools/quality-check.sh, leaving a CI coverage gap.
Coordinated remediation: write tombstone before terminal asset mutation, recover the legacy deleted-asset crash window only with strict matching backup and deletion evidence, use deterministic unique audit event IDs under the audit-chain lock, replay missing request/completion audits, register both round 151 and 152 regressions, and document review chronology.
Accept only after matching exact-head PHP 8.1/8.3/8.4 CI and package gates are green. The complete original master plan and staging/live/operational state remain independently unverified.
