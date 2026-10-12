# CF-04 Review Round 196 — File 17 grant revocation recovery

Exact frozen review baseline: PR #5 SHA 94bdd99a9e024bb563d939b1d8c5995bb19bc977, after exact head Push #759 and PR #760 CI both passed 3/3.

## Read-only defect ledger
TransferService::revoke persisted status=revoked before calling DeliveryService::revokeForAsset. On grant-store failure, first request could leave an active grant while transfer was committed revoked. A second call returned immediately when status=revoked and never reconciled active grants. The same early return preceded sender/media_reprocess authorization, allowing the caller to obtain revoked transfer data without the method's normal owner/capability check. There is no claim of an observed live exploit; this is a recoverability/authorization source finding.

## Coherent correction after freeze
Move actor/owner capability check before revoked status early return. On idempotent revoked retry, re-run existing bounded revokeForAsset reconciliation so crash-partial states can heal. Keep new-transfer revocation ordering commit-first to block new issueRecipientGrant calls, then revoke grants, reusing the existing fail-closed DeliveryService::revokeForAsset. Add an integrated regression that constructs a real signed CF04 File17-recipient grant, simulates a post-commit crash by persisting revoked transfer with still-active grant, then calls revoke and checks grant becomes revoked; repeat idempotently. This is CF04-owned behavior only. File17 remains canonical transfer/relationship decision owner.

## Release gate
Exact corrected SHA push+PR CI PHP 8.1/8.3/8.4; no live/staging/DB/migration proof. Standalone amended CF04 v1.1 original still needs independent plan-parity review.
