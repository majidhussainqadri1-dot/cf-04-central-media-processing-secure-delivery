<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitService,RecordStore};

RecordStore::resetMemory();
$provider='source-private';$id='r254-provider-exit';
$plan=RecordStore::put('provider_exit',$id,[
 'actor_id'=>11,'status'=>'credentials_pending','source_provider'=>$provider,
 'target_provider'=>'target-private','items'=>[],
 'credentials_revocation_required'=>true,'purged'=>0
]);
$lateId='r254-source-derivative';
RecordStore::put('derivative',$lateId,[
 'actor_id'=>11,'status'=>'validated','asset_id'=>'r254-source-parent',
 'storage'=>['provider_id'=>$provider],
 'object_key'=>hash('sha256','r254-old-source-object')
]);
$finalize=new ReflectionMethod(ProviderExitService::class,'finalizeCredentials');
err(fn()=>$finalize->invoke(null,$plan),
    'provider_exit_inventory_drift',
    'R254 unplanned late derivative blocks provider-credential retirement');
$unchanged=RecordStore::get('provider_exit',$id);
ok(($unchanged['status']??null)==='credentials_pending'
    &&($unchanged['credentials_revocation_required']??false)===true
    &&!isset($unchanged['credentials_revoked_at']),
    'R254 no completed state or credential evidence recorded after live alias');
$late=RecordStore::get('derivative',$lateId);
$late['status']='deleted';RecordStore::put('derivative',$lateId,$late,(int)$late['version']);
$done=$finalize->invoke(null,$unchanged);
ok($done['status']==='completed'&&!$done['credentials_revocation_required']
    &&isset($done['credentials_revoked_at']),
    'R254 terminal state occurs only after every source-provider alias is gone');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
$start=strpos((string)$source,'private static function finalizeCredentials(');
$end=strpos((string)$source,'private static function assertSourceDrained(', $start);
$block=substr((string)$source,$start,$end-$start);
ok(substr_count($block,'self::assertSourceDrained($plan);')>=2
    &&str_contains($block,'revokeExitCredentials'),
    'R254 source drain is checked before credential revocation and before final state');
echo "REVIEW ROUND 254 CREDENTIAL EXIT LATE REFERENCE: PASS\n";
