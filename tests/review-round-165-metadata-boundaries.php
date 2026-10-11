<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{AccessibilityMetadataService,DomainRegistry,FutureAdapterRegistry,RecordStore,RichMediaMetadataService,Utils};

RecordStore::resetMemory();
$p=policy('video','C3',['view','download','transform','reprocess']);
$asset=[
    'actor_id'=>11,'status'=>'ready','asset_id'=>'r165','owner_domain'=>'file17',
    'owner_object'=>'message:r165','object_version'=>1,'id'=>'r165',
    'sha256'=>hash('sha256','r165'),'size'=>1000,'media_class'=>'video','mime'=>'video/mp4',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']
];
RecordStore::put('asset','r165',$asset);
$called=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_014',static function(array $context)use(&$called): array{
    $called++;
    return ['ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:story'];
});
$GLOBALS['scm_user_id']=0;
err(fn()=>RichMediaMetadataService::smartPreview('r165',11,[]),'authentication_required',
    'Round 165 preview authenticates before invoking adapter');
ok($called===0,'Round 165 denied preview did not invoke provider');
$GLOBALS['scm_user_id']=11;

DomainRegistry::register('r165-deny','1.0.0',[
    'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1],
    'authorize_delivery'=>static fn()=>['allowed'=>false,'object_version'=>1]
]);
$denied=$asset;$denied['id']='r165-denied';$denied['asset_id']='r165-denied';
$denied['owner_domain']='r165-deny';RecordStore::put('asset','r165-denied',$denied);
err(fn()=>RichMediaMetadataService::smartPreview('r165-denied',11,[]),'domain_authorization_denied',
    'Round 165 owning domain denies preview before adapter');
ok($called===0,'Round 165 owner-denied preview did not invoke provider');

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_014',static function(array $context): array{
    $row=RecordStore::get('asset','r165');$row['object_version']=2;
    RecordStore::put('asset','r165',$row,(int)$row['version']);
    return ['ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:story'];
});
err(fn()=>RichMediaMetadataService::smartPreview('r165',11,[]),'asset_state_changed',
    'Round 165 concurrent asset change invalidates generated preview');
ok(RecordStore::all('smart_preview')===[],'Round 165 stale preview was not persisted');

$row=RecordStore::get('asset','r165');$row['object_version']=1;
RecordStore::put('asset','r165',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_014',static fn()=>[
    'ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:story'
]);
$preview=RichMediaMetadataService::smartPreview('r165',11,[]);
ok($preview['status']==='active','Round 165 authorized current preview persists');
$caption=RichMediaMetadataService::captionTrack('r165',11,[
    'kind'=>'sdh','locale'=>'ur-PK','content_hash'=>hash('sha256','caption'),'source'=>'human'
]);

$approved=['actor_id'=>11,'status'=>'approved','asset_id'=>'r165','owner_object_version'=>1,
    'policy_hash'=>$p['policy_hash'],'rights_hash'=>$p['rights']['policy_hash'],
    'kind'=>'caption','locale'=>'ur-pk','content_hash'=>hash('sha256','approved')];
RecordStore::put('accessibility_metadata','r165-approved',$approved);
RecordStore::put('accessibility_metadata','r165-revoked',array_replace($approved,['status'=>'revoked']));
RecordStore::put('accessibility_metadata','r165-stale',array_replace($approved,['rights_hash'=>hash('sha256','stale')]));
RecordStore::put('accessibility_metadata','r165-version',array_replace($approved,['owner_object_version'=>2]));
RecordStore::put('rich_caption','r165-revoked',array_replace($caption,['status'=>'revoked','id'=>'r165-revoked']));
RecordStore::put('rich_caption','r165-stale',array_replace($caption,['policy_hash'=>hash('sha256','stale'),'id'=>'r165-stale']));

$rows=AccessibilityMetadataService::forAsset('r165');
ok(count($rows)===1&&$rows[0]['id']==='r165-approved',
    'Round 165 metadata projection excludes revoked and stale evidence');
$manifest=RichMediaMetadataService::accessibilityManifest('r165');
ok(count($manifest['tracks']['accessibility_metadata'])===1
    &&count($manifest['tracks']['rich_caption'])===1
    &&$manifest['complete']===true,
    'Round 165 manifest excludes revoked/stale metadata');
ok($manifest['policy_hash']===$p['policy_hash']
    &&$manifest['rights_hash']===$p['rights']['policy_hash'],
    'Round 165 manifest explicitly binds current policy and rights');
$payload=$manifest;unset($payload['manifest_hash'],$payload['generated_at']);
ok($manifest['manifest_hash']===hash('sha256',Utils::canonicalJson($payload)),
    'Round 165 manifest hash is deterministic and excludes generation time');
sleep(1);
$manifest2=RichMediaMetadataService::accessibilityManifest('r165');
ok($manifest['manifest_hash']===$manifest2['manifest_hash'],
    'Round 165 repeated unchanged projection preserves evidence hash');

$GLOBALS['scm_user_id']=0;
err(fn()=>AccessibilityMetadataService::forAsset('r165'),'authentication_required',
    'Round 165 metadata read requires authenticated actor');
err(fn()=>RichMediaMetadataService::accessibilityManifest('r165'),'authentication_required',
    'Round 165 manifest read requires authenticated actor');
$GLOBALS['scm_user_id']=11;
err(fn()=>AccessibilityMetadataService::forAsset('r165-denied'),'domain_authorization_denied',
    'Round 165 owner can deny metadata read');
err(fn()=>RichMediaMetadataService::accessibilityManifest('r165-denied'),'domain_authorization_denied',
    'Round 165 owner can deny manifest read');

$row=RecordStore::get('asset','r165');
$row['policy_hash']=hash('sha256','changed-policy');
RecordStore::put('asset','r165',$row,(int)$row['version']);
ok(AccessibilityMetadataService::forAsset('r165')===[],
    'Round 165 policy mutation hides stale accessibility records');
$manifest=RichMediaMetadataService::accessibilityManifest('r165');
ok($manifest['complete']===false&&$manifest['tracks']['rich_caption']===[],
    'Round 165 policy mutation hides stale rich tracks');
echo "REVIEW ROUND 165 METADATA BOUNDARIES: PASS\n";
