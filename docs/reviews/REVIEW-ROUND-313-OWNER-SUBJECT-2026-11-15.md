# R313 — native owner approval subject-bound return evidence

Confirmed source security omission: the new opt-in approval-only callback verified `object_version` and `record_cas`, but did not require the approving owner to echo the exact `asset_id`, `record_id`, `source_provider`, and `target_provider`. A generic affirmative decision or cross-subject callback response could therefore be mistaken for approval of the current migration.

The read-only preflight now rejects any mismatch with `provider_owner_subject_mismatch`. R310 regression covers forged asset and swapped destination-provider responses, while preserving the separate stale-version, missing-callback, denied-capability and post-callback-drift tests.

The report remains `owner_approved_unfenced` with explicit `authorization_to_switch=false`. These unit-tested reference-bound decisions still do not establish a real cross-owner lease or execution capability on Hostinger.
