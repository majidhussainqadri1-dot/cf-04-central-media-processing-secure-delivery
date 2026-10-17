# PROVIDER-OUTAGE Runbook

1. Keep runtime fail-closed; do not bypass security or owner contracts.
2. Capture incident ID, affected provider/domain, first/last failure and audit evidence without secrets.
3. Stop new unsafe work, preserve quarantined objects and revoke affected grants where necessary.
4. Execute the relevant health, reconciliation, retry, migration or restore procedure.
5. Verify integrity, rights, holds, deletion state and owner authorization before service restoration.
6. Record reviewer, evidence, outcome and rollback decision; notify operations through the File 19 adapter.
