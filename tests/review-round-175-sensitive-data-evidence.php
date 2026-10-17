<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,RecordStore,SensitiveDataProtectionService};
$p=policy('document','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r175','asset_id'=>'r175',
'owner_domain'=>'file17','owner_object'=>'message:r175','object_version'=>1,
'sha256'=>hash('sha256','r175'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r175',$a);
DomainRegistry::register('r175-deny','1.0.0',[
'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r175-denied';$d['asset_id']='r175-denied';
$d['owner_domain']='r175-deny';RecordStore::put('asset','r175-denied',$d);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_021',static function()use(&$calls): array{
$calls++;return ['ok'=>true,'signals'=>[['type'=>'email','confidence'=>0.9,'region'=>[1,2,3,4]]]];
});
err(fn()=>SensitiveDataProtectionService::detect('r175-denied',11,['email']),
'domain_authorization_denied','Round 175 detector owner gate');
ok($calls===0,'Round 175 no unauthorized adapter call');
foreach([[['email']],['email','email'],array_fill(0,33,'email'),['email!']] as $classes)
err(fn()=>SensitiveDataProtectionService::detect('r175',11,$classes),
'sensitive_classes_invalid','Round 175 malformed classes');
ok($calls===0,'Round 175 no invalid class adapter call');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_021',static fn()=>[
'ok'=>true,'signals'=>[['type'=>'email','confidence'=>0.9,'raw_value'=>'unexpected']]]);
err(fn()=>SensitiveDataProtectionService::detect('r175',11,['email']),
'sensitive_signal_invalid','Round 175 unapproved signal field');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_021',static fn()=>[
'ok'=>true,'signals'=>[['type'=>'email','confidence'=>'0.9']]]);
err(fn()=>SensitiveDataProtectionService::detect('r175',11,['email']),
'sensitive_signal_invalid','Round 175 nonnumeric confidence');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_021',static function(): array{
$row=RecordStore::get('asset','r175');$row['object_version']=2;
RecordStore::put('asset','r175',$row,(int)$row['version']);
return ['ok'=>true,'signals'=>[['type'=>'email','confidence'=>0.9]]];
});
err(fn()=>SensitiveDataProtectionService::detect('r175',11,['email']),
'asset_state_changed','Round 175 detector race');
ok(RecordStore::all('sensitive_signal')===[],'Round 175 stale detector evidence excluded');
$row=RecordStore::get('asset','r175');$row['object_version']=1;
RecordStore::put('asset','r175',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_021',static fn()=>[
'ok'=>true,'signals'=>[['type'=>'email','confidence'=>0.9,'region'=>[1,2,3,4]]]]);
$det=SensitiveDataProtectionService::detect('r175',11,['email']);
ok($det['status']==='review_required'&&$det['source_sha256']===$a['sha256']
&&$det['rights_hash']===$p['rights']['policy_hash'],
'Round 175 current detector evidence');
FutureAdapterRegistry::reset();
$redCalls=0;
FutureAdapterRegistry::register('future_022',static function()use(&$redCalls): array{
$redCalls++;return ['ok'=>true,'redacted_sha256'=>hash('sha256','result')];
});
$regions=[['type'=>'email','box'=>[1,2,3,4]]];
err(fn()=>SensitiveDataProtectionService::redact('r175-denied',11,$regions,'cleanup'),
'domain_authorization_denied','Round 175 redaction owner gate');
foreach([
[['type'=>'email','box'=>['1',2,3,4]]],
[['type'=>'email','box'=>[1,2,0,4]]],
[['type'=>'email','box'=>[1,2,3,4],'raw_value'=>'unexpected']],
] as $bad)
err(fn()=>SensitiveDataProtectionService::redact('r175',11,$bad,'cleanup'),
'redaction_request_invalid','Round 175 invalid region');
ok($redCalls===0,'Round 175 no unauthorized redaction call');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_022',static fn()=>[
'ok'=>true,'redacted_sha256'=>hash('sha256','result').'suffix']);
err(fn()=>SensitiveDataProtectionService::redact('r175',11,$regions,'cleanup'),
'redaction_output_invalid','Round 175 exact hash required');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_022',static function(): array{
$row=RecordStore::get('asset','r175');$row['object_version']=3;
RecordStore::put('asset','r175',$row,(int)$row['version']);
return ['ok'=>true,'redacted_sha256'=>hash('sha256','result')];
});
err(fn()=>SensitiveDataProtectionService::redact('r175',11,$regions,'cleanup'),
'asset_state_changed','Round 175 redaction race');
ok(RecordStore::all('redaction_derivative')===[],'Round 175 stale redaction excluded');
$row=RecordStore::get('asset','r175');$row['object_version']=1;
RecordStore::put('asset','r175',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_022',static fn()=>[
'ok'=>true,'redacted_sha256'=>hash('sha256','result')]);
$red=SensitiveDataProtectionService::redact('r175',11,$regions,'cleanup');
ok($red['status']==='approved'&&$red['source_sha256']===$a['sha256']
&&$red['rights_hash']===$p['rights']['policy_hash']
&&$red['policy_hash']===$p['policy_hash'],
'Round 175 current redaction evidence');
echo "REVIEW ROUND 175 SENSITIVE DATA EVIDENCE: PASS\n";
