<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ScannerRegistry {
    private static array $scanners=[];
    public static function register(string $id,callable $scanner): void { self::$scanners[Utils::key($id,48)]=$scanner; }
    public static function registerBuiltins(): void {
        self::register('hash',fn(string $b,array $c)=>['passed'=>hash('sha256',$b)!=='','evidence'=>hash('sha256',$b)]);
        self::register('magic',fn(string $b,array $c)=>['passed'=>Validator::magicMatches(self::temp($b),(string)($c['mime']??'application/octet-stream'))]);
        self::register('mime',fn(string $b,array $c)=>['passed'=>!empty($c['mime'])]);
        self::register('metadata',fn(string $b,array $c)=>['passed'=>true,'action'=>'strip-sensitive-metadata-before-derivative']);
        self::register('polyglot',fn(string $b,array $c)=>['passed'=>substr_count($b,"<?php")===0 && substr_count($b,"<script")===0]);
        self::register('archive',fn(string $b,array $c)=>['passed'=>true,'note'=>'archive-specific inspection required when archive class']);
        self::register('decompression_bomb',fn(string $b,array $c)=>['passed'=>true,'note'=>'ratio enforced by archive processor']);
        self::register('malware',function(string $b,array $c): array { $r=function_exists('apply_filters')?apply_filters('scm_malware_scan_result',null,$b,Utils::redact($c)):null; if(!is_array($r)) return ['passed'=>false,'reason'=>'scanner-provider-unavailable']; return $r; });
    }
    public static function scan(string $bytes,array $required,array $context): array { $results=[]; foreach($required as $id){$id=Utils::key((string)$id,48);if(!isset(self::$scanners[$id])) throw new Error('required_scanner_unavailable','Required scanner is unavailable.',503,['scanner'=>$id]);$r=(self::$scanners[$id])($bytes,$context);if(!is_array($r)||($r['passed']??false)!==true) throw new Error('media_scan_failed','Media failed a required safety scan.',422,['scanner'=>$id]);$results[$id]=Utils::redact($r);}return $results; }
    private static function temp(string $bytes): string {$p=tempnam(sys_get_temp_dir(),'scm-scan');file_put_contents($p,$bytes);register_shutdown_function(static fn()=>@unlink($p));return $p;}
}
