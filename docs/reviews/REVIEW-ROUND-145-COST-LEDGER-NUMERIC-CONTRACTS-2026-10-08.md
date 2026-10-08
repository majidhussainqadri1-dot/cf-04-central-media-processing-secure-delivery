# Round 145 frozen review

Baseline: 1102a6176640649745c9097c701aa98a0dcd6f54.
Scope: FR-030 cost attribution, budgets, invoice reconciliation.
Defects: lossy unit/rate numeric casts; missing rates counted as zero; persisted ledger amounts and timestamps cast without validation; invoice totals and tolerance cast without validation; owner domain not validated; corrupt budget discovered only after a new cost write.
All defects require one verified correction before Round 146.
