<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,MediaOptimizationService,RecordStore};

$p=policy('video','C3',['view','download','reprocess']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r173','asset_id'=>'r173',
    'owner_domain'=>'file17','owner_object'=>'message:r173','object_version'=>1,
    'sha256'=>hash('sha256','r173'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r173',$a);
DomainRegistry::register('r173-deny','1.0.0',['authorize_reprocess'=>static fn()=>['allowed'=>false,'object_version'=>1]]);
$d=$a;$d['id']='r173-denied';$d['asset_id']='r173-denied';$d['owner_domain']='r173-deny';
RecordStore::put('asset','r173-denied',$d);
$input=['source_space'=>'bt2020','target_space'=>'srgb','hdr_mode'=>'tone_map',
    'icc_hash'=>hash('sha256','icc'),'tone_map'=>'approved-v1'];
err(fn()=>MediaOptimizationService::colorPolicy('r173-denied',11,$input),
    'domain_authorization_denied','Round 173 owner can deny color policy');
err(fn()=>MediaOptimizationService::colorPolicy('r173',11,array_replace($input,['source_space'=>['bt2020']])),
    'color_policy_invalid','Round 173 nontext color space denied');
err(fn()=>MediaOptimizationService::colorPolicy('r173',11,array_replace($input,['hdr_mode'=>'unknown'])),
    'color_policy_invalid','Round 173 unapproved HDR mode denied');
err(fn()=>MediaOptimizationService::colorPolicy('r173',11,array_replace($input,['tone_map'=>'none'])),
    'color_policy_invalid','Round 173 tone-map mode requires real profile');
err(fn()=>MediaOptimizationService::colorPolicy('r173',11,array_replace($input,['icc_hash'=>$input['icc_hash'].'suffix'])),
    'icc_hash_invalid','Round 173 overlong ICC hash denied');
err(fn()=>MediaOptimizationService::colorPolicy('r173',11,array_replace($input,['icc_hash'=>[$input['icc_hash']]])),
    'icc_hash_invalid','Round 173 nontext ICC hash denied');
ok(RecordStore::all('color_policy')===[],'Round 173 rejected policies not persisted');
$first=MediaOptimizationService::colorPolicy('r173',11,$input);
ok($first['status']==='active'&&$first['source_sha256']===$a['sha256']
    &&$first['rights_hash']===$p['rights']['policy_hash'],
    'Round 173 approved color policy binds current source');
$row=RecordStore::get('asset','r173');$row['object_version']=2;
RecordStore::put('asset','r173',$row,(int)$row['version']);
$second=MediaOptimizationService::colorPolicy('r173',11,$input);
ok($second['id']!==$first['id']&&count(RecordStore::all('color_policy'))===2,
    'Round 173 new source generation retains distinct color evidence');
echo "REVIEW ROUND 173 COLOR POLICY EVIDENCE: PASS\n";
