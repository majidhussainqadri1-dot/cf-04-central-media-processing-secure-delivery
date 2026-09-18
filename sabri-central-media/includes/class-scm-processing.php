<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class JobService {
    public const PRIORITIES=['security'=>100,'revocation'=>95,'deletion'=>90,'interactive'=>70,'normal'=>50,'bulk'=>20,'maintenance'=>10];
    public static function graph(string $assetId,array $policy,int $generation=1): array {$generation=max(1,$generation);
        $nodes=[['name'=>'probe','depends'=>[],'priority'=>'security'],['name'=>'scan','depends'=>['probe'],'priority'=>'security'],['name'=>'metadata','depends'=>['scan'],'priority'=>'normal'],['name'=>'transform','depends'=>['metadata'],'priority'=>'normal'],['name'=>'validate_outputs','depends'=>['transform'],'priority'=>'security'],['name'=>'store_derivatives','depends'=>['validate_outputs'],'priority'=>'normal'],['name'=>'manifest_switch','depends'=>['store_derivatives'],'priority'=>'interactive']];
        $ids=[];foreach($nodes as $node){$id=hash('sha256',$assetId.'|'.$node['name'].'|'.$policy['policy_hash'].'|'.$generation);$ids[$node['name']]=$id;$existing=RecordStore::get('job',$id);if($existing){if(($existing['asset_id']??'')!==$assetId||($existing['job_type']??'')!==$node['name']||(int)($existing['processing_generation']??0)!==$generation||($existing['tenant']??'')!==$policy['owner_domain'])throw new Error('job_identity_conflict','Existing processing job identity conflicts.',409);continue;}try{RecordStore::put('job',$id,['actor_id'=>0,'job_id'=>$id,'asset_id'=>$assetId,'job_type'=>$node['name'],'depends_on'=>$node['depends'],'priority'=>$node['priority'],'priority_weight'=>self::PRIORITIES[$node['priority']],'tenant'=>$policy['owner_domain'],'processing_generation'=>$generation,'status'=>'queued','attempts'=>0,'max_attempts'=>5,'next_attempt_at'=>Utils::now(),'created_at'=>Utils::now()],0);}catch(Error $createError){if($createError->errorCode!=='record_version_conflict')throw $createError;$winner=RecordStore::get('job',$id);if(!$winner||($winner['asset_id']??'')!==$assetId||($winner['job_type']??'')!==$node['name']||(int)($winner['processing_generation']??0)!==$generation||($winner['tenant']??'')!==$policy['owner_domain'])throw new Error('job_identity_conflict','Concurrent processing job identity conflicts.',409);}}return $ids;
    }
    public static function lease(string $workerId,array $capabilities,int $leaseSeconds=120): ?array {
        $workerId=Utils::text($workerId,96);$capabilities=array_values(array_filter(array_unique(array_map(fn($value)=>Utils::key((string)$value,64),$capabilities))));if($workerId===''||$capabilities===[])throw new Error('worker_identity_invalid','Worker identity and capabilities are required.',400);$now=Utils::now();$queued=RecordStore::all('job',0,null,100000);$eligible=[];foreach($queued as $job){if(!in_array(($job['status']??''),['queued','retry'],true)||(int)($job['next_attempt_at']??0)>$now)continue;if(!in_array((string)$job['job_type'],$capabilities,true))continue;if(!self::dependenciesComplete($job))continue;$age=max(0,$now-(int)($job['created_at']??$now));$fairness=min(30,(int)floor($age/60));$tenantPenalty=self::tenantActiveLeases((string)$job['tenant'])*10;$job['_score']=(int)$job['priority_weight']+$fairness-$tenantPenalty;$eligible[]=$job;}if($eligible===[])return null;usort($eligible,fn($a,$b)=>$b['_score']<=>$a['_score'] ?: ((int)$a['created_at']<=>(int)$b['created_at']));foreach($eligible as $job){unset($job['_score']);$fresh=RecordStore::get('job',(string)$job['id']);if(!$fresh||!in_array(($fresh['status']??''),['queued','retry'],true)||(int)($fresh['next_attempt_at']??0)>$now||!in_array((string)$fresh['job_type'],$capabilities,true)||!self::dependenciesComplete($fresh))continue;$fresh['status']='leased';$fresh['lease_owner']=$workerId;$leaseToken=Utils::id('lease');$fresh['lease_token_hash']=hash('sha256',$leaseToken);$fresh['lease_expires_at']=$now+max(30,min(600,$leaseSeconds));$fresh['heartbeat_at']=$now;$fresh['attempts']=(int)$fresh['attempts']+1;try{$saved=RecordStore::put('job',(string)$fresh['id'],$fresh,(int)$fresh['version']);return $saved+['lease_token'=>$leaseToken];}catch(Error $leaseError){if($leaseError->errorCode!=='record_version_conflict')throw $leaseError;}}return null;
    }
    private static function tenantActiveLeases(string $tenant): int {return count(array_filter(RecordStore::all('job',0,null,100000),fn($j)=>($j['tenant']??'')===$tenant&&($j['status']??'')==='leased'&&(int)($j['lease_expires_at']??0)>Utils::now()));}
    private static function dependenciesComplete(array $job): bool {$asset=RecordStore::get('asset',(string)($job['asset_id']??''));if(!$asset||((int)($job['processing_generation']??1)!==(int)($asset['processing_generation']??1)))return false;foreach((array)($job['depends_on']??[]) as $name){$id=(string)($asset['job_graph'][$name]??'');$dep=$id!==''?RecordStore::get('job',$id):null;if(!$dep||($dep['status']??'')!=='completed')return false;}return true;}
    public static function leaseSpecific(string $jobId,string $workerId,int $leaseSeconds=120): array {
        $workerId=Utils::text($workerId,96);if($workerId==='')throw new Error('worker_identity_invalid','Worker identity is required.',400);$job=RecordStore::get('job',$jobId);if(!$job||!in_array(($job['status']??''),['queued','retry'],true)||(int)($job['next_attempt_at']??0)>Utils::now()||!self::dependenciesComplete($job))throw new Error('job_not_leaseable','Requested job is not leaseable.',409,['job_id'=>$jobId]);$job['status']='leased';$job['lease_owner']=$workerId;$leaseToken=Utils::id('lease');$job['lease_token_hash']=hash('sha256',$leaseToken);$job['lease_expires_at']=Utils::now()+max(30,min(600,$leaseSeconds));$job['heartbeat_at']=Utils::now();$job['attempts']=(int)$job['attempts']+1;$saved=RecordStore::put('job',$jobId,$job,(int)$job['version']);return $saved+['lease_token'=>$leaseToken];
    }
    public static function heartbeat(string $jobId,string $leaseToken,int $extend=120): array {$j=RecordStore::get('job',$jobId);if(!$j||($j['status']??'')!=='leased'||!hash_equals((string)($j['lease_token_hash']??''),hash('sha256',$leaseToken)))throw new Error('job_lease_invalid','Job lease invalid.',409);if((int)$j['lease_expires_at']<=Utils::now())throw new Error('job_lease_expired','Job lease expired.',409);$j['heartbeat_at']=Utils::now();$j['lease_expires_at']=Utils::now()+max(30,min(600,$extend));return RecordStore::put('job',$jobId,$j,(int)$j['version']);}
    public static function complete(string $jobId,string $leaseToken,array $result=[]): array {$job=RecordStore::get('job',$jobId);if(!$job||($job['status']??'')!=='leased'||!hash_equals((string)($job['lease_token_hash']??''),hash('sha256',$leaseToken)))throw new Error('job_lease_invalid','Job lease invalid.',409);if((int)($job['lease_expires_at']??0)<=Utils::now())throw new Error('job_lease_expired','Job lease expired.',409);$job['status']='completed';$job['result']=Utils::redact($result);$job['completed_at']=Utils::now();unset($job['lease_token_hash'],$job['lease_owner'],$job['lease_expires_at']);return RecordStore::put('job',$jobId,$job,(int)$job['version']);}
    public static function fail(string $jobId,string $leaseToken,string $code,bool $retryable=true): array {$job=RecordStore::get('job',$jobId);if(!$job||($job['status']??'')!=='leased'||!hash_equals((string)($job['lease_token_hash']??''),hash('sha256',$leaseToken)))throw new Error('job_lease_invalid','Job lease invalid.',409);if((int)($job['lease_expires_at']??0)<=Utils::now())throw new Error('job_lease_expired','Job lease expired.',409);$job['last_error']=Utils::key($code,64);$job['failed_at']=Utils::now();if($retryable&&(int)$job['attempts']<(int)$job['max_attempts']){$job['status']='retry';$job['next_attempt_at']=Utils::now()+min(3600,2**(int)$job['attempts']*15);}else{$job['status']='dead_letter';$job['dead_lettered_at']=Utils::now();}unset($job['lease_token_hash'],$job['lease_owner'],$job['lease_expires_at']);return RecordStore::put('job',$jobId,$job,(int)$job['version']);}
    public static function recoverOrphans(): int {$count=0;foreach(RecordStore::all('job',0,null,100000) as $j){if(($j['status']??'')==='leased'&&(int)($j['lease_expires_at']??0)<=Utils::now()){$j['status']=(int)$j['attempts']<(int)$j['max_attempts']?'retry':'dead_letter';$j['next_attempt_at']=Utils::now();$j['last_error']='lease_expired';unset($j['lease_token_hash'],$j['lease_owner'],$j['lease_expires_at']);try{RecordStore::put('job',(string)$j['id'],$j,(int)$j['version']);$count++;}catch(Error $recoverError){if($recoverError->errorCode!=='record_version_conflict')throw $recoverError;}}}return $count;}
    public static function retryDeadLetter(string $jobId,int $actor,string $reason): array {Auth::capability('media_reprocess');Auth::assertActor($actor,'manage_options');$reason=Utils::text($reason,500);if($reason==='')throw new Error('operator_reason_required','A retry reason is required.',400);$job=RecordStore::get('job',$jobId);if(!$job||($job['status']??'')!=='dead_letter')throw new Error('dead_letter_not_found','Dead-letter job not found.',404);$job['status']='queued';$job['attempts']=0;$job['next_attempt_at']=Utils::now();$job['operator_reason']=$reason;$job['operator_id']=$actor;return RecordStore::put('job',$jobId,$job,(int)$job['version']);}
    public static function awaitSafetyReview(string $jobId,string $leaseToken,string $code,string $signalId): array {$job=RecordStore::get('job',$jobId);if(!$job||($job['status']??'')!=='leased'||!hash_equals((string)($job['lease_token_hash']??''),hash('sha256',$leaseToken)))throw new Error('job_lease_invalid','Job lease invalid.',409);if((int)($job['lease_expires_at']??0)<=Utils::now())throw new Error('job_lease_expired','Job lease expired.',409);$job['status']='waiting_review';$job['last_error']=Utils::key($code,64);$job['review_signal_id']=Utils::text($signalId,96);$job['review_wait_started_at']=$job['review_wait_started_at']??Utils::now();unset($job['lease_token_hash'],$job['lease_owner'],$job['lease_expires_at']);return RecordStore::put('job',$jobId,$job,(int)$job['version']);}
    public static function resumeSafetyReview(string $jobId,string $signalId): array {$job=RecordStore::get('job',$jobId);if(!$job||($job['status']??'')!=='waiting_review')return $job??throw new Error('job_missing','Processing job missing.',500);if((string)($job['review_signal_id']??'')!==$signalId)throw new Error('safety_review_signal_mismatch','Waiting job is bound to a different safety signal.',409);$signal=RecordStore::get('safety_signal',$signalId);if(!$signal)throw new Error('safety_signal_missing','Safety signal missing.',409);$status=(string)($signal['status']??'pending_review');if(in_array($status,['accepted','informational'],true)){$job['status']='queued';$job['next_attempt_at']=Utils::now();unset($job['review_signal_id'],$job['review_wait_started_at']);return RecordStore::put('job',$jobId,$job,(int)$job['version']);}if($status==='rejected'){$job['status']='dead_letter';$job['last_error']='safety_review_rejected';$job['dead_lettered_at']=Utils::now();return RecordStore::put('job',$jobId,$job,(int)$job['version']);}return $job;}
}

final class DerivativeService {
    public static function store(string $assetId,string $kind,$stream,array $lineage,array $policy): array {
    if(!is_resource($stream))throw new Error('derivative_stream_invalid','Derivative output stream invalid.',500);
    $kind=Utils::key($kind,64);
    if($assetId===''||$kind==='')throw new Error('derivative_identity_invalid','Derivative identity invalid.',400);
    $stats=Utils::streamHash($stream);
    if($stats['size']<1)throw new Error('derivative_empty','Derivative output is empty.',422);
    $mime=self::mime($kind,$lineage,$policy);
    $id=Utils::id('drv');
    $objectKey=hash('sha256','derivative|'.$assetId.'|'.$id.'|'.$kind.'|'.$stats['sha256'].'|'.$policy['policy_hash']);
    $providerId=ProviderRegistry::activeId();ResidencyCryptoService::assertProviderRegion($assetId,$providerId);$store=ProviderRegistry::store($providerId);
    $stored=$store->putStream($objectKey,$stream,['scope'=>'derivative','asset_id'=>$assetId,'kind'=>$kind,'mime'=>$mime,'privacy_class'=>$policy['privacy_class']]);
    $stored['provider_id']=$providerId;
    $record=['actor_id'=>0,'derivative_id'=>$id,'asset_id'=>$assetId,'kind'=>$kind,'mime'=>$mime,'status'=>'validated','sha256'=>$stats['sha256'],'size'=>$stats['size'],'object_key'=>$stored['object_key'],'storage'=>$stored,'lineage'=>self::lineage($lineage+['mime'=>$mime]),'created_at'=>Utils::now(),'superseded_by'=>null];
    try{return RecordStore::put('derivative',$id,$record);}
    catch(\Throwable $exception){try{$store->delete((string)$stored['object_key']);}catch(\Throwable){}throw $exception;}
}

    private static function mime(string $kind,array $lineage,array $policy): string {
        $map=['thumbnail'=>'image/webp','small'=>'image/webp','medium'=>'image/webp','large'=>'image/avif','preview'=>'image/png','text'=>'text/plain; charset=utf-8','ocr'=>'text/plain; charset=utf-8','hls-manifest'=>'application/vnd.apple.mpegurl','hls-segment'=>'video/mp2t','dash-manifest'=>'application/dash+xml','poster'=>'image/jpeg','waveform'=>'application/json'];
        $mime=strtolower(trim((string)($lineage['mime']??($map[$kind]??($policy['allowed_mime_types'][0]??'application/octet-stream')))));
        if($mime===''||str_contains($mime,"\r")||str_contains($mime,"\n")||!preg_match('~^[a-z0-9][a-z0-9!#$&^_.+-]*/[a-z0-9][a-z0-9!#$&^_.+-]*(?:;\s*charset=utf-8)?$~i',$mime))throw new Error('derivative_mime_invalid','Derivative MIME type invalid.',422,['kind'=>$kind]);
        return $mime;
    }

    private static function lineage(array $lineage): array {
    Utils::requireFields($lineage,['source_sha256','job_id','tool','tool_version','preset','preset_version','mime'],'lineage_incomplete');
    $out=['source_sha256'=>(string)$lineage['source_sha256'],'job_id'=>Utils::text((string)$lineage['job_id'],96),'tool'=>Utils::text((string)$lineage['tool'],96),'tool_version'=>Utils::text((string)$lineage['tool_version'],64),'preset'=>Utils::text((string)$lineage['preset'],96),'preset_version'=>Utils::text((string)$lineage['preset_version'],64),'mime'=>strtolower(trim((string)$lineage['mime'])),'processing_generation'=>max(1,(int)($lineage['processing_generation']??1)),'repair_id'=>Utils::text((string)($lineage['repair_id']??''),96),'dimensions'=>$lineage['dimensions']??null,'bitrate'=>$lineage['bitrate']??null,'pages'=>$lineage['pages']??null,'duration'=>$lineage['duration']??null,'created_at'=>Utils::now()];
    if(!preg_match('/^[a-f0-9]{64}$/',$out['source_sha256'])||$out['job_id']===''||$out['tool']===''||$out['tool_version']===''||$out['preset']===''||$out['preset_version']===''||$out['mime']==='')throw new Error('lineage_invalid','Derivative lineage invalid.',500);
    return $out;
}
    public static function manifest(string $assetId,array $derivativeIds,int $expectedVersion): array {
    $asset=RecordStore::get('asset',$assetId);
    if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    if((int)($asset['manifest_version']??0)!==$expectedVersion)throw new Error('manifest_version_conflict','Asset manifest changed concurrently.',409,['expected'=>$expectedVersion,'actual'=>(int)($asset['manifest_version']??0)]);
    $derivativeIds=array_values(array_unique(array_map('strval',$derivativeIds)));
    if($derivativeIds===[])throw new Error('derivative_set_empty','No derivatives supplied for manifest.',422);
    $currentGeneration=max(1,(int)($asset['processing_generation']??1));$carryover=[];$activeId=(string)($asset['active_manifest_id']??'');
    if($activeId!==''){$active=RecordStore::get('manifest',$activeId);if($active&&($active['status']??'')==='active')foreach((array)($active['derivatives']??[]) as $item){$carryId=(string)($item['derivative_id']??'');if($carryId!=='')$carryover[$carryId]=true;}}
    $items=[];$kinds=[];
    foreach($derivativeIds as $id){
        $derivative=RecordStore::get('derivative',$id);
        if(!$derivative||($derivative['asset_id']??'')!==$assetId||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))throw new Error('derivative_not_validated','Derivative is not validated.',409,['derivative_id'=>$id]);
        $generation=max(1,(int)($derivative['lineage']['processing_generation']??1));if($generation!==$currentGeneration&&!isset($carryover[$id]))throw new Error('derivative_generation_stale','Derivative is not from the current processing generation or the active-manifest carryover set.',409,['derivative_id'=>$id,'generation'=>$generation,'current_generation'=>$currentGeneration]);
        if(isset($kinds[$derivative['kind']]))throw new Error('derivative_kind_duplicate','Manifest contains duplicate derivative kinds.',409,['kind'=>$derivative['kind']]);
        $kinds[$derivative['kind']]=true;
        $items[]=['derivative_id'=>$derivative['id'],'kind'=>$derivative['kind'],'mime'=>$derivative['mime'],'sha256'=>$derivative['sha256'],'size'=>$derivative['size'],'lineage'=>$derivative['lineage']];
    }
    $manifestId=Utils::id('man');
    $manifest=['actor_id'=>0,'manifest_id'=>$manifestId,'asset_id'=>$assetId,'manifest_version'=>$expectedVersion+1,'processing_generation'=>(int)($asset['processing_generation']??1),'status'=>'pending_switch','derivatives'=>$items,'created_at'=>Utils::now()];
    $manifest=RecordStore::put('manifest',$manifestId,$manifest);
    $old=$asset['active_manifest_id']??null;$original=$asset;
    $asset['active_manifest_id']=$manifestId;$asset['manifest_version']=$manifest['manifest_version'];$asset['processing_status']='completed';$asset['status']='ready';$asset['ready_at']=Utils::now();
    unset($asset['reprocess_context']);
    $savedAsset=null;
    try{$savedAsset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}
    catch(\Throwable $exception){RecordStore::delete('manifest',$manifestId);throw $exception;}
    try{$manifest['status']='active';$manifest=RecordStore::put('manifest',$manifestId,$manifest,(int)$manifest['version']);}
    catch(\Throwable $exception){try{$current=RecordStore::get('asset',$assetId);if($current&&($current['active_manifest_id']??null)===$manifestId){$rollback=$current;$rollback['active_manifest_id']=$old;$rollback['manifest_version']=$original['manifest_version']??0;$rollback['processing_status']=$original['processing_status']??'pending';$rollback['status']=$original['status']??'quarantined';if(isset($original['ready_at']))$rollback['ready_at']=$original['ready_at'];else unset($rollback['ready_at']);RecordStore::put('asset',$assetId,$rollback,(int)$current['version']);}}catch(\Throwable $rollbackError){Audit::record('manifest_switch_reconciliation_required',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'reason'=>'activation_failed_rollback_failed']);}try{$failed=RecordStore::get('manifest',$manifestId);if($failed){$failed['status']='activation_failed';RecordStore::put('manifest',$manifestId,$failed,(int)$failed['version']);}}catch(\Throwable){}throw $exception;}
    if($old){$oldManifest=RecordStore::get('manifest',(string)$old);if($oldManifest&&($oldManifest['status']??'')==='active'){try{$oldManifest['status']='superseded';$oldManifest['superseded_by']=$manifestId;RecordStore::put('manifest',(string)$old,$oldManifest,(int)$oldManifest['version']);}catch(\Throwable){Audit::record('manifest_supersede_reconciliation_required',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'previous_manifest'=>$old]);}}}
    Audit::record('manifest_atomically_switched',['asset_id'=>$assetId,'manifest_id'=>$manifestId,'previous_manifest'=>$old,'processing_generation'=>$manifest['processing_generation']]);
    return $manifest;
}
    public static function forAsset(string $assetId): array {return array_values(array_filter(RecordStore::all('derivative',0,null,200000),fn($d)=>($d['asset_id']??'')===$assetId));}
}

final class ImagePipeline {
    public static function run(array $asset,$source,string $jobId): array {$policy=$asset['policy'];$meta=MetadataPolicy::transform('image',['stream'=>$source,'mime'=>$asset['mime'],'sha256'=>$asset['sha256']],$policy);$clean=$meta['output_stream'];$spec=['orientation'=>'auto','color_profile'=>'srgb','max_width'=>$policy['max_width'],'max_height'=>$policy['max_height'],'max_pixels'=>$policy['max_pixels'],'formats'=>['jpeg','webp','avif'],'fallback'=>'jpeg','derivatives'=>$policy['derivative_set'],'quality_presets'=>['thumb'=>78,'small'=>80,'medium'=>82,'large'=>84]];try{$result=ToolRunner::transform('image-pipeline',['stream'=>$clean,'mime'=>$asset['mime']],$spec);}finally{fclose($clean);}return self::validateOutputs($result,$asset,$jobId);}
    private static function validateOutputs(array $result,array $asset,string $jobId): array {
    $outputs=(array)($result['outputs']??[]);
    if($outputs===[])throw new Error('image_pipeline_empty','Image pipeline returned no outputs.',422);
    $ids=[];$mimes=['jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','avif'=>'image/avif'];
    foreach($outputs as $output){
        if(!is_array($output)||!is_resource($output['stream']??null)||($output['width']??0)<1||($output['height']??0)<1||!in_array(($output['format']??''),array_keys($mimes),true))throw new Error('image_output_invalid','Image output invalid.',422);
        try{
            $kind=Utils::key((string)($output['kind']??''),64);
            if(!ProcessingService::wantsKind($asset,$kind))continue;
            $ids[]=DerivativeService::store($asset['asset_id'],$kind,$output['stream'],ProcessingService::lineage($asset,$jobId,$result,$kind,(string)($output['preset_version']??'1'),['mime'=>$mimes[(string)$output['format']],'dimensions'=>[(int)$output['width'],(int)$output['height']]]),$asset['policy'])['id'];
        }finally{fclose($output['stream']);}
    }
    if($ids===[])throw new Error('target_derivative_missing','Image pipeline did not produce a requested derivative.',422);
    return $ids;
}
}

final class AvPipeline {
    public static function run(array $asset,$source,string $jobId): array {
    $spec=$asset['media_class']==='video'?['probe'=>true,'adaptive'=>true,'manifests'=>['hls','dash'],'video_codecs'=>['h264','vp9','av1'],'audio_codecs'=>['aac','opus'],'poster'=>true,'waveform'=>true,'loudness'=>'EBU-R128','captions_reference'=>true,'transcript_reference'=>true,'low_bandwidth'=>true,'audio_only'=>true,'segment_hashes'=>true,'duration_limit'=>$asset['policy']['max_duration_seconds']]:['probe'=>true,'audio_codecs'=>['aac','opus','mp3'],'waveform'=>true,'loudness'=>'EBU-R128','captions_reference'=>true,'transcript_reference'=>true,'low_bandwidth'=>true,'duration_limit'=>$asset['policy']['max_duration_seconds']];
    $result=ToolRunner::transform($asset['media_class'].'-pipeline',['stream'=>$source,'mime'=>$asset['mime']],$spec);
    $probe=(array)($result['probe']??[]);
    if(($probe['supported']??false)!==true||(int)($probe['duration_seconds']??0)<1)throw new Error('av_probe_failed','Audio/video probe failed.',422);
    if($asset['policy']['max_duration_seconds']>0&&(int)$probe['duration_seconds']>$asset['policy']['max_duration_seconds'])throw new Error('duration_limit_exceeded','Media duration exceeds policy.',413);
    $outputs=(array)($result['outputs']??[]);
    if($outputs===[])throw new Error('av_pipeline_empty','Audio/video pipeline returned no outputs.',422);
    $ids=[];$allKinds=[];$mimes=['hls-manifest'=>'application/vnd.apple.mpegurl','hls-segment'=>'video/mp2t','dash-manifest'=>'application/dash+xml','poster'=>'image/jpeg','waveform'=>'application/json','audio-aac'=>'audio/aac','audio-opus'=>'audio/ogg','audio-mp3'=>'audio/mpeg','video-h264'=>'video/mp4','video-vp9'=>'video/webm','video-av1'=>'video/mp4','video-low'=>'video/mp4','audio-low'=>'audio/aac','caption-ref'=>'application/json','transcript-ref'=>'application/json'];
    foreach($outputs as $output){
        if(!is_array($output)||!is_resource($output['stream']??null)||empty($output['kind'])||empty($output['sha256']))throw new Error('av_output_invalid','Audio/video output invalid.',422);
        try{
            $kind=Utils::key((string)$output['kind'],64);$allKinds[]=$kind;
            $stats=Utils::streamHash($output['stream']);
            if(!hash_equals(strtolower((string)$output['sha256']),$stats['sha256']))throw new Error('av_output_hash_mismatch','Audio/video output hash mismatch.',422);
            if(!ProcessingService::wantsKind($asset,$kind))continue;
            $mime=(string)($output['mime']??($mimes[$kind]??$asset['mime']));
            $ids[]=DerivativeService::store($asset['asset_id'],$kind,$output['stream'],ProcessingService::lineage($asset,$jobId,$result,$kind,(string)($output['preset_version']??'1'),['mime'=>$mime,'bitrate'=>$output['bitrate']??null,'duration'=>$probe['duration_seconds']]),$asset['policy'])['id'];
        }finally{fclose($output['stream']);}
    }
    if($asset['media_class']==='video')foreach(['hls-manifest','poster','video-low','audio-low','caption-ref','transcript-ref'] as $required)if(!in_array($required,$allKinds,true))throw new Error('video_derivative_missing','Required video derivative missing.',422,['kind'=>$required]);
    if($asset['media_class']==='audio')foreach(['audio-low','caption-ref','transcript-ref'] as $required)if(!in_array($required,$allKinds,true))throw new Error('audio_derivative_missing','Required audio accessibility/low-bandwidth derivative missing.',422,['kind'=>$required]);if($ids===[])throw new Error('target_derivative_missing','Audio/video pipeline did not produce a requested derivative.',422);
    return $ids;
}
}

final class DocumentPipeline {
    public static function run(array $asset,$source,string $jobId): array {
    $rights=$asset['rights'];$allowText=in_array('extract_text',(array)$rights['allowed_operations'],true);$allowOcr=in_array('ocr',(array)$rights['allowed_operations'],true);
    $spec=['structural_validation'=>true,'max_pages'=>$asset['policy']['max_pages'],'preview_raster'=>true,'extract_text'=>$allowText,'ocr'=>$allowOcr,'suppress_active_content'=>true,'font_render_sandbox'=>true,'preserve_rights_metadata'=>true];
    $result=ToolRunner::transform('document-pipeline',['stream'=>$source,'mime'=>$asset['mime']],$spec);
    if(($result['structure']['valid']??false)!==true||($result['active_content_suppressed']??false)!==true)throw new Error('document_processing_failed','Document processing failed safety validation.',422);
    $pages=(int)($result['structure']['pages']??0);
    if($pages<1||($asset['policy']['max_pages']>0&&$pages>$asset['policy']['max_pages']))throw new Error('document_page_limit','Document page count outside policy.',413);
    $outputs=(array)($result['outputs']??[]);
    if($outputs===[])throw new Error('document_pipeline_empty','Document pipeline returned no outputs.',422);
    $ids=[];$mimes=['preview'=>'image/png','text'=>'text/plain; charset=utf-8','ocr'=>'text/plain; charset=utf-8'];
    foreach($outputs as $output){
        if(!is_array($output)||!is_resource($output['stream']??null)||empty($output['kind']))throw new Error('document_output_invalid','Document output invalid.',422);
        try{
            $kind=Utils::key((string)$output['kind'],64);
            if($kind==='text'&&!$allowText)throw new Error('unauthorized_text_output','Unauthorized text extraction output.',500);
            if($kind==='ocr'&&!$allowOcr)throw new Error('unauthorized_ocr_output','Unauthorized OCR output.',500);
            if(!ProcessingService::wantsKind($asset,$kind))continue;
            $ids[]=DerivativeService::store($asset['asset_id'],$kind,$output['stream'],ProcessingService::lineage($asset,$jobId,$result,$kind,(string)($output['preset_version']??'1'),['mime'=>(string)($output['mime']??($mimes[$kind]??'application/octet-stream')),'pages'=>$pages]),$asset['policy'])['id'];
        }finally{fclose($output['stream']);}
    }
    if($ids===[])throw new Error('target_derivative_missing','Document pipeline did not produce a requested derivative.',422);
    return $ids;
}
}

final class ProcessingService {
    public static function start(string $assetId): array {
    RuntimeGuard::requireReady(['streaming']);
    $asset=RecordStore::get('asset',$assetId);
    if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    if(($asset['processing_status']??'')==='queued'&&!empty($asset['job_graph']))return (array)$asset['job_graph'];
    if(($asset['status']??'')!=='quarantined')throw new Error('asset_state_invalid','Only quarantined assets may enter processing.',409);
    LegalHoldService::assertNoHold($assetId,'processing');
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_processing',['asset'=>$asset,'operation'=>'start']);
    if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner version changed.',409);
    $generation=max(1,(int)($asset['processing_generation']??0)+1);
    $jobs=JobService::graph($assetId,$asset['policy'],$generation);
    $asset['processing_status']='queued';$asset['processing_generation']=$generation;$asset['job_graph']=$jobs;
    try{RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}
    catch(\Throwable $exception){$fresh=RecordStore::get('asset',$assetId);$attached=$fresh&&(int)($fresh['processing_generation']??0)===$generation&&(array)($fresh['job_graph']??[])===$jobs;if(!$attached){foreach($jobs as $jobId){$job=RecordStore::get('job',(string)$jobId);if($job&&(int)($job['processing_generation']??0)===$generation&&in_array(($job['status']??''),['queued','retry'],true)){try{RecordStore::delete('job',(string)$jobId);}catch(\Throwable){}}}}throw $exception;}
    Audit::record('processing_graph_created',['asset_id'=>$assetId,'processing_generation'=>$generation,'jobs'=>array_keys($jobs)]);
    return $jobs;
}
    public static function execute(string $assetId,string $workerId='inline-worker'): array {$asset=RecordStore::get('asset',$assetId);if(!$asset)throw new Error('asset_not_found','Asset not found.',404);if(empty($asset['job_graph'])){self::start($assetId);$asset=RecordStore::get('asset',$assetId)??throw new Error('asset_not_found','Asset vanished.',500);}$derivatives=[];foreach(['probe','scan','metadata','transform','validate_outputs','store_derivatives','manifest_switch'] as $type){$jobId=$asset['job_graph'][$type]??hash('sha256',$assetId.'|'.$type.'|'.$asset['policy_hash']);$job=RecordStore::get('job',$jobId);if(!$job)throw new Error('job_missing','Processing job missing.',500,['job'=>$type]);if($type==='scan'&&($job['status']??'')==='waiting_review'){$signalId=(string)($asset['safety_signal_id']??$job['review_signal_id']??'');if($signalId==='')throw new Error('safety_signal_missing','Waiting scan job has no safety signal.',409);$job=JobService::resumeSafetyReview($jobId,$signalId);if(($job['status']??'')==='waiting_review')throw new Error('safety_review_required','Technical safety review is still pending.',409,['signal_id'=>$signalId]);if(($job['status']??'')==='dead_letter')throw new Error('safety_review_rejected','Technical safety signal was rejected by review.',422,['signal_id'=>$signalId]);$asset=RecordStore::get('asset',$assetId)??$asset;$asset['processing_status']='queued';unset($asset['last_processing_error']);$asset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);}if(($job['status']??'')==='completed'){if($type==='transform')$derivatives=(array)($job['result']['derivatives']??$derivatives);continue;}$leased=JobService::leaseSpecific($jobId,$workerId);try{$result=self::executeNode($type,$assetId,$jobId,$derivatives);if($type==='transform')$derivatives=(array)$result['derivatives'];JobService::complete($jobId,(string)$leased['lease_token'],$result);}catch(\Throwable $exception){$code=$exception instanceof Error?$exception->errorCode:'unexpected';if($exception instanceof Error&&in_array($code,['safety_review_required','safety_review_escalated'],true)){$signalId=(string)(($exception->context['signal_id']??'')?:((RecordStore::get('asset',$assetId)['safety_signal_id']??'')));try{JobService::awaitSafetyReview($jobId,(string)$leased['lease_token'],$code,$signalId);}catch(\Throwable){}$asset=RecordStore::get('asset',$assetId)??$asset;$asset['processing_status']='awaiting_review';$asset['last_processing_error']=$code;RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);throw $exception;}try{JobService::fail($jobId,(string)$leased['lease_token'],$code,!($exception instanceof Error)||$exception->httpStatus>=500);}catch(\Throwable){}$asset=RecordStore::get('asset',$assetId)??$asset;$asset['processing_status']='failed';$asset['last_processing_error']=$code;RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);throw $exception;}$asset=RecordStore::get('asset',$assetId)??$asset;}return RecordStore::get('asset',$assetId)??throw new Error('asset_not_found','Asset vanished.',500);}
    private static function executeNode(string $type,string $assetId,string $jobId,array $derivatives): array {
    $asset=RecordStore::get('asset',$assetId);
    if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
    $provider=ProviderRegistry::get((string)($asset['storage']['provider_id']??ProviderRegistry::activeId()));
    $source=$provider->openStream((string)$asset['object_key']);
    try{return match($type){
        'probe'=>self::probeNode($source,$asset),
        'scan'=>self::scanNode($source,$asset),
        'metadata'=>['metadata_policy'=>'validated'],
        'transform'=>['derivatives'=>self::pipeline($asset,$source,$jobId)],
        'validate_outputs'=>self::validateDerivatives($assetId,$derivatives),
        'store_derivatives'=>['derivatives'=>$derivatives,'stored'=>true],
        'manifest_switch'=>['manifest'=>DerivativeService::manifest($assetId,self::mergeManifestDerivatives($asset,$derivatives),(int)$asset['manifest_version'])],
        default=>throw new Error('job_type_unknown','Unknown processing job.',500)
    };}finally{fclose($source);}
}
    private static function probeNode($source,array $asset): array {$path=self::streamToTempPath($source);try{return ['probe'=>ToolRunner::probe($path,$asset['mime'],$asset['policy'])];}finally{@unlink($path);}}
    private static function scanNode($source,array $asset): array {$scans=ScannerRegistry::scan($source,$asset['policy']['required_scans'],['asset_id'=>$asset['asset_id'],'mime'=>$asset['mime'],'policy'=>$asset['policy']]);$signal=null;$existingSignalId=(string)($asset['safety_signal_id']??'');if($existingSignalId!==''){$candidate=RecordStore::get('safety_signal',$existingSignalId);if($candidate&&($candidate['asset_id']??'')===$asset['asset_id'])$signal=$candidate;}if(!$signal)$signal=SafetySignalService::evaluate($source,$asset);$status=(string)($signal['status']??'pending_review');$asset['scan_results']=$scans;$asset['safety_signal_id']=$signal['id'];if($status==='rejected'){$asset['scan_status']='rejected';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_rejected','Technical safety signal was rejected by review.',422,['signal_id'=>$signal['id']]);}if($status==='escalated'){$asset['scan_status']='awaiting_review';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_escalated','Technical safety signal remains escalated.',409,['signal_id'=>$signal['id']]);}if($status==='pending_review'&&$asset['policy']['safety']['require_reviewer_for_low_confidence']){$asset['scan_status']='awaiting_review';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);throw new Error('safety_review_required','Low-confidence technical safety signal requires review.',409,['signal_id'=>$signal['id']]);}$asset['scan_status']='passed';RecordStore::put('asset',$asset['asset_id'],$asset,(int)$asset['version']);return ['scans'=>$scans,'safety_signal'=>$signal['id']];}
    private static function pipeline(array $asset,$source,string $jobId): array {rewind($source);if($asset['media_class']==='image')return ImagePipeline::run($asset,$source,$jobId);$metadata=MetadataPolicy::transform((string)$asset['media_class'],['stream'=>$source,'mime'=>$asset['mime'],'sha256'=>$asset['sha256']],$asset['policy']);$clean=$metadata['output_stream'];try{return match($asset['media_class']){'audio','video'=>AvPipeline::run($asset,$clean,$jobId),'document'=>DocumentPipeline::run($asset,$clean,$jobId),default=>self::genericPipeline($asset,$clean,$jobId)};}finally{fclose($clean);}}
    private static function genericPipeline(array $asset,$source,string $jobId): array {
    $result=ToolRunner::transform('generic-safe-copy',['stream'=>$source,'mime'=>$asset['mime']],['strip_metadata'=>true,'validate_output'=>true]);
    $output=$result['output_stream']??null;
    if(!is_resource($output))throw new Error('generic_output_invalid','Generic processing output invalid.',422);
    try{
        if(!self::wantsKind($asset,'safe-copy'))throw new Error('target_derivative_missing','Generic pipeline cannot produce requested derivative.',422);
        return [DerivativeService::store($asset['asset_id'],'safe-copy',$output,self::lineage($asset,$jobId,$result,'safe-copy','1',['mime'=>$asset['mime']]),$asset['policy'])['id']];
    }finally{fclose($output);}
}
    private static function validateDerivatives(string $assetId,array $ids): array {if($ids===[])throw new Error('derivative_set_empty','No derivatives produced.',422);foreach(array_values(array_unique(array_map('strval',$ids))) as $id){$derivative=RecordStore::get('derivative',$id);if(!$derivative||($derivative['asset_id']??'')!==$assetId||($derivative['status']??'')!=='validated')throw new Error('derivative_validation_failed','Derivative validation failed.',422);$provider=ProviderRegistry::get((string)($derivative['storage']['provider_id']??ProviderRegistry::activeId()));$stream=$provider->openStream((string)$derivative['object_key']);try{$stats=Utils::streamHash($stream);if(!hash_equals((string)$derivative['sha256'],$stats['sha256'])||(int)$derivative['size']!==(int)$stats['size'])throw new Error('derivative_integrity_failed','Derivative integrity failed.',422);}finally{fclose($stream);}}return ['validated'=>count($ids)];}

    public static function wantsKind(array $asset,string $kind): bool {
        $targets=array_values(array_filter(array_unique(array_map(fn($value)=>Utils::key((string)$value,64),(array)($asset['reprocess_context']['target_kinds']??[])))));
        return $targets===[]||in_array(Utils::key($kind,64),$targets,true);
    }
    public static function lineage(array $asset,string $jobId,array $result,string $kind,string $defaultPresetVersion,array $extra=[]): array {
        $context=(array)($asset['reprocess_context']??[]);
        $preset=(array)($context['preset']??[]);
        return array_replace(['source_sha256'=>$asset['sha256'],'job_id'=>$jobId,'tool'=>(string)($result['worker']['id']??'unknown-worker'),'tool_version'=>(string)($result['worker']['version']??'unknown'),'preset'=>(string)($preset['name']??$kind),'preset_version'=>(string)($preset['version']??$defaultPresetVersion),'processing_generation'=>(int)($asset['processing_generation']??1),'repair_id'=>(string)($context['repair_id']??'')],$extra);
    }
    private static function mergeManifestDerivatives(array $asset,array $newIds): array {
        $targets=array_values(array_filter(array_unique(array_map(fn($value)=>Utils::key((string)$value,64),(array)($asset['reprocess_context']['target_kinds']??[])))));
        if($targets===[])return array_values(array_unique(array_map('strval',$newIds)));
        $newByKind=[];
        foreach($newIds as $id){$derivative=RecordStore::get('derivative',(string)$id);if($derivative)$newByKind[(string)$derivative['kind']]=(string)$id;}
        foreach($targets as $kind)if(!isset($newByKind[$kind]))throw new Error('target_derivative_missing','A requested repair derivative was not produced.',422,['kind'=>$kind]);
        $merged=$newByKind;
        $oldId=(string)($asset['active_manifest_id']??'');
        $old=$oldId!==''?RecordStore::get('manifest',$oldId):null;
        if($old&&in_array(($old['status']??''),['active','superseded'],true))foreach((array)$old['derivatives'] as $item){$kind=Utils::key((string)($item['kind']??''),64);if($kind!==''&&!in_array($kind,$targets,true)&&!isset($merged[$kind]))$merged[$kind]=(string)$item['derivative_id'];}
        return array_values($merged);
    }

    private static function streamToTempPath($stream): string {$path=tempnam(sys_get_temp_dir(),'scm-probe-');if($path===false)throw new Error('temp_unavailable','Probe workspace unavailable.',500);$out=fopen($path,'wb');if($out===false){@unlink($path);throw new Error('temp_unavailable','Probe workspace unavailable.',500);}rewind($stream);try{Utils::copyStream($stream,$out);}catch(\Throwable $exception){@unlink($path);throw $exception;}finally{fclose($out);rewind($stream);}return $path;}
}
