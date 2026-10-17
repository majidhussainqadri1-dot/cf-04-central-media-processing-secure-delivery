# CF-04 Review Round 197 — Upload size/native object/credential TTL identity

## Frozen read-only review and governing context
Reviewed exact PR head 10cf4f9c795112060e421b99cc178ec7e06ebb57 (prior R196 push #761 and PR #762 all PHP jobs success). Reviewed original CF-04 v1.0 requirement for canonical binary-owner relation, resumable purpose-bound upload and expiring grant, consolidated central master plan 1 GiB File17 verified-user transfer, and current File17 owner repository status; no claim of deployed integration. Latest amended CF04 v1.1 standalone plan still unavailable; cross-plan completeness conditional.

## Defect ledger frozen before modifying code
- UploadService::validateMetadata silently (int)-cast a noncanonical upload size, including a fractional value or a numeric prefix followed by extra characters. Stored expected_size then differed from caller input.
- owner_object validation accepted values that Utils::text truncated at 191 chars, stripped control characters or whitespace, creating a different owner reference/authorization context.
- session_ttl_seconds silently (int)-cast and clamped a caller-specified too-short/too-long upload credential TTL to 300..86400 seconds, potentially authorizing a different expiry from the requested one.
- Integrated tests covered the normal path but did not reject these malformed values.

## Cohesive correction, tests and gate
Validate exact bounded upload byte count via Utils::integer; require canonical string owner-object reference that survives Utils::text losslessly; validate session TTL before idempotency claim, use the validated value for first credential and replay; never silently widen user TTL. Add ten negative and one canonical positive end-to-end runtime regression tests to mandatory tests/run-all.php. Acceptance requires both exact corrected SHA CI push+PR success with PHP8.1/8.3/8.4 and deterministic packaging. Repo-only assertion: deployed version, DB and migration, staging and live remain unverified. This records one actual correction round, NOT a completed 20/20 batch.
