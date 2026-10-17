<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,RichMediaMetadataService};

RecordStore::resetMemory();
$p=policy('video','C3',['view','download']);
$asset=RecordStore::put('asset','r239-source',[
    'actor_id'=>11,'asset_id'=>'r239-source','status'=>'ready',
    'owner_domain'=>'file17','owner_object'=>'message:r239','object_version'=>1,
    'sha256'=>hash('sha256','r239-source'),'size'=>100,'media_class'=>'video',
    'mime'=>'video/mp4','privacy_class'=>'C3','policy'=>$p,
    'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights'],
]);

for($i=0;$i<2600;$i++){
    RecordStore::put('rich_caption',sprintf('r239-track-%05d',$i),[
        'actor_id'=>11,'status'=>'active','asset_id'=>'unrelated-'.$i,
        'owner_object_version'=>1,'policy_hash'=>$p['policy_hash'],
        'rights_hash'=>$p['rights']['policy_hash'],
    ]);
}
$valid=[
    'actor_id'=>11,'status'=>'active','asset_id'=>$asset['id'],
    'owner_object_version'=>1,'policy_hash'=>$p['policy_hash'],
    'rights_hash'=>$p['rights']['policy_hash'],
    'data'=>['locale'=>'ur-PK','kind'=>'sdh','content_hash'=>hash('sha256','r239-track')],
];
RecordStore::put('rich_caption','r239-track-02600',array_replace($valid,[
    'policy_hash'=>hash('sha256','different-policy')
]));
RecordStore::put('rich_caption','r239-track-02601',array_replace($valid,[
    'status'=>'retracted'
]));
RecordStore::put('rich_caption','r239-track-02602',$valid);

$manifest=RichMediaMetadataService::accessibilityManifest($asset['id']);
ok(($manifest['tracks']['rich_caption'][0]['id']??null)==='r239-track-02602'
    &&count($manifest['tracks']['rich_caption'])===1,
    'R239 manifest reaches a late valid caption through stable pages, excluding stale and retracted tracks');
ok($manifest['complete']===true&&strlen($manifest['manifest_hash'])===64,
    'R239 accessibility manifest remains complete and content-addressed');
foreach(['audio_description','sign_language_track','ocr_coordinate_map','chapter_manifest','accessibility_metadata'] as $type)
    ok($manifest['tracks'][$type]===[],'R239 no cross-type track leakage: '.$type);

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-future40.php');
ok(is_string($source)&&str_contains($source,'RecordStore::scan($type,250,$after)')
    &&!str_contains($source,'RecordStore::all($type,0,null,100000)'),
    'R239 accessibility projection no longer loads globally capped 100k track inventories');

echo "REVIEW ROUND 239 ACCESSIBILITY MANIFEST KEYSET: PASS\n";
