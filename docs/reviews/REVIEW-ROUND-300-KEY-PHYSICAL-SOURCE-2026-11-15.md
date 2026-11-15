# R300: Stage physical ciphertext key/source verification

V12.3 CF04-FR-017 requires key rotation and encryption metadata integrity. The old key-rotation candidate stage trusts metadata only and cannot attest the actual physical encryption key.

R300 adds an optional ObjectCipherKeyInspector interface, implemented for the already encrypted LocalObjectStore, and the read-only KeyRotationPhysicalPreflight method inspectSegment. This method rechecks the complete candidate inventory, visits 1-25 candidates per invocation, verifies source owner/provenance/hash/size, attests the real ciphertext key ID and independently hashes every decrypted stream. It rejects tampered source keys even if plaintext is unchanged.

All results remain partial_unfenced or segment_physically_verified_unfenced. This is not a durable group lease, actual re-encryption, KMS/HSM integration, key retirement or large-scale (>2,000 aliases) cutover.

R300 negative tests cover nonprivileged access, identical plaintext re-encrypted under the wrong physical key, missing source, bounded segment handling and no destructive mutation. Real cloud provider adapters lacking a trustworthy cipher-key inspector fail closed and remain an external integration blocker.