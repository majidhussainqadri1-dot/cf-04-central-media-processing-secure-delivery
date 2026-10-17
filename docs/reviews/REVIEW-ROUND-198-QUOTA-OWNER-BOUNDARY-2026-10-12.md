# CF-04 Review Round 198 — Owner quota and burst exception authority

Reviewed read-only exact PR #5 SHA 638520c2475beb5172cad451b9cf358cc1c88f25, after Push CI #763 and PR CI #764 each passed 3/3. Compared CF04 v1.0 FR-005 quotas/purpose, consolidated central File17 1GiB verified-user transparent account/daily-volume limits and other owner-domain policies. Current File17 main uses its own native daily transfer limiter; CF04 may enforce its own quota only after activation. Latest standalone amended v1.1 Future40 remains independently unverified.

## Frozen defects, prior to corrections
- QuotaService::reserve used max(1,(int)value), silently authorizing one unit when explicit owner quota was zero and accepting malformed strings/fractional values. Abuse threshold had the same int cast problem.
- UploadService::effectiveLimits silently clamped invalid owner-domain quota limits, widening explicit zero to one.
- The governed_exception_authorized flag could be string 'true' and be accepted as a true burst waiver (raw QuotaService and upload owner decision).
- An invalid quota/policy decision after Idempotency::claim left a live 'claimed' record; fixing the rejected owner quota and retrying with the same request key would be blocked until timeout.

## Fix and executable regression
Bound quota policy fields using canonical Utils::integer; require explicit boolean for governed exception; reject malformed owner quota maps. If quota validation/reserve fails, safely mark the existing matching upload idempotency claim failed, preserving retry after limits are legitimately corrected. Add negative quota/waiver tests and successful retry test to the existing required integrated PHP source tests.

## Evidence rule
Accept only after exact new SHA push+PR CI all PHP 8.1/8.3/8.4 quality/packaging gates succeed; no staging/live/installed DB claim; File17 business policy ownership retained.
