# Review Round 112 — REST error and contract boundary — 2026-09-19

Review completed in full before correction.

## Findings
Several REST handlers performed contract checks, upload-stream/body validation, route parsing, or input extraction before entering the shared `Rest::wrap()` error translator. A rejected contract or oversized/malformed request could therefore escape the canonical safe REST error response and be surfaced as an unhandled host exception.

Affected paths included upload part/action, grants, grant metadata, transfers, downloads, deletion/hold operations, and provider-webhook preprocessing.

## Correction after review
All throwing preprocessing for those handlers now executes inside the shared REST error boundary. Upload stream resources still close in a local `finally`. Provider webhook body-size/signature preprocessing is also translated through the same safe boundary. Exact-head regression coverage was added.

No staging, live, or operational acceptance is inferred.
