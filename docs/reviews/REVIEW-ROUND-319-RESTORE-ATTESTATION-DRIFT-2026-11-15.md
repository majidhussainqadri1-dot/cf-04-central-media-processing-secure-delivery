# R319 — post-attestation DB/asset source revalidation

**Source defect:** even a valid signed independent restore execution descriptor could be obtained from a callback that concurrently mutates a media record. Without a final source-version recheck, a previously reconciled inventory could be used to reopen serving despite data changes during attestation.

`RestoreService::authorizeServe()` now verifies the entire authoritative source fingerprint *again after* the external proof callback returns and *before* persisting `serve_authorized`. Changes fail closed with `restore_attestation_source_drift`. R319 exercises an actually signed test proof from a callback that mutates one source record; serving stays blocked, then succeeds only after fresh reconciliation and new signed proof. This is still neither an executed backup restore nor proof of real Hostinger/KMS integration.
