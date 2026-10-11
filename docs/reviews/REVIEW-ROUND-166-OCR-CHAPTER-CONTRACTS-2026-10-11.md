# CF-04 Review Round 166 — Chapter/OCR Numeric Contracts (2026-10-11)

Read-only review source: `0aa0f467f5c0745c2380d35cec4a7d8e72544977`. Target main: `0294442f0fddd1ca5440d9d5ac992ba80aced972`. Governing repository plan: CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1; schema/contract 1.5.0. Review completed before correction.

## Frozen findings
1. Chapter integer casting accepted overflowing/noncanonical timecodes.
2. Chapter entry/title types and chapter count lacked bounded shape validation.
3. OCR page numbers accepted malformed casts and duplicate/nonascending pages.
4. OCR confidence/coordinate coercion accepted malformed values, non-list boxes and invalid geometry.
5. OCR page/item/text volume and typed text lacked explicit bounds.
6. Dedicated adversarial regressions absent.

## Joint correction
Use canonical bounded chapter times and ordered lists. Require typed OCR page, confidence, text and normalized numeric rectangles with positive dimensions and in-bounds geometry. Enforce explicit volume limits. Add `tests/review-round-166-ocr-chapter-contracts.php` to the quality gate.

Exact-head QA is necessary before source acceptance. No staging, live or operational acceptance is claimed.
