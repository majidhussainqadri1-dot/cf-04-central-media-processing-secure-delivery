# R302 — bounded staged physical shadow re-encryption

Plan scope: CF04-FR-017 / V12.3. R302 can take the durable candidate inventory (R276–R281) and make real private encrypted target objects under the approved new active key, without changing original asset or derivative mappings, deleting source ciphertexts, retiring keys, switching providers or granting delivery.

Bounded one-segment action: KeyRotationShadowStage::copySegment(inventoryId,segmentId,actor,limit 1–25). It runs fresh candidate source/key inspection, copies to a deterministic canonical target key, verifies provider output and actual target key ID + decrypted hash, and saves every successfully verified item under a CAS cursor. Shared old ciphertext aliases reuse the same target key, including after interrupted replay.

Independent read-only verification: KeyRotationShadowStage::verifySegment performs fresh full source/target rehash and checks every saved proof for the segment.

Negative tests: unauthorized operator, missing source preventing next copy without cursor loss, missing target after completion, recovery and true no-remap semantics.

This remains explicitly shadow_reencrypted_unfenced. Full provider/KMS acceptance, approved cross-owner write fences, >2,000-alias operational coordinator, complete key cutover/retirement, orphan cleanup, immutable backup/restore, Staging and Founder production authorization are NOT DONE. The stage intentionally preserves old ciphertext and refuses to call a shadow copy a completed rotation.