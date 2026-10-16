<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{FutureAdapterRegistry,PerceptualMediaService,RecordStore};

RecordStore::resetMemory();
$p=policy('document','C3',['view','download','reprocess']);
$base=[
 'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','owner_object'=>'message:r242-source',
 'object_version'=>1,'sha256'=>hash('sha256','r242-binary'),'size'=>100,
 'media_class'=>'document','mime'=>'application/pdf','privacy_class'=>'C3',
 'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']
];
$source=RecordStore::put('asset','r242-source',$base);
$other=$base;
$other['owner_object']='message:r242-candidate';
$candidate=RecordStore::put('asset','r242-candidate',$other);
for($i=0;$i<2600;$i++){
    RecordStore::put('perceptual_fingerprint',sprintf('r242-fp-%05d',$i),[
      'actor_id'=>11,'status'=>'active','asset_id'=>'unrelated-'.$i,
      'algorithm'=>'unrelated','algorithm_version'=>'1',
      'fingerprint'=>'old','source_sha256'=>hash('sha256','unrelated-'.$i),
      'owner_object_version'=>1,'privacy_class'=>'C3',
      'owner_domain'=>'file17','policy_hash'=>$p['policy_hash'],
      'rights_hash'=>$p['rights']['policy_hash']
    ]);
}
$fp=['actor_id'=>11,'status'=>'active','source_sha256'=>$source['sha256'],
  'owner_object_version'=>1,'algorithm'=>'phash','algorithm_version'=>'1',
  'fingerprint'=>'phash:valid','privacy_class'=>'C3','owner_domain'=>'file17',
  'policy_hash'=>$p['policy_hash'],'rights_hash'=>$p['rights']['policy_hash']];
RecordStore::put('perceptual_fingerprint','r242-fp-02600',array_replace($fp,[
 'asset_id'=>'r242-source'
]));
RecordStore::put('perceptual_fingerprint','r242-fp-02601',array_replace($fp,[
 'asset_id'=>'r242-candidate'
]));
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_004',static fn()=>['ok'=>true,'similarity'=>1.0]);
$matches=PerceptualMediaService::nearDuplicates($source['id'],11,0.9);
ok(count($matches)===1&&$matches[0]['asset_id']===$candidate['id'],
    'R242 late source and candidate fingerprints match after 2600 unrelated records');
$changed=RecordStore::get('asset',$candidate['id']);
$changed['rights']['policy_hash']=hash('sha256','r242-other-rights');
RecordStore::put('asset',$candidate['id'],$changed,(int)$changed['version']);
ok(PerceptualMediaService::nearDuplicates($source['id'],11,0.9)===[],
    'R242 changed recipient rights invalidates a previously matching fingerprint');
$code=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-future40.php');
ok(str_contains((string)$code,"RecordStore::scan('perceptual_fingerprint',250,\$after,0,'active')")
   &&!str_contains((string)$code,"RecordStore::all('perceptual_fingerprint',0,'active',100000)"),
   'R242 source no longer materializes global 100k perceptual inventory');
echo "REVIEW ROUND 242 PERCEPTUAL KEYSET DUPLICATES: PASS\n";
