# Review Round 128 — Future-40 adversarial cost/provider routing — 2026-09-19

Review completed in full before correction.

## Findings
FUT-035 accepted non-finite price/plan numbers, which could produce NAN/INF estimates and invalid evidence. FUT-036 could also admit an approved/healthy candidate with an empty provider identity or non-finite cost/latency scores, making routing nondeterministic or returning an unusable provider.

## Correction after review
Cost estimation now requires finite non-negative prices and plan quantities, a valid currency, and a bounded finite total. Provider auto-routing now normalizes requirements/capabilities, requires a non-empty provider identity, excludes invalid/non-finite scores, and uses a deterministic identity tie-break.

Other Future-40 privacy, offline grant, resume, residency/WORM, migration-signature, staging-chaos and advisory storage-tier controls were reviewed in the same pass.
