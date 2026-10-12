# CF-04 Review Round 194 — Workspace upload numeric/flag integrity

Baseline PR #5 SHA: 305905da655fc6c4ad8ac9afcf0e3f09c87c523c, with push CI #755 and PR CI #756 GREEN. This is the first of R194–R211 follow-up rounds and not 20 completed passes.

## Governing sources & cross-file checks
The original 2026 CF-04 conditional plan and the consolidated Sabri governing plan require server-side image/audio media validation, privacy-safe metadata stripping, crop/rotate/compression, audio preview/trim, owner-bound uploads, and CF-04 processing only following activation. File 17 main and File 19 main contract comparison still show activation-conditional integration; do not call File17/File19 live acceptance complete. The full amended standalone CF-04 v1.1 Future40 plan original remains unavailable for line-by-line independent confirmation.

## Frozen read-only defect ledger
- WorkspaceUploadService::normalize image rotation and compression_quality used (int) casts; '90junk' and '80junk' could be accepted as valid numbers.
- Image crop coordinates were cast and negatives silently clamped to 0; explicit crop flag with zero dimensions could pass.
- Audio trim start/end used (float) casts, so '5garbage' silently became 5 seconds; noncanonical numeric inputs could alter the requested edit rather than fail closed.
- Crop and loudness flags used Utils::bool, silently converting 'false' strings to false instead of rejecting ambiguous user inputs.

## Coherent correction
Enforce canonical integer rotation, quality, and bounded crop coordinates; require explicitly typed boolean flags; require finite, nonnegative, exact numeric audio trim inputs and ordered trim ranges. Add negative input and positive control regressions to the mandatory integrated tests/run-all.php. This fixes CF-04-owned workspace normalization only: File 20 owns shell UI and the media domain retains its canonical content metadata.

## Verification
Round accepted only after exact corrective SHA Push CI and PR CI success under PHP 8.1, 8.3, 8.4 with deterministic QA/package gate. No staging/live installation, real provider, DB or migration state asserted.
