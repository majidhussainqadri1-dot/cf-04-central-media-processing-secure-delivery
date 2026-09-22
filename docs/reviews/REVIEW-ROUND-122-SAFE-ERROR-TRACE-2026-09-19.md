# Review Round 122 — API error-schema and traceability review — 2026-09-19

Review completed in full before correction.

## Finding
The governing API constitution requires safe error code/message plus a trace identifier. Unexpected REST failures had an incident identifier, but ordinary governed errors and streaming-delivery errors did not expose a correlation trace. This made support/incident correlation inconsistent across the API surface.

## Correction after review
REST and streaming-delivery error responses now expose a bounded opaque `trace_id`. Unexpected streaming failures also write redacted audit evidence keyed by that trace. No stack trace, SQL, path, token, object key or secret is exposed.

Exact-head regression coverage was added.
