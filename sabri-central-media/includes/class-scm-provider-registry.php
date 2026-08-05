<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ProviderRegistry {
    private static array $providers=[];
    public static function register(string $id,ObjectStore $store,array $meta=[]): void {
        $key=Utils::key($id,64);
        if($key==='') throw new Error('provider_id_invalid','Storage provider identifier is invalid.',500);
        self::$providers[$key]=['store'=>$store,'meta'=>Utils::redact($meta)];
    }
    public static function store(string $id='local-private'): ObjectStore {
        $key=Utils::key($id,64);
        if(!isset(self::$providers[$key])) {
            if(defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true) throw new Error('storage_provider_unavailable','Required storage provider is not registered.',503,['provider'=>$key]);
            self::register('local-private',new LocalObjectStore());
        }
        $store=self::$providers[$key]['store'];
        if(defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true && !$store->healthy()) throw new Error('storage_provider_unhealthy','Required storage provider is not healthy.',503,['provider'=>$key]);
        return $store;
    }
    public static function health(): array {
        $health=[];
        foreach(self::$providers as $id=>$provider) $health[$id]=['healthy'=>$provider['store']->healthy(),'meta'=>$provider['meta']];
        return $health;
    }
    public static function reset(): void { self::$providers=[]; }
}
