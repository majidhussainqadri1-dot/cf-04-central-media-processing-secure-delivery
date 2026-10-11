<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ContentSafetyUpgradeService,DomainRegistry,FutureAdapterRegistry,RecordStore};

$p=policy('document','C3',['view','download','reprocess']);
$asset=['actor_id'=>11,'status'=>'ready','id'=>'r169','asset_id'=>'r169',
    'owner_domain'=>'file17','owner_object'=>'message:r169','object_version'=>1,
    'sha256'=>hash('sha256','r169'),'size'=>1024,'media_class'=>'document','mime'=>'application/pdf',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r169',$asset);
DomainRegistry::register('r169-deny','1.0.0',[
    'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]
]);
$denied=$asset;$denied['id']='r169-denied';$denied['asset_id']='r169-denied';
$denied['owner_domain']='r169-deny';$denied['mime']='application/x-denied';
RecordStore::put('asset','r169-denied',$denied);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_006',static function(array $ctx)use(&$calls): array{
    $calls++;return ['ok'=>true,'safe_sha256'=>hash('sha256','safe'),'removed_features'=>['javascript']];
});
err(fn()=>ContentSafetyUpgradeService::disarm('r169-denied',11,'document-safe'),
    'domain_authorization_denied','Round 169 owner denies CDR before provider');
ok($calls===0,'Round 169 denied CDR did not invoke adapter');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_006',static function(array $ctx): array{
    $row=RecordStore::get('asset','r169');$row['object_version']=2;
    RecordStore::put('asset','r169',$row,(int)$row['version']);
    return ['ok'=>true,'safe_sha256'=>hash('sha256','safe'),'removed_features'=>[]];
});
err(fn()=>ContentSafetyUpgradeService::disarm('r169',11,'document-safe'),
    'asset_state_changed','Round 169 source race denies CDR');
ok(RecordStore::all('cdr_result')===[],'Round 169 stale CDR not persisted');
$row=RecordStore::get('asset','r169');$row['object_version']=1;
RecordStore::put('asset','r169',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_006',static fn()=>[
    'ok'=>true,'safe_sha256'=>hash('sha256','safe').'extra','removed_features'=>[]]);
err(fn()=>ContentSafetyUpgradeService::disarm('r169',11,'document-safe'),
    'cdr_output_invalid','Round 169 exact output hash required');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_006',static fn()=>[
    'ok'=>true,'safe_sha256'=>hash('sha256','safe'),'removed_features'=>[['not-text']]]);
err(fn()=>ContentSafetyUpgradeService::disarm('r169',11,'document-safe'),
    'cdr_output_invalid','Round 169 removed feature evidence typed');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_006',static fn()=>[
    'ok'=>true,'safe_sha256'=>hash('sha256','safe'),'removed_features'=>['javascript','embedded-action']]);
$cdr=ContentSafetyUpgradeService::disarm('r169',11,'document-safe');
ok($cdr['status']==='validated'&&$cdr['owner_object_version']===1
    &&$cdr['rights_hash']===$p['rights']['policy_hash'],
    'Round 169 CDR binds current source and rights');

$one=ContentSafetyUpgradeService::scheduleRescan('signature-update',['mime'=>'application/pdf'],10,11);
$repeat=ContentSafetyUpgradeService::scheduleRescan('signature-update',['mime'=>'application/pdf'],10,11);
ok($one['queued']===1&&$repeat['queued']===0,'Round 169 unchanged generation not duplicated');
$row=RecordStore::get('asset','r169');$row['object_version']=2;
RecordStore::put('asset','r169',$row,(int)$row['version']);
$next=ContentSafetyUpgradeService::scheduleRescan('signature-update',['mime'=>'application/pdf'],10,11);
ok($next['queued']===1&&count(RecordStore::all('future_rescan'))===2,
    'Round 169 changed source generation can be rescanned');
ContentSafetyUpgradeService::killSwitch('application/x-r169',11,true,'emergency');
err(fn()=>ContentSafetyUpgradeService::assertAllowed('application/x-r169'),
    'format_emergency_blocked','Round 169 emergency kill switch remains effective');
ContentSafetyUpgradeService::killSwitch('application/x-r169',11,false,'released');
ContentSafetyUpgradeService::assertAllowed('application/x-r169');
echo "REVIEW ROUND 169 CONTENT SAFETY EVIDENCE: PASS\n";
