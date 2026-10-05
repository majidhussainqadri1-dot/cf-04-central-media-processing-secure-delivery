# Review Round 135 — Rich metadata integrity

Review completed before correction.

## Frozen defect ledger
1. Chapter timecodes were integer-cast without proving integer input, so malformed scalar values could collapse to a valid-looking timecode.
2. Caption, audio-description and sign-language tracks required field presence but did not prove SHA-256 integrity values or normalized locale/provenance/reference identities.
3. Smart-preview adapter references could be whitespace-only while still satisfying raw required-field presence.

## Correction
- Chapter timecodes now require explicit non-negative integer input and are stored in normalized form.
- Track locale/reference/provenance fields are normalized and required to remain non-empty.
- Content/synchronization hashes are required to be exact SHA-256 values.
- Smart-preview references are sanitized and fail closed when blank.
- Added permanent Round 135 regression coverage.

No staging/live claim is made.
