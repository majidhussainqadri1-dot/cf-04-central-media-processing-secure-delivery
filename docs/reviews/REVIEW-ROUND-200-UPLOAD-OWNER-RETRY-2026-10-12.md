# CF-04 Review Round 200 — No stranded idempotency on native owner-contract failure

Read-only reviewed PR #5 SHA 7bc93c741f61a11cb978d1f9003589423f24acd6, Push CI #769 and PR CI #770 success on PHP8.1/8.3/8.4. Original CF04 plan and consolidated master plan mandate owner-domain permission at upload actions, retries and isolation; canonical File17/other domain truth must remain external.

## Frozen finding
After Idempotency::claim in UploadService::create, the DomainRegistry::decision(owner,'authorize_upload') call was still outside the try/catch introduced at R198 for quota errors. When owner-domain contract is temporarily unavailable/denied/throws, first create fails but leaves the matching idempotency row 'claimed' until timeout. Retrying same legitimate request after owner contract recovery would instead return idempotency_in_progress, delaying recovery. No claim of observed live failure.

## Correction after full scoped read-only review
Move owner authorization inside existing try/catch that already calls Idempotency::fail with exact claim identity. Keep owner authorization fail-closed (it must not be bypassed), preserve owner-specific canonical decisions and never add local File17 business truth. Add an executable native-owner-unavailable negative regression checking the error, durable failed idempotency state, and a second fresh authorization attempt with same idempotency key.

## Acceptance
New SHA Push/PR CI and full PHP8.1/8.3/8.4 quality gates mandatory. Historical successful CI not accepted for a new SHA. Latest standalone CF04 amended v1.1 still unavailable for direct independent source comparison, live/staging/DB/migration unverified.
