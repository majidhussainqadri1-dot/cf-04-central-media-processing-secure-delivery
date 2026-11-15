# R303 — cooperative staged key shadow coordinator

KeyRotationShadowCoordinator begins only from an independently verified source key inventory and then walks durable keyset segment journals. It copies at most 25 candidate aliases and completes no more than 3 segment journals per invocation. Each completed segment is physically reverified; a durable CAS worker cursor resumes after missing objects and crashes, and a complete all-segment proof is required before the terminal shadow_verified_unfenced state.

R303 regression creates 2,605 authoritative asset records across more than 10 source pages, with 4 true stale-key sources and the remainder deleted. It tests unauthorized access, a missing source that blocks completion, successful interrupted replay, full physical rehash, removed shadow ciphertext rejection, and no native owner/old-key mutation.

The coordinator is not atomic across WordPress owners, lacks real KMS/HSM, lacks global write fencing and does not switch aliases or retire old ciphertext. Its full-fleet scalability and real provider/MySQL performance remain external acceptance gates.