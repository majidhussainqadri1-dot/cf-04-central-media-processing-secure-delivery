# Threat Model

Protected assets include restricted originals, derivatives, clinical/confidential transfers, credentials, keys, rights/consent state, audit evidence and deletion records. Adversaries include unauthenticated attackers, compromised accounts, malicious uploads, parser exploits, insiders, compromised providers and replay/race actors.

Primary controls: fail-closed runtime readiness; action-time File 00/native-owner verification; purpose-bound credentials; strict size/part/ratio/page/pixel/duration limits; private encrypted quarantine; signature-first type checks; mandatory scanners; isolated workers; immutable lineage; atomic CAS persistence; signed grants bound to actor/service/audience/context/session/operation/range/policy; no-store same-origin delivery; ordered revocation/purge/deletion; governed holds; tamper-evident audits; provider exit and restore reconciliation.

Residual risks require real-provider validation, penetration testing, operational monitoring and incident response before production.
