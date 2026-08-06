<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class ServiceAuth {
    private const MAX_BODY_BYTES=1048576;
    public static function verify(string $serviceId,string $method,string $path,string $body,string $nonce,int $timestamp,string $signature): array {
        if(strlen($body)>self::MAX_BODY_BYTES)throw new Error('service_body_too_large','Service authentication body exceeds the permitted size.',413);
        $serviceId=Utils::key($serviceId,64);$method=strtoupper(Utils::text($method,12));$path=self::path($path);$nonce=Utils::text($nonce,96);$signature=Utils::text($signature,256);
        if($serviceId===''||!in_array($method,['GET','POST','PUT','DELETE','PATCH'],true)||$nonce===''||$signature===''||abs(Utils::now()-$timestamp)>300)throw new Error('service_auth_invalid','Service authentication envelope invalid.',403);
        $key=function_exists('apply_filters')?apply_filters('scm_service_secret',null,$serviceId):null;if(!is_string($key)||strlen($key)<32)throw new Error('service_secret_unavailable','Service secret unavailable.',503,['service_id'=>$serviceId]);
        $bodyHash=hash('sha256',$body);$canonical=$serviceId.'|'.$method.'|'.$path.'|'.$timestamp.'|'.$nonce.'|'.$bodyHash;$expected=Utils::b64url(hash_hmac('sha256',$canonical,$key,true));if(!hash_equals($expected,$signature))throw new Error('service_signature_invalid','Service signature invalid.',403);
        $id=hash('sha256',$serviceId.'|'.$nonce);$existing=RecordStore::get('service_nonce',$id);if($existing&&(int)($existing['expires_at']??0)>Utils::now())throw new Error('service_replay_denied','Service nonce already used.',409);
        RecordStore::put('service_nonce',$id,['actor_id'=>0,'service_id'=>$serviceId,'nonce_hash'=>hash('sha256',$nonce),'body_hash'=>$bodyHash,'status'=>'consumed','expires_at'=>Utils::now()+600,'created_at'=>Utils::now()],$existing?(int)$existing['version']:null);
        return ['service_id'=>$serviceId,'method'=>$method,'path'=>$path,'body_hash'=>$bodyHash];
    }
    private static function path(string $path): string {$path=parse_url($path,PHP_URL_PATH);if(!is_string($path)||$path===''||strlen($path)>1024||str_contains($path,"\r")||str_contains($path,"\n")||preg_match('#(?:^|/)\.\.(?:/|$)#',$path)||!str_starts_with($path,'/'))throw new Error('service_path_invalid','Service path invalid.',400);return $path;}
}

final class StreamingEndpoint {
    public static function register(): void {
        if(function_exists('add_rewrite_rule'))add_rewrite_rule('^scm-media-delivery/?$','index.php?scm_media_delivery=1','top');
        if(function_exists('add_filter'))add_filter('query_vars',static function(array $vars): array {if(!in_array('scm_media_delivery',$vars,true))$vars[]='scm_media_delivery';return $vars;});
        if(function_exists('add_action'))add_action('template_redirect',[self::class,'handle'],0);
    }
    public static function handle(): void {
        $active=function_exists('get_query_var')?(int)get_query_var('scm_media_delivery'):0;if($active!==1)return;
        $stream=null;$started=false;
        try{
            $actor=Auth::requireUser();$token=self::bearer();$resolved=function_exists('apply_filters')?apply_filters('scm_delivery_request_context',null,$actor):null;
            if(!is_array($resolved))throw new Error('delivery_request_context_unavailable','Delivery request context unavailable.',503);
            Utils::requireFields($resolved,['service_id','audience','context','session_id'],'delivery_request_context_incomplete');
            $range=isset($_SERVER['HTTP_RANGE'])?Utils::text((string)$_SERVER['HTTP_RANGE'],191):null;
            $result=DeliveryService::serve($token,$actor,(string)$resolved['service_id'],(array)$resolved['audience'],(array)$resolved['context'],(string)$resolved['session_id'],$range);
            $stream=$result['stream']??null;if(!is_resource($stream))throw new Error('delivery_stream_invalid','Delivery stream unavailable.',500);
            if(function_exists('status_header'))status_header((int)$result['status']);else http_response_code((int)$result['status']);
            foreach((array)$result['headers'] as $name=>$value){$name=trim((string)$name);$value=str_replace(["\r","\n"],'',trim((string)$value));if($name!==''&&$value!=='')header($name.': '.$value,true);}
            header('X-Robots-Tag: noindex, nofollow, noarchive',true);header('X-Frame-Options: DENY',true);$started=true;
            while(!feof($stream)){if(connection_aborted())break;$chunk=fread($stream,1048576);if($chunk===false)throw new Error('delivery_read_failed','Delivery stream failed.',500);if($chunk==='')break;echo $chunk;if(function_exists('ob_get_level')&&ob_get_level()>0)@ob_flush();flush();}
            exit;
        }catch(Error $e){if($started)exit;if(function_exists('status_header'))status_header($e->httpStatus);else http_response_code($e->httpStatus);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo Utils::json(['code'=>$e->errorCode,'message'=>$e->getMessage(),'status'=>$e->httpStatus,'context'=>Utils::redact($e->context)]);exit;}catch(\Throwable $e){if($started)exit;http_response_code(500);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo Utils::json(['code'=>'internal_error','message'=>'Delivery failed.','status'=>500,'context'=>[]]);exit;}finally{if(is_resource($stream))fclose($stream);}
    }
    private static function bearer(): string {$header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');$token='';if(preg_match('/^Bearer\s+([^\s]+)$/i',$header,$m))$token=trim($m[1]);elseif(isset($_SERVER['HTTP_X_DELIVERY_TOKEN']))$token=trim((string)$_SERVER['HTTP_X_DELIVERY_TOKEN']);if($token===''||strlen($token)>8192)throw new Error('delivery_token_missing','Delivery token missing or invalid.',401);return $token;}
}

final class Rest {
    private const MAX_WEBHOOK_BYTES=1048576;
    public static function register(): void {if(!function_exists('register_rest_route'))return;$ns='sabri-media/v1';
        register_rest_route($ns,'/health',['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/health/details',['methods'=>'GET','callback'=>[self::class,'healthDetails'],'permission_callback'=>fn()=>self::can('manage_sabri_media')]);
        register_rest_route($ns,'/uploads',['methods'=>'POST','callback'=>[self::class,'createUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/parts/(?P<part>\d+)',['methods'=>'PUT','callback'=>[self::class,'putPart'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/complete',['methods'=>'POST','callback'=>[self::class,'completeUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/(?P<action>pause|resume|abort)',['methods'=>'POST','callback'=>[self::class,'uploadAction'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/assets/(?P<id>[A-Za-z0-9-]+)/process',['methods'=>'POST','callback'=>[self::class,'processAsset'],'permission_callback'=>fn()=>self::can('media_reprocess')]);
        register_rest_route($ns,'/delivery/grants',['methods'=>'POST','callback'=>[self::class,'issueGrant'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/delivery',['methods'=>'GET','callback'=>[self::class,'serveMetadata'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/transfers',['methods'=>'POST','callback'=>[self::class,'createTransfer'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/downloads',['methods'=>'GET','callback'=>[self::class,'listDownloads'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/downloads',['methods'=>'POST','callback'=>[self::class,'createDownload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/deletions',['methods'=>'POST','callback'=>[self::class,'requestDeletion'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/deletions/(?P<id>[A-Za-z0-9-]+)/process',['methods'=>'POST','callback'=>[self::class,'processDeletion'],'permission_callback'=>fn()=>self::can('media_reprocess')]);
        register_rest_route($ns,'/holds',['methods'=>'POST','callback'=>[self::class,'placeHold'],'permission_callback'=>fn()=>self::can('media_hold')]);
        register_rest_route($ns,'/providers/webhook/(?P<provider>[A-Za-z0-9_.-]+)',['methods'=>'POST','callback'=>[self::class,'providerWebhook'],'permission_callback'=>'__return_true']);
    }
    private static function loggedIn(): bool {return function_exists('is_user_logged_in')?is_user_logged_in():Auth::currentUser()>0;}
    private static function can(string $cap): bool {return function_exists('current_user_can')&&current_user_can($cap);}
    private static function input($request): array {$params=is_object($request)&&method_exists($request,'get_params')?$request->get_params():[];$json=is_object($request)&&method_exists($request,'get_json_params')?$request->get_json_params():[];return array_replace(is_array($params)?$params:[],is_array($json)?$json:[]);}
    private static function header($request,string $name): string {return is_object($request)&&method_exists($request,'get_header')?Utils::text((string)$request->get_header($name),8192):'';}
    private static function route($request,string $key): string {if(is_array($request))return Utils::text((string)($request[$key]??''),191);if($request instanceof \ArrayAccess)return Utils::text((string)($request[$key]??''),191);return '';}
    private static function bodyText($request,int $max): string {$length=(int)self::header($request,'content-length');if($length>$max)throw new Error('request_body_too_large','Request body exceeds the permitted size.',413);$body=is_object($request)&&method_exists($request,'get_body')?(string)$request->get_body():'';if(strlen($body)>$max)throw new Error('request_body_too_large','Request body exceeds the permitted size.',413);return $body;}
    /** @return array{0:resource,1:bool} */
    private static function partStream($request,int $max): array {
    if($max<1)throw new Error('upload_part_policy_invalid','Upload part policy is unavailable.',503);
    $length=(int)self::header($request,'content-length');
    if($length>$max)throw new Error('upload_part_size_invalid','Upload part exceeds policy.',413);
    $filtered=function_exists('apply_filters')?apply_filters('scm_rest_upload_stream',null,$request,$max):null;
    if(is_resource($filtered))return [$filtered,false];
    if(is_object($request)&&method_exists($request,'get_body_stream')){$candidate=$request->get_body_stream();if(is_resource($candidate))return [$candidate,false];}
    $input=@fopen('php://input','rb');
    if(is_resource($input))return [$input,true];
    $body=self::bodyText($request,$max);if($body==='')throw new Error('upload_part_empty','Upload part body is empty.',400);
    $stream=Utils::tempStream();Utils::writeAll($stream,$body);rewind($stream);return [$stream,true];
}
    private static function clean(array $record,array $remove=[]): array {foreach(array_merge(['record_type','object_key','storage','credential_hash','upload_credential_hash','policy_hash','rights_hash','key_id','nonce','tag'], $remove) as $key)unset($record[$key]);if(isset($record['policy']))$record['policy']=['policy_id'=>$record['policy']['policy_id']??'','policy_version'=>$record['policy']['policy_version']??0,'owner_domain'=>$record['policy']['owner_domain']??'','purpose'=>$record['policy']['purpose']??'','privacy_class'=>$record['policy']['privacy_class']??'','media_class'=>$record['policy']['media_class']??''];if(isset($record['rights']))$record['rights']=['rights_id'=>$record['rights']['rights_id']??'','rights_version'=>$record['rights']['rights_version']??0,'consent_status'=>$record['rights']['consent_status']??'','expires_at'=>$record['rights']['expires_at']??0];return Utils::redact($record);}
    public static function health(): mixed {return self::wrap(function(){$h=Observability::health();return ['status'=>$h['status']??'unavailable','runtime_enabled'=>(bool)($h['runtime_enabled']??false),'schema_ready'=>(bool)($h['schema_ready']??false)];});}
    public static function healthDetails(): mixed {return self::wrap(fn()=>Observability::health());}
    public static function createUpload($request): mixed {$i=self::input($request);return self::wrap(function()use($i){$result=UploadService::create(Auth::currentUser(),(array)($i['metadata']??[]),(array)($i['policy']??[]),(string)($i['idempotency_key']??''));$credential=(string)($result['upload_credential']??'');$view=self::clean($result,['parts','policy','rights']);$view['upload_credential']=$credential;return $view;});}
    public static function putPart($request): mixed {$id=self::route($request,'id');$part=(int)self::route($request,'part');$upload=RecordStore::get('upload',$id);if(!$upload||(int)($upload['actor_id']??0)!==Auth::currentUser())return self::wrap(fn()=>throw new Error('upload_not_found','Upload session not found.',404));$max=(int)($upload['policy']['max_part_size_bytes']??0);[$stream,$close]=self::partStream($request,$max);try{return self::wrap(fn()=>self::clean(UploadService::putPart($id,Auth::currentUser(),$part,$stream,self::header($request,'x-content-sha256'),self::header($request,'x-upload-credential'))));}finally{if($close&&is_resource($stream))fclose($stream);}}
    public static function completeUpload($request): mixed {$i=self::input($request);return self::wrap(fn()=>self::clean(UploadService::complete(self::route($request,'id'),Auth::currentUser(),self::header($request,'x-upload-credential'),(string)($i['idempotency_key']??'')),['scan_results','job_graph']));}
    public static function uploadAction($request): mixed {$i=self::input($request);$id=self::route($request,'id');$actor=Auth::currentUser();$cred=self::header($request,'x-upload-credential');return self::wrap(fn()=>self::clean(match(self::route($request,'action')){'pause'=>UploadService::pause($id,$actor,$cred),'resume'=>UploadService::resume($id,$actor,$cred),'abort'=>UploadService::abort($id,$actor,$cred,(string)($i['reason']??'user-abort')),default=>throw new Error('upload_action_invalid','Invalid upload action.',400)},['policy','parts']));}
    public static function processAsset($request): mixed {return self::wrap(function()use($request){$id=self::route($request,'id');$jobs=ProcessingService::start($id);return ['asset_id'=>$id,'processing_status'=>'queued','jobs'=>array_keys($jobs)];});}
    public static function issueGrant($request): mixed {$i=self::input($request);return self::wrap(function()use($i){$actor=Auth::currentUser();$context=(array)($i['context']??[]);$context['audience_type']='private';$service=function_exists('apply_filters')?(string)apply_filters('scm_web_delivery_service_id','web',$actor):'web';$token=DeliveryService::issue((string)($i['asset_id']??''),isset($i['derivative_id'])?(string)$i['derivative_id']:null,$actor,$service,['type'=>'user','user_id'=>$actor],$context,(string)($i['operation']??'view'),(array)($i['range_policy']??[]),(string)($i['session_id']??''),(int)($i['ttl']??300),(int)($i['max_uses']??20));return ['endpoint'=>function_exists('home_url')?home_url('/scm-media-delivery/'):'/scm-media-delivery/','token'=>$token];});}
    public static function serveMetadata($request): mixed {$i=self::input($request);return self::wrap(function()use($i){Utils::requireFields($i,['grant_id'],'grant_id_missing');$grant=RecordStore::get('grant',(string)$i['grant_id']);if(!$grant||(int)$grant['actor_id']!==Auth::currentUser())throw new Error('grant_not_found','Grant not found.',404);return ['grant_id'=>$grant['id'],'status'=>$grant['status'],'expires_at'=>$grant['expires_at'],'endpoint'=>function_exists('home_url')?home_url('/scm-media-delivery/'):'/scm-media-delivery/'];});}
    public static function createTransfer($request): mixed {$i=self::input($request);return self::wrap(fn()=>self::clean(TransferService::create((array)($i['transfer']??[]),(array)($i['policy']??[])),['policy']));}
    public static function listDownloads(): mixed {return self::wrap(fn()=>array_map(fn($v)=>self::clean((array)$v),DownloadManagerService::list(Auth::currentUser())));}
    public static function createDownload($request): mixed {$i=self::input($request);return self::wrap(fn()=>self::clean(DownloadManagerService::create(Auth::currentUser(),(string)($i['asset_id']??''),isset($i['derivative_id'])?(string)$i['derivative_id']:null,(string)($i['mode']??'view'),(array)($i['context']??[]))));}
    public static function requestDeletion($request): mixed {$i=self::input($request);return self::wrap(fn()=>self::clean(DeletionService::request((string)($i['asset_id']??''),Auth::currentUser(),(string)($i['reason']??'user-request'),(array)($i['context']??[]))));}
    public static function processDeletion($request): mixed {return self::wrap(fn()=>self::clean(DeletionService::process(self::route($request,'id'))));}
    public static function placeHold($request): mixed {$i=self::input($request);return self::wrap(fn()=>self::clean(LegalHoldService::place((string)($i['asset_id']??''),Auth::currentUser(),(array)($i['hold']??[]))));}
    public static function providerWebhook($request): mixed {$provider=self::route($request,'provider');$body=self::bodyText($request,self::MAX_WEBHOOK_BYTES);$event=self::header($request,'x-event-id');$timestamp=(int)self::header($request,'x-event-timestamp');$signature=self::header($request,'x-event-signature');$secret=function_exists('apply_filters')?(string)apply_filters('scm_provider_webhook_secret','',$provider):'';return self::wrap(fn()=>WebhookService::verify($provider,$event,$timestamp,$body,$signature,$secret));}
    private static function wrap(callable $callback): mixed {try{$value=$callback();return function_exists('rest_ensure_response')?rest_ensure_response($value):$value;}catch(Error $e){$context=Utils::redact($e->context);return class_exists('WP_Error')?new \WP_Error($e->errorCode,$e->getMessage(),['status'=>$e->httpStatus,'context'=>$context]):['code'=>$e->errorCode,'message'=>$e->getMessage(),'status'=>$e->httpStatus,'context'=>$context];}catch(\Throwable $e){$incident=Utils::id('err');try{Audit::record('rest_unexpected_error',['incident_id'=>$incident,'exception_class'=>get_class($e)]);}catch(\Throwable){}$payload=['code'=>'internal_error','message'=>'The media service could not complete this request.','status'=>500,'context'=>['incident_id'=>$incident]];return class_exists('WP_Error')?new \WP_Error($payload['code'],$payload['message'],['status'=>500,'context'=>$payload['context']]):$payload;}}
}
