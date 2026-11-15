# R299: Bounded physical source audit for restore checkpoints

V12.3 section CF04-FR-032 requires integrity and rights reconciliation before restoring media delivery. Existing RestoreCheckpointStage verifies nine families of source metadata, but not all physical object bytes.

R299 adds RestorePhysicalAuditStage:
- Begin only from a fully verified source checkpoint.
- Inspect one 10-record asset/derivative keyset page per call and verify physical encrypted streams against the recorded SHA-256 and size.
- Persist a CAS-protected hash chain and per-page proof, with retries after a missing source preserving the previous completed page.
- Verify the original checkpoint and rehash all source bytes before reporting completion.
- Restrict all calls to media_manage_providers and the authorized actor.
- Do not restore a database, switch a provider, purge an object, alter a media record, rotate any key, or authorize serving.

The only successful completion state is physical_verified_unfenced. Without an immutable DB/object/key snapshot, real MySQL and provider rehearsal, an owner write fence and actual restore execution, production recovery remains UNVERIFIED. Regression: tests/review-round-299-restore-physical-audit.php.