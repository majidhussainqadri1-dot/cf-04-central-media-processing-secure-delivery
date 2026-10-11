# Review Round 121 — Public contract version parity — 2026-09-19

Review completed in full before correction.

## Finding
The plugin declared `SCM_CONTRACT_VERSION=1.5.0`, while every public JSON Schema still advertised a `1.4.0` schema identifier. That created a public contract identity/version contradiction.

## Correction after review
All ten public JSON Schema `$id` values now match 1.5.0. Contract-runtime verification now derives the declared runtime contract version and rejects any schema-id mismatch. An exact-head regression test was added.

No live/deployed contract parity is claimed.
