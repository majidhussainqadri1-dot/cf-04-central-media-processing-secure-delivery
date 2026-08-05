<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class LocalObjectStore implements ObjectStore {
    public function __construct(private ?string $root=null){ $this->root=$root??(defined('SCM_PRIVATE_ROOT')?SCM_PRIVATE_ROOT:sys_get_temp_dir().'/scm-private'); if(!is_dir($this->root))@mkdir($this->root,0700,true); }
    private function path(string $key): string { $k=preg_replace('/[^a-f0-9]/','',strtolower($key))??''; if(strlen($k)<32) throw new Error('object_key_invalid','Invalid object key.',400); return $this->root.'/'.substr($k,0,2).'/'.$k; }
    public function put(string $key,string $bytes,array $meta=[]): array { $p=$this->path($key); @mkdir(dirname($p),0700,true); $enc=Crypto::encrypt($bytes,$key); $json=Utils::json(['envelope'=>$enc,'meta'=>Utils::redact($meta),'sha256'=>hash('sha256',$bytes)]); if(file_put_contents($p,$json,LOCK_EX)===false) throw new Error('object_write_failed','Private object write failed.',500); @chmod($p,0600); return ['key'=>$key,'sha256'=>hash('sha256',$bytes),'size'=>strlen($bytes)]; }
    public function get(string $key): string { $p=$this->path($key); $raw=@file_get_contents($p); if($raw===false) throw new Error('object_not_found','Object not found.',404); $j=json_decode($raw,true,32,JSON_THROW_ON_ERROR); $plain=Crypto::decrypt($j['envelope'],$key); if(!hash_equals((string)$j['sha256'],hash('sha256',$plain))) throw new Error('object_integrity_failed','Stored object integrity failure.',500); return $plain; }
    public function delete(string $key): bool { $p=$this->path($key); return !is_file($p)||@unlink($p); }
    public function exists(string $key): bool { return is_file($this->path($key)); }
    public function healthy(): bool { return is_dir($this->root)&&is_writable($this->root); }
}
