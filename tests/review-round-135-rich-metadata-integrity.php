<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RichMediaMetadataService,FutureAdapterRegistry,RecordStore};

$p=policy('video','C3',['view','download','transform','reprocess']);
$a=['actor_id'=>11,'asset_id'=>'r135','id'=>'r135','status'=>'ready','sha256'=>hash('sha256','r135'),'size'=>1024,'media_class'=>'video','mime'=>'video/mp4','owner_domain'=>'file17','owner_object'=>'message:r135','object_version'=>1,'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r135',$a);
err(fn()=>RichMediaMetadataService::chapters('r135',11,[['start_ms'=>'not-a-time','title'=>'Intro']]),'chapter_manifest_invalid','Round 135 chapter timecode rejects non-integer input');
err(fn()=>RichMediaMetadataService::captionTrack('r135',11,['locale'=>'ur-PK','kind'=>'sdh','content_hash'=>'not-sha','source'=>'human']),'caption_hash_invalid','Round 135 caption hash is integrity-bound');
err(fn()=>RichMediaMetadataService::audioDescription('r135',11,['locale'=>'ur-PK','track_ref'=>'asset:ad','content_hash'=>'bad']),'audio_description_hash_invalid','Round 135 audio-description hash is integrity-bound');
err(fn()=>RichMediaMetadataService::signLanguage('r135',11,['locale'=>'pks','track_ref'=>'asset:sl','synchronization_hash'=>'bad']),'sign_language_hash_invalid','Round 135 sign-language synchronization hash is integrity-bound');
FutureAdapterRegistry::reset();FutureAdapterRegistry::register('future_014',static fn()=>['ok'=>true,'poster_ref'=>'   ','storyboard_ref'=>'derivative:story','selection'=>[]]);
err(fn()=>RichMediaMetadataService::smartPreview('r135',11,[]),'smart_preview_invalid','Round 135 smart-preview references cannot be blank');

echo "REVIEW ROUND 135 RICH METADATA INTEGRITY: PASS\n";
