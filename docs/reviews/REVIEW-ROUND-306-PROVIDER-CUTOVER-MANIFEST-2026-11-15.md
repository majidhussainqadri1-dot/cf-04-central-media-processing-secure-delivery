# R306 — Cross-owner provider cutover mapping manifest (unfenced)

V12.3 CF04-FR-027 requires complete provider exit inventory, physical encrypted shadow copy verification, safe native owner mapping, authorization, rollback and cleanup.

R306 adds `ProviderExitCutoverManifestStage` as a **read-only native owner mapping manifest**, not an executable cutover.

- Begin requires full R273/R275 inventory revalidation and R279/R283/293 shadow ciphertext verification.
- Each advance commits no more than one source segment of identity-bound mapping records with native domain, owner object version, record CAS, parent asset, policy conditions, old/new provider and object identity, SHA-256 and size.
- Complete re-verification checks all physical source and target ciphertexts, every native owner/record version, saved map proofs, per-segment chain and exact catalogue cardinality.
- Negative tests cover forged mapping, absent target ciphertext and nonprivileged access.
- It cannot remap owner records, authorize switch, purge a ciphertext, revoke provider credentials or bypass native write fencing. Terminal state `verified_unfenced` expressly forbids cutover.

**Outstanding**: a real multi-domain owner write fence and approved atomic cutover/rollback executor; real staged data, KMS, Hostinger/MySQL and production Founder approval. CI source pass does not meet these gates.