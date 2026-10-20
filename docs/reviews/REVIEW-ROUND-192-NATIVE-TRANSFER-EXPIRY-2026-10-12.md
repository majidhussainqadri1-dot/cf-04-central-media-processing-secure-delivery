# CF-04 Round 192 — File 17 recipient grant/native transfer expiry parity

Frozen reviewed PR head: b36607000bf2f8909d1fc8a81120ff00bbf4adfd. Focused sequential review after Round 191 exact-head push and PR CI (#751/#752) succeeded. The standalone 2026-09-16 CF-04 v1.1 Future-40 amended plan remains independently unavailable; original CF-04 v1.0 plus central governing plan impose recipient-only transfer, signed/expiring grants and expiration/revocation controls.

## Completed read-only finding
TransferService::issueRecipientGrant authorized a currently active native File 17 transfer but requested a fixed 300-second CF-04 token. If a transfer had less than five minutes of native lifetime remaining, the signed token could outlive File17 expiry. Generic DeliveryService::serve verifies owner-domain access and token expiry, but without an explicit bound to this transfer deadline. This is a time-of-check/time-of-use authorization gap; no live exploitation asserted.

## Fix after defect ledger freeze
Cap token TTL to remaining native transfer lifetime, with 2 seconds reserved for clock/processing skew and fail-closed behavior near expiry. Preserve File 17 transfer authority and CF-04 binary delivery ownership. End-to-end tests construct a C4 processed asset and ready File17 transfer, validate signed exp no later than transfer expires_at, and reject near-expired grant requests.

## Gate
Correction is accepted only after both exact-corrected-HEAD Push/PR PHP 8.1, 8.3 and 8.4 quality CI success. Source/repo evidence only, not staging/live/DB/migration acceptance.
