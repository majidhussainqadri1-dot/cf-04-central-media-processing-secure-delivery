# R305 — 2,001 affected aliases, one immutable old ciphertext and one new shadow

The prior R303 stress test covered more than 2,600 metadata records but only four truly stale-key references. R305 directly exercises more than **2,000 affected stale aliases** through the cooperative source-only key-shadow coordinator. A test-only in-memory ObjectStore with a physical key-id inspection contract makes this adversarial scale regression deterministic and independent of host filesystem fsync latency.

- 2,001 stale asset aliases share one old encrypted object.
- Nine asset inventory pages plus the derivative completion page produce ten durable segments.
- A bounded 25-alias coordinator completes all references without touching any original asset mapping; one canonical target ciphertext is reused.
- The independent verifier rehashes all source and shadow witnesses and rejects a changed physical old-key ID even when plaintext and database metadata are unchanged.
- Both first/last aliases remain on original source key; no KMS retire, old-key deletion or live mapping switch occurs.

This proves repository algorithm/test-scale coverage, **not** real KMS, database SQL performance, Hostinger staging, cross-owner write fencing or live release acceptance. R305 must remain explicitly shadow_verified_unfenced.