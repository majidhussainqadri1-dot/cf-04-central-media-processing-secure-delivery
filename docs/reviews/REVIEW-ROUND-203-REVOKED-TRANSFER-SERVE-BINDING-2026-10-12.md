# CF-04 Review Round 203 — File17 native transfer revoke/serve-time consistency

Exact read-only PR baseline 828da6296b2be1e14e323512f913886735f5f72d; previous round R202 push CI #775 and PR CI #776 both PHP 8.1, 8.3, 8.4 all Green.

## Read-only frozen root-cause evidence
TransferService::revoke persists revoked status first, then calls DeliveryService::revokeForAsset. Partial failure or scan/reconciliation fault can leave an issued delivery grant active even after transfer status is durably revoked. DeliveryService::serve checks grant status, asset, policy and generic owner authorize_delivery, but has no immutable transfer identifier in that grant or a specific current transfer-status check. Consequently recipient access might remain possible until signed grant expiry in a partial-revoke failure. This is a source-level fail-closed gap, NOT evidence that the production site is affected.

## Coordinated correction after read-only freeze
Attach exact CF04 File17 transfer ID to signed claim and durable grant only for the service_id=file17-transfer path; reject transfer service issuance without exact currently-ready binding; on every serve re-check current native transfer status, expiry, recipient, File00 eligibility and File17 domain authorize_transfer_delivery decision/version. Do not treat this CF04 transport state as canonical File17 relationship truth. Add adversarial end-to-end regression preserving a signed grant in active status after a simulated post-commit transfer revocation, and assert serve rejects it. Other download/grant consumers continue without changes to signature requirements.

## Acceptance
Require exact corrected SHA push+PR all three PHP jobs and package gate Green before marking R203 accepted. Full amended CF04 v1.1 plan, staging, deployed source/version, DB/migration and live checks remain unverified. Do not infer 20/20 from 3 scoped reviews.
