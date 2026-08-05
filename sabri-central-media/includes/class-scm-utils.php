<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Utils {
    public static function key(string $value, int $max=64): string { $v=strtolower(trim($value)); $v=preg_replace('/[^a-z0-9_.:-]+/','-',$v)??''; return substr(trim($v,'-'),0,$max); }
    public static function text(string $value, int $max=255): string { $v=trim(preg_replace('/[\x00-\x1F\x7F]+/u',' ',strip_tags($value))??''); return function_exists('mb_substr') ? mb_substr($v,0,$max) : substr($v,0,$max); }
    public static function id(string $prefix='scm'): string { return $prefix.'-'.bin2hex(random_bytes(16)); }
    public static function now(): int { return time(); }
    public static function json(array $data): string { return json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    public static function canonicalize(mixed $value): mixed {
        if(!is_array($value)) return $value;
        if(array_is_list($value)) return array_map([self::class,'canonicalize'],$value);
        ksort($value,SORT_STRING);
        foreach($value as $key=>$item) $value[$key]=self::canonicalize($item);
        return $value;
    }
    public static function canonicalJson(array $data): string { return self::json(self::canonicalize($data)); }
    public static function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw),'+/','-_'),'='); }
    public static function b64urlDecode(string $value): string { $pad=(4-strlen($value)%4)%4; $out=base64_decode(strtr($value,'-_','+/').str_repeat('=',$pad),true); if($out===false) throw new Error('token_encoding_invalid','Invalid encoded value.',400); return $out; }
    public static function filename(string $name): string { $name=basename(str_replace('\\','/',$name)); $name=preg_replace('/[^\pL\pN._ -]+/u','-',$name)??'file'; $name=preg_replace('/\s+/u','-',$name)??$name; $name=trim($name,'.- '); return function_exists('mb_substr') ? mb_substr($name!==''?$name:'file',0,180) : substr($name!==''?$name:'file',0,180); }
    public static function hashReference(string $value): string { return hash_hmac('sha256',$value,self::hashKey()); }
    public static function hashKey(): string { if(defined('SCM_HASH_KEY') && is_string(SCM_HASH_KEY) && strlen(SCM_HASH_KEY)>=32) return SCM_HASH_KEY; if(function_exists('wp_salt')) { $salt=(string)wp_salt('auth'); if(strlen($salt)>=32) return hash('sha256',$salt); } if(defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true) throw new Error('crypto_key_unavailable','A deployment secret is required before runtime activation.',503); return hash('sha256',__FILE__); }
    public static function redact(mixed $value): mixed { if(!is_array($value)) return $value; $out=[]; foreach($value as $k=>$v){ $lk=strtolower((string)$k); if(preg_match('/secret|token|password|credential|key_material|raw_path|recipient_reference/',$lk)){$out[$k]='[redacted]';} else {$out[$k]=is_array($v)?self::redact($v):$v;} } return $out; }
    public static function requireRuntime(): void { if(!(defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true)) throw new Error('runtime_disabled','CF-04 runtime is disabled pending approved activation.',503); }
    public static function bool(mixed $v): bool { return $v===true || $v===1 || $v==='1' || $v==='true'; }
}
