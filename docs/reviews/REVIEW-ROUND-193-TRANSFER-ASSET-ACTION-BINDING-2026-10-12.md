# CF-04 Review Round 193 — File 17 asset integrity on recipient delivery

Exact read-only reviewed PR HEAD: 94260e861f0b244cc926a6cf9df5e465c8354b61, after Round 192 Push CI #753 and PR CI #754 both passed PHP 8.1, 8.3 and 8.4. Original CF-04 plan and current central consolidated plan supply the canonical native owner, typed object policy, cross-file consent and at-action authorization requirements. Standalone latest amended v1.1 CF-04 document remains independently unverified.

## Frozen defect ledger
TransferService::bindAsset validates File17 native owner_object, sender, version, policy hash and byte size. However markReady and issueRecipientGrant checked only ready status and version/recipient authorization without rechecking persisted transfer-to-asset identity, sender, size, privacy class, rights fingerprint or policy hash. Corrupt or stale binding could create a grant for a different ready asset even though transfer owner and recipient ID had not changed. This is a source security risk, not proof of a live incident.

## Coordinated correction
Add a shared fail-closed assertNativeAssetBinding guard and invoke it at markReady and at issueRecipientGrant, using exact native object/sender/version, size, CF04 media/privacy, policy hash and rights hash. R192 processing fixture is adjusted to model a fully bound canonical transfer; regressions cover wrong asset, size drift and positive authorized grant. No File17 native domain truth is copied or modified.

## Verification law
Pending exact corrected SHA Push CI and PR CI, PHP 8.1/8.3/8.4; only after 6/6 success mark this round accepted. No staging/live/migration acceptance or live bug claim.
