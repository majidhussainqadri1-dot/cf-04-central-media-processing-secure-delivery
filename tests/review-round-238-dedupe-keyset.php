<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{RecordStore,UploadService};

RecordStore::resetMemory();
$upload=[
    'expected_sha256'=>hash('sha256','r238-binary-content'),
    'policy_hash'=>hash('sha256','r238-policy'),
    'policy'=>[
        'privacy_class'=>'C3',
        'rights'=>['policy_hash'=>hash('sha256','r238-rights')],
    ],
    'owner_domain'=>'file17',
    'owner_object'=>'message:r238-owner',
];
$eligible=[
    'actor_id'=>11,
    'status'=>'ready',
    'sha256'=>$upload['expected_sha256'],
    'policy_hash'=>$upload['policy_hash'],
    'privacy_class'=>$upload['policy']['privacy_class'],
    'rights'=>$upload['policy']['rights'],
    'owner_domain'=>$upload['owner_domain'],
    'owner_object'=>$upload['owner_object'],
];

for($i=0;$i<2600;$i++){
    RecordStore::put('asset',sprintf('r238-asset-%05d',$i),[
        'actor_id'=>11,'status'=>'ready',
        'sha256'=>hash('sha256','unrelated-'.$i),
        'policy_hash'=>$upload['policy_hash'],
    ]);
}
RecordStore::put('asset','r238-asset-02600',array_replace($eligible,[
    'status'=>'deleted',
]));
RecordStore::put('asset','r238-asset-02601',array_replace($eligible,[
    'rights'=>['policy_hash'=>hash('sha256','different-rights')],
]));
RecordStore::put('asset','r238-asset-02602',array_replace($eligible,[
    'owner_object'=>'message:not-the-same-owner',
]));
RecordStore::put('asset','r238-asset-02603',$eligible);

$method=new ReflectionMethod(UploadService::class,'dedupe');
$match=$method->invoke(null,$upload);
ok(($match['id']??null)==='r238-asset-02603',
    'R238 250-record keyset traversal reaches eligible duplicate after 2600 other assets');
ok(($match['rights']['policy_hash']??null)===$upload['policy']['rights']['policy_hash'],
    'R238 duplicate remains rights-bound, excludes different rights and terminal records');

$other=$upload;
$other['owner_object']='message:some-other-owner';
ok($method->invoke(null,$other)===null,
    'R238 no cross-owner duplicate is returned even when content hash is identical');

$other=$upload;
$other['policy']['rights']['policy_hash']=hash('sha256','different-rights');
ok(($method->invoke(null,$other)['id']??null)==='r238-asset-02601',
    'R238 matching only the authorized rights fingerprint remains deterministic');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-upload.php');
ok(is_string($source)
    &&str_contains($source,"RecordStore::scan('asset',250,\$after)")
    &&!str_contains($source,"RecordStore::all('asset',0,null,100000)"),
    'R238 source no longer loads or caps the entire asset inventory on upload completion');

echo "REVIEW ROUND 238 DEDUPLICATION KEYSET: PASS\n";
