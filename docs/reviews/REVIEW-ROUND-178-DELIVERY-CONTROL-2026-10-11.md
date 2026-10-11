# CF-04 Round 178 — Delivery Control

Frozen read-only source d2a71152ca10222dc646decfb21645e7336ad621, main 0294442f0fddd1ca5440d9d5ac992ba80aced972, Draft PR #5. Governing CF-04 v1.1 Future-40 Amended; runtime 1.3.0-rc.1; schema/contract 1.5.0.

Findings: C0 privacy alone allowed CDN routing without rights/owner authorization; candidate IDs, priorities and list size could be coerced or unbounded; origin-shield and edge inputs lacked strict numeric and boolean contracts; missing adversarial regression.

Joint correction: require runtime, authorized control-plane user and owning-domain approval, public delivery rights and policy; validate bounded candidates and deterministic selection; validate origin and edge profiles; add regression gate. No staging/live/operational claim. Acceptance requires exact-head CI.
