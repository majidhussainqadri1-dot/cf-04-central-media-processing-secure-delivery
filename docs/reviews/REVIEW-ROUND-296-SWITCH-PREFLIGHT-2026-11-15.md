# R296: pre-switch fresh integrity and privilege review (source scope)

Verified defect 1: ProviderExitService::switch trusted its earlier shadow verification after target/source bytes could change. The method could remap an earlier object before discovering a later corrupted target. R296 now reopens and hashes **every** source and target, checks all source record mappings and stored provider envelopes, and fails before the first remap. This check does not provide an atomic write fence; a fresh mutation after preflight still requires real owner write-fencing, staged migration and live provider acceptance.

Verified defect 2: ProviderExitService copy/shadow/switch/purge mutators lacked their own user capability guard. R296 requires media_manage_providers at entry, including destructive cutover and purge; no anonymous service execution is authorized by default.

Reviewed canonical target provider identity: copy evidence now forcibly binds provider_id to the approved target instead of permitting an adapter-supplied provider_id to take precedence.

Test: tests/review-round-296-provider-switch-preflight.php. Negative cases include missing source, corrupt later target, forged target provider identity and denied permissions, with no remap before the complete preflight passes. This is source CI evidence only. No production cutover, source purge, staging or real-provider acceptance occurred.
