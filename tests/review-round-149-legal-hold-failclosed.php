<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,LegalHoldService,RecordStore};

RecordStore::resetMemory();
$asset=RecordStore::put('asset','round149-asset',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
]);
$assetId=$asset['id'];
$input=['authority'=>'Security Office','reason'=>'Preserve evidence',
    'scope'=>['deletion'],'review_at'=>time()+3600,'access_restriction'=>'restricted'];
$hold=LegalHoldService::place($assetId,11,$input);
ok(count(LegalHoldService::active($assetId,'delete'))===1,'Round 149 valid hold blocks deletion');
ok(LegalHoldService::active($assetId,'delivery')===[],'Round 149 valid hold remains scoped');
err(fn()=>LegalHoldService::active($assetId,'unknown-operation'),'hold_operation_invalid',
    'Round 149 unknown operation cannot bypass scoped hold');
foreach(['1e12','10junk',false,[],null,-1] as $index=>$bad){
    err(fn()=>LegalHoldService::place($assetId,11,array_replace($input,['review_at'=>$bad])),
        'hold_schedule_invalid','Round 149 invalid review timestamp '.$index.' is rejected');
    err(fn()=>LegalHoldService::place($assetId,11,array_replace($input,['expires_at'=>$bad])),
        'hold_schedule_invalid','Round 149 invalid expiry timestamp '.$index.' is rejected');
}
foreach(['deletion',[],['delete'],['deletion','deletion'],['deletion',5],['deletion','']] as $index=>$bad){
    err(fn()=>LegalHoldService::place($assetId,11,array_replace($input,['scope'=>$bad])),
        'hold_scope_invalid','Round 149 invalid scope '.$index.' is rejected');
}
foreach(['none','unrestricted',false,[]] as $index=>$bad){
    err(fn()=>LegalHoldService::place($assetId,11,array_replace($input,['access_restriction'=>$bad])),
        'hold_access_restriction_invalid','Round 149 unsafe access restriction '.$index.' is rejected');
}
foreach([
    ['expires_at'=>'1e10'],['expires_at'=>false],['review_at'=>'1e10'],
    ['scope'=>['unknown']],['scope'=>'deletion'],['status'=>'unknown'],
    ['hold_id'=>'different-hold'],['version_number'=>'1e2'],
    ['object_version'=>'1e2'],['access_restriction'=>'none'],
] as $index=>$changes){
    $corrupt=RecordStore::put('hold',$hold['id'],array_replace($hold,$changes),
        (int)RecordStore::get('hold',$hold['id'])['version']);
    err(fn()=>LegalHoldService::active($assetId,'deletion'),'hold_record_invalid',
        'Round 149 corrupt persisted hold '.$index.' cannot bypass deletion');
    err(fn()=>LegalHoldService::review($hold['id'],11,'release','review complete'),
        'hold_record_invalid','Round 149 corrupt persisted hold '.$index.' cannot be released');
    $hold=RecordStore::put('hold',$hold['id'],$hold,(int)$corrupt['version']);
}
$released=LegalHoldService::review($hold['id'],11,'release','review complete');
ok($released['status']==='released'&&LegalHoldService::active($assetId,'deletion')===[],
    'Round 149 valid owner-approved release succeeds');

DomainRegistry::register('round149-denying-owner','1.0.0',[
    'authorize_hold'=>static fn(array $context):array=>[
        'allowed'=>true,'object_version'=>1,
        'hold_allowed'=>($context['phase']??'')!=='review',
    ],
]);
$deniedAsset=RecordStore::put('asset','round149-denied-asset',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'round149-denying-owner','object_version'=>1,
]);
$deniedHold=LegalHoldService::place($deniedAsset['id'],11,$input);
err(fn()=>LegalHoldService::review($deniedHold['id'],11,'release','owner refused'),
    'hold_denied','Round 149 explicit owner veto prevents legal-hold release');
ok(RecordStore::get('hold',$deniedHold['id'])['status']==='active',
    'Round 149 denied release does not mutate persisted hold');
echo "REVIEW ROUND 149 LEGAL HOLD FAIL-CLOSED: PASS\n";
