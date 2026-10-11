# CF-04 Round 180 — Resume Checkpoint Boundaries

Frozen read-only source e97b7379f65e59f092971e8527cc24e52d2fbc3d; main 0294442f0fddd1ca5440d9d5ac992ba80aced972; Draft PR #5. Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1, schema/contract 1.5.0.

Findings: resume checkpoint offset and device values were silently coerced; checkpoint creation lacked owning-domain and rights authorization; one actor/asset key overwrote different devices; grant did not validate persisted offset bounds; regression coverage missing.

Joint correction: bounded typed checkpoint fields, authorization before persistence, per-device state identity, action-time rights checks, stored offset validation, and adversarial regression in full quality gate. Exact-head CI required for acceptance. No staging/live/operational claim.
