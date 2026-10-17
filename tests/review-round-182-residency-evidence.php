<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,RecordStore,ResidencyCryptoService};
$p=policy('document','C3',['view','download']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r182','asset_id'=>'r182',
'owner_domain'=>'file17','owner_object'=>'message:r182','object_version'=>1,
'sha256'=>hash('sha256','r182'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r182',$a);
foreach([[['TEST-A']],['test-a'],['TEST-A','TEST-A'],array_fill(0,33,'TEST-A')] as $bad)
err(fn()=>ResidencyCryptoService::residency('r182',11,$bad),
'residency_regions_invalid','Round 182 invalid region list denied');
DomainRegistry::register('r182-deny','1.0.0',[
'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r182-denied';$d['asset_id']='r182-denied';
$d['owner_domain']='r182-deny';RecordStore::put('asset','r182-denied',$d);
err(fn()=>ResidencyCryptoService::residency('r182-denied',11,['TEST-A']),
'domain_authorization_denied','Round 182 owner controls residency');
$policy=ResidencyCryptoService::residency('r182',11,['TEST-A','TEST-B']);
ResidencyCryptoService::assertRegion('r182','TEST-A');
err(fn()=>ResidencyCryptoService::assertRegion('r182','OTHER'),
'residency_region_denied','Round 182 unapproved region denied');
ResidencyCryptoService::assertProviderRegion('r182','source-private');
ok($policy['asset_sha256']===$a['sha256']
&&$policy['rights_hash']===$p['rights']['policy_hash'],
'Round 182 current residency evidence bound');
$row=RecordStore::get('residency_policy',$policy['id']);
$row['allowed_regions']=[['TEST-A']];
RecordStore::put('residency_policy',$policy['id'],$row,(int)$row['version']);
err(fn()=>ResidencyCryptoService::assertRegion('r182','TEST-A'),
'residency_policy_invalid','Round 182 malformed persisted region fails closed');
$row=RecordStore::get('residency_policy',$policy['id']);
$row['allowed_regions']=['TEST-A','TEST-B'];
RecordStore::put('residency_policy',$policy['id'],$row,(int)$row['version']);
$row=RecordStore::get('asset','r182');
$row['rights']['policy_hash']=hash('sha256','changed');
RecordStore::put('asset','r182',$row,(int)$row['version']);
err(fn()=>ResidencyCryptoService::assertRegion('r182','TEST-A'),
'residency_policy_stale','Round 182 changed rights invalidate residency evidence');
echo "REVIEW ROUND 182 RESIDENCY EVIDENCE: PASS\n";
