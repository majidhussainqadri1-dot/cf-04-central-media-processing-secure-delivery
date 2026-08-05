<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DomainRegistry {
    private static array $domains=[];
    public static function register(string $domain,string $version,array $callbacks): void { $d=Utils::key($domain,64); if($d===''||$version==='') throw new Error('domain_contract_invalid','Invalid domain registration.',500); self::$domains[$d]=['version'=>$version,'callbacks'=>$callbacks]; }
    public static function supports(string $domain,string $callback): bool { $d=Utils::key($domain,64); return isset(self::$domains[$d]['callbacks'][$callback])&&is_callable(self::$domains[$d]['callbacks'][$callback]); }
    public static function call(string $domain,string $callback,array $context): mixed { if(!self::supports($domain,$callback)) throw new Error('domain_contract_unavailable','Owning domain contract unavailable.',503,['domain'=>$domain,'callback'=>$callback]); return (self::$domains[Utils::key($domain,64)]['callbacks'][$callback])($context); }
    public static function callOptional(string $domain,string $callback,array $context): mixed { return self::call($domain,$callback,$context); }
    public static function version(string $domain): string { return (string)(self::$domains[Utils::key($domain,64)]['version']??''); }
    public static function reset(): void { self::$domains=[]; }
}
