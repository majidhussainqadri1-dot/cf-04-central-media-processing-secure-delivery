<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ProviderRegistry {
    private static array $providers=[];
    public static function register(string $id,ObjectStore $store,array $meta=[]): void { self::$providers[Utils::key($id,64)]=['store'=>$store,'meta'=>Utils::redact($meta)]; }
    public static function store(string $id='local-private'): ObjectStore { $k=Utils::key($id,64); if(!isset(self::$providers[$k])) self::register('local-private',new LocalObjectStore()); return self::$providers[$k]['store']; }
    public static function health(): array { return array_map(fn($p)=>['healthy'=>$p['store']->healthy(),'meta'=>$p['meta']],self::$providers); }
}
