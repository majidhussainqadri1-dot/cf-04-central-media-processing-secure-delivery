# Review Round 13 — Lifecycle, Concurrency and Adversarial Behavior

## Findings and fixes

1. Asset-specific processing now leases the exact job instead of accidentally selecting another tenant's eligible job.
2. Upload credentials, pause/resume/abort, part integrity, quota settlement and idempotent completion are persistent and replay-safe.
3. Retention distinguishes derivative expiry from source deletion; it no longer deletes the entire asset at the earliest of two retention boundaries.
4. A deletion request immediately marks the asset `deletion_pending`, preventing new grants while ordered reconciliation proceeds.
5. Deletion order is enforced as revoke grants → purge CDN → delete derivatives → delete source → delete mappings → backup-expiry ledger → tombstone, with retry state and audit alerts.
6. Provider exit implements inventory, copy, hash verification, shadow reads, switch, rollback window, purge and credential-revocation flag.
7. Key rotation groups shared object references so one physical object is re-encrypted once and every logical reference is atomically updated before old-object removal.
8. Queue aging, priorities, tenant lease penalties, heartbeats, retries, dead letters and orphan recovery were adversarially tested.
9. Repair preserves the last valid manifest and supports rollback; restore blocks serving until schema, keys, providers, tombstones, rights, manifests and integrity reconcile.

## Result

Lifecycle and adversarial source gate: **PASS**.
