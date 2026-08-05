<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class ScannerRegistry {
    private static array $scanners=[];
    public static function register(string $id,callable $scanner): void { $id=Utils::key($id,48);if($id==='')throw new Error('scanner_id_invalid','Scanner identifier is invalid.',500);self::$scanners[$id]=$scanner; }
    public static function registerBuiltins(): void {
        self::register('hash',fn(string $bytes,array $context)=>['passed'=>true,'evidence'=>hash('sha256',$bytes)]);
        self::register('magic',function(string $bytes,array $context): array { $path=self::temp($bytes);$detected=Validator::detectMime($path);return ['passed'=>$detected!==''&&Validator::magicMatches($path,(string)($context['mime']??'')),'detected_mime'=>$detected]; });
        self::register('mime',function(string $bytes,array $context): array { $detected=Validator::detectMime(self::temp($bytes));$declared=Validator::canonicalMime((string)($context['mime']??''));return ['passed'=>$detected!==''&&$declared!==''&&hash_equals($detected,$declared),'detected_mime'=>$detected,'declared_mime'=>$declared]; });
        self::register('metadata',fn(string $bytes,array $context)=>self::external('scm_metadata_scan_result',$bytes,$context,'metadata-provider-unavailable'));
        self::register('polyglot',function(string $bytes,array $context): array { $sample=strtolower(substr($bytes,0,1048576));$active=str_contains($sample,'<?php')||str_contains($sample,'<script')||str_contains($sample,'javascript:')||str_contains($sample,'<iframe');return ['passed'=>!$active,'reason'=>$active?'active-content-signature':'none']; });
        self::register('archive',function(string $bytes,array $context): array { $mime=Validator::canonicalMime((string)($context['mime']??''));if($mime!=='application/zip')return ['passed'=>true,'not_applicable'=>true];return self::external('scm_archive_scan_result',$bytes,$context,'archive-inspector-unavailable'); });
        self::register('decompression_bomb',function(string $bytes,array $context): array { $mime=Validator::canonicalMime((string)($context['mime']??''));if($mime!=='application/zip')return ['passed'=>true,'not_applicable'=>true];return self::external('scm_decompression_scan_result',$bytes,$context,'decompression-inspector-unavailable'); });
        self::register('malware',fn(string $bytes,array $context)=>self::external('scm_malware_scan_result',$bytes,$context,'scanner-provider-unavailable'));
    }
    private static function external(string $filter,string $bytes,array $context,string $reason): array {
        $result=function_exists('apply_filters')?apply_filters($filter,null,$bytes,Utils::redact($context)):null;
        if(!is_array($result)) return ['passed'=>false,'reason'=>$reason];
        return $result;
    }
    public static function scan(string $bytes,array $required,array $context): array {
        if($bytes==='') throw new Error('media_scan_empty','Empty media cannot pass scanning.',422);
        $results=[];
        foreach(array_values(array_unique($required)) as $id){
            $id=Utils::key((string)$id,48);
            if($id===''||!isset(self::$scanners[$id])) throw new Error('required_scanner_unavailable','Required scanner is unavailable.',503,['scanner'=>$id]);
            try{$result=(self::$scanners[$id])($bytes,$context);}catch(Error $e){throw $e;}catch(\Throwable $e){throw new Error('scanner_execution_failed','Required scanner failed unexpectedly.',503,['scanner'=>$id]);}
            if(!is_array($result)||($result['passed']??false)!==true) throw new Error('media_scan_failed','Media failed a required safety scan.',422,['scanner'=>$id,'reason'=>Utils::key((string)($result['reason']??'failed'),64)]);
            $results[$id]=Utils::redact($result);
        }
        return $results;
    }
    private static function temp(string $bytes): string { $path=tempnam(sys_get_temp_dir(),'scm-scan');if($path===false||file_put_contents($path,$bytes,LOCK_EX)===false)throw new Error('scanner_temp_failed','Scanner temporary file could not be created.',500);register_shutdown_function(static fn()=>@unlink($path));return $path; }
    public static function reset(): void { self::$scanners=[]; }
}
