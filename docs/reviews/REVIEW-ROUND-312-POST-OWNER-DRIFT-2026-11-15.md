# R312 — owner callback postcondition revalidation

**Defect:** the optional owner-approval-only preflight could return a successful `owner_approved_unfenced` report after an external native owner callback modified a staged source object. Although no provider cutover occurs, this would be a stale claim about approval against previously observed data.

**Fix:** before emitting the approval report, independently rerun `ProviderExitCutoverManifestStage::verifyComplete()`, including source-record versions, staged hash/chains and physical source/target bytes. A callback or concurrent mutation fails closed rather than returning stale approval.

**Regression:** R310 extended with an adversarial owner callback that deliberately changes a live object version between approval and response. Expected `provider_exit_inventory_drift`; no switching or data deletion. A final postcheck is still **not** a durable cross-owner write fence and cannot authorize cutover.
