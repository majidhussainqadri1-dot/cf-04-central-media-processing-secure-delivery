# Review Round 141 — Delivery numeric contracts

Baseline exact PR #5 HEAD: `3467876e2599134dfa9a73fbe22419537eb580ec`. Entire review completed read-only before corrections.

## Frozen defect ledger
1. User/recipient audience identity casts accepted noncanonical numbers.
2. Optional group identity was cast or silently omitted when malformed.
3. Requested range maximum was cast/clamped instead of validated.
4. Persisted grant expiry was cast unchecked before authorization.
5. Persisted grant use count and maximum were cast unchecked.
6. Concurrent grant consumption rechecked and incremented unchecked counters.
7. Persisted range maximum and REST grant TTL/use limits lacked canonical validation.
8. Stored target size was cast unchecked before byte range and streaming calculations.

## Unified correction and evidence
Canonical integer validation now applies to audience, range, REST issue limits, grant counters at both initial and CAS boundaries, and target sizes. Malformed values fail closed; canonical decimal strings remain supported. Round 141 executable regressions are part of the quality gate. Acceptance requires exact corrected HEAD CI success.

Repository-only source evidence; staging, live and operational acceptance remain unverified.
