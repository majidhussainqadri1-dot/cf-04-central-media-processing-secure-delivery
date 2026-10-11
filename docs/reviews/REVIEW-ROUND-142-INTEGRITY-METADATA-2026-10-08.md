# Review Round 142 — Integrity sampling metadata and manifest completeness

Exact baseline PR #5 HEAD: `504296722f66134adbb2c3adf3069c0156443e12`. All findings were collected in a read-only review before this unified correction.

## Frozen defect ledger
1. Source and derivative stored sizes were cast to integers; noncanonical values could compare equal to actual byte counts.
2. Missing sizes emitted undefined-field diagnostics and had no explicit fail-closed integrity error.
3. An asset referencing a missing, inactive, or foreign active manifest could be sampled as source-only and reported clean.
4. Missing, stale, superseded, or foreign derivatives referenced by an active manifest were silently skipped.
5. Malformed or absent stored SHA-256 values were not canonically validated before comparison.
6. Malformed or absent storage keys/provider identifiers could generate diagnostics or obscure integrity errors.

## Correction and verification gate
Canonical integer sizes, SHA-256 digests, storage keys, provider identities, manifest ownership, and every active derivative reference are checked. Invalid metadata quarantines the asset, revokes delivery grants, and raises a stable fail-closed error. The regression suite covers valid source/derivative samples and malformed/missing source, derivative, manifest, and storage metadata. `tools/quality-check.sh` runs the regression.

Acceptance requires the corrected branch exact HEAD and matching successful CI on all required PHP versions. Repository code is not deployed code. Staging, live deployment, and operational acceptance remain unverified.
