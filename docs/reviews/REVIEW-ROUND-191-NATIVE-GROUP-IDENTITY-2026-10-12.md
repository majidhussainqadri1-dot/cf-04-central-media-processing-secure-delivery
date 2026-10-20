# CF-04 Review Round 191 — File 17 recipient/native reference integrity

Frozen read-only baseline: PR #5 e147ea3a267d9ad2934557a6a48d4e8eeb905f6e. Previous Round 190 exact-head push #749 and PR #750 verified green, with PHP 8.1/8.3/8.4 accepted.

## Complete scoped read-only findings
The File 17 native transfer ID was silently cast/truncated by Utils::text(...,191); the group recipient ID was cast/truncated by Utils::text(...,96). Distinct canonical File17 references could collide after truncation/normalization and be presented to owner authorization as a different object/group. The normalizer also accepted a user transfer with a conflicting group ID, or a group transfer with an otherwise supplied user ID, resulting in ambiguous audience identities. Source: class-scm-transfer.php TransferService::normalize. The central plan mandates selected recipient-only delivery and canonical File 17 owner permission at action time.

## Correction after read-only freeze
Reject truncated/coerced native and group reference IDs; reject ambiguous dual user/group audiences; retain exact canonical valid group identity. Keep native File 17 relationship and group authorization with File 17; no cross-owner table or notification projection duplicated. Add runnable negative and positive regressions in mandatory tests/run-all.php.

Acceptance gate: exact corrected PR HEAD both push and PR runtime quality CI for PHP 8.1, 8.3, 8.4; no staging/live or installed DB/migration conclusion. This records a single focused review, not twenty fresh review rounds.
