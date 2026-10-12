# CF-04 Review Round 199 — File 17 owner reauthorization on idempotent upload replay

Frozen read-only PR HEAD ec9fd004d8783d77e2c3f5330a9f80639c40b583. Previous Round 198 exact push CI #765 and PR CI #766 passed PHP 8.1, 8.3, 8.4. Governing CF-04 plan and central governing plan require object-version, relationship, rights, consent and suspension authorization at sensitive action time, and File17 retains native recipient/content authority.

## Scoped read-only defect ledger
UploadService::create first returns stored idempotency-completed result in 'uploading' or 'paused' and immediately issues a fresh time-limited upload credential. Unlike putPart/pause/resume/finalize actions, this path did NOT call reauthorizeActiveUpload, so a previous File17/domain owner authorization with now-stale object_version could be replayed to refresh credential. It also did not assert that stored replay upload retains the caller's actor/owner/policy/size binding. This is source-based authorization gap, not observed live exploit.

## Unified root-cause correction
Before issuing any replay credential, fail closed if stored actor, owner domain/object, policy hash, or exact size contradict the validated request; then call existing reauthorizeActiveUpload($row,$actor,'replay') to verify File00 identity and native owner authorize_upload permission + object_version at current time. No new File17 canonical backend. Dynamic regression mutates the stored upload owner version, checks replay rejection, restores valid owner version, verifies authorized replay still works, and cleans up.

## Acceptance and provenance
Only exact corrected SHA push + PR PHP 8.1/8.3/8.4 CI qualify this R199 as corrected. Full amended CF04 v1.1 future40 plan original not yet independently available, staging/live/DB/migration parity remains unverified.
