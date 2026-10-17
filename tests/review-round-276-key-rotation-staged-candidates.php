<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{KeyRotationInventoryStage,Keyring,RecordStore,ProviderRegistry};
RecordStore::resetMemory();
$old='test-v2';$active='test-v1';
for($i=0;$i<3000;$i++){
    $id=sprintf('r276-media-%05d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17',
        'storage'=>['provider_id'=>'source-private','key_id'=>$i<2301?$old:$active],
        'object_key'=>hash('sha256','r276-key-'.$i),
        'sha256'=>hash('sha256','r276-content-'.$i),'size'=>14
    ]);
}
RecordStore::put('derivative','r276-derivative',[
    'actor_id'=>11,'status'=>'validated','asset_id'=>'r276-media-00000',
    'storage'=>['provider_id'=>'source-private','key_id'=>$old],
    'object_key'=>hash('sha256','r276-derivative-key'),
    'sha256'=>hash('sha256','r276-derivative-bytes'),'size'=>24
]);
$oldProvider=ProviderRegistry::activeId();
$s=KeyRotationInventoryStage::begin(11);
$n=0;
while(($s['status']??null)==='planning'&&$n++<100){
    $s=KeyRotationInventoryStage::advance($s['id'],11);
}
ok(($s['status']??null)==='inventory_complete_unfenced'
    &&$s['stale_references']===2302&&$s['visited_rows']===3001
    &&$s['segments']===14,
    'R276 >2300 stale references are journaled across bounded source pages without re-encryption');
$segments=RecordStore::all('key_rotation_inventory_segment',0,null,100);
ok(count($segments)===$s['segments']&&ProviderRegistry::activeId()===$oldProvider
    &&RecordStore::all('key_rotation')===[],
    'R276 discovery has no key rotation run, ciphertext mutation or provider cutover');
$total=0;
foreach($segments as $seg){
    ok(count($seg['items'])<=250&&($seg['status']??null)==='candidate_unfenced',
        'R276 durable segment is strictly bounded and cannot run crypto work');
    $total+=count($seg['items']);
}
ok($total===2302,'R276 all 2302 stale candidates are preserved and current records excluded');
$same=KeyRotationInventoryStage::advance($s['id'],11);
ok($same['version']===$s['version'],'R276 completed stage advance idempotent');
$again=KeyRotationInventoryStage::begin(11);
$first=KeyRotationInventoryStage::advance($again['id'],11);
$segId=hash('sha256',$again['id'].'|asset|');
$seg=RecordStore::get('key_rotation_inventory_segment',$segId);
$rewound=$first;
$rewound['record_family']='asset';$rewound['after_id']='';$rewound['segments']=0;
$rewound['visited_rows']=0;$rewound['stale_references']=0;
$rewound['chain_hash']=str_repeat('0',64);
RecordStore::put('key_rotation_inventory',$first['id'],$rewound,(int)$first['version']);
$replayed=KeyRotationInventoryStage::advance($first['id'],11);
ok($replayed['segments']===1
    &&RecordStore::get('key_rotation_inventory_segment',$segId)['version']===$seg['version'],
    'R276 crash replay reuses exact durable original segment without duplicate append');
$rewound=$replayed;
$rewound['record_family']='asset';$rewound['after_id']='';$rewound['segments']=0;
$rewound['visited_rows']=0;$rewound['stale_references']=0;
$rewound['chain_hash']=str_repeat('0',64);
RecordStore::put('key_rotation_inventory',$replayed['id'],$rewound,(int)$replayed['version']);
$tampered=RecordStore::get('key_rotation_inventory_segment',$segId);
$tampered['items'][0]['object_key']=hash('sha256','r276-forgery');
RecordStore::put('key_rotation_inventory_segment',$segId,$tampered,(int)$tampered['version']);
err(fn()=>KeyRotationInventoryStage::advance($again['id'],11),
    'key_rotation_inventory_drift',
    'R276 resumed stale segment with altered object identity is rejected');
$keyChange=KeyRotationInventoryStage::begin(11);
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),'test-v2'=>str_repeat('m',64)],'test-v2');
err(fn()=>KeyRotationInventoryStage::advance($keyChange['id'],11),
    'key_rotation_inventory_key_changed',
    'R276 discovery cannot silently span active-key epoch changes');
echo "REVIEW ROUND 276 DURABLE KEY ROTATION CANDIDATE STAGING: PASS\n";
