# CF-04 Fresh Review/Fix Round 1 — 2026-08-06

## Review method
A security-first pass traced each external input through canonical ownership, authorization, storage, cryptography, delivery, revocation, deletion and evidence persistence. Positive paths and negative/failure paths were reviewed separately.

## Defects corrected
1. Canonical JSON serialization added for stable signatures, audience binding and policy fingerprints.
2. Grant and encrypted-envelope parsing hardened against malformed, oversized, future-issued and overlong tokens.
3. Local object storage restricted to an explicit absolute private root; object writes made atomic.
4. Runtime provider fallback removed; unavailable/unhealthy storage now fails closed.
5. Audit events made persistable with bounded memory fallback for tests only.
6. Delivery grants changed from transient state to persistent records; object keys removed from claims.
7. Download creation bound to the current canonical asset and native object version.
8. Deletion bound to canonical state, legal holds, owner authorization and a truthful tombstone; active grants are revoked.

## Fresh verification added
`tests/review-round-9-source-audit.php` permanently checks the corrected invariants so later edits cannot silently restore the defects.

## Round decision
Accepted for a second independent adversarial review. No staging or production acceptance was inferred from source corrections.
