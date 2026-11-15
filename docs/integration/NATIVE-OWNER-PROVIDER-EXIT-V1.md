# CF-04 v1: native owner provider-exit approval-only interface

Scope: cross-module integration contract for File 17, File 12 and other canonical media owners. This is a read-only consent check, NOT a write fence, permission for provider switching, key retirement, or deletion.

## Registration
Register an authorize_provider_exit callback in DomainRegistry with a current contract version. The callback must be a trusted, authorized, side-effect-free native owner operation. The absence of the callback, a denied approval, or stale object versions fail closed.

## Request
The request schema in contracts/native-owner-provider-exit-v1.schema.json contains plan_id, asset_id, record_type, record_id, object_version, record_cas, both provider IDs, SHA-256 of each private object key, and phase=read_only_preflight. No raw storage keys, patient content, clinical media bytes, passwords or provider secrets may leave CF-04.

## Response
The owner MUST acknowledge allowed=true and approved_for_provider_exit=true and exactly echo asset_id, record_id, source_provider, target_provider, object_version, record_cas. An affirmative decision for another object or migration target is invalid.

## Boundaries
CF-04 revalidates source and target bytes and the immutable version/CAS mapping before and after owner callbacks. Bounded persistent owner approvals may check 250 references per stage invocation; the final decision chain is rechecked. Even a positive result means owner_approved_unfenced with native_owner_write_fenced=false and authorization_to_switch=false.

Real owner plugins, durable multi-owner write fencing, lease renewal, atomic 2,000+ cutover, rollback, provider credentials retirement, Hostinger staging, and live deployment require separate independently accepted code and evidence. This contract does not prove them.

## Mandatory caller identity (R321)

Every owner preflight supplies a positive actor_id and service_id=cf04, obtained from the currently authenticated privileged WordPress operator. The owner must independently verify its permissions and must not infer authority merely from this field. The caller's identity is not a write fence or production migration authorization.
