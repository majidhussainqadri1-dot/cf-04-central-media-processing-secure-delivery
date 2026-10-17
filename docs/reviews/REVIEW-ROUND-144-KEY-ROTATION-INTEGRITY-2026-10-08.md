# Review Round 144 — Key-rotation canonical identity and safe remapping

Baseline exact PR #5 HEAD: `49c26df2723521e0f48fb20ee733ddf145f3a6d2`. The full read-only review was completed before any correction.

## Frozen defect ledger
1. Shared-reference size checks used lossy integer casts; missing/noncanonical digest, size, object key, provider ID, or key ID were not uniformly rejected.
2. Re-encryption could write a new object before verifying that decrypted source bytes match the persisted digest and size.
3. Re-encrypted storage results were checked with casts and without requiring the active encryption key ID; reused ciphertext under an older key could be misrepresented as rotated.
4. Fresh record remapping checked only object key and did not reject digest, size, provider or previous key identity drift.
5. Group identity errors occurred outside per-group error handling, potentially aborting the run with a running ledger and no per-group failure.
6. Failure cleanup could delete an already-existing reused ciphertext object or fail to account for unresolved cleanup.

## Unified correction
Canonical persisted identity guard; source bytes verified before write; returned digest/size/active key checked; full fresh-record identity revalidated before remap; group errors captured in per-group failure ledger; cleanup never deletes a reused or live-referenced object. Executable regression covers canonical success, malformed fields, source mismatch, and reused old-key ciphertext. Quality gate includes Round 144.

## Evidence boundary
This is source-only until the exact corrected PR HEAD and its automated quality checks succeed. No staging, live deployment or operational acceptance is claimed.
