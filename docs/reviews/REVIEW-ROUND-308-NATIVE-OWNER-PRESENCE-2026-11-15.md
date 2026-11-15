# Review Round 308 — Registered native owner requirement (2026-11-15)

**Confirmed source defect:** the read-only provider cutover mapping manifest previously considered a syntactically valid but non-existent `owner_domain` sufficient to create a source-complete manifest.

**Corrected:** `ProviderExitCutoverManifestStage::mapping()` now requires the canonical owner to be registered in `DomainRegistry`. A missing native owner fails closed with `provider_cutover_owner_unregistered`. This check does **not** constitute the owner's consent, a write fence or authorization to switch providers. The stage remains `verified_unfenced` at most.

**Regression:** R308 creates real encrypted source/shadow bytes but an unregistered fake owner, demands fail-closed, fixes owner identity to registered file17, and confirms proof-only completion without remapping or purging sources. This test is added to the mandatory quality gate.

**External status:** real cross-module registration and DB/provider/Hostinger migration remain unverified.
