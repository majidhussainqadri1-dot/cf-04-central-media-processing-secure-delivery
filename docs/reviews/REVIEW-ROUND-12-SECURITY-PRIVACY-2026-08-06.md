# Review Round 12 — Security, Privacy and Authorization

## Findings and fixes

1. Durable persistence and schema readiness are mandatory outside explicit test mode; no production memory fallback.
2. Audit events use a persistent tamper-evident hash chain and fail closed on write failure.
3. Encryption supports chunk-streaming AES-256-GCM, explicit key IDs and historical-key grant verification during rotation.
4. Signed grants bind actor, service, audience, context, session, operation, range policy, object version, policy version, rights version, target hash, expiry, nonce and use count.
5. Raw object keys remain outside signed claims.
6. Public CDN publication now rechecks rights and native-owner authorization.
7. Legal/security holds block issuance and consumption.
8. Delivery tokens were removed from URL paths; the streaming endpoint accepts Authorization/X-Delivery-Token headers and streams bounded chunks with no-store, no-referrer, nosniff and sandbox headers.
9. Scanners and sandbox workers require versions, isolation attestations and fail-closed results.
10. Service and webhook signatures include timestamps/nonces and replay protection.

## Result

Security/privacy source gate: **PASS**. Real provider and independent penetration evidence remain external gates.
