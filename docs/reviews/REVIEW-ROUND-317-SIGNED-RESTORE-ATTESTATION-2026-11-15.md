# R317 — independently signed restoration execution proof (2026-11-15)

**Defect/gap:** RestoreService::authorizeServe historically performed valid schema/rights/manifest/source-inventory checks but could not distinguish a verified current environment from a DB+object+key backup actually executed and restored. A successful reconciliation alone is not proof of a completed restoration.

**Source fix:** production-only RestoreExecutionAttestation::requireForServe must pass before the restore gate is opened. It checks a fresh, short-lived HMAC-SHA256 signature with a separately provisioned SCM_RESTORE_ATTESTATION_KEY (minimum 32 bytes), bound to exact restore ID, inventory hash, source fingerprint, and three independently named DB/object/key backup digests. It explicitly requires execution, rights reapplication, tombstone checks and owner reconciliation. Expired/replayed cross-run or absent proofs fail closed.

**Operational boundary:** the trusted external restore executor and key provisioning are NOT implemented in this module. The HMAC represents its claims; it does not itself prove that backups were actually restored. Until a real independently audited executor supplies proof via scm_restore_execution_proof, production RestoreService::authorizeServe will deny serving. This is intentional; safe fail-closed protection, not a claim that CF04-FR-032's real restore is complete. Unit tests retain their existing SCM_TEST_MODE mock behavior; R317 calls the cryptographic verifier directly and includes negative cases.

**Security:** do not store the HMAC key in WordPress options, plugin source, logs or public release artifacts. No external KMS/Hostinger/live restore tested.
