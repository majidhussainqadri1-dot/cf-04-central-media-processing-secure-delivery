# Review Round 126 — Processing state-machine contract review — 2026-09-19

Review completed in full before correction.

## Finding
The runtime has a durable `waiting_review` processing-job state for low-confidence/escalated safety review, but the published 1.5.0 job-record schema rejected that state. A valid persisted/runtime job could therefore fail the public state-machine contract.

## Correction after review
The job-record status enum now includes `waiting_review`; exact-head regression coverage binds the schema to all runtime queue/review/terminal states.

The current generation binding, lease ownership, bounded retries, dead-letter recovery, hold checks and per-node canonical-owner authorization were reviewed in the same pass.
