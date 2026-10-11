# Review Round 140 — Action-time rights consent and integrity

Baseline accepted HEAD: `99c572d55861d0f5692ca5153f58a0321aa3c30d`. Full read-only review completed before corrections.

## Frozen defect ledger

1. `RightsPolicy::assert()` enforced operation/audience/territory/expiry but did not revalidate current `consent_status`, allowing a persisted withdrawn consent to retain access.
2. Runtime authorization did not re-enforce normalized clinical confidentiality and canonical rights fields, so a corrupted rights record could bypass upload-time constraints.
3. A persisted rights policy hash could become stale after field mutation while action-time authorization still accepted the modified fields.

## Correction and regression

- Revalidate the full rights contract at the action-time boundary via canonical normalization.
- Where a stored `policy_hash` exists, require its exact match to the re-derived normalized rights hash.
- Preserve compatibility with canonical unhashed direct rights input, while retaining fail-closed authorization.
- Add executable Round 140 regression cases and include them in the canonical quality gate.

Repository evidence only; no staging, live or operational claim.
