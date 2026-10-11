<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RetentionService,RecordStore};

RecordStore::resetMemory();
$policyHash=hash('sha256','round150-policy');
$retention=['class'=>'standard','source_seconds'=>86400,'derivative_seconds'=>3600,
    'temporary_seconds'=>600,'backup_expiry_seconds'=>172800];
$asset=RecordStore::put('asset','round150-asset',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
    'policy_hash'=>$policyHash,'policy'=>['retention'=>$retention],
]);
$schedule=RetentionService::schedule($asset['id']);
ok($schedule['source_delete_at']>time()&&$schedule['backup_expiry_at']>time(),
    'Round 150 valid canonical retention schedule is accepted');
foreach(['1e2','10junk',false,[],null,-1,PHP_INT_MAX] as $index=>$bad){
    $badAsset=RecordStore::get('asset',$asset['id']);
    $badAsset['policy']['retention']['source_seconds']=$bad;
    RecordStore::put('asset',$asset['id'],$badAsset,(int)$badAsset['version']);
    err(fn()=>RetentionService::schedule($asset['id']),'retention_schedule_invalid',
        'Round 150 malformed or overflowing policy duration '.$index.' is rejected');
}
$restored=RecordStore::get('asset',$asset['id']);$restored['policy']['retention']=$retention;
RecordStore::put('asset',$asset['id'],$restored,(int)$restored['version']);
foreach([
    ['source_delete_at'=>'1e2'],['derivative_delete_at'=>false],
    ['backup_expiry_at'=>null],['temporary_cleanup_at'=>'10junk'],
    ['status'=>'unexpected'],['asset_id'=>''],['retention_class'=>''],
    ['derivatives_expired'=>'false'],['deletion_id'=>[]],
] as $index=>$changes){
    $original=RecordStore::get('retention',$schedule['id']);
    $bad=RecordStore::put('retention',$schedule['id'],array_replace($original,$changes),(int)$original['version']);
    $out=RetentionService::run();
    ok($out['records_failed']>=1&&$out['deletion_requested']===0,
        'Round 150 corrupt persisted retention record '.$index.' fails closed');
    RecordStore::put('retention',$schedule['id'],$original,(int)$bad['version']);
}
$changed=RecordStore::get('asset',$asset['id']);
$changed['policy_hash']=hash('sha256','round150-new-policy');
RecordStore::put('asset',$asset['id'],$changed,(int)$changed['version']);
$out=RetentionService::run();
ok($out['records_failed']>=1&&$out['deletion_requested']===0,
    'Round 150 stale policy-bound retention schedule cannot delete media');
echo "REVIEW ROUND 150 RETENTION FAIL-CLOSED: PASS\n";
