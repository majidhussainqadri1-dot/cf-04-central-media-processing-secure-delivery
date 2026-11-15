# CF-04 V12.3 Live Reality Freeze — read-only evidence

Run only from an authorized WordPress WP-CLI session. Copy tools/cf04-live-reality-freeze.php outside the public web root and run:

    wp eval-file /trusted/private/cf04-live-reality-freeze.php > /trusted/private/cf04-freeze.json

This script performs no writes, upgrades, provider access, object deletion, key rotation or migration. It collects WordPress/PHP versions, active plugin IDs, CF-04 constants and allowlisted option states, two CF-04 table structures and safe row counts, plus the SHA-256 digest of the plugin PHP entrypoint and includes.

The tree digest is NOT an exact deployed ZIP package checksum or proof of parity with GitHub. Compare it independently with the matching deployed build and manifest. Migration status is unknown until migration logs and options are examined; runtime errors/log contents and external provider health are not collected. Do not publish raw CLI output without security review.

Keep the incident's five truth fields separate: Repository HEAD, Deployed Version, DB Version, Migration State, Live Verification Status. WordPress deployment, provider cutover, schema changes, Restore, staged key rotation and public activation all remain blocked until the exact installed system, owner contracts and Founder approval are verified.

## Deterministic source-parity witness

The candidate CI package manifest and RELEASE-EVIDENCE now include php_tree_sha256 and php_tree_file_count, using the same ordered path + NUL + file SHA-256 + newline scheme as the WordPress CLI freeze collector. Compare the observed deployed php_tree_sha256 to the exact candidate manifest before making a source-parity claim. A matching PHP digest proves only this limited PHP file scope; it does not establish DB migration, provider configuration, external workers, installed artifact packaging, consent state, or live end-to-end acceptance.

## Exact truth-label correction (R297)

The CLI field `loaded_plugin_version_constant` refers exclusively to the `SCM_VERSION` constant actually loaded by WordPress. It is **not** a GitHub source repository commit or proof of deployed package parity. The repository SHA must be collected separately and matched to a deployed artifact independently.
