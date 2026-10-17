<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,ProvenanceCredentialService,RecordStore};
$p=policy('document','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r177','asset_id'=>'r177',
'owner_domain'=>'file17','owner_object'=>'message:r177','object_version'=>1,
'sha256'=>hash('sha256','r177'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r177',$a);
$parent=$a;$parent['id']='r177-parent';$parent['asset_id']='r177-parent';
$parent['owner_object']='message:r177-parent';RecordStore::put('asset','r177-parent',$parent);
DomainRegistry::register('r177-deny','1.0.0',[
'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r177-denied';$d['asset_id']='r177-denied';
$d['owner_domain']='r177-deny';RecordStore::put('asset','r177-denied',$d);
$input=['origin'=>'camera_upload','synthetic_state'=>'ai_edited',
'tool'=>'approved-editor','tool_version'=>'1.0','transformations'=>['crop']];
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static function()use(&$calls): array{
$calls++;return ['ok'=>true,'format'=>'c2pa-compatible-envelope'];
});
err(fn()=>ProvenanceCredentialService::record('r177-denied',11,$input),
'domain_authorization_denied','Round 177 owner gate');
err(fn()=>ProvenanceCredentialService::record('r177',11,
array_replace($input,['synthetic_state'=>['ai_edited']])),
'synthetic_state_invalid','Round 177 typed synthetic state');
err(fn()=>ProvenanceCredentialService::record('r177',11,
array_replace($input,['origin'=>['camera_upload']])),
'provenance_input_invalid','Round 177 typed origin');
err(fn()=>ProvenanceCredentialService::record('r177',11,
array_replace($input,['parent_asset_ids'=>[['r177-parent']]])),
'provenance_input_invalid','Round 177 typed parents');
ok($calls===0,'Round 177 denied/invalid inputs avoid adapter');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static fn()=>['ok'=>true,'format'=>['c2pa']]);
err(fn()=>ProvenanceCredentialService::record('r177',11,$input),
'provenance_credential_invalid','Round 177 provider format validated');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static function(): array{
$row=RecordStore::get('asset','r177');$row['object_version']=2;
RecordStore::put('asset','r177',$row,(int)$row['version']);
return ['ok'=>true,'format'=>'c2pa-compatible-envelope'];
});
err(fn()=>ProvenanceCredentialService::record('r177',11,$input),
'asset_state_changed','Round 177 source race');
ok(RecordStore::all('provenance_credential')===[],'Round 177 stale evidence excluded');
$row=RecordStore::get('asset','r177');$row['object_version']=1;
RecordStore::put('asset','r177',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static function(): array{
$row=RecordStore::get('asset','r177-parent');$row['object_version']=2;
RecordStore::put('asset','r177-parent',$row,(int)$row['version']);
return ['ok'=>true,'format'=>'c2pa-compatible-envelope'];
});
err(fn()=>ProvenanceCredentialService::record('r177',11,
array_replace($input,['parent_asset_ids'=>['r177-parent']])),
'asset_state_changed','Round 177 parent race');
$row=RecordStore::get('asset','r177-parent');$row['object_version']=1;
RecordStore::put('asset','r177-parent',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_001',static fn()=>[
'ok'=>true,'format'=>'c2pa-compatible-envelope']);
$good=ProvenanceCredentialService::record('r177',11,
array_replace($input,['parent_asset_ids'=>['r177-parent']]));
ok($good['status']==='pending_external_verification'
&&$good['verification_state']==='external_pending'
&&$good['source_sha256']===$a['sha256']
&&$good['rights_hash']===$p['rights']['policy_hash'],
'Round 177 bounded lineage is not claimed externally verified');
echo "REVIEW ROUND 177 PROVENANCE BOUNDARIES: PASS\n";
