# Security Policy

## Supported status

CF-04 is currently a **conditional, pre-runtime repository**. No production deployment or security certification is claimed.

## Public-repository prohibition

Never commit:

- storage, CDN, scanner or transcoder credentials;
- stream keys, signing keys, encryption keys or recovery material;
- raw bucket names/paths where disclosure increases risk;
- signed URLs, cookies, bearer tokens, webhooks secrets, OTPs or session data;
- unredacted patient, identity, message or restricted-media data;
- private incident playbooks, exploit details or provider account identifiers.

Use protected environment configuration or an approved private secret manager after activation. Repository examples must use placeholders only.

## Security invariants

- Quarantine is private and deny-by-default.
- Unscanned, invalid, malicious or unauthorized assets are never deliverable.
- Browser-supplied MIME type and file extension are untrusted.
- Workers run sandboxed, non-root, network-denied by default and with bounded CPU, memory, time, recursion, dimensions and output.
- Delivery grants are short-lived, scope-limited and revalidated against current domain state.
- Private sources are not publicly cached.
- Logs exclude content, secrets and reusable delivery tokens.
- Deletion is staged and cannot be reported complete before provider/CDN evidence is recorded.
- Automated technical classification is a signal only; it cannot publish, prescribe or make final editorial/medical/Sharīʿah decisions.

## Reporting a vulnerability

Do not publish exploitable details in a public GitHub issue. Report privately to the Founder or the designated security operator, including:

1. affected component and version;
2. reproduction steps using non-sensitive test data;
3. impact and prerequisites;
4. suggested containment;
5. whether credentials or private data may have been exposed.

Critical containment may occur immediately, but permanent product changes require post-incident change-control ratification.
