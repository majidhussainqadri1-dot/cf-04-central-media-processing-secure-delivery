# Review Round 151 — Deletion state and evidence integrity
Baseline PR #5: db6e837bf4da8755cad37be4cdb4932d647fd6c0 (main 0294442f0fddd1ca5440d9d5ac992ba80aced972).
Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1, schema/contract 1.5.0.
Read-only review was completed before the frozen 18-defect ledger and coordinated correction.
Frozen defects: (1) in-flight request missing current authorization; (2) prior request identity unchecked;
(3) unsafe backup-expiry coercion; (4) malformed persisted deletion identity/numeric/status;
(5) invalid or out-of-order step state; (6) unverified completed record;
(7) coerced retry due timestamp; (8) attempt count not saved before side effects;
(9) retry counter lost on fresh reload; (10) backup stage not reauthorized;
(11) tombstone stage not reauthorized; (12) conflicting backup ledger not checked;
(13) conflicting tombstone not checked; (14) post-completion audit failure downgrades completion;
(15) reconcile accepts unverified completion; (16) reconcile assumes every process return completed;
(17) underconstrained deletion JSON Schema; (18) missing regression/README/quality chronology.
Unified correction: strict persisted-state validation, ordered steps, verified completion evidence,
current owner/hold authorization, durable attempts, evidence conflict checks, terminal-state preservation,
reconciliation truth, tightened schema, executable regression and quality gate.
Acceptance only on successful exact-new-HEAD CI; no staging/live/operational evidence is asserted.
