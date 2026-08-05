<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class ServiceAuth {
    public static function verify(string $serviceId,string $method,string $path,string $body,string $nonce,int $timestamp,string $signature): array {
        $serviceId=Utils::key($serviceId,64);$method=strtoupper(Utils::text($method,12));$path=self::path($path);$nonce=Utils::text($nonce,96);if($serviceId===''||!in_array($method,['GET','POST','PUT','DELETE','PATCH'],true)||$nonce===''||abs(Utils::now()-$timestamp)>300)throw new Error('service_auth_invalid','Service authentication envelope invalid.',403);
        $key=function_exists('apply_filters')?apply_filters('scm_service_secret',null,$serviceId):null;if(!is_string($key)||strlen($key)<32)throw new Error('service_secret_unavailable','Service secret unavailable.',503,['service_id'=>$serviceId]);$canonical=$serviceId.'|'.$method.'|'.$path.'|'.$timestamp.'|'.$nonce.'|'.hash('sha256',$body);$expected=Utils::b64url(hash_hmac('sha256',$canonical,$key,true));if(!hash_equals($expected,$signature))throw new Error('service_signature_invalid','Service signature invalid.',403);$id=hash('sha256',$serviceId.'|'.$nonce);$existing=RecordStore::get('service_nonce',$id);if($existing)throw new Error('service_replay_denied','Service nonce already used.',409);RecordStore::put('service_nonce',$id,['actor_id'=>0,'service_id'=>$serviceId,'nonce_hash'=>hash('sha256',$nonce),'status'=>'consumed','expires_at'=>Utils::now()+600,'created_at'=>Utils::now()]);return ['service_id'=>$serviceId,'method'=>$method,'path'=>$path,'body_hash'=>hash('sha256',$body)];
    }
    private static function path(string $path): string {$path=parse_url($path,PHP_URL_PATH);if(!is_string($path)||$path===''||str_contains($path,"\r")||str_contains($path,"\n")||str_contains($path,'..')||!str_starts_with($path,'/'))throw new Error('service_path_invalid','Service path invalid.',400);return $path;}
}

final class StreamingEndpoint {
    public static function register(): void {
        if(function_exists('add_rewrite_rule'))add_rewrite_rule('^scm-media-delivery/?$','index.php?scm_media_delivery=1','top');
        if(function_exists('add_filter'))add_filter('query_vars',static function(array $vars): array {$vars[]='scm_media_delivery';return $vars;});
        if(function_exists('add_action'))add_action('template_redirect',[self::class,'handle'],0);
    }
    public static function handle(): void {
        $active=function_exists('get_query_var')?(int)get_query_var('scm_media_delivery'):0;if($active!==1)return;
        try{
            $actor=Auth::requireUser();$token=self::bearer();$resolved=function_exists('apply_filters')?apply_filters('scm_delivery_request_context',null,$actor):null;
            if(!is_array($resolved))throw new Error('delivery_request_context_unavailable','Delivery request context unavailable.',503);
            Utils::requireFields($resolved,['service_id','audience','context','session_id'],'delivery_request_context_incomplete');
            $range=isset($_SERVER['HTTP_RANGE'])?(string)$_SERVER['HTTP_RANGE']:null;
            $result=DeliveryService::serve($token,$actor,(string)$resolved['service_id'],(array)$resolved['audience'],(array)$resolved['context'],(string)$resolved['session_id'],$range);
            if(function_exists('status_header'))status_header((int)$result['status']);else http_response_code((int)$result['status']);
            foreach($result['headers'] as $name=>$value)header($name.': '.$value,true);
            header('X-Robots-Tag: noindex, nofollow, noarchive',true);header('X-Frame-Options: DENY',true);
            while(!feof($result['stream'])){$chunk=fread($result['stream'],1048576);if($chunk===false)throw new Error('delivery_read_failed','Delivery stream failed.',500);if($chunk==='')break;echo $chunk;if(function_exists('fastcgi_finish_request')){}else{@ob_flush();flush();}}
            fclose($result['stream']);exit;
        }catch(Error $e){if(function_exists('status_header'))status_header($e->httpStatus);else http_response_code($e->httpStatus);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo Utils::json($e->asArray());exit;}catch(\Throwable $e){http_response_code(500);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo Utils::json(['code'=>'internal_error','message'=>'Delivery failed.','status'=>500,'context'=>[]]);exit;}
    }
    private static function bearer(): string {$header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');if(preg_match('/^Bearer\s+(.+)$/i',$header,$m))return trim($m[1]);$token=(string)($_SERVER['HTTP_X_DELIVERY_TOKEN']??'');if($token==='')throw new Error('delivery_token_missing','Delivery token missing.',401);return $token;}
}

final class Rest {
    public static function register(): void {if(!function_exists('register_rest_route'))return;$ns='sabri-media/v1';
        register_rest_route($ns,'/health',['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/uploads',['methods'=>'POST','callback'=>[self::class,'createUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/parts/(?P<part>\d+)',['methods'=>'PUT','callback'=>[self::class,'putPart'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/complete',['methods'=>'POST','callback'=>[self::class,'completeUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/(?P<action>pause|resume|abort)',['methods'=>'POST','callback'=>[self::class,'uploadAction'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/assets/(?P<id>[A-Za-z0-9-]+)/process',['methods'=>'POST','callback'=>[self::class,'processAsset'],'permission_callback'=>fn()=>self::can('media_reprocess')]);
        register_rest_route($ns,'/delivery/grants',['methods'=>'POST','callback'=>[self::class,'issueGrant'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/delivery',['methods'=>'GET','callback'=>[self::class,'serveMetadata'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/transfers',['methods'=>'POST','callback'=>[self::class,'createTransfer'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/downloads',['methods'=>'GET','callback'=>fn()=>self::wrap(fn()=>DownloadManagerService::list(Auth::currentUser())),'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/downloads',['methods'=>'POST','callback'=>[self::class,'createDownload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/deletions',['methods'=>'POST','callback'=>[self::class,'requestDeletion'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/deletions/(?P<id>[A-Za-z0-9-]+)/process',['methods'=>'POST','callback'=>[self::class,'processDeletion'],'permission_callback'=>fn()=>self::can('media_reprocess')]);
        register_rest_route($ns,'/holds',['methods'=>'POST','callback'=>[self::class,'placeHold'],'permission_callback'=>fn()=>self::can('media_hold')]);
        register_rest_route($ns,'/providers/webhook/(?P<provider>[A-Za-z0-9_.-]+)',['methods'=>'POST','callback'=>[self::class,'providerWebhook'],'permission_callback'=>'__return_true']);
    }
    private static function loggedIn(): bool {return function_exists('is_user_logged_in')?is_user_logged_in():Auth::currentUser()>0;}
    private static function can(string $cap): bool {return function_exists('current_user_can')&&current_user_can($cap);}
    private static function input($request): array {$params=is_object($request)&&method_exists($request,'get_params')?$request->get_params():[];$json=is_object($request)&&method_exists($request,'get_json_params')?$request->get_json_params():[];return array_replace(is_array($params)?$params:[],is_array($json)?$json:[]);}
    private static function header($request,string $name): string {return is_object($request)&&method_exists($request,'get_header')?(string)$request->get_header($name):'';}
    public static function health(): mixed {return self::wrap(fn()=>Observability::health());}
    public static function createUpload($request): mixed {$i=self::input($request);return self::wrap(fn()=>UploadService::create(Auth::currentUser(),(array)($i['metadata']??[]),(array)($i['policy']??[]),(string)($i['idempotency_key']??''),(array)($i['quota_limits']??[])));}
    public static function putPart($request): mixed {$body=is_object($request)&&method_exists($request,'get_body')?(string)$request->get_body():'';$stream=Utils::tempStream();fwrite($stream,$body);rewind($stream);try{return self::wrap(fn()=>UploadService::putPart((string)$request['id'],Auth::currentUser(),(int)$request['part'],$stream,self::header($request,'x-content-sha256'),self::header($request,'x-upload-credential')));}finally{fclose($stream);}}
    public static function completeUpload($request): mixed {$i=self::input($request);return self::wrap(fn()=>UploadService::complete((string)$request['id'],Auth::currentUser(),self::header($request,'x-upload-credential'),(string)($i['idempotency_key']??'')));}
    public static function uploadAction($request): mixed {$i=self::input($request);$id=(string)$request['id'];$actor=Auth::currentUser();$cred=self::header($request,'x-upload-credential');return self::wrap(fn()=>match((string)$request['action']){'pause'=>UploadService::pause($id,$actor,$cred),'resume'=>UploadService::resume($id,$actor,$cred),'abort'=>UploadService::abort($id,$actor,$cred,(string)($i['reason']??'user-abort')),default=>throw new Error('upload_action_invalid','Invalid upload action.',400)});}
    public static function processAsset($request): mixed {return self::wrap(function()use($request){ProcessingService::start((string)$request['id']);return ProcessingService::execute((string)$request['id'],'rest-worker');});}
    public static function issueGrant($request): mixed {$i=self::input($request);return self::wrap(fn()=>['endpoint'=>function_exists('home_url')?home_url('/scm-media-delivery/'):'/scm-media-delivery/','token'=>DeliveryService::issue((string)($i['asset_id']??''),isset($i['derivative_id'])?(string)$i['derivative_id']:null,Auth::currentUser(),(string)($i['service_id']??'web'),(array)($i['audience']??[]),(array)($i['context']??[]),(string)($i['operation']??'view'),(array)($i['range_policy']??[]),(string)($i['session_id']??''),(int)($i['ttl']??300),(int)($i['max_uses']??20))]);}
    public static function serveMetadata($request): mixed {$i=self::input($request);return self::wrap(function()use($i){Utils::requireFields($i,['grant_id'],'grant_id_missing');$grant=RecordStore::get('grant',(string)$i['grant_id']);if(!$grant||(int)$grant['actor_id']!==Auth::currentUser())throw new Error('grant_not_found','Grant not found.',404);return ['grant_id'=>$grant['id'],'status'=>$grant['status'],'expires_at'=>$grant['expires_at'],'endpoint'=>function_exists('home_url')?home_url('/scm-media-delivery/'):'/scm-media-delivery/'];});}

    public static function createTransfer($request): mixed {$i=self::input($request);return self::wrap(fn()=>TransferService::create((array)($i['transfer']??[]),(array)($i['policy']??[]),(array)($i['quota_limits']??[])));}
    public static function createDownload($request): mixed {$i=self::input($request);return self::wrap(fn()=>DownloadManagerService::create(Auth::currentUser(),(string)($i['asset_id']??''),isset($i['derivative_id'])?(string)$i['derivative_id']:null,(string)($i['mode']??'view'),(array)($i['context']??[])));}
    public static function requestDeletion($request): mixed {$i=self::input($request);return self::wrap(fn()=>DeletionService::request((string)($i['asset_id']??''),Auth::currentUser(),(string)($i['reason']??'user-request'),(array)($i['context']??[])));}
    public static function processDeletion($request): mixed {return self::wrap(fn()=>DeletionService::process((string)$request['id']));}
    public static function placeHold($request): mixed {$i=self::input($request);return self::wrap(fn()=>LegalHoldService::place((string)($i['asset_id']??''),Auth::currentUser(),(array)($i['hold']??[])));}
    public static function providerWebhook($request): mixed {$provider=(string)$request['provider'];$body=is_object($request)&&method_exists($request,'get_body')?(string)$request->get_body():'';$event=self::header($request,'x-event-id');$timestamp=(int)self::header($request,'x-event-timestamp');$signature=self::header($request,'x-event-signature');$secret=function_exists('apply_filters')?(string)apply_filters('scm_provider_webhook_secret','',$provider):'';return self::wrap(fn()=>WebhookService::verify($provider,$event,$timestamp,$body,$signature,$secret));}
    private static function wrap(callable $callback): mixed {try{$value=$callback();return function_exists('rest_ensure_response')?rest_ensure_response($value):$value;}catch(Error $e){return class_exists('WP_Error')?new \WP_Error($e->errorCode,$e->getMessage(),['status'=>$e->httpStatus,'context'=>Utils::redact($e->context)]):$e->asArray();}catch(\Throwable $e){$incident=Utils::id('err');try{Audit::record('rest_unexpected_error',['incident_id'=>$incident,'exception_class'=>get_class($e)]);}catch(\Throwable){}$payload=['code'=>'internal_error','message'=>'The media service could not complete this request.','status'=>500,'context'=>['incident_id'=>$incident]];return class_exists('WP_Error')?new \WP_Error($payload['code'],$payload['message'],['status'=>500,'context'=>$payload['context']]):$payload;}}
}
