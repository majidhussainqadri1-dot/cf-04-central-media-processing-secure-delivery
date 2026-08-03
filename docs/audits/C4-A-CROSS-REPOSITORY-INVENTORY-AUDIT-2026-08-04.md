# C4-A Cross-Repository Media Inventory Audit

**Date:** 2026-08-04  
**Scope:** source and repository evidence for Files 10, 11, 12, 17, 21 and 22  
**Status:** Initial forensic source inventory complete; staging/data/provider inventory remains pending  
**CF-04 runtime authorization:** Not granted

## 1. Governing boundary

This audit does not activate CF-04, move bytes, create a provider integration or authorize production use. It identifies the current source-side binary ownership, storage mechanisms, upload paths, delivery paths, deletion behavior and repository-state blockers required by CF04-A-003.

Repository prose and source code can establish architecture and candidate behavior. They cannot establish the actual Hostinger inventory, real media volume, current raw URLs, orphan count, provider cost, deletion drift, backup contents or user consent state. Those facts require controlled staging and database/filesystem evidence.

## 2. Repository reference snapshot

| Domain | Repository | Reference inspected | Repository state relevant to CF-04 |
|---|---|---|---|
| File 10 — Video Wall | `10-video-wall-and-educational-broadcasting-foundation` | Draft PR #2 head `40a278da9d28a20ec89377c939f1fffca8d6b3fc` | Corrective 0.2.0 candidate is open and unmerged; `main` is not the corrective runtime source |
| File 11 — Reels | `11-reels-and-short-video-discovery-foundation` | Draft PR #2 head `601117b3f3be8dc582dbe823bf9f1c8968a2e6b9` | Corrective 0.2.0 candidate is open, unmerged and based on the baseline branch |
| File 12 — PDF Library | `12-pdf-library-and-digital-reading-foundation` | Draft PR #2 head `1ddc48e0271c896f32dbaa6d53a41711c22031ed` | Corrective 0.2.0 candidate is open, unmerged and based on the baseline branch |
| File 17 — Network and Messages | `17-sabri-network` | Canonical `main` after merged 2.0.0 remediation | Main contains the reviewed private-attachment implementation; PR #5 is a separate CF-01 context candidate |
| File 21 — Home and News Feed | `sabri-complete-home-news-feed` | Canonical `main` after merged package/public corrections | Main contains the native media handler and publication ownership |
| File 22 — Universal Composer | `sabri-universal-post-composer` | Canonical `main` plus Draft PR #24 head `3cfa75e5ded61d5c60ade97b2e26d72395167e31` | Main contains metadata-only orchestration; R8 diagnostic candidate remains open and unmerged |

### Audit consequence

A single `main`-branch-only inventory would be false. Files 10, 11 and 12 currently keep their materially corrected candidate source in open draft branches. Any later extraction decision must first designate an exact accepted source reference for every domain.

## 3. File 10 — Video Wall binary inventory

### 3.1 Canonical domain truth

File 10 owns video entities, channels/playlists where implemented, publication state, player/replay behavior, duration/category metadata and moderation. CF-04 must not assume ownership of those facts.

### 3.2 Current source model

The corrective candidate supports three media-source modes:

- remote YouTube URL;
- remote Vimeo URL;
- local video upload.

Local videos and thumbnails are created through WordPress Media APIs. The source uses `media_handle_upload()` for `local_video` and `thumbnail`, records the local video attachment ID in video metadata, assigns the thumbnail through `set_post_thumbnail()`, and reparents uploaded attachments to the final video post.

Observed local-video MIME allowlist:

- `video/mp4`;
- `video/webm`;
- `video/ogg`.

Observed thumbnail MIME allowlist:

- JPEG;
- PNG;
- WebP.

Failed transactions invoke permanent WordPress attachment deletion for the attachments created by that submission.

### 3.3 Storage and delivery classification

| Item | Current mechanism | CF-04 inventory classification |
|---|---|---|
| Local source video | WordPress attachment and uploads filesystem | Candidate source binary requiring attachment ID, file path, URL, hash, size and parent inventory |
| Thumbnail | WordPress image attachment and generated derivatives | Source/derivative family requiring metadata and generated-size lineage inventory |
| YouTube/Vimeo | Remote provider URL and provider-specific embed | External mapping, not a CF-04-owned object unless separately cached or imported |
| Publication metadata | File 10 post and post metadata | Remains File 10 truth; pointer only in CF-04 |

### 3.4 Confirmed blockers

- Corrective source is not merged to `main`.
- No repository evidence establishes actual Hostinger attachment IDs, paths, sizes, duplicate files, remote-provider validity or volume.
- Existing WordPress generated derivatives and local video bytes require a database/filesystem reconciliation before any ownership transfer.

## 4. File 11 — Reels binary inventory

### 4.1 Canonical relationship to File 10

The corrective source uses File 10's video post type, helper API, interaction service and media metadata. A Reel is marked on a File 10 video object rather than stored as an independent binary domain.

### 4.2 Current source model

The source accepts YouTube, Vimeo or local video. For local video it uses `media_handle_upload()` and the same MP4/WebM/Ogg family. The cover image is also uploaded through WordPress Media. Local playback resolves the File 10 attachment ID through `wp_get_attachment_url()`.

The Reel-specific layer adds duration enforcement, feed/history/progress behavior and `_svw_is_reel` classification. It does not justify a second copy of the source video.

### 4.3 CF-04 classification

| Item | Owner | Extraction rule |
|---|---|---|
| Source local video and thumbnail | File 10 binary record today | Inventory once under the File 10 asset; attach a Reel-purpose/domain reference without duplicating bytes |
| Reel status, feed/ranking, watch history and progress | File 11 | Never move into CF-04 |
| Remote provider URL | File 10/File 11 domain metadata according to accepted contract | Store only approved external mapping if CF-04 later needs it |

### 4.4 Confirmed blockers

- File 11 corrective candidate depends on an exact accepted File 10 implementation, while both corrective candidates remain open and unmerged.
- The current shared use of File 10 metadata must be formally frozen before a central asset identifier can be introduced.

## 5. File 12 — PDF Library binary inventory

### 5.1 Split storage model

File 12 already separates the document source from its public cover:

- PDF source bytes are encrypted and stored in a private filesystem directory outside the public web root;
- cover images remain WordPress Media attachments;
- document catalog, rights, reader, progress, notes and publication status remain File 12 truth.

### 5.2 Private source storage

The corrective candidate resolves the PDF storage directory from `SPL_PDF_STORAGE_DIR` or defaults to a sibling private directory named `sabri-private/pdf-library`. It rejects unsafe public-root placement, creates deny files, writes encrypted output to a temporary file, and atomically renames the completed encrypted object into place.

The document is encrypted in authenticated chunks and streamed through a controlled `admin-post.php` action rather than exposed as a raw public PDF URL. Stored metadata includes an opaque storage name, original name and cryptographic metadata needed for integrity-checked streaming.

### 5.3 CF-04 classification

| Item | Current mechanism | Migration sensitivity |
|---|---|---|
| Encrypted PDF source | File 12 private filesystem + key ring | Highest: requires key-ID mapping, exact ciphertext/plaintext integrity proof, backup/key recovery and rollback proof |
| Cover image | WordPress attachment | Lower: ordinary image source/derivative inventory, while preserving File 12 association |
| Reader/download authorization | File 12 stream action and rights policy | Must remain File 12 decision; CF-04 may only issue a bounded delivery grant after fresh File 12 authorization |
| Bibliographic/rights/progress/notes | File 12 | Never migrate as CF-04 truth |

### 5.4 Confirmed blockers

- Corrective candidate remains open and unmerged.
- Key material, key backups and real storage paths are intentionally absent from the public repository.
- Actual encrypted object count, orphan count, missing-cover count, key-ID distribution and backup consistency require private operational evidence.

## 6. File 17 — Network and Messages attachment inventory

### 6.1 Existing private-storage subsystem

File 17 already implements a substantial private attachment service:

- default storage outside the web root at a sibling directory named `sabri-network-private`;
- configurable storage through the `sn_network_private_storage_dir` filter;
- year/month plus UUID storage keys;
- File 17-owned attachment table containing owner, storage key, original name, MIME, size, SHA-256, scan status and lifecycle fields;
- MIME/extension checks, image normalization, PDF signature validation and an external scanner hook;
- authenticated, conversation-aware delivery through an expiring WordPress nonce URL;
- authorization revocation before byte deletion;
- failed-byte-deletion audit and bounded retry scheduling.

### 6.2 Current allowed classes

The source distinguishes image and document attachments. Documents require an approved scanner result; images are normalized and dimension-bounded before hashing and storage.

### 6.3 CF-04 classification

File 17 is not merely a consumer of public Media Library objects. It is already an owner of private attachment records and private byte lifecycle. Any centralization would therefore be an ownership-transfer migration, not a simple adapter addition.

CF-04 must preserve:

- File 17 conversation and membership authorization;
- current attachment IDs or an explicit stable mapping;
- scan-state semantics;
- owner and conversation relations;
- revoke-before-delete ordering;
- deletion retry evidence;
- privacy export/erasure behavior;
- no-copy forwarding boundary.

### 6.4 Confirmed blockers

- No measured evidence yet proves that replacing File 17's mature private storage with CF-04 is safer, cheaper or more reliable.
- Production storage path, object count, size distribution, scanner health and failed-delete backlog are not public-repository facts.
- Until economic and operational justification exists, a future CF-04 adapter may be safer than immediate byte ownership transfer.

## 7. File 21 — Home and News Feed media inventory

### 7.1 Current upload mechanism

File 21's `MediaHandler` uses standard WordPress upload and attachment APIs:

- `wp_handle_upload()` writes the file;
- `wp_insert_attachment()` creates an attachment object;
- generated metadata/derivatives are created through WordPress image metadata APIs;
- a pending-composer marker is added until the attachment is associated with the final post;
- the attachment is reparented to the File 21 post after successful publication;
- failed mixed upload batches are cleaned up.

Configured source classes include images, PDF, video and audio. The handler performs extension/MIME verification and maintains attachment-page and REST visibility checks.

### 7.2 CF-04 classification

| Item | Current mechanism | Required inventory |
|---|---|---|
| Images and generated sizes | WordPress Media | attachment IDs, original file, metadata sizes, parent, author, public state, hash, URL |
| PDF/video/audio uploads | WordPress Media | raw path/URL, MIME, size, parent, visibility, direct-URL behavior and retention state |
| Media captions/alt text | attachment metadata | domain metadata remains File 21/WordPress; CF-04 receives bounded technical fields only |
| Post/publication decision | File 21 | Never transferred to CF-04 |

### 7.3 High-priority verification concern

Standard WordPress uploads normally create filesystem URLs. File 21 protects attachment pages and REST responses, but source review alone cannot prove that a restricted, draft or rejected attachment's raw file URL is inaccessible at the web-server/CDN layer. This is a staging test requirement, not a concluded production vulnerability.

### 7.4 Confirmed blockers

- Exact production attachment inventory and direct-URL access behavior remain unknown.
- File 21 currently accepts media classes also owned by File 10 and File 12; future policy must prevent duplicate technical pipelines and direct binary copies.

## 8. File 22 — Universal Composer inventory

File 22 is an orchestration owner, not a binary owner. Its current architecture keeps durable sessions, submission identity and reconciliation outbox as metadata-only records. Native upload, Video and PDF workflows remain on their canonical owner routes.

CF-04 must not inventory File 22 session/outbox rows as media objects and must not create a File 22 media store. The relevant inventory fields are limited to:

- adapter key and version;
- native owner reference;
- upload-purpose declaration where a native owner exposes it;
- idempotency/correlation reference;
- reconciliation state;
- no-copy proof.

Draft PR #24 updates diagnostics and package identity but does not transfer media ownership.

## 9. Cross-domain findings

### Finding C4A-F-001 — Candidate-reference fragmentation

Files 10, 11 and 12 do not currently expose their corrected implementation on canonical `main`. A source freeze must precede any schema or migration freeze.

**Severity:** High for planning accuracy.  
**Required correction:** Founder/domain-owner acceptance of exact repository refs or merge decisions.

### Finding C4A-F-002 — Three incompatible current storage families

The platform currently contains at least three materially different binary storage families:

1. WordPress Media Library and public uploads paths — Files 10/11/21 and File 12 covers;
2. private encrypted filesystem objects — File 12 PDFs;
3. private hashed attachment objects with a dedicated table — File 17.

**Severity:** High.  
**Required correction:** separate migration adapters and rollback plans; no single generic copy script.

### Finding C4A-F-003 — File 11 is not an independent source-byte owner

File 11 reuses File 10 video records and attachment IDs.

**Severity:** High if overlooked, because duplicate migration would create two sources of truth.  
**Required correction:** one File 10 asset with a File 11 purpose/reference projection.

### Finding C4A-F-004 — Raw WordPress upload delivery must be tested

Repository source cannot prove private/restricted raw file URL behavior for WordPress Media objects.

**Severity:** High pending staging verification.  
**Required correction:** authenticated/anonymous raw-URL, cache, CDN and post-state test matrix.

### Finding C4A-F-005 — File 17 extraction lacks justification evidence

File 17 already has private storage, hashing, scanning, authorization and deletion compensation.

**Severity:** Medium planning blocker.  
**Required correction:** volume, reliability, operational burden and cost evidence before ownership transfer.

### Finding C4A-F-006 — File 12 key migration is a separate security ceremony

A PDF move without exact key-ID, authenticated-chunk, backup and recovery proof can make records permanently unreadable.

**Severity:** Critical for any future migration.  
**Required correction:** private key inventory, recovery rehearsal and cryptographic migration specification outside the public repository.

## 10. Gate status after this audit

| Activation gate | Status | Evidence |
|---|---|---|
| Source repositories identified | Complete | Exact repositories and candidate refs recorded |
| Source storage mechanisms classified | Complete for inspected candidate source | Sections 3–8 |
| Canonical accepted refs frozen | Blocked | Files 10/11/12 candidates remain unmerged |
| Production database/filesystem inventory | Pending | Requires controlled environment access |
| Orphan/duplicate/missing/corrupt reconciliation | Pending | Requires real data and hashes |
| Volume/reliability/cost justification | Pending | Not present in source repositories |
| Provider and CDN inventory | Pending | Private operational evidence required |
| Retention/consent/rights reconciliation | Pending | Requires owner data and approved privacy review |
| CF-04 runtime development authorization | Not granted | Founder change-control record absent |

## 11. Required next evidence package

For each affected staging installation, collect a redacted aggregate export containing:

- plugin/package version and exact source commit;
- database attachment/object counts by domain and media class;
- bytes and object counts by storage family;
- MIME, extension, size and age distributions;
- parent/domain-object linkage counts;
- missing bytes, missing rows, orphan bytes, duplicate hashes and invalid paths;
- public/private/restricted state counts;
- direct raw URL access results for anonymous and unauthorized users;
- scanner status distribution;
- deletion-pending and failed-deletion counts;
- backup inclusion and restore sample results;
- provider/CDN identifiers represented only by approved aliases;
- monthly storage, egress and processing cost aggregates.

No user content, raw private path, token, key, signed URL, phone, identity evidence, message body, clinical data or secret may enter this public repository.

## 12. Truthful conclusion

CF04-A-003 has progressed from an empty template to a source-grounded cross-repository architecture inventory. The audit confirms that a shared media pipeline cannot safely begin as one generic WordPress upload abstraction. File 10/11, File 12, File 17 and File 21 require distinct source adapters, migration proofs and rollback strategies, while File 22 remains orchestration-only.

The next blocking step is controlled real-environment evidence collection and exact source-reference freeze. Runtime ingest, transcoding, storage or delivery coding remains unauthorized.