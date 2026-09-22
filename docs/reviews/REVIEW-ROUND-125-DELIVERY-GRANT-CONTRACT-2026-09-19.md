# Review Round 125 — Delivery grant contract/runtime parity — 2026-09-19

Review completed in full before correction.

## Findings
The public delivery-grant JSON Schema used `additionalProperties:false` but omitted two claims always emitted by the runtime: `type=delivery-grant` and `download_mode`. It also limited `operation` to `view/download` while the runtime and rights layer explicitly support `stream`, `extract_text`, and `ocr`.

A valid runtime-signed grant could therefore fail its own published contract.

## Correction after review
The 1.5.0 grant schema now includes and requires the runtime discriminator and download-mode boolean, and its operation enum matches the runtime delivery operations. Exact-head regression coverage was added.

Grant binding, consume-time owner authorization, rights checks, use-count CAS, range policy, residency checks, public CDN integrity and purge reconciliation were also reviewed.
