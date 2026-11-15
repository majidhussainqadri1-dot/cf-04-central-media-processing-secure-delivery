# R304 — reject unplanned segments in all three staged inventories

Source defect: previously, completed source inventories rehashed all **expected** source-derived segment identifiers, but failed to reject an **additional** segment record claiming the same inventory/checkpoint ID. This could cause downstream staged coordinators to attempt extra shadow work before discovering inconsistent totals.

R304 adds StageSegmentCatalogGuard.assertExactCount, a complete bounded-keyset catalogue count check on the owner ID. It is invoked in ProviderExitInventoryStage::verifyComplete, KeyRotationInventoryStage::verifyComplete and RestoreCheckpointStage::verifyComplete.

The R304 regression deliberately inserts one extra segment with the same parent ID into each family; all three verifiers must fail closed before their downstream operations. Removing the invalid record restores the authentic stage witness. The guard does not solve concurrent owner write fencing and cannot prove the underlying live MySQL/provider snapshot is immutable.

No production database, provider migration, rotation, restore or live operation was performed.