<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderExitShadowCopyStage,ProviderRegistry,RecordStore,Utils};
RecordStore::resetMemory();
$source=ProviderRegistry::get('source-private');
$bytes='r288-private-file17-media-ciphertext';
$key=hash('sha256','r288-source-object');
$stream=Utils::tempStream();
Utils::writeAll($stream,$bytes);
rewind($stream);
try{$source->putStream($key,$stream);}finally{fclose($stream);}
RecordStore::put('asset','r288-file17-private',[
    'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
    'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
    'object_key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)
]);
$active=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
while(($stage['status']??null)==='planning'){
    $stage=ProviderExitInventoryStage::advance($stage['id'],11);
}
$segmentId=hash('sha256',$stage['id'].'|asset|');
$copyId=hash('sha256',$stage['id'].'|'.$segmentId);
$copied=ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1);
ok(($copied['status']??null)==='copied_unfenced',
    'R288 seeded encrypted private copy has a completed journal');
$emptyDerivative=hash('sha256',$stage['id'].'|derivative|');
ProviderExitShadowCopyStage::copySegment($stage['id'],$emptyDerivative,11,1);
ok(ProviderExitShadowCopyStage::verifyComplete($stage['id'],11)['verified_objects']===1,
    'R288 baseline independently hashes both source and target');

$corruptions=[
    ['name'=>'copy status','field'=>'status','target'=>'copy','value'=>'unverified'],
    ['name'=>'inventory binding','field'=>'inventory_id','target'=>'row','value'=>'other-inventory'],
    ['name'=>'segment binding','field'=>'segment_id','target'=>'row','value'=>'other-segment'],
    ['name'=>'actor binding','field'=>'actor_id','target'=>'row','value'=>0],
    ['name'=>'copy list shape','field'=>'copies','target'=>'row','value'=>'non-list']
];
foreach($corruptions as $case){
    $record=RecordStore::get('provider_exit_shadow_segment',$copyId);
    $before=$record;
    if($case['target']==='copy')$record['copies'][0][$case['field']]=$case['value'];
    elseif($case['value']==='non-list')$record['copies']=[7=>$record['copies'][0]];
    else $record[$case['field']]=$case['value'];
    $changed=RecordStore::put('provider_exit_shadow_segment',$copyId,$record,(int)$record['version']);
    err(fn()=>ProviderExitShadowCopyStage::copySegment($stage['id'],$segmentId,11,1),
        'provider_exit_shadow_journal_invalid',
        'R288 copied segment rejects forged '.$case['name'].' before trusting an idempotent replay');
    $verificationCode=$case['target']==='copy'
        ?'provider_exit_shadow_journal_invalid':'provider_exit_shadow_incomplete';
    err(fn()=>ProviderExitShadowCopyStage::verifyComplete($stage['id'],11),
        $verificationCode,
        'R288 full inventory verification rejects forged '.$case['name']);
    // Restore the canonical record without changing the source asset or bytes.
    RecordStore::put('provider_exit_shadow_segment',$copyId,$before,(int)$changed['version']);
    ok(ProviderExitShadowCopyStage::verifyComplete($stage['id'],11)['verified_objects']===1,
        'R288 genuine proof remains verifiable after removing forged '.$case['name']);
}
ok(ProviderRegistry::activeId()===$active
    &&RecordStore::all('provider_exit')===[]
    &&RecordStore::get('asset','r288-file17-private')['storage']['provider_id']==='source-private'
    &&$source->exists($key),
    'R288 all tampering trials are non-destructive and never switch native owner/provider');
echo "REVIEW ROUND 288 SHADOW-COPY JOURNAL IDENTITY: PASS\n";
