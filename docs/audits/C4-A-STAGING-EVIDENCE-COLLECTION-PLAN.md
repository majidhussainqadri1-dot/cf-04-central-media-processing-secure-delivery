# C4-A Staging Evidence Collection Plan

**Purpose:** collect the minimum redacted evidence needed to decide whether CF-04 extraction is justified and safe.  
**Environment:** controlled staging only.  
**Runtime effect:** read-only inventory and bounded test uploads; no migration, ownership transfer or production activation.  
**Public-repository rule:** only aggregate, redacted results may be committed.

## 1. Preconditions

Before collection begins:

- record the staging URL as a private operational alias, not a raw URL in this repository;
- record installed plugin/package versions and exact source commits;
- take and verify a full database and filesystem backup;
- confirm a restoration destination and responsible operator;
- use synthetic media only for new tests;
- assign one evidence identifier to the collection run;
- disable any destructive cleanup or migration command;
- preserve File 10, 11, 12, 17 and 21 native ownership throughout the run.

## 2. Prohibited collection

Do not export or commit:

- source media bytes;
- filenames containing personal data;
- raw storage paths or bucket names;
- keys, tokens, signed URLs, nonces, webhook secrets or provider credentials;
- message bodies, clinical records, identity evidence or private attachment metadata;
- user names, email addresses, phone numbers, IP addresses or exact user IDs;
- unredacted database rows;
- screenshots exposing protected content.

Use stable run-scoped aliases and aggregate counts.

## 3. Environment identity record

Collect privately, then commit only the approved redacted form:

| Field | Required result |
|---|---|
| WordPress version | Exact version |
| PHP version | Exact version |
| Database engine/version | Exact version |
| File 10 package/ref | Exact candidate identity |
| File 11 package/ref | Exact candidate identity |
| File 12 package/ref | Exact candidate identity |
| File 17 package/ref | Exact canonical identity |
| File 21 package/ref | Exact canonical identity |
| File 22 package/ref | Exact canonical identity |
| Active theme and File 20 | Version aliases only |
| Cache/CDN/object providers | Approved aliases only |
| Backup evidence | Evidence ID and pass/fail |

Any mismatch between installed package and audited source ref invalidates that domain's inventory result.

## 4. WordPress Media aggregate inventory

Applies to File 10 local videos/thumbnails, File 11 shared video assets, File 12 covers and File 21 uploads.

Collect aggregates by domain attribution and MIME class:

- attachment count;
- original-byte total;
- generated-derivative count and byte total;
- parented versus unparented count;
- attachment author present/missing count;
- public, draft-linked, pending-linked, rejected-linked, private-linked and deleted-parent counts;
- missing `_wp_attached_file` count;
- attachment rows whose resolved file is missing;
- files present without a matching attachment row;
- duplicate SHA-256 groups and total duplicate bytes;
- unsafe or non-canonical relative-path count;
- oldest/newest object dates;
- direct raw URL availability by state.

### Domain-attribution law

Attribution must be derived from the canonical parent object, owner metadata and accepted adapter rules. File 11 references the File 10 video asset; it must not be counted as a second source binary.

## 5. File 10 and File 11 test matrix

Use synthetic samples within approved size and duration bounds.

| Test | Expected result |
|---|---|
| Valid local MP4/WebM/Ogg upload | Native File 10 attachment created and linked once |
| Invalid extension/MIME pair | Rejected without orphan attachment |
| Failed thumbnail after local video upload | Transaction cleans all newly created attachments |
| Remote YouTube/Vimeo reference | No local source bytes silently created |
| Reel created from File 10 local video | Same source attachment ID retained; no copied binary |
| 59-second and 601-second Reel | Rejected under File 11 duration law |
| Deleted/rejected video | Native visibility and raw-file behavior recorded |
| Anonymous raw source URL | Result recorded by publication state and cache state |
| Cache/CDN purge after restriction | Old public access removed within measured interval |

## 6. File 12 encrypted PDF inventory

Run only with the designated File 12 security operator.

Collect redacted aggregates:

- encrypted-object count and total ciphertext bytes;
- catalog rows with object present/missing;
- objects without catalog rows;
- key-ID distribution using aliases only;
- objects using unknown/retired key IDs;
- integrity-verification pass/fail count;
- cover attachment present/missing count;
- reader and download authorization outcomes by public/private policy;
- backup inclusion count;
- sampled restore and authenticated-decryption result;
- atomic temporary-file residue count;
- failed or incomplete encryption records.

### Mandatory cryptographic boundary

Never move or re-encrypt a File 12 source during this evidence phase. A future migration requires a separately approved key inventory, backup recovery rehearsal, source/ciphertext hash plan, dual-read design and rollback ceremony.

## 7. File 17 private attachment inventory

Collect aggregates from the File 17-owned attachment registry and private storage:

- active, deleted and deletion-retry record counts;
- count and bytes by image/video/audio/document class;
- scan-state distribution;
- scanner-unavailable and rejected counts;
- rows with missing bytes;
- bytes without active rows;
- duplicate SHA-256 groups;
- owner or conversation-linkage failure counts;
- unsafe-path rejection count;
- failed-delete backlog and oldest retry age;
- authorization outcomes for participant, non-participant, blocked participant and logged-out user;
- privacy export/erasure sample result;
- backup and restore sample result.

Do not assume extraction is beneficial merely because CF-04 exists. Compare the existing File 17 service against the proposed shared service using measurable reliability, support burden and cost.

## 8. File 21 media inventory and raw-delivery test

Collect aggregate counts by media class and publication state. For synthetic objects, test:

- public post attachment page;
- direct original-file URL;
- generated image derivative URL;
- REST attachment response;
- draft, pending-review, rejected, private and deleted-parent states;
- anonymous, authenticated unauthorized and authorized views;
- browser cache, LiteSpeed cache and any CDN behavior;
- state change from public to restricted;
- purge propagation time;
- failed composer transaction cleanup;
- pending-marker cleanup after successful association.

A raw URL test result is evidence for the installed staging stack only. It must not be generalized to production without a matching environment and cache/CDN configuration.

## 9. File 22 no-copy verification

Verify that File 22 contains only orchestration metadata:

- no media body or file path in session records;
- no copied post/PDF/video body;
- native owner reference present where needed;
- idempotency and reconciliation data remain bounded and one-way where specified;
- retry/reconciliation does not create duplicate native attachments;
- failure between native upload and acknowledgement is observable and repairable without blind resubmission.

## 10. Cost and capacity evidence

Collect monthly aggregates for the current stack and one conservative CF-04 scenario:

- stored source bytes;
- derivative bytes;
- monthly upload bytes;
- monthly delivery/egress bytes;
- processing minutes or jobs;
- scanner invocations;
- failed and retried jobs;
- support incidents related to uploads, playback, reading or attachments;
- backup storage and restore duration;
- provider base charges and usage charges;
- projected twelve-month growth.

Record assumptions and confidence. Do not treat estimated savings as measured savings.

## 11. Reliability evidence

For each storage family, measure:

- upload success rate;
- processing success rate;
- median and high-percentile completion time;
- delivery error rate;
- stale-access or purge failures;
- missing-object incidents;
- failed deletion rate;
- restore success rate and duration;
- provider outage behavior;
- queue or worker saturation where applicable.

A central service is justified only when it demonstrably improves safety, reliability, cost or operability without weakening domain authorization.

## 12. Reconciliation result schema

Each aggregate result must contain:

- evidence ID;
- collection timestamp and timezone;
- environment alias;
- domain and storage family;
- exact installed source identity;
- query/check version;
- expected and actual result;
- aggregate count or pass/fail;
- reviewer;
- defect ID where applicable;
- correction/retest status;
- private evidence location;
- public-safe summary location.

## 13. Acceptance decision

After collection, classify each proposed domain integration:

- **No extraction:** native system remains safer or extraction lacks justification;
- **Adapter only:** CF-04 receives technical processing/delivery calls but native bytes remain temporarily owned by the domain;
- **Shadow migration candidate:** copied and hash-verified in non-authoritative mode;
- **Ownership-transfer candidate:** all security, migration, rollback and operational gates satisfied;
- **Blocked:** unresolved Critical/High defect, missing evidence or unaccepted risk.

No classification authorizes production activation by itself. Founder-approved change control remains mandatory.

## 14. Review doctrine

This evidence plan and every resulting script/report require:

1. first review for scope, accuracy, privacy, security, ownership and rollback;
2. correction and retest;
3. fresh adversarial review for leakage, false counts, stale state, authorization bypass, duplicate attribution and destructive behavior;
4. correction and retest;
5. final evidence indexing.
