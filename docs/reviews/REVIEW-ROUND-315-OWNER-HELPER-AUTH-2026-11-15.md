# R315 — direct helper authorization in native owner approvals

Root cause: factoring the native owner approval predicate into `ProviderExitOwnerReadiness::inspectMapping()` made it a public method callable without first invoking the parent administrative workflow. A direct caller could trigger owner-domain callbacks without `media_manage_providers` authorization, even though the outer stages already checked that capability.

The shared helper now independently enforces `Auth::capability('media_manage_providers')` on every call, including direct method calls and staged processing. R310 adds a denied-capability negative test against the helper itself. No source cutover, owner remap or live deployment occurred.
