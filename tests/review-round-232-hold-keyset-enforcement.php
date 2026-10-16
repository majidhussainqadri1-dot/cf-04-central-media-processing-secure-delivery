<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,LegalHoldService,Utils};

RecordStore::resetMemory();
$future=Utils::now()+86400;
for($i=0;$i<2530;$i++){
    $id=sprintf('r232-%05d',$i);
    RecordStore::put('hold',$id,[
        'actor_id'=>11,'hold_id'=>$id,
        'asset_id'=>$i===2529?'r232-sensitive':'r232-other',
        'status'=>$i===2529?'active':'released',
        'scope'=>['deletion'],'access_restriction'=>'blocked',
        'object_version'=>1,'version_number'=>1,
        'authority'=>'review-admin','reason'=>'test consent withdrawal',
        'review_at'=>$future,'expires_at'=>0
    ]);
}
$holds=LegalHoldService::active('r232-sensitive','delete');
ok(count($holds)===1&&$holds[0]['id']==='r232-02529',
    'R232 legal hold after ten full pages is still visible for deletion');
err(fn()=>LegalHoldService::assertNoHold('r232-sensitive','delete'),'asset_on_hold',
    'R232 late legal hold denies destructive deletion');
ok(LegalHoldService::active('r232-other','delete')===[],
    'R232 released historical legal holds never falsely block other assets');
$hold=RecordStore::get('hold','r232-02529');
$hold['status']='released';
RecordStore::put('hold','r232-02529',$hold,(int)$hold['version']);
ok(LegalHoldService::active('r232-sensitive','delete')===[],
    'R232 fully released hold no longer prevents authorized deletion');
$broken=RecordStore::get('hold','r232-02500');
$broken['asset_id']='';
RecordStore::put('hold','r232-02500',$broken,(int)$broken['version']);
err(fn()=>LegalHoldService::active('r232-sensitive','delete'),'hold_record_invalid',
    'R232 malformed persisted hold is never silently skipped during keyset traversal');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-lifecycle.php');
ok(!str_contains($source,"RecordStore::all('hold',0,null,100000)")
    &&str_contains($source,"RecordStore::scan('hold',250"),
    'R232 legal holds no longer depend on a hard 100k global-record ceiling');
echo "REVIEW ROUND 232 LEGAL HOLD KEYSET ENFORCEMENT: PASS\n";
