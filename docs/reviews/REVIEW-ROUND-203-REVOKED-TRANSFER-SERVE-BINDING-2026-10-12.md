# CF-04 Review Round 203 — File17 native transfer revoke/serve-time consistency

Exact read-only PR baseline 828da6296b2be1e14e323512f913886735f5f72d; previous round R202 push CI #775 and PR CI #776 both PHP 8.1, 8.3, 8.4 all Green.

## Read-only frozen root-cause evidence
TransferService::revoke persists revoked status first, then calls DeliveryService::revokeForAsset. Partial failure or scan/reconciliation fault can leave an issued delivery grant active even after transfer status is durably revoked. DeliveryService::serve checks grant status, asset, policy and generic owner authorize_delivery, but has no immutable transfer identifier in that grant or a specific current transfer-status check. Consequently recipient access might remain possible until signed grant expiry in a partial-revoke failure. This is a source-level fail-closed gap, NOT evidence that the production site is affected.

## Coordinated correction after read-only freeze
Attach exact CF04 File17 transfer ID to signed claim and durable grant only for the service_id=file17-transfer path; reject transfer service issuance without exact currently-ready binding; on every serve re-check current native transfer status, expiry, recipient, File00 eligibility and File17 domain authorize_transfer_delivery decision/version. Do not treat this CF04 transport state as canonical File17 relationship truth. Add adversarial end-to-end regression preserving a signed grant in active status after a simulated post-commit transfer revocation, and assert serve rejects it. Other download/grant consumers continue without changes to signature requirements.

## Acceptance
Require exact corrected SHA push+PR all three PHP jobs and package gate Green before marking R203 accepted. Full amended CF04 v1.1 plan, staging, deployed source/version, DB/migration and live checks remain unverified. Do not infer 20/20 from 3 scoped reviews.

## Pre-acceptance CI regression fix
The first corrected-source push #777 and PR #778 signaled a test-fixture failure at tests/run-all.php line 231: the fixture looked up transfer using the baseline envelope `$transfer['id']`, which was not a durable transfer ID. The already-existing Round 192/196 persisted transfer reference is `$nearExpiryTransfer['id']`. Corrected only this fixture reference without changing production authority checks. This is a test setup defect, not a verified live vulnerability. R203 acceptance remains pending the fresh exact new SHA quality jobs.

## Exact CI follow-up: fixture ordering correction
Push #779 and PR #780 failed the same missing test-fixture reference: tests/run-all.php executes FR-020 (where R203 was first inserted) BEFORE the R192 creation of `$nearExpiryTransfer` and `$transferAsset` in later integration tests. This is a test-order defect. Moved the complete R203 adversarial exercise to after the R196 persisted-transfer/grant setup, immediately before the final runtime gate. Production code unchanged. Verify fresh exact-head CI before acceptance.
