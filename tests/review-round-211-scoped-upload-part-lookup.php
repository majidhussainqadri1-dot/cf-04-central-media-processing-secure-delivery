<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,PartStore,ProviderRegistry,Utils};

RecordStore::resetMemory();
$uploadId='r211-target';
$otherUpload='r211-unrelated';
$policy=['max_upload_parts'=>12];
RecordStore::put('upload',$uploadId,[
    'actor_id'=>11,'status'=>'uploading','policy'=>$policy,
    'received_parts'=>0,'received_size'=>0
]);
// A different session's parts must not participate in this session's lookup.
RecordStore::put('upload',$otherUpload,[
    'actor_id'=>11,'status'=>'uploading','policy'=>['max_upload_parts'=>20000]
]);
for($i=1;$i<=2400;$i++){
    RecordStore::put('upload_part',hash('sha256',$otherUpload.'|'.$i),[
        'actor_id'=>11,'upload_id'=>$otherUpload,'part_number'=>$i,
        'status'=>'stored','size'=>1,'sha256'=>str_repeat('c',64)
    ]);
}
$store=ProviderRegistry::store();
foreach([7,2,12] as $n){
    $data="r211-part-$n";
    $handle=stream_of($data);
    $key=hash('sha256','r211-part-object-'.$n);
    $saved=$store->putStream($key,$handle,['scope'=>'upload-part']);
    fclose($handle);
    RecordStore::put('upload_part',hash('sha256',$uploadId.'|'.$n),[
        'actor_id'=>11,'upload_id'=>$uploadId,'part_number'=>$n,
        'object_key'=>$saved['object_key'],
        'provider_id'=>ProviderRegistry::activeId(),
        'status'=>'stored','size'=>strlen($data),'sha256'=>hash('sha256',$data)
    ]);
}
$parts=PartStore::list($uploadId);
ok(array_column($parts,'part_number')===[2,7,12],
    'R211 scoped primary-key lookup retrieves only own parts in strict ordinal order');
ok(count(RecordStore::getMany('upload_part',[
    hash('sha256',$uploadId.'|2'),hash('sha256',$uploadId.'|12')
]))===2,'R211 bounded record batch retrieves exact existing IDs');
err(fn()=>RecordStore::getMany('upload_part',array_fill(0,251,'repeated-id')),
    'record_batch_invalid','R211 oversized primary-key batch fails closed');
err(fn()=>RecordStore::getMany('upload_part',["id\n"]),'record_batch_invalid',
    'R211 noncanonical primary-key batch fails closed');
PartStore::purge($uploadId);
ok(PartStore::list($uploadId)===[],'R211 purge removes own part metadata');
ok(count(PartStore::list($otherUpload))===2400,
    'R211 purge never removes unrelated upload parts, even in a large unrelated inventory');
foreach([7,2,12] as $n){
    $key=hash('sha256','r211-part-object-'.$n);
    ok(!$store->exists($key),'R211 purge deletes stored part object '.$n);
}
$forgedId=hash('sha256',$uploadId.'|2');
RecordStore::put('upload_part',$forgedId,[
    'actor_id'=>11,'upload_id'=>'r211-spoofed-parent','part_number'=>2,
    'status'=>'stored','size'=>1
]);
err(fn()=>PartStore::list($uploadId),'upload_part_identity_invalid',
    'R211 scoped lookup refuses mismatched parent identity in stored metadata');
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-upload.php');
ok(!str_contains($source,"RecordStore::all('upload_part',0,null,100000)")
    &&str_contains($source,"RecordStore::getMany('upload_part'"),
    'R211 current PartStore source does not rely on the global 100k-part cap');
echo "REVIEW ROUND 211 SCOPED PART LOOKUP: PASS\n";
