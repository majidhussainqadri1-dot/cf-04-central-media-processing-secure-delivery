# CF-04 Review Round 190 — File 17 / File 00 clinical transfer privacy and rights parity

## Exact frozen source
Review began on PR #5 e96463154fc35096d11e518e97a1a4f87fd2d609 (main 0294442f0fddd1ca5440d9d5ac992ba80aced972).
Governing context: original CF-04 conditional plan; consolidated central governing master plan C0–C5 and verified file-transfer clauses. The revised standalone CF-04 v1.1 Future-40 original was not independently retrieved and full v1.1 plan parity remains pending.

## Complete scoped read-only finding
TransferService::normalize used Utils::bool for clinical_confidentiality, silently converting arbitrary invalid values to false. TransferService::create allowed the File 17-supplied confidentiality_class (e.g. C3) to disagree with the CF-04 owner-approved upload policy privacy_class (e.g. C4); it likewise permitted File17 clinical_confidentiality to disagree with the effective rights clinical_confidentiality, and a clinical transfer to retain C3 rather than C4/C5. Because protected recipient transfer routing relies on the approved native owner classification, this is an integrity/confidentiality contract defect. These were frozen before modification.

## Coordinated correction
Reject ambiguous confidentiality boolean values; require native confidentiality class C3/C4/C5 exactly equal approved upload policy privacy class; require clinical confidentiality to match approved rights and require clinical documents C4 or C5. Regression tests added to the mandatory integrated tests/run-all.php, with positive transfer fixture preserved.

## Acceptance
Only accepted after matching exact SHA Push and PR PHP 8.1, 8.3 and 8.4 QA pass. Do not claim a live exploit, live deployment, staging or migration parity. This is ONE complete targeted review and not evidence of 20 new complete reviews.
