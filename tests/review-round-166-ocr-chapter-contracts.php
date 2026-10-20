<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RichMediaMetadataService};

$p=policy('video','C3',['view','download','transform','reprocess']);
RecordStore::put('asset','r166',['actor_id'=>11,'status'=>'ready','id'=>'r166','asset_id'=>'r166',
    'owner_domain'=>'file17','owner_object'=>'message:r166','object_version'=>1,
    'sha256'=>hash('sha256','r166'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$badChapters=[
    [['start_ms'=>'9999999999999999999999999999999999999999','title'=>'Overflow']],
    [['start_ms'=>'0001','title'=>'Noncanonical']],
    [['start_ms'=>1.5,'title'=>'Fraction']],
    [['start_ms'=>1,'title'=>['not text']]],
    [['start_ms'=>1,'title'=>'Intro'],['start_ms'=>1,'title'=>'Duplicate']],
    array_fill(0,10001,['start_ms'=>0,'title'=>'Huge'])
];
foreach($badChapters as $bad)
    err(fn()=>RichMediaMetadataService::chapters('r166',11,$bad),
        'chapter_manifest_invalid','Round 166 invalid chapter time/shape/count');
$chapters=RichMediaMetadataService::chapters('r166',11,[
    ['start_ms'=>0,'title'=>'Start'],['start_ms'=>'5000','title'=>'Next']]);
ok($chapters['data']['chapters'][1]['start_ms']===5000,
    'Round 166 canonical chapter strings normalize to integer');

$valid=['page'=>1,'items'=>[['text'=>'Test','confidence'=>0.98,'box'=>[0.1,0.1,0.2,0.05]]]];
$badPages=[
    [['page'=>'1garbage','items'=>$valid['items']]],
    [['page'=>1.5,'items'=>$valid['items']]],
    [['page'=>1,'items'=>$valid['items']],['page'=>1,'items'=>$valid['items']]],
    [['page'=>1,'items'=>[['text'=>'Test','confidence'=>'0.98','box'=>[0.1,0.1,0.2,0.05]]]]],
    [['page'=>1,'items'=>[['text'=>'Test','confidence'=>0.98,'box'=>['x'=>0.1,'y'=>0.1,'w'=>0.2,'h'=>0.05]]]]],
    [['page'=>1,'items'=>[['text'=>'Test','confidence'=>0.98,'box'=>['0.1',0.1,0.2,0.05]]]]],
    [['page'=>1,'items'=>[['text'=>'Test','confidence'=>0.98,'box'=>[0.9,0.1,0.2,0.05]]]]],
    [['page'=>1,'items'=>[['text'=>'Test','confidence'=>0.98,'box'=>[0.1,0.1,0.0,0.05]]]]],
    [['page'=>1,'items'=>[['confidence'=>0.98,'box'=>[0.1,0.1,0.2,0.05]]]]],
    [['page'=>1,'items'=>array_fill(0,2001,$valid['items'][0])]],
    array_fill(0,501,$valid)
];
foreach($badPages as $bad)
    err(fn()=>RichMediaMetadataService::ocrMap('r166',11,$bad),
        'ocr_map_invalid','Round 166 malformed OCR page/geometry/volume denied');
$ocr=RichMediaMetadataService::ocrMap('r166',11,[$valid]);
ok($ocr['status']==='active'&&$ocr['data']['pages'][0]['items'][0]['confidence']===0.98,
    'Round 166 valid OCR coordinate map remains supported');
ok(count(RecordStore::all('chapter_manifest'))===1&&count(RecordStore::all('ocr_coordinate_map'))===1,
    'Round 166 rejected payloads did not persist');
echo "REVIEW ROUND 166 OCR AND CHAPTER CONTRACTS: PASS\n";
