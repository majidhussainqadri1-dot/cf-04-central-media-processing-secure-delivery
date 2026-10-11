# CF-04 Review Round 164 — Telemetry Contract

Source reviewed without edits: d6c2addddf753b690e0b5fa6f599d6ecddd6df5e
Target main: 0294442f0fddd1ca5440d9d5ac992ba80aced972
Governing repository reference: CF-04 v1.1 Future-40 Amended; CF04-CEN-10 and CF04-FUT-037.

Frozen review findings:
1. Metric label values lacked approved categories.
2. Metric names lacked an approved vocabulary.
3. Label input types were converted instead of validated.
4. Direct Observability calls bypassed telemetry restrictions.
5. Metric numeric semantics lacked appropriate bounds.
6. Adversarial regression did not cover these cases.

Correction: enforce approved names, typed bounded metric values, fixed low-cardinality label categories and common validation for both telemetry entry points. Add a dedicated regression test to the quality gate.

Acceptance requires new exact-source-head CI. This source review provides no staging, deployment, or operational evidence.
