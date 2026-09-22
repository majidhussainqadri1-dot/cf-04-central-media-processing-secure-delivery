import pathlib,re
root=pathlib.Path(__file__).resolve().parents[1]
plugin=(root/'sabri-central-media/sabri-central-media.php').read_text()
includes=sorted((root/'sabri-central-media/includes').glob('*.php'))
assert len(includes)>=13
for path in includes: assert path.name in plugin, path.name
source='\n'.join(p.read_text() for p in includes)
classes=['Policy','RightsPolicy','UploadService','Validator','ScannerRegistry','ToolRunner','JobService','ImagePipeline','AvPipeline','DocumentPipeline','DerivativeService','DeliveryService','IntegrityService','TransferService','RetentionService','LegalHoldService','DeletionService','ProviderExitService','CostService','RepairService','RestoreService','Observability','PlanParityRegistry','AccessibilityMetadataService','RenditionSelector','DegradedStateService','PrivacyTelemetry','RightsRevocationService','Future40Registry','FutureAdapterRegistry','ProvenanceCredentialService','PerceptualMediaService','ContentSafetyUpgradeService','MediaOptimizationService','RichMediaMetadataService','SensitiveDataProtectionService','DeliveryResilienceService','ResidencyCryptoService','DisasterCostRoutingService','OperationsFutureService']
for name in classes: assert re.search(r'(?:final class|interface)\s+'+re.escape(name)+r'\b',source), name
matrix=(root/'docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md').read_text()
for n in range(1,34): assert f'CF04-FR-{n:03d}' in matrix
assert 'CHAT-XFER-001' in matrix
for n in range(1,11): assert f'CF04-CEN-{n:02d}' in matrix
for n in range(1,7): assert f'CF04-NJ-{n:02d}' in matrix
assert "define('SCM_RUNTIME_ENABLED',false)" in plugin
print('SOURCE INTEGRATION: PASS')
