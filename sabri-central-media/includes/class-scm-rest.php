<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Rest {
    public static function register(): void {
        if(!function_exists('register_rest_route')) return;
        $namespace='sabri-media/v1';
        register_rest_route($namespace,'/health',['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>'__return_true']);
        register_rest_route($namespace,'/uploads',['methods'=>'POST','callback'=>[self::class,'createUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/uploads/(?P<id>[A-Za-z0-9-]+)/parts/(?P<part>\d+)',['methods'=>'PUT','callback'=>[self::class,'putPart'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/uploads/(?P<id>[A-Za-z0-9-]+)/complete',['methods'=>'POST','callback'=>[self::class,'completeUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/assets/(?P<id>[A-Za-z0-9-]+)/scan',['methods'=>'POST','callback'=>fn($request)=>self::wrap(fn()=>ProcessingService::scanAndPromote((string)$request['id'])),'permission_callback'=>fn()=>self::can('media_review_safety_signals')]);
        register_rest_route($namespace,'/downloads',['methods'=>'GET','callback'=>fn()=>self::wrap(fn()=>DownloadManagerService::list(Auth::currentUser())),'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/downloads',['methods'=>'POST','callback'=>[self::class,'createDownload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/transfers',['methods'=>'POST','callback'=>[self::class,'createTransfer'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($namespace,'/workspace-uploads/(?P<type>image|audio)',['methods'=>'POST','callback'=>[self::class,'workspace'],'permission_callback'=>fn()=>self::loggedIn()]);
    }
    private static function loggedIn(): bool { return function_exists('is_user_logged_in') ? is_user_logged_in() : Auth::currentUser()>0; }
    private static function can(string $capability): bool { return function_exists('current_user_can')&&current_user_can($capability); }
    private static function input($request): array {
        if(!is_object($request)||!method_exists($request,'get_json_params')) return [];
        $params=$request->get_json_params();
        return is_array($params)?$params:[];
    }
    public static function health(): mixed {
        return self::wrap(function(): array {
            $providers=ProviderRegistry::health();
            return [
                'status'=>(defined('SCM_RUNTIME_ENABLED')&&SCM_RUNTIME_ENABLED===true&&$providers&&!in_array(false,array_column($providers,'healthy'),true))?'ready':'disabled_or_degraded',
                'runtime_enabled'=>defined('SCM_RUNTIME_ENABLED')&&SCM_RUNTIME_ENABLED===true,
                'manifest'=>IntegrationRegistry::manifest(),
                'provider_count'=>count($providers),
                'providers_healthy'=>$providers!==[]&&!in_array(false,array_column($providers,'healthy'),true),
            ];
        });
    }
    public static function createUpload($request): mixed { $input=self::input($request);return self::wrap(fn()=>UploadService::create(Auth::currentUser(),(array)($input['metadata']??[]),(array)($input['policy']??[]),(string)($input['idempotency_key']??''))); }
    public static function putPart($request): mixed {
        $bytes=is_object($request)&&method_exists($request,'get_body')?(string)$request->get_body():'';
        $sha=is_object($request)&&method_exists($request,'get_header')?(string)$request->get_header('x-content-sha256'):'';
        return self::wrap(fn()=>UploadService::putPart((string)$request['id'],Auth::currentUser(),(int)$request['part'],$bytes,$sha));
    }
    public static function completeUpload($request): mixed { $input=self::input($request);return self::wrap(fn()=>UploadService::complete((string)$request['id'],Auth::currentUser(),(string)($input['idempotency_key']??''))); }
    public static function createDownload($request): mixed { $input=self::input($request);return self::wrap(fn()=>DownloadManagerService::create(Auth::currentUser(),(array)($input['asset']??[]))); }
    public static function createTransfer($request): mixed { $input=self::input($request);return self::wrap(fn()=>TransferService::create((array)($input['transfer']??[]),(array)($input['policy']??[]))); }
    public static function workspace($request): mixed { return self::wrap(fn()=>WorkspaceUploadService::authorize((string)$request['type'],self::input($request),Auth::currentUser())); }
    private static function wrap(callable $callback): mixed {
        try {
            $value=$callback();
            return function_exists('rest_ensure_response')?rest_ensure_response($value):$value;
        } catch(Error $error){
            return class_exists('WP_Error')?new \WP_Error($error->errorCode,$error->getMessage(),['status'=>$error->httpStatus,'context'=>Utils::redact($error->context)]):$error->asArray();
        } catch(\Throwable $error){
            $incident=Utils::id('err');
            try{Audit::record('rest_unexpected_error',['incident_id'=>$incident,'exception_class'=>get_class($error)]);}catch(\Throwable){}
            $payload=['code'=>'internal_error','message'=>'The media service could not complete this request.','status'=>500,'context'=>['incident_id'=>$incident]];
            return class_exists('WP_Error')?new \WP_Error($payload['code'],$payload['message'],['status'=>500,'context'=>$payload['context']]):$payload;
        }
    }
}
