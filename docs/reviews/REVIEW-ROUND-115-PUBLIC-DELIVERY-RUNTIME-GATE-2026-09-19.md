# Review Round 115 — Delivery, grants, ranges and CDN — 2026-09-19

Review completed in full before correction.

## Finding
Private grant issuance and serving were runtime-gated, but `DeliveryService::publishPublic()` could enter the public CDN publication path without `RuntimeGuard::requireReady()`. A direct service call could therefore attempt remote public publication while CF-04 remained deliberately disabled or before activation/provider evidence was accepted.

## Correction after review
Public CDN publication now requires the same streaming/runtime readiness gate before restore checks, asset lookup, owner authorization or remote publication. Regression coverage was added.

Grant claim binding, action-time owner reauthorization, range limits, grant-use CAS, immutable CDN identity, publication reconciliation and revocation/purge paths were reviewed. Revocation/purge was intentionally left callable for safety/containment even when normal runtime work is disabled.

No real-CDN or live acceptance is claimed.
