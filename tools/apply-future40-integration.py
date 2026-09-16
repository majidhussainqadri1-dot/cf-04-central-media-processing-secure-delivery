#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

def read(rel): return (ROOT/rel).read_text()
def write(rel,s): (ROOT/rel).write_text(s)
def replace(rel,old,new):
    s=read(rel)
    if new in s: return
    if old not in s: raise SystemExit(f'{rel}: marker not found: {old[:100]!r}')
    write(rel,s.replace(old,new,1))

replace('sabri-central-media/includes/class-scm-plugin.php',
        'CompanionDomainAdapters::registerWordPressFilters();ScannerRegistry::registerWordPressAdapters();',
        'CompanionDomainAdapters::registerWordPressFilters();ScannerRegistry::registerWordPressAdapters();FutureAdapterRegistry::registerWordPressAdapters();')
replace('sabri-central-media/includes/class-scm-future40.php',
        "'authorize_transform',['asset'=>$asset,'actor_id'=>$actor,'transform'=>'redaction'",
        "'authorize_reprocess',['asset'=>$asset,'actor_id'=>$actor,'transform'=>'redaction'")
replace('tests/bootstrap.php',
        "'class-scm-operations.php','class-scm-plan-parity.php','class-scm-rest.php'",
        "'class-scm-operations.php','class-scm-plan-parity.php','class-scm-future40.php','class-scm-rest.php'")
replace('tools/quality-check.sh',
        'php "$ROOT/tests/new-plan-parity.php"\n',
        'php "$ROOT/tests/new-plan-parity.php"\nphp "$ROOT/tests/future40.php"\n')
replace('tools/quality-check.sh',
        "'native_journeys':'CF04-NJ-01..06','cross_plan_directives'",
        "'native_journeys':'CF04-NJ-01..06','future40_source_capabilities':'CF04-FUT-001..040','cross_plan_directives'")
replace('tools/build-package.sh',
        "'current_plan_native_journeys_source_covered':6,'cross_plan_directives'",
        "'current_plan_native_journeys_source_covered':6,'future40_source_capabilities':40,'cross_plan_directives'")
replace('.github/workflows/runtime-ci.yml',
        '          php tests/new-plan-parity.php\n',
        '          php tests/new-plan-parity.php\n          php tests/future40.php\n')
replace('.github/workflows/runtime-ci.yml',
        "          grep -q 'class-scm-plan-parity.php' sabri-central-media/sabri-central-media.php\n",
        "          grep -q 'class-scm-plan-parity.php' sabri-central-media/sabri-central-media.php\n          grep -q 'class-scm-future40.php' sabri-central-media/sabri-central-media.php\n          grep -q 'CF04-FUT-040' sabri-central-media/includes/class-scm-future40.php\n")
replace('tests/source-integration.py',
        "'RestoreService','Observability','PlanParityRegistry','AccessibilityMetadataService','RenditionSelector','DegradedStateService','PrivacyTelemetry','RightsRevocationService']",
        "'RestoreService','Observability','PlanParityRegistry','AccessibilityMetadataService','RenditionSelector','DegradedStateService','PrivacyTelemetry','RightsRevocationService','Future40Registry','FutureAdapterRegistry','ProvenanceCredentialService','PerceptualMediaService','ContentSafetyUpgradeService','MediaOptimizationService','RichMediaMetadataService','SensitiveDataProtectionService','DeliveryResilienceService','ResidencyCryptoService','DisasterCostRoutingService','OperationsFutureService']")

matrix=read('docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md')
if '## Approved Future-40 source capabilities' not in matrix:
    rows='\n'.join(f"| CF04-FUT-{i:03d} | Implemented in source; provider-dependent engines remain fail-closed until approved adapter/staging evidence | `class-scm-future40.php`, `tests/future40.php` | Complete in source |" for i in range(1,41))
    matrix += f'''\n\n## Approved Future-40 source capabilities\n\nThe Founder-approved Future-40 extension is implemented as a fail-closed source layer. Capabilities requiring specialist external engines (for example provenance signing, perceptual hashing, CDR, quality scoring or sensitive-data detection) require an approved adapter and do not silently simulate production success. Staging/live/provider acceptance remains a separate lifecycle gate.\n\n| Requirement | Source responsibility | Evidence | Source status |\n|---|---|---|---|\n{rows}\n'''
    write('docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md',matrix)

status=read('docs/runtime/STATUS.md')
if '## Future-40 extension — 2026-09-16' not in status:
    status += '''\n\n## Future-40 extension — 2026-09-16\n\n- CF04-FUT-001 through CF04-FUT-040 are implemented in source and covered by `tests/future40.php`.\n- External media engines/providers are adapter-gated and fail closed when absent.\n- Canonical ownership remains with the existing domain owners; CF-04 owns binary/media infrastructure only.\n- This source completion does not claim real C2PA/provider certification, real CDN failover, real regional replication, staging acceptance, live deployment or operational acceptance.\n'''
    write('docs/runtime/STATUS.md',status)

readme=read('README.md')
if '## Future-40 media infrastructure extension' not in readme:
    readme += '''\n\n## Future-40 media infrastructure extension\n\nThe approved CF-04 Future-40 extension adds source-level contracts and fail-closed orchestration for content credentials/provenance, synthetic-media declaration, perceptual fingerprints and privacy-safe dedupe, CDR/re-scan/kill-switch controls, content-aware encoding and objective quality gates, HDR/audio QC, smart previews/chapters/accessibility tracks/OCR maps, sensitive-data detection and governed redaction, multi-CDN/origin-shield/edge authorization/network-adaptive transfer/offline packages, regional residency/object lock/per-asset key envelopes/crypto agility, multi-region DR/storage-tier/cost/provider routing, privacy-minimal QoE, staging-only chaos exercises, signed migration bundles and a versioned SDK contract.\n\nProvider-specific engines remain adapter-gated and the runtime remains fail closed until the existing staging/provider/migration/restore/rollback activation evidence is approved.\n'''
    write('README.md',readme)

review=ROOT/'docs/reviews/FUTURE40-SOURCE-IMPLEMENTATION-2026-09-16.md'
if not review.exists():
    review.write_text('''# CF-04 Future-40 Source Implementation — 2026-09-16\n\n## Governing boundary\n\nThis implementation extends CF-04 media infrastructure only. It does not seize canonical content/editorial/clinical/messaging ownership from Files 10/11/12/17/21/22 or CF-01.\n\n## Implemented source groups\n\n- CF04-FUT-001..005: provenance, synthetic declaration, perceptual fingerprinting, privacy-safe near-duplicate detection and same-envelope dedupe decisions.\n- CF04-FUT-006..013: CDR, re-scan, kill switch, content-aware encoding, objective quality, codec negotiation, color/HDR lineage and audio QC.\n- CF04-FUT-014..022: smart preview/storyboard, chapters, rich captions, audio description, sign-language tracks, OCR coordinate maps, accessibility manifest, sensitive-data signals and governed redaction.\n- CF04-FUT-023..032: multi-CDN routing, origin shield, edge authorization profile, adaptive upload, offline grant, resumable download state, residency, object lock, asset-key envelopes and crypto agility.\n- CF04-FUT-033..040: DR plan, storage-tier optimization, pre-processing cost estimate, approved-provider routing, privacy-minimal QoE, staging-only chaos, signed migration bundle and SDK contract.\n\n## Safety and evidence rule\n\nProvider-dependent capabilities are represented by approved adapters. Missing adapters fail closed. Automated source tests can establish source-coded/QA status only; real-provider, staging, live and operational status require separate evidence under the project lifecycle rules.\n''')

print('FUTURE40 INTEGRATION TRANSFORM: APPLIED')
