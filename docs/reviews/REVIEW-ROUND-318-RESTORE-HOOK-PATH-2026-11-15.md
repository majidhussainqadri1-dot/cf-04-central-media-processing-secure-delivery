# R318 — verify the actual production attestation hook path

The cryptographic descriptor tests in R317 now also execute `RestoreExecutionAttestation::requireForServe()` with the isolated test-only enforcement constant `SCM_TEST_REQUIRE_SIGNED_RESTORE`. The negative fixture denies the absence of `scm_restore_execution_proof`, and a second fixture supplies an exact-run signed descriptor through a simulated trusted native callback. This does not validate a real backup engine or provide a live attestation key; production remains blocked without independent execution proof.
