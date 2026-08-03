# CF-04 — Central Media Processing and Secure Delivery

Conditional future shared-infrastructure module for the **Sabri Social Homeopathy Platform**.

## Current status

| Status | Value |
|---|---|
| Planning | Complete — four-round reviewed and corrected specification |
| Repository | Initialized |
| Current phase | C4-A — activation evidence, ownership charter, inventory and policy envelope |
| Runtime coding | Not authorized yet |
| Staging / live | Not started |
| Production claim | None |

The governing plan explicitly separates **planning completion** from Coded, Packaged, Automated-QA Green, Staging-Accepted, Live-Deployed and Operational status. This repository must never describe one status as another.

## Canonical purpose

After the activation gates are satisfied, CF-04 will own shared media infrastructure for:

- purpose-bound upload sessions;
- quarantine and deny-by-default ingest;
- MIME, magic-byte, hash, archive, polyglot and decompression-bomb validation;
- malware and technical safety scanning;
- sandboxed image, audio, video and document processing;
- derivatives, manifests and immutable lineage;
- encrypted object storage and provider abstraction;
- signed or expiring delivery grants, range delivery and CDN purge;
- quotas, queue priorities, retries, dead-letter handling and cost attribution;
- retention, legal holds, revocation, deletion and provider-exit evidence.

## Ownership boundaries

CF-04 will own **binary asset infrastructure**, not the domain truth represented by that binary.

- **File 10** remains owner of videos, channels, playlists, live events, player/replay truth and live moderation.
- **File 11** remains owner of Reel entities, feed/ranking and watch history.
- **File 12** remains owner of PDF catalog, rights, reader, progress and notes.
- **Files 21/22 and domain safety owners** retain publication, editorial and moderation decisions.
- **File 17** retains general conversation/message truth.
- **CF-01**, if separately activated, retains clinical attachment purpose, access and record relationship.
- **File 24** provides security/privacy/compliance assurance; native enforcement remains in CF-04 and each domain owner.
- **Files 20 and 25** retain shell and visual-system ownership.

CF-04 must not create a second content database, player, feed, reader, live-event system, moderation authority or public WordPress Media Library for restricted originals.

## Activation law

Runtime extraction or implementation starts only after all mandatory gates are evidenced, including:

1. Founder-approved change-control record and canonical ownership transfer charter.
2. Current File 10/11/12/17/21/22 binary inventory and reconciliation.
3. Versioned domain asset-reference and policy-envelope contracts.
4. Threat model, data-flow map, retention schedule, provider register and abuse-case review.
5. Migration, dual-read/shadow, reconciliation, cutover and rollback proof.
6. Independent sandbox, malware/polyglot/bomb, SSRF, authorization, encryption, CDN purge and provider-exit tests.
7. Representative volume, reliability and cost evidence proving that extraction is justified.
8. Founder, domain-owner and security acceptance.

Until then, this repository contains planning, evidence templates and non-runtime governance scaffolding only.

## Planned API surfaces

| Surface | Purpose |
|---|---|
| `/api/media/v1/uploads` | Create and complete purpose-bound upload sessions |
| `/api/media/v1/assets/{id}` | Owner/domain-scoped safe status and metadata |
| `/media/d/{grant}` | Short-lived authorized delivery or CDN grant |
| `/admin/media/queues` | Quarantine, processing and dead-letter health |
| `/admin/media/assets/{id}` | Authorized integrity, repair and deletion inspection |
| `/admin/media/providers` | Provider health, cost, configuration references and exit readiness |
| `/api/media/v1/webhooks/{provider}` | Signed, replay-safe provider callbacks |

These routes are specifications, not live endpoints.

## Delivery roadmap

- **C4-A:** evidence, ownership charter, inventory and policy envelope.
- **C4-B:** low-risk image ingest, quarantine, validation and scanner foundation.
- **C4-C:** processing job graph and derivative lineage.
- **C4-D:** encrypted storage, grants, CDN, revocation and deletion.
- **C4-E:** shadow-mode adapters for Files 10/11/12/17/21/22.
- **C4-F:** queue, provider, cost, repair and operational controls.
- **C4-G:** migration, cutover, rollback and provider exit.
- **C4-H:** penetration, restore, accessibility and staging acceptance.

## Repository rules

- No secrets, credentials, stream keys, raw bucket paths or private incident runbooks in this public repository.
- No runtime activation by feature detection alone.
- No direct writes into another module’s tables or metadata.
- Every sensitive action must revalidate actor, purpose, object, field, relationship, consent, suspension, guardian/age, entitlement and current record version server-side.
- Every coding batch requires review → correction → fresh adversarial review → correction → retest.
- Known unresolved Critical or High defects block release.

## License

No open-source license has yet been approved. All rights are reserved unless the Founder adopts a separate license through change control.
