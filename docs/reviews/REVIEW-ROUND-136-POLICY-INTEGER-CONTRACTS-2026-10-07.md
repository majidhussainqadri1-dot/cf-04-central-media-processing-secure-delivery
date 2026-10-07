# Review Round 136 — Policy integer contracts

Review completed before correction.

## Frozen defect ledger
1. Policy, rights, retention, delivery and archive integer fields were cast before validation, allowing malformed values such as fractional, exponent or unit-suffixed strings to collapse into valid-looking integers.
2. Version and expiry fields could similarly be coerced, weakening canonical policy-hash and contract identity guarantees.

## Correction
- Added one bounded canonical-integer validator used by policy and rights normalization.
- All governed integer fields now reject malformed or out-of-range input before normalization.
- Preserved acceptance of canonical decimal integer strings for form/API compatibility.
- Added permanent Round 136 regression coverage and quality-gate execution.

No staging/live claim is made.
