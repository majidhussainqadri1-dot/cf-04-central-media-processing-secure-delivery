# Review Round 154 — Durable terminal revocation notification
Baseline PR #5 bcb0fb683e3c65c572e89e043644344102c4999f; main 0294442f0fddd1ca5440d9d5ac992ba80aced972. Governing CF-04 v1.1 Future-40 Amended, runtime 1.3.0-rc.1, schema/contract 1.5.0.
Read-only review frozen finding: a downstream scm.media.revoked callback failure after terminal deletion can leave no delivered notification; completed process/reconcile paths never retry the callback.
Coordinated correction: durable revocation_notice pending/delivered record keyed to deletion, stable event_id, strict identity validation, retry on completed process/reconcile and legacy crash recovery, and fault-injection regression. Notification delivery is AT LEAST ONCE, not exactly once: a crash after callback and before delivered marker can replay; consumers must deduplicate event_id.
Round acceptance requires exact-head CI PHP 8.1/8.3/8.4 and package gates. No staging/live/operational claim.
