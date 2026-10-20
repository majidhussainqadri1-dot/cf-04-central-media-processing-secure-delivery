<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,FutureAdapterRegistry,MediaOptimizationService,RecordStore};
$p=policy('audio','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r176','asset_id'=>'r176',
'owner_domain'=>'file17','owner_object'=>'message:r176','object_version'=>1,
'sha256'=>hash('sha256','r176'),'size'=>1024,'media_class'=>'audio',
'mime'=>'audio/mpeg','privacy_class'=>'C3',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r176',$a);
DomainRegistry::register('r176-deny','1.0.0',[
'authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r176-denied';$d['asset_id']='r176-denied';
$d['owner_domain']='r176-deny';RecordStore::put('asset','r176-denied',$d);
$calls=0;
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static function()use(&$calls): array{
$calls++;return ['ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0,'peak_db'=>-1.2],'issues'=>[]];
});
err(fn()=>MediaOptimizationService::audioQc('r176-denied',11,['lufs_target'=>-16]),
'domain_authorization_denied','Round 176 owner denial before QC adapter');
ok($calls===0,'Round 176 denied audio QC never invoked provider');
foreach([
['unexpected_metric'=>1],['lufs_target'=>'-16'],['lufs_target'=>NAN],
['lufs_target'=>-16,'lufs_min'=>-10,'lufs_max'=>-20]
] as $bad)
err(fn()=>MediaOptimizationService::audioQc('r176',11,$bad),
'audio_qc_threshold_invalid','Round 176 invalid threshold rejected');
ok($calls===0,'Round 176 malformed thresholds never invoked provider');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static fn()=>[
'ok'=>true,'passed'=>true,'metrics'=>['lufs'=>'-16.0']]);
err(fn()=>MediaOptimizationService::audioQc('r176',11,['lufs_target'=>-16]),
'audio_qc_metric_invalid','Round 176 numeric-string adapter metric rejected');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static fn()=>[
'ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0],'issues'=>[['untyped']]]);
err(fn()=>MediaOptimizationService::audioQc('r176',11,['lufs_target'=>-16]),
'audio_qc_issue_invalid','Round 176 untyped issue rejected');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static fn()=>[
'ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-25.0],'issues'=>[]]);
err(fn()=>MediaOptimizationService::audioQc('r176',11,['lufs_target'=>-16]),
'audio_qc_failed','Round 176 adapter pass cannot override objective threshold');
$rows=RecordStore::all('audio_qc');
ok(count($rows)===1&&$rows[0]['status']==='failed',
'Round 176 objective failed QC evidence persisted');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static function(): array{
$row=RecordStore::get('asset','r176');$row['object_version']=2;
RecordStore::put('asset','r176',$row,(int)$row['version']);
return ['ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0],'issues'=>[]];
});
err(fn()=>MediaOptimizationService::audioQc('r176',11,['lufs_target'=>-16]),
'asset_state_changed','Round 176 concurrent asset mutation rejected');
ok(count(RecordStore::all('audio_qc'))===1,
'Round 176 stale QC did not persist');
$row=RecordStore::get('asset','r176');$row['object_version']=1;
RecordStore::put('asset','r176',$row,(int)$row['version']);
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_013',static fn()=>[
'ok'=>true,'passed'=>true,'metrics'=>['lufs'=>-16.0,'peak_db'=>-1.2],'issues'=>[]]);
$good=MediaOptimizationService::audioQc('r176',11,['lufs_target'=>-16,'peak_db_max'=>0]);
ok($good['status']==='passed'&&$good['source_sha256']===$a['sha256']
&&$good['rights_hash']===$p['rights']['policy_hash']
&&$good['policy_hash']===$p['policy_hash'],
'Round 176 valid QC source, owner, policy and rights bound');
echo "REVIEW ROUND 176 AUDIO QC EVIDENCE: PASS\n";
