# CF-04 Fresh Adversarial Review/Fix Round 2 — 2026-08-06

## Independent negative-path focus
The corrected source was reviewed again for races, replay, stale authorization, scanner/provider outage, malformed files, oversized parts, memory exhaustion, partial cleanup, REST exceptions and test/package drift.

## Defects corrected
1. Record persistence changed to atomic optimistic compare-and-swap rather than replace semantics.
2. Idempotency gained claimed/completed/failed states, concurrent-claim rejection and deterministic result replay.
3. Upload metadata and policies gained canonical MIME values, exact media-class binding, bounded part sizes and capacity validation.
4. Upload creation/completion now replays safely, rejects gaps/overlaps, verifies final hash/magic, compensates failed object commits and tracks deferred part cleanup.
5. Unsafe large local assembly now fails closed pending an approved streaming provider.
6. Signature-based MIME detection covers the permitted image/document/audio/video formats and rejects unknown signatures.
7. Required archive, decompression, metadata and malware scanners can no longer silently pass without provider evidence.
8. File 00 verified-transfer assertions no longer have a test-like permissive runtime fallback.
9. Scan failure evidence is persisted; promotion requires current native owner version.
10. REST catches unexpected throwables without disclosing internals and emits a safe incident identifier.
11. Workspace upload authorization now requires runtime activation, authenticated actor and current owner version.
12. Unit tests were replaced with full lifecycle, replay, revocation, deletion, concurrency, upload, scanner and provider-failure coverage.

## Fresh verification added
`tests/review-round-10-fresh-adversarial.php` exercises stale owner decisions, hidden object keys, persistent grant records, concurrent idempotency and large-object fail-closed behavior.

## Round decision
Accepted as `1.1.0-rc.3` for deterministic packaging and exact-head GitHub Actions verification. External scanner/provider, Hostinger staging, migration, rollback and operational gates remain pending and are not represented as complete.
