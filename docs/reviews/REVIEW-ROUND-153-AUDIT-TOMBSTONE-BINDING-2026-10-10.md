# Review Round 153 — Stable audit actor and tombstone identity binding
Read-only baseline PR #5: 8cbda91b66d5a12388a2e9cb53a42392f039a74a; main 0294442f0fddd1ca5440d9d5ac992ba80aced972.
Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1; schema/contract 1.5.0.
Frozen findings before edits: (1) deterministic completion audit depends on implicit current-user actor, causing background reconcile idempotency conflicts; (2) tombstone evidence lacks owner identity and deletion reference binding, permitting inconsistent completion evidence. Prior synthetic fixture requires full canonical tombstone identity.
Unified correction: explicit stable deletion actor in completion audit; strict owner-domain/object/version/policy/rights tombstone evidence matching; new tombstones bind deletion_id; existing historical tombstones without that field remain valid only when their other canonical identity fields match; regression and CI quality registration updated.
Exact-new-HEAD CI is required before round acceptance. No staging/live/operational claim.
