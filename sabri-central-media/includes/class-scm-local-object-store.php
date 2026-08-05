<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class LocalObjectStore implements ObjectStore {
    private string $root;
    private bool $configured;
    public function __construct(?string $root=null){
        $candidate=$root;
        if($candidate===null && defined('SCM_PRIVATE_ROOT') && is_string(SCM_PRIVATE_ROOT)) $candidate=SCM_PRIVATE_ROOT;
        if($candidate===null && !(defined('SCM_RUNTIME_ENABLED') && SCM_RUNTIME_ENABLED===true)) $candidate=sys_get_temp_dir().'/scm-private';
        $candidate=rtrim((string)$candidate,"/\\");
        $this->configured=$candidate!=='' && self::isAbsolute($candidate) && !self::insidePublicRoot($candidate);
        $this->root=$candidate;
        if($this->configured && !is_dir($this->root) && !mkdir($this->root,0700,true) && !is_dir($this->root)) $this->configured=false;
        if($this->configured) @chmod($this->root,0700);
    }
    private static function isAbsolute(string $path): bool { return str_starts_with($path,'/') || preg_match('/^[A-Za-z]:[\\\\\/]/',$path)===1; }
    private static function insidePublicRoot(string $path): bool {
        if(!defined('ABSPATH') || !is_string(ABSPATH) || ABSPATH==='') return false;
        $root=realpath(ABSPATH);
        $parent=realpath(dirname($path));
        if($root===false || $parent===false) return false;
        $root=rtrim(str_replace('\\','/',$root),'/').'/';
        $parent=rtrim(str_replace('\\','/',$parent),'/').'/';
        return str_starts_with($parent,$root);
    }
    private function ensureConfigured(): void { if(!$this->configured) throw new Error('object_store_unconfigured','Private object storage is not safely configured.',503); }
    private function path(string $key): string { $this->ensureConfigured(); $k=strtolower(trim($key)); if(preg_match('/^[a-f0-9]{64}$/',$k)!==1) throw new Error('object_key_invalid','Invalid object key.',400); return $this->root.'/'.substr($k,0,2).'/'.$k; }
    public function put(string $key,string $bytes,array $meta=[]): array {
        $p=$this->path($key);
        $dir=dirname($p);
        if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new Error('object_directory_failed','Private object directory could not be created.',500);
        @chmod($dir,0700);
        $enc=Crypto::encrypt($bytes,$key);
        $json=Utils::json(['envelope'=>$enc,'meta'=>Utils::redact($meta),'sha256'=>hash('sha256',$bytes)]);
        $tmp=$dir.'/.'.basename($p).'.'.bin2hex(random_bytes(8)).'.tmp';
        $written=file_put_contents($tmp,$json,LOCK_EX);
        if($written===false || $written!==strlen($json)){ @unlink($tmp); throw new Error('object_write_failed','Private object write failed.',500); }
        @chmod($tmp,0600);
        if(!@rename($tmp,$p)){ @unlink($tmp); throw new Error('object_commit_failed','Private object could not be committed atomically.',500); }
        @chmod($p,0600);
        return ['key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)];
    }
    public function get(string $key): string {
        $p=$this->path($key);
        $raw=@file_get_contents($p);
        if($raw===false) throw new Error('object_not_found','Object not found.',404);
        try { $j=json_decode($raw,true,32,JSON_THROW_ON_ERROR); } catch(\JsonException $e) { throw new Error('object_envelope_invalid','Stored object envelope is invalid.',500); }
        if(!is_array($j) || !isset($j['envelope'],$j['sha256']) || !is_array($j['envelope']) || !is_string($j['sha256'])) throw new Error('object_envelope_invalid','Stored object envelope is incomplete.',500);
        $plain=Crypto::decrypt($j['envelope'],$key);
        if(!preg_match('/^[a-f0-9]{64}$/',$j['sha256']) || !hash_equals($j['sha256'],hash('sha256',$plain))) throw new Error('object_integrity_failed','Stored object integrity failure.',500);
        return $plain;
    }
    public function delete(string $key): bool { $p=$this->path($key); return !is_file($p)||@unlink($p); }
    public function exists(string $key): bool { try{return is_file($this->path($key));}catch(Error){return false;} }
    public function healthy(): bool { return $this->configured && is_dir($this->root) && is_writable($this->root); }
}
