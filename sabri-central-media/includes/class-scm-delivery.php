<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class DeliveryService {
    public static function issue(string $assetId,?string $derivativeId,int $actor,string $serviceId,array $audience,array $context,string $operation,array $rangePolicy,string $sessionId,int $ttl=300,int $maxUses=20): string {
    RuntimeGuard::requireReady(['streaming']);RestoreService::assertServeAllowed();Auth::assertActor($actor);
    $serviceId=Utils::key($serviceId,64);$sessionId=Utils::text($sessionId,128);$audience=self::audience($audience);
    if($serviceId===''||$sessionId==='')throw new Error('delivery_context_incomplete','Service and session identity required.',400);
    Auth::verifiedUser($actor,'media_delivery_issue',$serviceId,['asset_id'=>$assetId]);
    $asset=self::asset($assetId);$target=self::target($asset,$derivativeId);$operation=Utils::key($operation,32);
    if($operation===''||!in_array($operation,['view','download','stream','extract_text','ocr'],true))throw new Error('delivery_operation_invalid','Delivery operation invalid.',400);
    if($audience['type']==='public'&&($asset['privacy_class']!=='C0'||!Utils::bool($asset['policy']['delivery']['public_cdn']??false)))throw new Error('public_delivery_denied','Public delivery requires an explicit public policy.',403);
    $rightsContext=array_replace($context,['audience_type'=>$audience['type']]);RightsPolicy::assert($asset['rights'],$operation,$rightsContext);self::assertDeliverable($asset,$target,$operation);
    $assetRange=(array)$asset['policy']['delivery'];
    $policyMax=Utils::integer($assetRange['max_range_bytes']??null,'delivery_range_integer_invalid',0);
    $requestMax=Utils::integer($rangePolicy['max_range_bytes']??($policyMax>0?$policyMax:1),'delivery_range_integer_invalid',1);
    $ranges=Utils::bool($assetRange['allow_ranges'])&&Utils::bool($rangePolicy['allow_ranges']??$assetRange['allow_ranges']);
    if($ranges&&$policyMax<1)throw new Error('delivery_range_integer_invalid','Range policy requires a positive maximum.',400);
    $effectiveRange=['allow_ranges'=>$ranges,'max_range_bytes'=>min($policyMax,$requestMax)];
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'derivative'=>$target,'actor_id'=>$actor,'service_id'=>$serviceId,'audience'=>$audience,'context'=>Utils::redact($context),'operation'=>$operation,'session_id'=>$sessionId,'range_policy'=>$effectiveRange]);
    if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner authorization is stale.',409);
    $grantId=Utils::id('gr');$audienceHash=Utils::hashReference(Utils::canonicalJson($audience));$contextHash=Utils::hashReference(Utils::canonicalJson($context));$sessionHash=Utils::hashReference($sessionId);$rangeHash=Utils::hashReference(Utils::canonicalJson($effectiveRange));
    $claims=['type'=>'delivery-grant','grant_id'=>$grantId,'asset_id'=>$assetId,'derivative_id'=>$derivativeId??'source','actor_id'=>$actor,'service_id'=>$serviceId,'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],'object_version'=>(int)$asset['object_version'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'purpose'=>$asset['policy']['purpose'],'privacy_class'=>$asset['privacy_class'],'audience_hash'=>$audienceHash,'context_hash'=>$contextHash,'session_hash'=>$sessionHash,'operation'=>$operation,'range_hash'=>$rangeHash,'download_mode'=>$operation==='download','target_sha256'=>$target['sha256']];
    $ttl=max(1,min($ttl,(int)$asset['policy']['delivery']['grant_ttl_seconds']));$token=Crypto::sign($claims,$ttl);
    $record=['actor_id'=>$actor,'grant_id'=>$grantId,'asset_id'=>$assetId,'derivative_id'=>$derivativeId??'source','service_id'=>$serviceId,'status'=>'active','audience_hash'=>$audienceHash,'context_hash'=>$contextHash,'session_hash'=>$sessionHash,'range_hash'=>$rangeHash,'range_policy'=>$effectiveRange,'operation'=>$operation,'token_hash'=>hash('sha256',$token),'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'object_version'=>$asset['object_version'],'target_sha256'=>$target['sha256'],'max_uses'=>max(1,min(1000,$maxUses)),'uses'=>0,'expires_at'=>Utils::now()+$ttl,'created_at'=>Utils::now()];
    RecordStore::put('grant',$grantId,$record);Audit::record('delivery_grant_issued',['grant_id'=>$grantId,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'actor_id'=>$actor,'service_id'=>$serviceId,'operation'=>$operation,'expires_in'=>$ttl]);return $token;
}
    public static function serve(string $token,int $actor,string $serviceId,array $audience,array $context,string $sessionId,?string $rangeHeader=null): array {
    RuntimeGuard::requireReady(['streaming']);RestoreService::assertServeAllowed();Auth::assertActor($actor);$audience=self::audience($audience);
    $claims=Crypto::verify($token);Utils::requireFields($claims,['type','grant_id','asset_id','derivative_id','actor_id','service_id','owner_domain','owner_object','object_version','policy_hash','rights_hash','purpose','privacy_class','audience_hash','context_hash','session_hash','operation','range_hash','target_sha256'],'grant_claims_invalid');
    if($claims['type']!=='delivery-grant')throw new Error('grant_type_invalid','Invalid grant type.',403);
    $grant=RecordStore::get('grant',(string)$claims['grant_id']);if(!$grant||($grant['status']??'')!=='active')throw new Error('grant_revoked','Delivery grant unavailable or revoked.',403);
    self::assertGrantUsable($grant);
    if(!hash_equals((string)$grant['token_hash'],hash('sha256',$token)))throw new Error('grant_record_mismatch','Delivery grant record mismatch.',403);
    $serviceId=Utils::key($serviceId,64);$sessionId=Utils::text($sessionId,128);$sessionHash=Utils::hashReference($sessionId);$audienceHash=Utils::hashReference(Utils::canonicalJson($audience));$contextHash=Utils::hashReference(Utils::canonicalJson($context));
    foreach(['actor_id'=>$actor,'service_id'=>$serviceId,'audience_hash'=>$audienceHash,'context_hash'=>$contextHash,'session_hash'=>$sessionHash] as $field=>$value)if(($claims[$field]??null)!==$value||($grant[$field]??null)!==$value)throw new Error('grant_binding_mismatch','Delivery grant binding mismatch.',403,['field'=>$field]);
    Auth::verifiedUser($actor,'media_delivery_consume',$serviceId,['asset_id'=>$claims['asset_id']]);
    $asset=self::asset((string)$claims['asset_id']);$target=self::target($asset,$claims['derivative_id']==='source'?null:(string)$claims['derivative_id']);self::assertDeliverable($asset,$target,(string)$claims['operation']);
    foreach(['owner_domain','owner_object','policy_hash'] as $field)if(!hash_equals((string)$claims[$field],(string)$asset[$field]))throw new Error('grant_asset_state_changed','Asset state changed after grant issuance.',403,['field'=>$field]);
    if((int)$claims['object_version']!==(int)$asset['object_version']||!hash_equals((string)$claims['rights_hash'],(string)$asset['rights']['policy_hash'])||!hash_equals((string)$claims['purpose'],(string)$asset['policy']['purpose'])||!hash_equals((string)$claims['privacy_class'],(string)$asset['privacy_class'])||!hash_equals((string)$claims['target_sha256'],(string)$target['sha256']))throw new Error('grant_asset_state_changed','Asset, purpose, privacy or policy version changed.',403);
    RightsPolicy::assert($asset['rights'],(string)$claims['operation'],array_replace($context,['audience_type'=>$audience['type']]));
    $effectiveRange=(array)($grant['range_policy']??[]);
    if(!isset($effectiveRange['allow_ranges'],$effectiveRange['max_range_bytes'])||!hash_equals((string)$claims['range_hash'],Utils::hashReference(Utils::canonicalJson($effectiveRange)))||!hash_equals((string)$grant['range_hash'],(string)$claims['range_hash']))throw new Error('grant_range_policy_changed','Range policy changed.',403);
    $storedRangeMax=Utils::integer($effectiveRange['max_range_bytes'],'grant_range_integer_invalid',Utils::bool($effectiveRange['allow_ranges'])?1:0);
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'derivative'=>$target,'actor_id'=>$actor,'service_id'=>$serviceId,'audience'=>$audience,'context'=>Utils::redact($context),'operation'=>$claims['operation'],'session_id'=>$sessionId,'grant_id'=>$claims['grant_id'],'range_header'=>$rangeHeader]);
    if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner authorization is stale.',409);
    if($target['size']<1)throw new Error('delivery_target_empty','Delivery target is empty.',409);
    if($rangeHeader!==null&&$rangeHeader!==''&&!Utils::bool($effectiveRange['allow_ranges']))throw new Error('range_denied','Byte ranges are not allowed.',416);
    $range=$rangeHeader!==null&&$rangeHeader!==''?Validator::range($rangeHeader,$target['size'],$storedRangeMax):['start'=>0,'end'=>$target['size']-1,'length'=>$target['size'],'partial'=>false];
    $providerId=Utils::key((string)($target['storage']['provider_id']??''),64);if($providerId==='')throw new Error('storage_provider_missing','Delivery target provider identity missing.',500);ResidencyCryptoService::assertProviderRegion((string)$asset['asset_id'],$providerId);
    $stream=ProviderRegistry::get($providerId)->openStream((string)$target['object_key']);try{$output=self::sliceStream($stream,$range['start'],$range['length']);}finally{fclose($stream);}
    $stats=Utils::streamHash($output);if(!$range['partial']&&!hash_equals((string)$target['sha256'],$stats['sha256'])){fclose($output);IntegrityService::quarantine($asset['asset_id'],'delivery_hash_mismatch');throw new Error('delivery_integrity_failed','Delivery integrity verification failed.',500);}
    $consumed=false;for($attempt=0;$attempt<5;$attempt++){$freshGrant=RecordStore::get('grant',(string)$claims['grant_id']);if(!$freshGrant||($freshGrant['status']??'')!=='active'){fclose($output);throw new Error('grant_revoked','Delivery grant unavailable or revoked.',403);}try{$used=self::assertGrantUsable($freshGrant);}catch(\Throwable $failure){fclose($output);throw $failure;}if(!hash_equals((string)$freshGrant['token_hash'],hash('sha256',$token))){fclose($output);throw new Error('grant_record_mismatch','Delivery grant record mismatch.',403);}$freshGrant['uses']=$used+1;$freshGrant['last_used_at']=Utils::now();$freshGrant['last_range']=$range;try{$grant=RecordStore::put('grant',(string)$freshGrant['id'],$freshGrant,(int)$freshGrant['version']);$consumed=true;break;}catch(Error $race){if($race->errorCode!=='record_version_conflict'){fclose($output);throw $race;}}}if(!$consumed){fclose($output);throw new Error('grant_contention','Delivery grant changed too frequently to consume safely.',409);}
    Audit::record('delivery_grant_consumed',['grant_id'=>$grant['id'],'asset_id'=>$asset['asset_id'],'actor_id'=>$actor,'service_id'=>$serviceId,'range'=>$range]);
    $download=($claims['operation']==='download');$filename=Utils::filename((string)$asset['declared_name']);
    return ['stream'=>$output,'status'=>$range['partial']?206:200,'headers'=>self::headers($target,$filename,$download,$range,Utils::bool($effectiveRange['allow_ranges'])),'range'=>$range,'grant_uses'=>$grant['uses']];
}
    private static function assertGrantUsable(array $grant): int {
        $expiry=Utils::integer($grant['expires_at']??null,'grant_integer_invalid',1);
        $uses=Utils::integer($grant['uses']??null,'grant_integer_invalid',0);
        $limit=Utils::integer($grant['max_uses']??null,'grant_integer_invalid',1,1000);
        if($expiry<=Utils::now())throw new Error('grant_expired','Delivery grant expired.',403);
        if($uses>=$limit)throw new Error('grant_use_limit','Delivery grant use limit reached.',403);
        return $uses;
    }
    private static function sliceStream($source,int $start,int $length){$output=Utils::tempStream();try{rewind($source);if($start>0&&fseek($source,$start)!==0)throw new Error('delivery_seek_failed','Delivery seek failed.',500);$remaining=$length;while($remaining>0&&!feof($source)){$chunk=fread($source,min(1048576,$remaining));if($chunk===false)throw new Error('delivery_read_failed','Delivery read failed.',500);if($chunk==='')break;Utils::writeAll($output,$chunk);$remaining-=strlen($chunk);}if($remaining!==0)throw new Error('delivery_range_incomplete','Delivery range incomplete.',500);rewind($output);return $output;}catch(\Throwable $exception){fclose($output);throw $exception;}}
    private static function headers(array $target,string $filename,bool $download,array $range,bool $allowRanges): array {$filename=str_replace(["\r","\n"],'',Utils::filename($filename));$headers=['Content-Type'=>(string)($target['mime']??'application/octet-stream'),'Content-Length'=>(string)$range['length'],'Content-Disposition'=>($download?'attachment':'inline').'; filename="'.addcslashes($filename,'"\\').'"; filename*=UTF-8\'\''.rawurlencode($filename),'Cache-Control'=>'private, no-store, max-age=0','Pragma'=>'no-cache','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox",'Cross-Origin-Resource-Policy'=>'same-origin'];if($allowRanges)$headers['Accept-Ranges']='bytes';if($range['partial'])$headers['Content-Range']='bytes '.$range['start'].'-'.$range['end'].'/'.$target['size'];return $headers;}
    private static function audience(array $audience): array {
    $type=Utils::key((string)($audience['type']??''),32);
    if(!in_array($type,['private','user','recipient','group','public'],true))throw new Error('audience_invalid','Delivery audience invalid.',400);
    if(in_array($type,['user','recipient'],true)){
        $userId=Utils::integer($audience['user_id']??null,'audience_invalid',1);
        return ['type'=>$type,'user_id'=>$userId];
    }
    if($type==='group'){
        $groupId=Utils::text((string)($audience['group_id']??''),96);if($groupId==='')throw new Error('audience_invalid','Audience group identity required.',400);
        $out=['type'=>'group','group_id'=>$groupId];if(array_key_exists('user_id',$audience))$out['user_id']=Utils::integer($audience['user_id'],'audience_invalid',1);return $out;
    }
    return ['type'=>$type];
}
    private static function asset(string $id): array {$a=RecordStore::get('asset',$id);if(!$a)throw new Error('asset_not_found','Asset not found.',404);return $a;}
    private static function target(array $asset,?string $derivativeId): array {
        if($derivativeId===null)$target=['id'=>$asset['asset_id'],'sha256'=>$asset['sha256'],'size'=>$asset['size']??null,'object_key'=>$asset['object_key'],'storage'=>$asset['storage'],'mime'=>$asset['mime'],'status'=>$asset['status']];
        else{
            $manifestId=(string)($asset['active_manifest_id']??'');$manifest=$manifestId!==''?RecordStore::get('manifest',$manifestId):null;
            if(!$manifest||($manifest['status']??'')!=='active'||($manifest['asset_id']??'')!==$asset['asset_id'])throw new Error('active_manifest_missing','Active derivative manifest unavailable.',409);
            $allowed=array_column((array)$manifest['derivatives'],'derivative_id');
            if(!in_array($derivativeId,$allowed,true))throw new Error('derivative_not_active','Derivative is not in the active manifest.',409);
            $derivative=RecordStore::get('derivative',$derivativeId);
            if(!$derivative||($derivative['asset_id']??'')!==$asset['asset_id']||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))throw new Error('derivative_not_found','Derivative not found or superseded.',404);
            $target=$derivative+['mime'=>$derivative['mime']??$asset['mime']];
        }
        $target['size']=Utils::integer($target['size']??null,'delivery_target_size_invalid',1);
        return $target;
    }
    private static function assertDeliverable(array $asset,array $target,string $operation): void {
    if(($asset['status']??'')!=='ready'||($asset['scan_status']??'')!=='passed'||($asset['processing_status']??'')!=='completed')throw new Error('asset_not_deliverable','Asset has not completed safe processing.',409);
    LegalHoldService::assertNoHold((string)$asset['asset_id'],'delivery');
    if(($target['status']??'')==='deleted'||($target['status']??'')==='quarantined')throw new Error('target_not_deliverable','Target is not deliverable.',409);
    if($operation==='download'&&!Utils::bool($asset['policy']['delivery']['allow_download']??false))throw new Error('download_denied','Download is prohibited by policy.',403);
}
    public static function revoke(string $grantId,int $actor,string $reason='owner-revoke'): array {Auth::assertActor($actor,'manage_options');$reason=Utils::key($reason,64);if($reason==='')throw new Error('revoke_reason_required','Grant revoke reason required.',400);$grant=RecordStore::get('grant',$grantId);if(!$grant)throw new Error('grant_not_found','Grant not found.',404);if(($grant['status']??'')==='revoked')return $grant;$asset=self::asset((string)$grant['asset_id']);if($actor!==(int)$grant['actor_id'])Auth::capability('media_reprocess');$decision=DomainRegistry::decision($asset['owner_domain'],'authorize_grant_revoke',['asset'=>$asset,'grant'=>$grant,'actor_id'=>$actor,'reason'=>$reason]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner authorization is stale.',409);$grant['status']='revoked';$grant['revoked_at']=Utils::now();$grant['revoked_by']=$actor;$grant['revoke_reason']=$reason;$grant=RecordStore::put('grant',$grantId,$grant,(int)$grant['version']);Audit::record('delivery_grant_revoked',['grant_id'=>$grantId,'asset_id'=>$asset['asset_id'],'actor_id'=>$actor,'reason'=>$reason]);return $grant;}
    public static function revokeForAsset(string $assetId,string $reason): int {
        $reason=Utils::key($reason,64);if($assetId===''||$reason==='')throw new Error('revoke_context_invalid','Asset and revoke reason are required.',400);$count=0;
        foreach(RecordStore::all('grant',0,null,100000) as $snapshot){
            if(($snapshot['asset_id']??'')!==$assetId||($snapshot['status']??'')!=='active')continue;$grantId=(string)$snapshot['id'];$settled=false;
            for($attempt=0;$attempt<4;$attempt++){
                $g=RecordStore::get('grant',$grantId);if(!$g||($g['asset_id']??'')!==$assetId||($g['status']??'')!=='active'){$settled=true;break;}
                $g['status']='revoked';$g['revoked_at']=Utils::now();$g['revoke_reason']=$reason;
                try{RecordStore::put('grant',$grantId,$g,(int)$g['version']);$count++;$settled=true;break;}
                catch(Error $conflict){if($conflict->errorCode!=='record_version_conflict')throw $conflict;}
            }
            if(!$settled){$latest=RecordStore::get('grant',$grantId);if($latest&&($latest['asset_id']??'')===$assetId&&($latest['status']??'')==='active')throw new Error('grant_revoke_conflict','Grant remained active after bounded revocation retries.',409,['grant_id'=>$grantId]);}
        }
        self::purgePublicForAsset($assetId,$reason);
        return $count;
    }
    public static function purgePublicForAsset(string $assetId,string $reason): array {
        $reason=Utils::key($reason,64);if($assetId===''||$reason==='')throw new Error('cdn_purge_context_invalid','Asset and purge reason are required.',400);$result=['asset_id'=>$assetId,'purged'=>0,'pending'=>0,'checked'=>0];
        foreach(RecordStore::all('cdn_mapping',0,null,100000) as $snapshot){
            if(($snapshot['asset_id']??'')!==$assetId||!in_array(($snapshot['status']??''),['published','publication_unknown','publishing','purge_pending'],true))continue;$result['checked']++;$id=(string)$snapshot['id'];$map=RecordStore::get('cdn_mapping',$id)??$snapshot;if(($map['status']??'')==='purged')continue;$versionKey=Utils::text((string)($map['version_key']??''),191);
            if($versionKey===''){$map['status']='purge_pending';$map['purge_reason']=$reason;$map['last_purge_error']='cdn_version_key_missing';$map['purge_requested_at']=$map['purge_requested_at']??Utils::now();try{RecordStore::put('cdn_mapping',$id,$map,(int)$map['version']);}catch(Error $race){if($race->errorCode!=='record_version_conflict')throw $race;}$result['pending']++;continue;}
            try{$purge=CdnRegistry::adapter()->purge([$versionKey]);if(($purge['purged']??false)!==true)throw new Error('cdn_purge_pending','CDN purge was not confirmed.',503);$fresh=RecordStore::get('cdn_mapping',$id)??$map;if(($fresh['status']??'')!=='purged'){$fresh['status']='purged';$fresh['purged_at']=Utils::now();$fresh['purge_reason']=$reason;$fresh['purge_evidence']=Utils::redact($purge);RecordStore::put('cdn_mapping',$id,$fresh,(int)$fresh['version']);}$result['purged']++;}
            catch(\Throwable $failure){$fresh=RecordStore::get('cdn_mapping',$id)??$map;if(($fresh['status']??'')!=='purged'){$fresh['status']='purge_pending';$fresh['purge_reason']=$reason;$fresh['last_purge_error']=$failure instanceof Error?$failure->errorCode:'unexpected';$fresh['purge_requested_at']=$fresh['purge_requested_at']??Utils::now();try{RecordStore::put('cdn_mapping',$id,$fresh,(int)$fresh['version']);}catch(\Throwable){}}$result['pending']++;}
        }
        if($result['pending']>0){try{DegradedStateService::record('cdn-purge','cdn_purge_pending',['asset_ref'=>Utils::hashReference($assetId),'pending'=>$result['pending'],'reason'=>$reason]);}catch(\Throwable){}}
        Audit::record('cdn_asset_purge_reconciled',['asset_id'=>$assetId,'reason'=>$reason,'purged'=>$result['purged'],'pending'=>$result['pending']]);return $result;
    }
    public static function reconcilePublicPurges(int $limit=200): array {
        $limit=max(1,min(2000,$limit));$assets=[];foreach(RecordStore::all('cdn_mapping',0,'purge_pending',100000) as $map){$assetId=(string)($map['asset_id']??'');if($assetId!=='')$assets[$assetId]=(string)($map['purge_reason']??'reconcile');if(count($assets)>=$limit)break;}$out=['assets'=>0,'purged'=>0,'pending'=>0];foreach($assets as $assetId=>$reason){$r=self::purgePublicForAsset($assetId,$reason);$out['assets']++;$out['purged']+=(int)$r['purged'];$out['pending']+=(int)$r['pending'];}return $out;
    }
    public static function publishPublic(string $assetId,string $derivativeId): array {
    RuntimeGuard::requireReady(['streaming']);RestoreService::assertServeAllowed();$asset=self::asset($assetId);$derivative=self::target($asset,$derivativeId);
    if($asset['privacy_class']!=='C0'||!Utils::bool($asset['policy']['delivery']['public_cdn']??false))throw new Error('public_cdn_denied','Only public policy-approved assets may use CDN.',403);
    self::assertDeliverable($asset,$derivative,'view');RightsPolicy::assert($asset['rights'],'view',['territory'=>'GLOBAL','audience_type'=>'public']);
    $providerId=Utils::key((string)($derivative['storage']['provider_id']??''),64);if($providerId==='')throw new Error('storage_provider_missing','CDN source provider identity missing.',500);$source=ProviderRegistry::get($providerId)->openStream((string)$derivative['object_key']);try{$stats=Utils::streamHash($source);}finally{fclose($source);}if(!hash_equals((string)$derivative['sha256'],$stats['sha256'])||(int)$derivative['size']!==(int)$stats['size']){IntegrityService::quarantine($assetId,'cdn_source_integrity_failed');throw new Error('cdn_source_integrity_failed','CDN publication source failed immutable integrity verification.',409);}
    $decision=DomainRegistry::decision($asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'derivative'=>$derivative,'actor_id'=>Auth::currentUser(),'service_id'=>'public-cdn','audience'=>['type'=>'public'],'context'=>['territory'=>'GLOBAL','audience_type'=>'public'],'operation'=>'view','session_id'=>'cdn-publication']);
    if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner authorization is stale.',409);ResidencyCryptoService::assertPublicCdnAllowed($assetId);
    $existing=array_values(array_filter(RecordStore::all('cdn_mapping',0,null,100000),fn($mapping)=>($mapping['asset_id']??'')===$assetId&&($mapping['derivative_id']??'')===$derivativeId&&($mapping['status']??'')==='published'&&($mapping['sha256']??'')===$derivative['sha256']&&($mapping['privacy_class']??'')===$asset['privacy_class']&&hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])));
    if($existing!==[])return $existing[0];
    $cacheKey=hash('sha256',$assetId.'|'.$derivativeId.'|'.$derivative['sha256'].'|'.$asset['privacy_class'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']).'/'.$derivative['sha256'];$mappingId=hash('sha256','cdn-map|'.$cacheKey);$map=['actor_id'=>0,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'status'=>'publishing','cache_key'=>$cacheKey,'publish_started_at'=>Utils::now()];
    try{$map=RecordStore::put('cdn_mapping',$mappingId,$map,0);}catch(Error $conflict){if($conflict->errorCode!=='record_version_conflict')throw $conflict;$current=RecordStore::get('cdn_mapping',$mappingId);$same=$current&&($current['asset_id']??'')===$assetId&&($current['derivative_id']??'')===$derivativeId&&hash_equals((string)($current['sha256']??''),(string)$derivative['sha256'])&&hash_equals((string)($current['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($current['rights_hash']??''),(string)$asset['rights']['policy_hash']);if(!$same)throw new Error('cdn_mapping_conflict','CDN mapping identity conflicts with current asset state.',409);if(($current['status']??'')==='published')return $current;if(($current['status']??'')==='publication_unknown')throw new Error('cdn_publish_reconciliation_required','A prior CDN publish may have succeeded but durable mapping confirmation failed.',409,['mapping_id'=>$mappingId]);if(($current['status']??'')==='publish_failed'){$current['status']='publishing';$current['publish_started_at']=Utils::now();unset($current['last_publish_error'],$current['last_publish_error_at']);$map=RecordStore::put('cdn_mapping',$mappingId,$current,(int)$current['version']);}elseif(($current['status']??'')==='publishing'&&(int)($current['publish_started_at']??0)>0&&(int)$current['publish_started_at']<=Utils::now()-900){$current['status']='publication_unknown';$current['last_publish_error']='publish_lease_expired';$current['last_publish_error_at']=Utils::now();RecordStore::put('cdn_mapping',$mappingId,$current,(int)$current['version']);throw new Error('cdn_publish_reconciliation_required','Stale CDN publication requires reconciliation before retry.',409,['mapping_id'=>$mappingId]);}else throw new Error('cdn_publish_in_progress','A publication for this exact immutable CDN identity is already in progress.',409,['mapping_id'=>$mappingId]);}
    $remotePublished=false;$result=null;
    try{$result=CdnRegistry::adapter()->publish(['asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'object_key'=>$derivative['object_key'],'cache_key'=>$cacheKey,'headers'=>['Content-Type'=>$derivative['mime'],'Cache-Control'=>'public, max-age=31536000, immutable','X-Content-Type-Options'=>'nosniff','Cross-Origin-Resource-Policy'=>'same-site','Content-Security-Policy'=>"default-src 'none'; sandbox"]]);if(($result['published']??false)!==true||empty($result['url'])||empty($result['version_key']))throw new Error('cdn_publish_failed','CDN publication failed.',503);$remotePublished=true;$map['status']='published';$map['url']=Utils::text((string)$result['url'],1000);$map['version_key']=Utils::text((string)$result['version_key'],191);$map['published_at']=Utils::now();unset($map['publish_started_at']);return RecordStore::put('cdn_mapping',$mappingId,$map,(int)$map['version']);}catch(\Throwable $exception){$current=RecordStore::get('cdn_mapping',$mappingId);if($current){$status=(string)($current['status']??'');if($status==='publishing')$current['status']=$remotePublished?'publication_unknown':'publish_failed';elseif($remotePublished&&$status==='purge_pending')$current['status']='purge_pending';else{throw $exception;}$current['last_publish_error']=$exception instanceof Error?$exception->errorCode:'unexpected';$current['last_publish_error_at']=Utils::now();if($remotePublished&&is_array($result)){$current['url']=Utils::text((string)($result['url']??''),1000);$current['version_key']=Utils::text((string)($result['version_key']??''),191);$current['remote_publish_confirmed_at']=Utils::now();}try{$current=RecordStore::put('cdn_mapping',$mappingId,$current,(int)$current['version']);if($remotePublished&&($current['status']??'')==='purge_pending')self::purgePublicForAsset($assetId,(string)($current['purge_reason']??'concurrent-revocation'));}catch(\Throwable){}}throw $exception;}
}
}

final class IntegrityService {
    public static function sample(string $assetId): array {
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset)throw new Error('asset_not_found','Asset not found.',404);
        $targets=[['type'=>'source','id'=>$assetId,'key'=>$asset['object_key']??null,'provider_id'=>$asset['storage']['provider_id']??null,'sha256'=>$asset['sha256']??null,'size'=>$asset['size']??null]];
        $manifestId=$asset['active_manifest_id']??null;
        if($manifestId!==null&&$manifestId!==''){
            if(!is_string($manifestId))self::reject($assetId,'integrity_manifest_invalid');
            $manifest=RecordStore::get('manifest',$manifestId);
            if(!$manifest||($manifest['status']??'')!=='active'||($manifest['asset_id']??'')!==$assetId||!is_array($manifest['derivatives']??null))
                self::reject($assetId,'integrity_manifest_invalid');
            foreach($manifest['derivatives'] as $item){
                if(!is_array($item)||!is_string($item['derivative_id']??null)||$item['derivative_id']==='')
                    self::reject($assetId,'integrity_derivative_invalid');
                $derivative=RecordStore::get('derivative',$item['derivative_id']);
                if(!$derivative||($derivative['asset_id']??'')!==$assetId||($derivative['status']??'')!=='validated'||!empty($derivative['superseded_by']))
                    self::reject($assetId,'integrity_derivative_invalid');
                $targets[]=['type'=>'derivative','id'=>$derivative['id'],'key'=>$derivative['object_key']??null,'provider_id'=>$derivative['storage']['provider_id']??null,'sha256'=>$derivative['sha256']??null,'size'=>$derivative['size']??null];
            }
        }
        $results=[];
        foreach($targets as $target){
            try{$expectedSize=Utils::integer($target['size'],'integrity_size_invalid',0,1073741824);}
            catch(Error $invalid){self::reject($assetId,$invalid->errorCode);}
            $sha=$target['sha256'];
            if(!is_string($sha)||!preg_match('/^[a-f0-9]{64}$/D',$sha))
                self::reject($assetId,'integrity_hash_invalid');
            $key=$target['key'];$providerId=$target['provider_id'];
            if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!is_string($providerId)||$providerId===''||Utils::key($providerId,64)!==$providerId)
                self::reject($assetId,'integrity_target_invalid');
            $stream=ProviderRegistry::get($providerId)->openStream($key);
            try{$stats=Utils::streamHash($stream);}finally{fclose($stream);}
            $ok=hash_equals($sha,$stats['sha256'])&&$expectedSize===$stats['size'];
            $results[]=$target+['ok'=>$ok,'checked_at'=>Utils::now()];
            if(!$ok)self::quarantine($assetId,'bit_rot_detected');
        }
        RecordStore::put('integrity_sample',Utils::id('int'),['actor_id'=>0,'asset_id'=>$assetId,'status'=>array_filter($results,fn($result)=>!$result['ok'])?'failed':'passed','results'=>$results,'created_at'=>Utils::now()]);
        return $results;
    }
    private static function reject(string $assetId,string $code): never {
        self::quarantine($assetId,'integrity_metadata_invalid');
        throw new Error($code,'Integrity metadata is missing or invalid.',409);
    }
    public static function quarantine(string $assetId,string $reason): void {
        $asset=RecordStore::get('asset',$assetId);if(!$asset)return;
        $asset['status']='quarantined';$asset['integrity_status']='failed';$asset['integrity_reason']=Utils::key($reason,64);$asset['quarantined_at']=Utils::now();
        RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);
        DeliveryService::revokeForAsset($assetId,$reason);
        Audit::record('asset_integrity_quarantined',['asset_id'=>$assetId,'reason'=>$reason]);
    }
}
