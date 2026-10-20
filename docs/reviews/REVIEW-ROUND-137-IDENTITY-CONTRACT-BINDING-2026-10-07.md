# Review Round 137 — Identity and domain-contract binding

Review completed before correction.

## Frozen defect ledger
1. Domain authorization object versions and File 00 assertion versions were integer-cast before validation, so malformed numeric values could satisfy freshness gates.
2. A positive File 00 assertion was not explicitly bound to the requested user ID.
3. Transfer party identifiers could be coerced from malformed numeric strings before verification.

## Correction
- Domain object versions and verification assertion versions now require canonical positive integers.
- File 00 assertions must return the exact requested user ID.
- Transfer user identifiers now fail closed unless canonical positive integers.
- Added permanent Round 137 regression coverage and quality-gate execution.

No staging/live claim is made.
