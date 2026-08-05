# CF-04 Runtime Architecture

CF-04 is the conditional canonical owner of shared binary ingest, quarantine, technical scanning, processing, derivative lineage, private object storage and secure delivery after approved extraction. Native domain owners retain content metadata, editorial/medical/moderation decisions, conversations, live-event truth, readers, feeds and rights decisions.

## Current candidate boundary

The `1.1.0-rc.3` candidate implements only a subset of the target architecture:

- multipart upload and bounded local assembly;
- private encrypted local object storage;
- basic scan registry and native-domain authorization;
- signed persistent delivery-grant foundations;
- basic tombstone/download/transfer/workspace records.

It does **not** yet implement the complete processing/job/derivative, streaming-provider, CDN, key-rotation, retention/hold/deletion-reconciliation, provider-exit, cost, restore or observability architecture required by the CF-04 plan.

The authoritative status is maintained in `REQUIREMENTS-COMPLETION-MATRIX.md`. Runtime must remain disabled until the complete readiness, migration, rollback and staging gates pass.
