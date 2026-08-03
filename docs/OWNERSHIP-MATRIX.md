# CF-04 Ownership Matrix

## Domain boundaries

| Concern | Canonical owner | CF-04 relationship |
|---|---|---|
| Membership, roles, capabilities, guardian, entitlement | File 00 | Consume versioned assertions; never infer authorization from availability |
| Global routes and shell | File 20 | Register semantic surfaces; do not create another shell/navigation |
| Security/privacy/compliance assurance | File 24 | Publish manifests and evidence; retain native enforcement |
| Public visual components | File 25 | Supply safe state and actions; do not create a parallel design system |
| Video, channel, playlist, live event, player, replay | File 10 | Process and deliver binaries after activation; domain truth remains File 10 |
| Reel entity, feed/ranking, watch history | File 11 | Process Reel binaries; no feed or watch-history ownership |
| PDF catalog, rights, reader, progress, notes | File 12 | Validate/process/deliver documents; bibliographic and reader truth remains File 12 |
| Messages and conversations | File 17 | Process approved general attachments; no message-body or conversation ownership |
| Social/news publication and moderation | File 21 | Provide binary services; publication state and moderation remain File 21 |
| Composer upload orchestration | File 22 | Provide upload-session contract; Composer does not write CF-04 storage directly |
| Clinical attachments | CF-01, if activated | Process only under clinical C5 purpose/access contract |

## Command ownership

| Command family | Owner after activation | Required authorization context |
|---|---|---|
| Create upload session | CF-04 | actor/service, domain, purpose, object relation, policy version, quota |
| Complete multipart upload | CF-04 | session ownership, expected hash/size/type, idempotency |
| Approve publication | Domain owner | CF-04 never approves publication |
| Start processing job | CF-04 under domain policy | asset state, required derivative graph, resource class |
| Issue delivery grant | CF-04 | current domain visibility, actor/audience, operation, expiry, record version |
| Revoke delivery | Domain command + CF-04 execution | domain reason, asset/version, purge/deletion scope |
| Place legal/retention hold | Authorized domain/privacy authority | explicit scope, reason, authority, review date |
| Delete binary | CF-04 after domain authorization | hold check, relationship check, staged provider/CDN purge |
| Correct metadata/rights | Domain owner | CF-04 consumes updated policy and invalidates affected derivatives/grants |

## Data classification rule

CF-04 inherits the strictest applicable domain classification and may increase technical protection, but may never reduce classification. Hash-based deduplication must not reveal the existence of a restricted object across domains.

## Direct-write prohibition

No companion module may write CF-04 tables, object metadata, provider mappings, delivery grants or job states directly. CF-04 likewise may not mutate another domain’s content, publication, message, clinical or entitlement records directly.
