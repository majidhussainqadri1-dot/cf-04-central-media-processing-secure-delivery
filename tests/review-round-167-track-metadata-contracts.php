<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{FutureAdapterRegistry,RecordStore,RichMediaMetadataService};

$p=policy('video','C3',['view','download','transform','reprocess']);
RecordStore::put('asset','r167',['actor_id'=>11,'status'=>'ready','id'=>'r167','asset_id'=>'r167',
    'owner_domain'=>'file17','owner_object'=>'message:r167','object_version'=>1,
    'sha256'=>hash('sha256','r167'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4',
    'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']]);
$hash=hash('sha256','caption');
$caption=['kind'=>'sdh','locale'=>'ur-PK','content_hash'=>$hash,'source'=>'human'];
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['source'=>'unapproved'])),
    'caption_source_invalid','Round 167 denies unapproved caption source');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['source'=>['human']])),
    'caption_source_invalid','Round 167 rejects nontext source');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['kind'=>['sdh']])),
    'caption_kind_invalid','Round 167 rejects nontext kind');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['locale'=>['ur-PK']])),
    'track_locale_invalid','Round 167 rejects nontext locale');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['content_hash'=>$hash.'suffix'])),
    'caption_hash_invalid','Round 167 rejects overlong hash');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,array_replace($caption,['content_hash'=>[$hash]])),
    'caption_hash_invalid','Round 167 rejects nontext hash');
err(fn()=>RichMediaMetadataService::captionTrack('r167',11,$caption+['unexpected_field'=>'unapproved']),
    'track_metadata_invalid','Round 167 rejects extra caption field');
ok(RichMediaMetadataService::captionTrack('r167',11,$caption)['status']==='active',
    'Round 167 valid caption persists');

$audio=['locale'=>'ur-PK','track_ref'=>'asset:audio-description','content_hash'=>hash('sha256','audio')];
foreach(['https://outside.invalid/track','../other/path',['asset:audio-description']] as $bad)
    err(fn()=>RichMediaMetadataService::audioDescription('r167',11,array_replace($audio,['track_ref'=>$bad])),
        'track_reference_invalid','Round 167 rejects non-internal audio reference');
err(fn()=>RichMediaMetadataService::audioDescription('r167',11,$audio+['unexpected_field'=>'unapproved']),
    'track_metadata_invalid','Round 167 rejects extra audio field');
ok(RichMediaMetadataService::audioDescription('r167',11,$audio)['status']==='active',
    'Round 167 valid audio persists');
$sign=['locale'=>'pks','track_ref'=>'asset:sign-language','synchronization_hash'=>hash('sha256','sync')];
err(fn()=>RichMediaMetadataService::signLanguage('r167',11,array_replace($sign,['track_ref'=>'https://outside.invalid'])),
    'track_reference_invalid','Round 167 rejects external sign reference');
err(fn()=>RichMediaMetadataService::signLanguage('r167',11,array_replace($sign,['synchronization_hash'=>$sign['synchronization_hash'].'x'])),
    'sign_language_hash_invalid','Round 167 rejects overlong sign hash');
ok(RichMediaMetadataService::signLanguage('r167',11,$sign)['status']==='active',
    'Round 167 valid sign track persists');

FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_014',static fn()=>[
    'ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:story',
    'selection'=>['method'=>'scene-change','unexpected_field'=>'unapproved']]);
err(fn()=>RichMediaMetadataService::smartPreview('r167',11,[]),'smart_preview_invalid',
    'Round 167 rejects unapproved preview selection');
ok(RecordStore::all('smart_preview')===[],'Round 167 rejected preview did not persist');
FutureAdapterRegistry::reset();
FutureAdapterRegistry::register('future_014',static fn()=>[
    'ok'=>true,'poster_ref'=>'derivative:poster','storyboard_ref'=>'derivative:story',
    'selection'=>['method'=>'scene-change']]);
ok(RichMediaMetadataService::smartPreview('r167',11,[])['status']==='active',
    'Round 167 bounded preview persists');
ok(count(RecordStore::all('rich_caption'))===1&&count(RecordStore::all('audio_description'))===1
    &&count(RecordStore::all('sign_language_track'))===1&&count(RecordStore::all('smart_preview'))===1,
    'Round 167 rejected records not persisted');
echo "REVIEW ROUND 167 TRACK METADATA CONTRACTS: PASS\n";
