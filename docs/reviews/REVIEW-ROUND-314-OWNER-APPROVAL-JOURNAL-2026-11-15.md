# R314 — bounded durable owner approval checkpoints (2026-11-15)

Added a cooperative, version/CAS-bound owner approval journal across up to 250 native mappings per work invocation. It uses immutable record/source validation, a durable per-manifest-segment approval digest and a compare-and-swap parent cursor. It can resume after interruption, detect tampered journals, and re-check native owner callbacks and every physical source/target before final proof.

This is **approval only**. Its terminal state remains `owner_approved_unfenced`; `native_owner_write_fenced=false`, `authorization_to_switch=false`, and no physical copy, provider activation, deletion, rollback, credential revocation or KMS is performed by this stage. An owner's callback must be independently integrated and verified safe before staging.

R314 fixture covers two separate owner domains, at least two segment journals, interruption-compatible re-entry, idempotent completion, forged digest rejection and unchanged source provider. Real >2,000-object provider cutover and atomic fencing remain outstanding and require real Hostinger/MySQL/provider and native owner evidence.
