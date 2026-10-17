<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationService,RecordStore};

RecordStore::resetMemory();
for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r246-asset-%05d',$i),[
        'actor_id'=>11,'status'=>'deleted','storage'=>['key_id'=>'test-v1']
    ]);
}
$late='r246-asset-02600';$sha=hash('sha256','r246-late-source');
RecordStore::put('asset',$late,[
    'actor_id'=>11,'status'=>'ready','sha256'=>$sha,
    'storage'=>['key_id'=>'test-v1'],
]);
$envId=hash('sha256',$late);
$envelope=RecordStore::put('asset_key_envelope',$envId,[
    'actor_id'=>11,'status'=>'active','asset_id'=>$late,
    'asset_sha256'=>$sha,'key_id'=>'old-key',
    'algorithm'=>'old-policy'
]);
$method=new ReflectionMethod(KeyRotationService::class,'reconcileCurrentEnvelopes');
$updated=$method->invoke(null,'test-v1','r246-rotation');
$after=RecordStore::get('asset_key_envelope',$envId);
ok($updated===1&&$after['key_id']==='test-v1'
    &&$after['algorithm']==='aes-256-gcm'
    &&$after['rotation_id']==='r246-rotation',
    'R246 active key envelope is repaired after ten 250-record pages');
ok($method->invoke(null,'test-v1','r246-noop')===0,
    'R246 once-reconciled envelope is stable on repeated pass');

$asset2='r246-asset-02601';$sha2=hash('sha256','r246-other-source');
RecordStore::put('asset',$asset2,[
    'actor_id'=>11,'status'=>'ready','sha256'=>$sha2,
    'storage'=>['key_id'=>'test-v1'],
]);
RecordStore::put('asset_key_envelope',hash('sha256',$asset2),[
    'actor_id'=>11,'status'=>'active','asset_id'=>$asset2,
    'asset_sha256'=>hash('sha256','forged-digest'),
    'key_id'=>'old-key','algorithm'=>'old-policy'
]);
err(fn()=>$method->invoke(null,'test-v1','r246-retry'),
    'asset_key_envelope_stale',
    'R246 late forged envelope fails closed rather than silently assuming parity');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-operations.php');
ok(str_contains((string)$source,"RecordStore::scan('asset',250,\$after)")
    &&!str_contains((string)$source,"foreach(RecordStore::all('asset',0,null,100000) as \$asset){if((\$asset['status']??'')==='deleted'||empty(\$asset['storage']['key_id'])"),
    'R246 old global 100k envelope inventory cap removed');
echo "REVIEW ROUND 246 ENVELOPE RECONCILIATION KEYSET: PASS\n";
