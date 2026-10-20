<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeliveryResilienceService,DomainRegistry,RecordStore};
$p=policy('document','C3',['view','download']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r181','asset_id'=>'r181',
'owner_domain'=>'file17','owner_object'=>'message:r181','object_version'=>1,
'sha256'=>hash('sha256','r181'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r181',$a);
foreach([59,86401] as $ttl)
err(fn()=>DeliveryResilienceService::offlineGrant('r181',11,$ttl),
'offline_grant_ttl_invalid','Round 181 invalid TTL rejected');
DomainRegistry::register('r181-deny','1.0.0',[
'authorize_download'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r181-denied';$d['asset_id']='r181-denied';
$d['owner_domain']='r181-deny';RecordStore::put('asset','r181-denied',$d);
err(fn()=>DeliveryResilienceService::offlineGrant('r181-denied',11,3600),
'domain_authorization_denied','Round 181 owner denial before issuance');
DomainRegistry::register('r181-race','1.0.0',[
'authorize_download'=>static function(): array{
$row=RecordStore::get('asset','r181-race');$row['object_version']=2;
RecordStore::put('asset','r181-race',$row,(int)$row['version']);
return ['allowed'=>true,'object_version'=>1];
}]);
$race=$a;$race['id']='r181-race';$race['asset_id']='r181-race';
$race['owner_domain']='r181-race';RecordStore::put('asset','r181-race',$race);
err(fn()=>DeliveryResilienceService::offlineGrant('r181-race',11,3600),
'asset_state_changed','Round 181 owner callback mutation prevents stale token');
$grant=DeliveryResilienceService::offlineGrant('r181',11,3600);
$claims=DeliveryResilienceService::verifyOfflineGrant($grant['token'],11);
ok($claims['asset_id']==='r181'&&$claims['type']==='offline_package',
'Round 181 valid encrypted grant verified');
$row=RecordStore::get('offline_package_grant',$grant['grant_id']);
$row['source_sha256']=hash('sha256','tampered');
RecordStore::put('offline_package_grant',$grant['grant_id'],$row,(int)$row['version']);
err(fn()=>DeliveryResilienceService::verifyOfflineGrant($grant['token'],11),
'offline_grant_state_changed','Round 181 tampered source evidence denied');
$grant2=DeliveryResilienceService::offlineGrant('r181',11,3600);
$row=RecordStore::get('asset','r181');
$row['rights']['policy_hash']=hash('sha256','revoked');
RecordStore::put('asset','r181',$row,(int)$row['version']);
err(fn()=>DeliveryResilienceService::verifyOfflineGrant($grant2['token'],11),
'offline_grant_state_changed','Round 181 changed rights deny old grant');
echo "REVIEW ROUND 181 OFFLINE GRANT INTEGRITY: PASS\n";
