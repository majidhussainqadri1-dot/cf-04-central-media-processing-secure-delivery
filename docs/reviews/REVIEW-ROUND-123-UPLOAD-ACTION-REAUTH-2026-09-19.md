# Review Round 123 — Upload continuation authorization — 2026-09-19

Review completed in full before correction.

## Finding
Upload creation checked File-00 eligibility and canonical-owner authorization, and completion rechecked owner version, but ordinary part upload and resume relied only on the previously issued credential. A user suspension or owner-version change could therefore leave an already-created session able to consume upload resources until final completion.

## Correction after review
Part upload, resume, and non-terminal completion now recheck current File-00 account eligibility, the canonical owner's `authorize_upload` decision, and the exact owner object version. Pause/abort remain available as containment controls after actor/credential authentication.

Regression coverage was added.
