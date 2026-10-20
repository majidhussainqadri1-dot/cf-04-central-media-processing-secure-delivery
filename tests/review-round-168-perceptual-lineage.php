<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,PerceptualMediaService,RecordStore};

$p=policy('document','C3',['view','download','reprocess']);
$asset=['actor_id'=>11,'status'=>'ready','id'=>'r168a','asset_id'=>'r168a',
    'owner_domain'=>'file17','owner_object'=>'message:r168a','object_version'=>1,
    'sha256'=>hash('sha256','same'),'size'=>1024,'media_class'=>'document','mime'=>'application/pdf',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r168a',$asset);
$b=$asset;$b['id']='r168b';$b['asset_id']='r168b';$b['owner_object']='message:r168b';
RecordStore::put('asset','r168b',$b);
DomainRegistry::register('r168-deny','1.0.0',[
    'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]
]);
$denied=$asset;$denied['id']='r168-denied';$denied['asset_id']='r168-denied';
$denied['owner_domain']='r168-deny';RecordStore::put('asset','r168-denied',$denied);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_003',static function(array $ctx)use(&$calls): array{
    $calls++;return ['ok'=>true,'fingerprint'=>'phash:valid'];
});
err(fn()=>PerceptualMediaService::fingerprint('r168-denied',11,'phash','1'),
    'domain_authorization_denied','Round 168 owner denies fingerprint before provider');
ok($calls===0,'Round 168 denied fingerprint did not invoke provider');
$GLOBALS['scm_user_id']=0;
err(fn()=>PerceptualMediaService::fingerprint('r168a',11,'phash','1'),
    'authentication_required','Round 168 anonymous fingerprint blocked');
$GLOBALS['scm_user_id']=11;
ok($calls===0,'Round 168 anonymous fingerprint did not invoke provider');

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_003',static function(array $ctx): array{
    $row=RecordStore::get('asset','r168a');$row['object_version']=2;
    RecordStore::put('asset','r168a',$row,(int)$row['version']);
    return ['ok'=>true,'fingerprint'=>'phash:stale'];
});
err(fn()=>PerceptualMediaService::fingerprint('r168a',11,'phash','1'),
    'asset_state_changed','Round 168 concurrent source change blocks fingerprint');
ok(RecordStore::all('perceptual_fingerprint')===[],
    'Round 168 stale fingerprint not persisted');
$row=RecordStore::get('asset','r168a');$row['object_version']=1;
RecordStore::put('asset','r168a',$row,(int)$row['version']);

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_003',static fn()=>['ok'=>true,'fingerprint'=>'phash:valid']);
FutureAdapterRegistry::register('future_004',static fn()=>['ok'=>true,'similarity'=>1.0]);
$a=PerceptualMediaService::fingerprint('r168a',11,'phash','1');
PerceptualMediaService::fingerprint('r168b',11,'phash','1');
ok($a['source_sha256']===$asset['sha256']&&$a['owner_object_version']===1
    &&$a['rights_hash']===$p['rights']['policy_hash'],
    'Round 168 fingerprint binds source, version and rights');
$matches=PerceptualMediaService::nearDuplicates('r168a',11);
ok(count($matches)===1&&$matches[0]['asset_id']==='r168b',
    'Round 168 authorized current match supported');
$other=RecordStore::get('asset','r168b');$other['rights']['policy_hash']=hash('sha256','changed');
RecordStore::put('asset','r168b',$other,(int)$other['version']);
ok(PerceptualMediaService::nearDuplicates('r168a',11)===[],
    'Round 168 stale candidate fingerprint excluded');
$other=RecordStore::get('asset','r168b');$other['rights']['policy_hash']=$p['rights']['policy_hash'];
RecordStore::put('asset','r168b',$other,(int)$other['version']);

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_004',static fn()=>['ok'=>true,'similarity'=>'1.0']);
err(fn()=>PerceptualMediaService::nearDuplicates('r168a',11),
    'perceptual_similarity_invalid','Round 168 string similarity rejected');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_004',static fn()=>['ok'=>true,'similarity'=>1.0]);
ok(PerceptualMediaService::dedupeDecision('r168a','r168b',11)['status']==='eligible',
    'Round 168 authorized dedupe eligibility preserved');
err(fn()=>PerceptualMediaService::dedupeDecision('r168a','r168-denied',11),
    'domain_authorization_denied','Round 168 denied candidate cannot enter dedupe');
echo "REVIEW ROUND 168 PERCEPTUAL LINEAGE: PASS\n";
