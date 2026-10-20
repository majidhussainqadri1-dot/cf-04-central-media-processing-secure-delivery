# CF-04 Round 189 — Verified-user transfer input integrity

Read-only focused review baseline: PR #5 49666a01303664aa3cbcfe579e53bd688716ffdf; target main 0294442f0fddd1ca5440d9d5ac992ba80aced972.

Governing evidence: original CF-04 Conditional Complete plan v1.0, centralized master plan transfer rules (File 00 verified identity, File 17 native transfer/recipient/consent authority, File 19 notifications, File 20 download surface, CF-04 1 GiB secure binary). Repository references CF-04 v1.1 Future-40 2026-09-16 but the exact amended standalone document was not independently available in this review.

## Read-only frozen defect ledger
- A noncanonical native sender/recipient user ID was silently cast to a different integer.
- A malformed or fractional transfer byte count was cast before the 1 GiB boundary.
- Native object version could be silently changed from zero/malformed input to version 1.
- Requested expiry could be cast/clamped to a different authority/consent lifetime without rejection.
- An unapproved/coerced media type or mismatch between transfer type and upload-policy type could pass transfer normalization.

## Coordinated correction
Enforce canonical positive integer sender/recipient identities, native version and 1 GiB byte count; exact recipient/media type vocabulary; strict bounded expiry, explicit media class parity with owner-approved upload policy. Add executable adversarial test cases to tests/run-all.php (already a mandatory source/quality CI gate); preserve File 17 as business decision owner and File 00 for verified identity. No new File 19 or File 20 ownership or services fabricated.

This document describes ONE focused defect-bearing review, not 20 completed clean rounds. Acceptance requires exact corrected PR HEAD with both push and PR PHP 8.1/8.3/8.4 CI green. Staging, installed schema, live deployed code, migrations and operational acceptance remain unverified.
