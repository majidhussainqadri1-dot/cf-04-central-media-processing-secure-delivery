# CF-04 Review Round 202 — Delivery native group and session identity integrity

Exact frozen source PR #5 19da52a9e15dbb9531c3ffd223887649d9b777f5 after push #773 and PR #774 both 3/3 successful. Governing central C2-C5 privacy, File08 clinical C4, File17 native recipient/relationship, File20 downloads and File24 security require canonical signed object and session reference. Latest amended CF04 v1.1 plan remains unverified.

## Frozen read-only defect ledger
- Group audience IDs were string-cast and passed through Utils::text(...,96), silently truncating or trimming File17 native identities before owner authorization/signing.
- The audience type enum was silently normalized to lowercase (e.g., uppercase initial type), contrary to canonical machine vocabulary.
- Delivery issue and consume both normalized session identifiers with Utils::text(...,128), creating potential collisions across distinct caller sessions.
These findings were collected before any modifications.

## Coherent correction
Require group identifiers exactly preserved within 96 bytes and literal canonical enum; use a common strict canonical session identity guard for issuing and consuming grants. Owner-domain native membership stays File17. Cover overlength/control/whitespace/numeric group, invalid enum, malformed session, and valid group signed token with negative consume test.

## Verification
Accept only after same corrected commit exact-head Push and PR PHP 8.1/8.3/8.4 quality+package gate success. No live, DB, schema, migration or entire 20-round claim.
