#!/usr/bin/env python3
from __future__ import annotations
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]


def read(rel:str)->str:
    return (ROOT/rel).read_text()

def write(rel:str,text:str)->None:
    p=ROOT/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(text)

def replace(rel:str,old:str,new:str,expected:int|None=1)->None:
    text=read(rel);count=text.count(old)
    if expected is not None and count!=expected:
        raise SystemExit(f'{rel}: expected {expected} occurrences, found {count}: {old[:120]!r}')
    if count<1:
        raise SystemExit(f'{rel}: marker not found: {old[:120]!r}')
    write(rel,text.replace(old,new))

def replace_all(rel:str,old:str,new:str,min_count:int=1)->None:
    text=read(rel);count=text.count(old)
    if count<min_count: raise SystemExit(f'{rel}: expected >= {min_count}, found {count}: {old!r}')
    write(rel,text.replace(old,new))

# ---------------------------------------------------------------------------
# Release identity: new-plan source candidate. External acceptance remains open.
# ---------------------------------------------------------------------------
for rel in [
    'sabri-central-media/sabri-central-media.php','sabri-central-media/readme.txt',
    'tools/build-package.sh','tools/quality-check.sh','.github/workflows/runtime-ci.yml',
    'docs/runtime/STATUS.md','README.md',
]:
    if (ROOT/rel).exists():
        t=read(rel).replace('1.2.0-rc.2','1.3.0-rc.1').replace('1.4.0','1.5.0')
        write(rel,t)
for p in (ROOT/'tests').glob('*'):
    if p.is_file() and p.suffix in {'.php','.py'}:
        t=p.read_text().replace('1.2.0-rc.2','1.3.0-rc.1').replace('1.4.0','1.5.0')
        p.write_text(t)

# ---------------------------------------------------------------------------
# Load the current-plan parity runtime services.
# ---------------------------------------------------------------------------
replace(
    'sabri-central-media/sabri-central-media.php',
    "'class-scm-lifecycle.php','class-scm-operations.php','class-scm-rest.php'",
    "'class-scm-lifecycle.php','class-scm-operations.php','class-scm-plan-parity.php','class-scm-rest.php'"
)
replace(
    'tests/bootstrap.php',
    "'class-scm-lifecycle.php','class-scm-operations.php','class-scm-rest.php'",
    "'class-scm-lifecycle.php','class-scm-operations.php','class-scm-plan-parity.php','class-scm-rest.php'"
)

# Native-domain approval hook for caption/transcript/alt-text provenance.
replace_all('tests/bootstrap.php',"'authorize_hold'=>$ownerDecision,","'authorize_hold'=>$ownerDecision,'authorize_accessibility_metadata'=>$ownerDecision,",2)

# Test policy helper follows current C0 public / C1 account constitution and
# carries purpose-lawful-basis-revocation metadata before ingest.
replace(
    'tests/bootstrap.php',
    "'owner_domain'=>'file17','purpose'=>'verified-user-transfer','privacy_class'=>$privacy",
    "'owner_domain'=>'file17','purpose'=>'verified-user-transfer','lawful_basis'=>'contract','revocation_hook'=>'owner-contract','privacy_class'=>$privacy"
)
replace_all('tests/bootstrap.php',"$privacy==='C1'","$privacy==='C0'",2)
replace(
    'tests/bootstrap.php',
    "'derivative_set'=>['preview','thumbnail','text','ocr','hls','dash','poster','waveform']",
    "'derivative_set'=>['preview','thumbnail','text','ocr','hls','dash','poster','waveform','video-low','audio-low','caption-ref','transcript-ref']"
)
replace(
    'tests/bootstrap.php',
    "elseif($op==='audio-pipeline'){$s=scm_clone_stream($source);$output=['probe'=>['supported'=>true,'duration_seconds'=>10],'outputs'=>[['kind'=>'audio-aac','stream'=>$s,'sha256'=>hash('sha256',stream_get_contents($s, -1, 0)),'bitrate'=>128000,'preset_version'=>'1'],['kind'=>'waveform','stream'=>scm_clone_stream($source),'sha256'=>hash('sha256',stream_get_contents(scm_clone_stream($source),-1,0)),'preset_version'=>'1']]];rewind($s);foreach($output['outputs'] as &$o){$stats=Utils::streamHash($o['stream']);$o['sha256']=$stats['sha256'];}unset($o);}",
    "elseif($op==='audio-pipeline'){$s=scm_clone_stream($source);$output=['probe'=>['supported'=>true,'duration_seconds'=>10],'outputs'=>[['kind'=>'audio-aac','stream'=>$s,'sha256'=>'','bitrate'=>128000,'preset_version'=>'1'],['kind'=>'audio-low','stream'=>scm_clone_stream($source),'sha256'=>'','bitrate'=>48000,'preset_version'=>'1'],['kind'=>'waveform','stream'=>scm_clone_stream($source),'sha256'=>'','preset_version'=>'1'],['kind'=>'caption-ref','stream'=>scm_clone_stream($source),'sha256'=>'','preset_version'=>'1'],['kind'=>'transcript-ref','stream'=>scm_clone_stream($source),'sha256'=>'','preset_version'=>'1']]];foreach($output['outputs'] as &$o){$stats=Utils::streamHash($o['stream']);$o['sha256']=$stats['sha256'];}unset($o);}")
replace(
    'tests/bootstrap.php',
    "$kinds=['hls-manifest','hls-segment','dash-manifest','poster','waveform'];",
    "$kinds=['hls-manifest','hls-segment','dash-manifest','poster','waveform','video-low','audio-low','caption-ref','transcript-ref'];"
)

# ---------------------------------------------------------------------------
# Policy/data constitution: C0 public, C1 account; every upload policy carries
# lawful basis and a revocation hook. C2-C5 are never public-CDN classes.
# ---------------------------------------------------------------------------
replace('sabri-central-media/includes/class-scm-contracts.php',"public const PRIVACY=['C1','C2','C3','C4','C5'];","public const PRIVACY=['C0','C1','C2','C3','C4','C5'];")
replace(
    'sabri-central-media/includes/class-scm-contracts.php',
    "'owner_domain','purpose','privacy_class','media_class'",
    "'owner_domain','purpose','lawful_basis','revocation_hook','privacy_class','media_class'"
)
replace(
    'sabri-central-media/includes/class-scm-contracts.php',
    "'owner_domain'=>Utils::key((string)$input['owner_domain'],64),'purpose'=>Utils::key((string)$input['purpose'],96),",
    "'owner_domain'=>Utils::key((string)$input['owner_domain'],64),'purpose'=>Utils::key((string)$input['purpose'],96),'lawful_basis'=>Utils::key((string)$input['lawful_basis'],64),'revocation_hook'=>Utils::key((string)$input['revocation_hook'],96),"
)
replace(
    'sabri-central-media/includes/class-scm-contracts.php',
    "if($normalized['owner_domain']===''||$normalized['purpose']==='')throw new Error('policy_identity_invalid','Policy owner and purpose are required.',400);",
    "if($normalized['owner_domain']===''||$normalized['purpose']===''||$normalized['lawful_basis']===''||$normalized['revocation_hook']==='')throw new Error('policy_identity_invalid','Policy owner, purpose, lawful basis and revocation hook are required.',400);"
)
replace_all('sabri-central-media/includes/class-scm-contracts.php',"$normalized['privacy_class']!=='C1'","$normalized['privacy_class']!=='C0'",1)
replace('sabri-central-media/includes/class-scm-contracts.php',"Public CDN requires privacy class C1.","Public CDN requires privacy class C0.")

# Contracts: schemas mirror the same data constitution and grant bindings.
for rel in ['contracts/media-asset-reference.schema.json','contracts/upload-policy-envelope.schema.json']:
    p=ROOT/rel;d=json.loads(p.read_text());d['properties']['privacy_class']['enum']=['C0','C1','C2','C3','C4','C5']
    if rel.endswith('upload-policy-envelope.schema.json'):
        for k in ['lawful_basis','revocation_hook']:
            if k not in d['required']: d['required'].append(k)
        d['properties']['lawful_basis']={'type':'string','minLength':1,'maxLength':64}
        d['properties']['revocation_hook']={'type':'string','minLength':1,'maxLength':96}
    p.write_text(json.dumps(d,sort_keys=True,separators=(',',':'))+'\n')
p=ROOT/'contracts/delivery-grant.schema.json';d=json.loads(p.read_text())
for k in ['purpose','privacy_class']:
    if k not in d['required']: d['required'].append(k)
d['properties']['purpose']={'type':'string','minLength':1,'maxLength':96}
d['properties']['privacy_class']={'enum':['C0','C1','C2','C3','C4','C5']}
p.write_text(json.dumps(d,sort_keys=True,separators=(',',':'))+'\n')

# ---------------------------------------------------------------------------
# Typed owner reference is recorded before processing.
# ---------------------------------------------------------------------------
replace(
    'sabri-central-media/includes/class-scm-upload.php',
    "$policy=Policy::normalize($rawPolicy,true);self::validateMetadata($metadata,$policy);$fingerprint=",
    "$policy=Policy::normalize($rawPolicy,true);self::validateMetadata($metadata,$policy);$ownerType=self::ownerType((string)$metadata['owner_object']);$fingerprint="
)
replace(
    'sabri-central-media/includes/class-scm-upload.php',
    "$ownerContext=['actor_id'=>$actor,'owner_object'=>Utils::text((string)($metadata['owner_object']??''),191),'policy'=>$policy,'metadata'=>Utils::redact($metadata)];",
    "$ownerContext=['actor_id'=>$actor,'owner_object'=>Utils::text((string)($metadata['owner_object']??''),191),'owner_type'=>$ownerType,'policy'=>$policy,'metadata'=>Utils::redact($metadata)];"
)
replace(
    'sabri-central-media/includes/class-scm-upload.php',
    "'owner_domain'=>$policy['owner_domain'],'owner_object'=>Utils::text((string)$metadata['owner_object'],191),'object_version'",
    "'owner_domain'=>$policy['owner_domain'],'owner_object'=>Utils::text((string)$metadata['owner_object'],191),'owner_type'=>$ownerType,'object_version'"
)
replace(
    'sabri-central-media/includes/class-scm-upload.php',
    "private static function validateMetadata(array $m,array $p): void",
    "private static function ownerType(string $reference): string {$reference=Utils::text($reference,191);$raw=str_contains($reference,':')?explode(':',$reference,2)[0]:'object';$type=Utils::key($raw,64);if($type==='')throw new Error('owner_type_invalid','Typed owner reference is required.',400);return $type;}\n    private static function validateMetadata(array $m,array $p): void"
)
replace(
    'sabri-central-media/includes/class-scm-upload.php',
    "'owner_domain'=>$u['owner_domain'],'owner_object'=>$u['owner_object'],'object_version'",
    "'owner_domain'=>$u['owner_domain'],'owner_object'=>$u['owner_object'],'owner_type'=>$u['owner_type'],'object_version'"
)

# ---------------------------------------------------------------------------
# Delivery grants: bind purpose/privacy. Public CDN is C0 only and cache identity
# includes privacy/policy/rights state, so stale authorization cannot reuse it.
# ---------------------------------------------------------------------------
replace_all('sabri-central-media/includes/class-scm-delivery.php',"$asset['privacy_class']!=='C1'","$asset['privacy_class']!=='C0'",2)
replace(
    'sabri-central-media/includes/class-scm-delivery.php',
    "'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'audience_hash'",
    "'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'purpose'=>$asset['policy']['purpose'],'privacy_class'=>$asset['privacy_class'],'audience_hash'",
    2
)
replace(
    'sabri-central-media/includes/class-scm-delivery.php',
    "'policy_hash','rights_hash','audience_hash'",
    "'policy_hash','rights_hash','purpose','privacy_class','audience_hash'"
)
replace(
    'sabri-central-media/includes/class-scm-delivery.php',
    "if((int)$claims['object_version']!==(int)$asset['object_version']||!hash_equals((string)$claims['rights_hash'],(string)$asset['rights']['policy_hash'])||!hash_equals((string)$claims['target_sha256'],(string)$target['sha256']))throw new Error('grant_asset_state_changed','Asset or policy version changed.',403);",
    "if((int)$claims['object_version']!==(int)$asset['object_version']||!hash_equals((string)$claims['rights_hash'],(string)$asset['rights']['policy_hash'])||!hash_equals((string)$claims['purpose'],(string)$asset['policy']['purpose'])||!hash_equals((string)$claims['privacy_class'],(string)$asset['privacy_class'])||!hash_equals((string)$claims['target_sha256'],(string)$target['sha256']))throw new Error('grant_asset_state_changed','Asset, purpose, privacy or policy version changed.',403);"
)
old="$result=CdnRegistry::adapter()->publish(['asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'object_key'=>$derivative['object_key'],'cache_key'=>$assetId.'/'.$derivativeId.'/'.$derivative['sha256'],'headers'=>"
new="$cacheKey=hash('sha256',$assetId.'|'.$derivativeId.'|'.$derivative['sha256'].'|'.$asset['privacy_class'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']).'/'.$derivative['sha256'];$result=CdnRegistry::adapter()->publish(['asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'object_key'=>$derivative['object_key'],'cache_key'=>$cacheKey,'headers'=>"
replace('sabri-central-media/includes/class-scm-delivery.php',old,new)
replace(
    'sabri-central-media/includes/class-scm-delivery.php',
    "$map=['actor_id'=>0,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'status'=>'published'",
    "$map=['actor_id'=>0,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'status'=>'published'"
)

# ---------------------------------------------------------------------------
# Low-bandwidth/audio/text alternatives. Real providers must emit required
# current-plan outputs; test sandbox does the same. Missing outputs fail closed.
# ---------------------------------------------------------------------------
replace(
    'sabri-central-media/includes/class-scm-processing.php',
    "'captions_reference'=>true,'segment_hashes'=>true,'duration_limit'",
    "'captions_reference'=>true,'transcript_reference'=>true,'low_bandwidth'=>true,'audio_only'=>true,'segment_hashes'=>true,'duration_limit'"
)
replace(
    'sabri-central-media/includes/class-scm-processing.php',
    "'waveform'=>true,'loudness'=>'EBU-R128','captions_reference'=>true,'duration_limit'",
    "'waveform'=>true,'loudness'=>'EBU-R128','captions_reference'=>true,'transcript_reference'=>true,'low_bandwidth'=>true,'duration_limit'"
)
replace(
    'sabri-central-media/includes/class-scm-processing.php',
    "'video-av1'=>'video/mp4'];",
    "'video-av1'=>'video/mp4','video-low'=>'video/mp4','audio-low'=>'audio/aac','caption-ref'=>'application/json','transcript-ref'=>'application/json'];"
)
replace(
    'sabri-central-media/includes/class-scm-processing.php',
    "if($asset['media_class']==='video')foreach(['hls-manifest','poster'] as $required)",
    "if($asset['media_class']==='video')foreach(['hls-manifest','poster','video-low','audio-low','caption-ref','transcript-ref'] as $required)"
)
replace(
    'sabri-central-media/includes/class-scm-processing.php',
    "if($ids===[])throw new Error('target_derivative_missing','Audio/video pipeline did not produce a requested derivative.',422);",
    "if($asset['media_class']==='audio')foreach(['audio-low','caption-ref','transcript-ref'] as $required)if(!in_array($required,$allKinds,true))throw new Error('audio_derivative_missing','Required audio accessibility/low-bandwidth derivative missing.',422,['kind'=>$required]);if($ids===[])throw new Error('target_derivative_missing','Audio/video pipeline did not produce a requested derivative.',422);"
)

# ---------------------------------------------------------------------------
# Revocation/deletion propagation emits a downstream invalidation event.
# ---------------------------------------------------------------------------
replace(
    'sabri-central-media/includes/class-scm-lifecycle.php',
    "Audit::record('derivatives_expired',['asset_id'=>$assetId,'reason'=>$reason,'count'=>count($deleted)]);return ['asset_id'=>$assetId,'deleted_derivatives'=>$deleted,'cdn_mappings'=>count($maps)];",
    "Audit::record('derivatives_expired',['asset_id'=>$assetId,'reason'=>$reason,'count'=>count($deleted)]);if(function_exists('do_action'))do_action('scm.media.revoked',['asset_id'=>$assetId,'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],'object_version'=>$asset['object_version'],'reason'=>$reason]);return ['asset_id'=>$assetId,'deleted_derivatives'=>$deleted,'cdn_mappings'=>count($maps)];"
)
replace(
    'sabri-central-media/includes/class-scm-lifecycle.php',
    "Audit::record('deletion_completed',['deletion_id'=>$d['id'],'asset_id'=>$asset['asset_id'],'attempts'=>$d['attempts']]);",
    "Audit::record('deletion_completed',['deletion_id'=>$d['id'],'asset_id'=>$asset['asset_id'],'attempts'=>$d['attempts']]);if(function_exists('do_action'))do_action('scm.media.revoked',['asset_id'=>$asset['asset_id'],'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],'object_version'=>$asset['object_version'],'reason'=>'deleted']);"
)

# Integration manifest explicitly exposes the new-plan parity contract.
replace(
    'sabri-central-media/includes/class-scm-plugin.php',
    "'runtime_default'=>'disabled','domain_contracts'=>DomainRegistry::manifest(),",
    "'runtime_default'=>'disabled','domain_contracts'=>DomainRegistry::manifest(),'current_plan_parity'=>PlanParityRegistry::manifest(),"
)
replace(
    'sabri-central-media/includes/class-scm-plugin.php',
    "'scm.provider.degraded','scm.budget.threshold'",
    "'scm.provider.degraded','scm.budget.threshold','scm.media.accessibility.updated','scm.media.revoked'"
)

# ---------------------------------------------------------------------------
# Source QA now treats current rewritten plans as a first-class release gate.
# ---------------------------------------------------------------------------
replace(
    'tools/quality-check.sh',
    "php \"$ROOT/tests/run-all.php\"\n",
    "php \"$ROOT/tests/run-all.php\"\nphp \"$ROOT/tests/new-plan-parity.php\"\n"
)
replace(
    'tools/quality-check.sh',
    "'source_requirements':'33/33','cross_plan_directives':['CHAT-XFER-001','CHAT-QA-001']",
    "'source_requirements':'33/33','current_plan_requirements':'CF04-CEN-01..10','native_journeys':'CF04-NJ-01..06','cross_plan_directives':['CHAT-XFER-001','CHAT-QA-001']"
)
replace(
    'tools/build-package.sh',
    "'requirements_complete_in_source':33,'cross_plan_directives'",
    "'requirements_complete_in_source':33,'current_plan_cen_complete_in_source':10,'current_plan_native_journeys_source_covered':6,'cross_plan_directives'"
)

# source-integration includes new classes and requires new-plan traceability.
replace(
    'tests/source-integration.py',
    "'RestoreService','Observability']",
    "'RestoreService','Observability','PlanParityRegistry','AccessibilityMetadataService','RenditionSelector','DegradedStateService','PrivacyTelemetry','RightsRevocationService']"
)
replace(
    'tests/source-integration.py',
    "assert 'CHAT-XFER-001' in matrix\n",
    "assert 'CHAT-XFER-001' in matrix\nfor n in range(1,11): assert f'CF04-CEN-{n:02d}' in matrix\nfor n in range(1,7): assert f'CF04-NJ-{n:02d}' in matrix\n"
)

# Runtime requirements matrix: source scope plus explicit owner/consumer boundaries.
matrix=read('docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md')
if '## Current rewritten-plan parity' not in matrix:
    matrix += '''\n## Current rewritten-plan parity\n\nThe 2026-09 current CF-04 plan adds the following source gates. These rows record only CF-04 native implementation/consumer-contract responsibility; domain capabilities remain with their canonical owners.\n\n| Requirement | CF-04 source responsibility | Evidence | Source status |\n|---|---|---|---|\n| CF04-CEN-01 | Typed owner reference + C0-C5 class + rights/purpose/lawful-basis/retention/revocation envelope | `tests/new-plan-parity.php` | Complete in source |\n| CF04-CEN-02 | Quarantine + required scans + fail-closed processing | existing FR-006..011 + new-plan suite | Complete in source |\n| CF04-CEN-03 | Versioned/checksum lineage + active-manifest stale-state checks | FR-016/022 + adversarial tests | Complete in source |\n| CF04-CEN-04 | Purpose/privacy-bound grants + rights-aware immutable CDN key | new-plan suite | Complete in source |\n| CF04-CEN-05 | C0-only public CDN; C2-C5 public delivery denied | new-plan suite | Complete in source |\n| CF04-CEN-06 | Accessibility metadata provenance + qualified high-risk review | `AccessibilityMetadataService` | Complete in source |\n| CF04-CEN-07 | Explicit low-bandwidth/audio/text selection + resumable transfer | `RenditionSelector`, FR-002/014/021, CHAT-XFER-001 | Complete in source |\n| CF04-CEN-08 | Rights/delete propagation through grants/CDN/derivatives + downstream invalidation event | `RightsRevocationService`, `DeletionService` | Complete in source |\n| CF04-CEN-09 | Explicit degraded-state evidence; no silent rendition substitution | `DegradedStateService`, `RenditionSelector` | Complete in source |\n| CF04-CEN-10 | Allowlisted aggregate operational telemetry; person/content labels denied | `PrivacyTelemetry` | Complete in source |\n\n### Native journeys\n\n| Journey | Source evidence |\n|---|---|\n| CF04-NJ-01 | upload/quarantine/scan/process/manifest path + owner contracts |\n| CF04-NJ-02 | AV adaptive + caption/transcript references + low-bandwidth outputs |\n| CF04-NJ-03 | verified 1 GiB resumable transfer + scoped grant/revoke |\n| CF04-NJ-04 | rights-expiry/deletion propagation + tombstone/projection evidence |\n| CF04-NJ-05 | durable jobs, bounded retry, dead-letter, repair and degraded state |\n| CF04-NJ-06 | malware/archive/polyglot/decompression-bomb fail-closed scan path |\n\nExternal browser/device/accessibility, real-provider, Hostinger staging, migration/restore/rollback, live and operational evidence remain separate gates and are not claimed by this source matrix.\n'''
write('docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md',matrix)

# Current status and README make the source/external boundary explicit.
status=read('docs/runtime/STATUS.md')
if 'Current rewritten-plan parity' not in status:
    status += '''\n## Current rewritten-plan parity\n\n- CF04-CEN-01 through CF04-CEN-10: implemented and source-tested in candidate `1.3.0-rc.1`.\n- CF04-NJ-01 through CF04-NJ-06: source paths/negative gates represented in the automated suite.\n- Data constitution: `C0 Public`, `C1 Account`, `C2 Private Communication`, `C3 Professional Evidence`, `C4 Financial/Legal`, `C5 Clinical/High Sensitivity`.\n- Runtime remains disabled by default. Hostinger/real-provider/browser/accessibility/load/penetration/migration/restore/rollback/live/operational acceptance is still pending.\n'''
write('docs/runtime/STATUS.md',status)
readme=read('README.md')
if '## Current rewritten-plan parity — 2026-09-16' not in readme:
    readme += '''\n## Current rewritten-plan parity — 2026-09-16\n\nCandidate `1.3.0-rc.1` reconciles source code with the current rewritten Central Master Plan and CF-04 plan: C0-C5 classification, CF04-CEN-01..10, and CF04-NJ-01..06 source paths are gated by automated tests. Canonical domain ownership remains unchanged. Runtime is still disabled by default, and no staging/live/operational claim is made.\n'''
write('README.md',readme)

# New public-safe traceability report. It records source completion, not external acceptance.
write('docs/reviews/CURRENT-PLAN-PARITY-2026-09-16.md','''# CF-04 Current-Plan Source Parity — 2026-09-16\n\n## Governing basis\n\n1. Current rewritten Sabri Social Homeopathy Platform central governing master plan.\n2. Current rewritten CF-04 Central Media Processing and Secure Delivery plan.\n3. Existing CF04-FR-001..033 and cross-plan transfer/QA directives where not superseded.\n\n## Reconciliation performed\n\n- Corrected privacy-class semantics from the former C1-public model to C0 Public / C1 Account / C2-C5 restricted classes.\n- Added typed owner references, lawful-basis and revocation-hook policy fields before processing.\n- Bound delivery grants to purpose and privacy class and made public CDN cache identity policy/rights/privacy aware.\n- Added qualified provenance workflow for caption/transcript/alt-text metadata.\n- Added explicit low-bandwidth/audio/text rendition selection without silent original substitution.\n- Added rights-expiry/downstream revocation projection evidence and privacy-minimal media telemetry.\n- Added current-plan traceability for CF04-CEN-01..10 and CF04-NJ-01..06.\n\n## Completion boundary\n\nThis report can establish only source-coded / deterministic-package / automated-QA status after exact-head CI passes. Hostinger staging, real provider validation, real migration/backup/restore/rollback, browser/accessibility/load/penetration, Founder release approval, live deployment and monitored operations remain external gates.\n''')

print('CURRENT PLAN PARITY TRANSFORM: APPLIED')
