# CF-04 Review Round 201 — Revoked grant permission boundary

## Frozen exact source and requirement evidence
Read-only source review baseline PR #5 SHA 05d7cd41493e35c762ac305bc760debdba46af82. Prior R200 exact-head CI push #771 and PR #772 all PHP 8.1, 8.3, 8.4 quality jobs SUCCESS. Original CF04 governing plan requires object- and actor-authorized grant revocation. Central consolidated plan C2-C5 privacy classification, File 08 clinical C4 data/appointment-context conditions, File 17 transfer and File 24 security require least-privilege protected operations. This is source proof only, not evidence of deployed behavior.

## Complete scoped read-only defect ledger (no mid-review modification)
DeliveryService::revoke correctly checks Auth::assertActor but returns a previously revoked grant record before checking whether this actor is the original grant actor or holds the media_reprocess capability. An authenticated different unprivileged actor could thus receive sensitive revoked grant metadata via an idempotent attempt. The same actor/capability must be enforced on normal and revoked paths. The existing test bootstrap always granted all capabilities, preventing meaningful negative coverage.

## Correction after frozen review
Move the existing original-actor-or-media_reprocess capability check before the revoked-status early return. Keep pre-existing owner DomainRegistry decision for active revoke; do not invent a separate File17 owner. Enhance the PHP test bootstrap with a test-only scoped capability-deny list (default permissions unchanged), and add a negative 12-vs-11 actor regression plus authorized original-owner idempotent retry.

## Acceptance criteria
Accept as corrected only upon exact new commit HEAD Push CI and PR CI PHP 8.1/8.3/8.4 success. Full 20 genuinely comprehensive code audits remain pending, as does complete amended CF04 v1.1 plan text, integration, deployed version, database/migrations and staging/live acceptance. One focused round does not imply 20/20.
