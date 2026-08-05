<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Rest {
    public static function register(): void { if(!function_exists('register_rest_route')) return; $ns='sabri-media/v1';
        register_rest_route($ns,'/health',['methods'=>'GET','callback'=>[self::class,'health'],'permission_callback'=>'__return_true']);
        register_rest_route($ns,'/uploads',['methods'=>'POST','callback'=>[self::class,'createUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/parts/(?P<part>\d+)',['methods'=>'PUT','callback'=>[self::class,'putPart'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/uploads/(?P<id>[A-Za-z0-9-]+)/complete',['methods'=>'POST','callback'=>[self::class,'completeUpload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/assets/(?P<id>[A-Za-z0-9-]+)/scan',['methods'=>'POST','callback'=>fn($r)=>self::wrap(fn()=>ProcessingService::scanAndPromote((string)$r['id'])),'permission_callback'=>fn()=>self::can('media_review_safety_signals')]);
        register_rest_route($ns,'/downloads',['methods'=>'GET','callback'=>fn()=>self::wrap(fn()=>DownloadManagerService::list(Auth::currentUser())),'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/downloads',['methods'=>'POST','callback'=>[self::class,'createDownload'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/transfers',['methods'=>'POST','callback'=>[self::class,'createTransfer'],'permission_callback'=>fn()=>self::loggedIn()]);
        register_rest_route($ns,'/workspace-uploads/(?P<type>image|audio)',['methods'=>'POST','callback'=>[self::class,'workspace'],'permission_callback'=>fn()=>self::loggedIn()]);
    }
    private static function loggedIn(): bool { return function_exists('is_user_logged_in') ? is_user_logged_in() : Auth::currentUser()>0; }
    private static function can(string $cap): bool { return function_exists('current_user_can')&&current_user_can($cap); }
    private static function input($r): array { return method_exists($r,'get_json_params')?(array)$r->get_json_params():[]; }
    public static function health(): mixed { return self::wrap(fn()=>['status'=>'ok','runtime_enabled'=>defined('SCM_RUNTIME_ENABLED')&&SCM_RUNTIME_ENABLED===true,'manifest'=>IntegrationRegistry::manifest(),'provider_count'=>count(ProviderRegistry::health())]); }
    public static function createUpload($r): mixed { $i=self::input($r); return self::wrap(fn()=>UploadService::create(Auth::currentUser(),(array)($i['metadata']??[]),(array)($i['policy']??[]),(string)($i['idempotency_key']??''))); }
    public static function putPart($r): mixed { $bytes=(string)$r->get_body(); return self::wrap(fn()=>UploadService::putPart((string)$r['id'],Auth::currentUser(),(int)$r['part'],$bytes,(string)($r->get_header('x-content-sha256')??''))); }
    public static function completeUpload($r): mixed { $i=self::input($r); return self::wrap(fn()=>UploadService::complete((string)$r['id'],Auth::currentUser(),(string)($i['idempotency_key']??''))); }
    public static function createDownload($r): mixed { $i=self::input($r); return self::wrap(fn()=>DownloadManagerService::create(Auth::currentUser(),(array)($i['asset']??[]))); }
    public static function createTransfer($r): mixed { $i=self::input($r); return self::wrap(fn()=>TransferService::create((array)($i['transfer']??[]),(array)($i['policy']??[]))); }
    public static function workspace($r): mixed { return self::wrap(fn()=>WorkspaceUploadService::authorize((string)$r['type'],self::input($r),Auth::currentUser())); }
    private static function wrap(callable $cb): mixed { try{$v=$cb();return function_exists('rest_ensure_response')?rest_ensure_response($v):$v;}catch(Error $e){return class_exists('WP_Error')?new \WP_Error($e->errorCode,$e->getMessage(),['status'=>$e->httpStatus,'context'=>Utils::redact($e->context)]):$e->asArray();} }
}
