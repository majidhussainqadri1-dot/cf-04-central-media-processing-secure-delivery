# CF-04 Review Round 195 — Policy authorization/privacy non-coercion

Frozen exact read-only baseline: PR #5 SHA 6aaca223e473a8f9480369f466040568bdf385d9; Round 194 exact SHA push CI #757 and PR CI #758 were 6/6 success. Governing source is original CF-04 v1.0 + consolidated Sabri central plan privacy lifecycle / secure grant / rights / retention requirements. Latest amended CF-04 v1.1 source unavailable for independent word-for-word audit.

## Read-only frozen defect ledger
Policy::normalize previously silently widened short/zero grant TTL to >=30s and temporary retention to >=60s rather than rejecting nonconforming owner requests. Delivery content_disposition invalid values silently became inline. Policy metadata stripping and delivery toggles passed through Utils::bool, which converts ambiguous flags to false; this could silently disable location/device stripping. The technical-safety minimum confidence used (float) coercion so '0.8junk' could be treated as valid 0.8.

## Corrected CF-04-owned source
Reject grant TTL outside explicit existing 30..86400 policy range and temporary retention under existing 60s floor; reject unsupported content disposition; require canonical booleans for delivery, metadata and safety fields; require finite exact 0..1 safety threshold. No business-domain policy authority transferred to CF04. Executable negative/positive regressions in tests/run-all.php.

## Verification
Pending fresh Push/PR exact-head PHP 8.1, 8.3 and 8.4 CI; never treat prior SHA Green as current, nor GitHub as staged/live/DB/migration truth. This is only one completed specific review, not proof of the full 20-round batch.
