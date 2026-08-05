<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class Keyring {
    private static array $testKeys=[];
    public static function setTestKeys(array $keys,string $active): void { if(!(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true))throw new Error('test_keys_forbidden','Test keys forbidden.',500);self::$testKeys=['keys'=>$keys,'active'=>$active]; }
    public static function all(): array {
        if(self::$testKeys)return self::$testKeys['keys'];
        $keys=function_exists('apply_filters')?apply_filters('scm_keyring',[],SCM_VERSION):[];
        if(!is_array($keys))$keys=[];
        if(defined('SCM_ENCRYPTION_KEY')&&is_string(SCM_ENCRYPTION_KEY)&&strlen(SCM_ENCRYPTION_KEY)>=32)$keys['env-v1']=SCM_ENCRYPTION_KEY;
        return $keys;
    }
    public static function activeId(): string { if(self::$testKeys)return self::$testKeys['active'];$id=function_exists('apply_filters')?(string)apply_filters('scm_active_key_id','env-v1'): 'env-v1';return Utils::key($id,64); }
    public static function key(string $id): string { $keys=self::all();$key=$keys[$id]??null;if(!is_string($key)||strlen($key)<32)throw new Error('encryption_key_unavailable','Encryption key unavailable.',503,['key_id'=>$id]);return hash('sha256',$key,true); }
    public static function assertReady(): void { $active=self::activeId(); self::key($active); self::hashKey($active); }
    public static function hashKey(?string $id=null): string { if(defined('SCM_HASH_KEY')&&is_string(SCM_HASH_KEY)&&strlen(SCM_HASH_KEY)>=32)return SCM_HASH_KEY;if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true&&$id===null)return str_repeat('h',32);$keys=self::all();$id=$id??self::activeId();if(isset($keys[$id]))return hash('sha256','hash|'.$keys[$id]);if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true)return str_repeat('h',32);throw new Error('hash_key_unavailable','Hash key unavailable.',503,['key_id'=>$id]); }
}

final class Crypto {
    public static function sign(array $claims,int $ttl=300): string { if($ttl<1||$ttl>86400)throw new Error('grant_ttl_invalid','Grant TTL outside policy.',400);$now=Utils::now();$kid=Keyring::activeId();$claims+=['iat'=>$now,'exp'=>$now+$ttl,'nonce'=>Utils::id('nonce'),'kid'=>$kid];$payload=Utils::b64url(Utils::canonicalJson($claims));$sig=hash_hmac('sha256',$payload,Keyring::hashKey($kid),true);return $payload.'.'.Utils::b64url($sig); }
    public static function verify(string $token): array { if(strlen($token)<20||strlen($token)>16384)throw new Error('grant_invalid','Malformed grant.',403);$p=explode('.',$token);if(count($p)!==2)throw new Error('grant_invalid','Malformed grant.',403);[$payload,$sig]=$p;try{$c=json_decode(Utils::b64urlDecode($payload),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new Error('grant_claims_invalid','Invalid grant claims.',403);}if(!is_array($c))throw new Error('grant_claims_invalid','Invalid grant claims.',403);Utils::requireFields($c,['iat','exp','nonce','kid'],'grant_claims_invalid');$kid=Utils::key((string)$c['kid'],64);$expected=Utils::b64url(hash_hmac('sha256',$payload,Keyring::hashKey($kid),true));if(!hash_equals($expected,$sig))throw new Error('grant_signature_invalid','Invalid grant signature.',403);$now=Utils::now();if((int)$c['iat']>$now+60||(int)$c['exp']<=$now||(int)$c['exp']-(int)$c['iat']>86400)throw new Error('grant_expired','Grant expired/outside policy.',403);return $c; }
    public static function encryptChunk(string $plain,string $aad,string $keyId): array { if(!function_exists('openssl_encrypt'))throw new Error('crypto_unavailable','OpenSSL unavailable.',503);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',Keyring::key($keyId),OPENSSL_RAW_DATA,$iv,$tag,$aad,16);if($cipher===false||strlen($tag)!==16)throw new Error('encryption_failed','Encryption failed.',500);return ['iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'ciphertext'=>base64_encode($cipher)]; }
    public static function decryptChunk(array $env,string $aad,string $keyId): string { foreach(['iv','tag','ciphertext'] as $f)if(!isset($env[$f]))throw new Error('envelope_invalid','Envelope incomplete.',500);$iv=base64_decode((string)$env['iv'],true);$tag=base64_decode((string)$env['tag'],true);$cipher=base64_decode((string)$env['ciphertext'],true);if($iv===false||$tag===false||$cipher===false||strlen($iv)!==12||strlen($tag)!==16)throw new Error('envelope_invalid','Envelope malformed.',500);$plain=openssl_decrypt($cipher,'aes-256-gcm',Keyring::key($keyId),OPENSSL_RAW_DATA,$iv,$tag,$aad);if($plain===false)throw new Error('decryption_failed','Integrity check failed.',500);return $plain; }
}

interface ObjectStore {
    public function putStream(string $key,$stream,array $meta=[]): array;
    public function openStream(string $key);
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function health(): array;
    public function capabilities(): array;
    public function copyTo(string $key,ObjectStore $target,string $targetKey): array;
}

final class LocalObjectStore implements ObjectStore {
    public function __construct(private ?string $root=null){$this->root=$root??(defined('SCM_PRIVATE_ROOT')?SCM_PRIVATE_ROOT:dirname(ABSPATH??sys_get_temp_dir()).'/scm-private');}
    private function root(): string { $root=(string)$this->root;if($root===''||str_contains($root,'..'))throw new Error('storage_root_invalid','Private root invalid.',503);if(defined('ABSPATH')&&str_starts_with(realpath(dirname($root))?:dirname($root),realpath(ABSPATH)?:ABSPATH))throw new Error('storage_root_public','Private storage cannot be under public WordPress root.',503);if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new Error('storage_root_unavailable','Private storage unavailable.',503);@chmod($root,0700);return $root; }
    private function path(string $key): string { if(!preg_match('/^[a-f0-9]{64}$/',$key))throw new Error('object_key_invalid','Invalid object key.',400);$root=$this->root();$dir=$root.'/'.substr($key,0,2);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new Error('storage_write_failed','Object directory unavailable.',500);return $dir.'/'.$key.'.scm'; }
    public function putStream(string $key,$stream,array $meta=[]): array { if(!is_resource($stream))throw new Error('stream_invalid','Invalid input stream.',500);$path=$this->path($key);$tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';$out=fopen($tmp,'xb');if($out===false)throw new Error('storage_write_failed','Cannot create object.',500);@chmod($tmp,0600);$kid=Keyring::activeId();$chunkSize=1048576;$index=0;$hash=hash_init('sha256');$size=0;fwrite($out,Utils::json(['format'=>'SCM-CHUNK-GCM-1','key_id'=>$kid,'chunk_size'=>$chunkSize,'meta'=>Utils::redact($meta)])."\n");rewind($stream);try{while(!feof($stream)){$plain=fread($stream,$chunkSize);if($plain===false)throw new Error('stream_read_failed','Input read failed.',500);if($plain==='')continue;$size+=strlen($plain);hash_update($hash,$plain);$aad=$key.'|'.$index;$env=Crypto::encryptChunk($plain,$aad,$kid);fwrite($out,Utils::json(['i'=>$index]+$env)."\n");$index++;}fflush($out);if(function_exists('fsync'))@fsync($out);}finally{fclose($out);}if(!rename($tmp,$path)){@unlink($tmp);throw new Error('storage_write_failed','Atomic object commit failed.',500);}return ['object_key'=>$key,'size'=>$size,'sha256'=>hash_final($hash),'key_id'=>$kid,'chunks'=>$index,'provider'=>'local-private','encrypted'=>true]; }
    public function openStream(string $key){$path=$this->path($key);if(!is_file($path))throw new Error('object_not_found','Object unavailable.',404);$in=fopen($path,'rb');if($in===false)throw new Error('storage_read_failed','Cannot open object.',500);$header=fgets($in);try{$h=json_decode((string)$header,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){fclose($in);throw new Error('object_envelope_invalid','Object header invalid.',500);}if(($h['format']??'')!=='SCM-CHUNK-GCM-1')throw new Error('object_envelope_invalid','Unsupported object format.',500);$kid=(string)($h['key_id']??'');$out=Utils::tempStream();$index=0;while(($line=fgets($in))!==false){try{$env=json_decode($line,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){fclose($in);fclose($out);throw new Error('object_envelope_invalid','Object chunk invalid.',500);}if((int)($env['i']??-1)!==$index)throw new Error('object_sequence_invalid','Object chunk sequence invalid.',500);fwrite($out,Crypto::decryptChunk($env,$key.'|'.$index,$kid));$index++;}fclose($in);rewind($out);return $out; }
    public function delete(string $key): bool { $path=$this->path($key);return !is_file($path)||unlink($path); }
    public function exists(string $key): bool { return is_file($this->path($key)); }
    public function health(): array { try{$root=$this->root();return ['healthy'=>is_writable($root),'provider'=>'local-private','private'=>true];}catch(\Throwable $e){return ['healthy'=>false,'provider'=>'local-private','error'=>$e->getMessage()];} }
    public function capabilities(): array { return ['private'=>true,'streaming'=>true,'server_side_encryption'=>false,'client_side_chunk_gcm'=>true,'range'=>false,'copy'=>true,'delete'=>true,'region'=>'local','max_object_bytes'=>1073741824]; }
    public function copyTo(string $key,ObjectStore $target,string $targetKey): array { $s=$this->openStream($key);try{return $target->putStream($targetKey,$s,['copied_from'=>$key]);}finally{fclose($s);} }
}

final class ProviderRegistry {
    private static array $stores=[];private static ?string $active=null;
    public static function reset(): void {self::$stores=[];self::$active=null;}
    public static function register(string $id,ObjectStore $store,array $meta=[]): void {$id=Utils::key($id,64);self::$stores[$id]=['store'=>$store,'meta'=>$meta];if(self::$active===null)self::$active=$id;}
    public static function activate(string $id): void {if(!isset(self::$stores[$id]))throw new Error('provider_unknown','Unknown provider.',503);$health=self::$stores[$id]['store']->health();if(($health['healthy']??false)!==true)throw new Error('provider_unhealthy','Provider unhealthy.',503);self::$active=$id;}
    public static function store(?string $id=null): ObjectStore {$id=$id??self::$active;if($id===null||!isset(self::$stores[$id]))throw new Error('provider_unavailable','Approved private object provider unavailable.',503);$s=self::$stores[$id]['store'];$h=$s->health();if(($h['healthy']??false)!==true)throw new Error('provider_unhealthy','Object provider unhealthy.',503);$caps=$s->capabilities();if(($caps['private']??false)!==true||($caps['streaming']??false)!==true)throw new Error('provider_capability_missing','Provider lacks required private streaming capabilities.',503);return $s;}
    public static function health(): array {$o=[];foreach(self::$stores as $id=>$v)$o[$id]=$v['store']->health()+['capabilities'=>$v['store']->capabilities(),'metadata'=>$v['meta'],'active'=>$id===self::$active];return $o;}
    public static function ids(): array {return array_keys(self::$stores);}
    public static function activeId(): string { if(self::$active===null) throw new Error('provider_unavailable','No active provider.',503); return self::$active; }
    public static function metadata(?string $id=null): array { $id=$id??self::$active; return $id!==null&&isset(self::$stores[$id])?(array)self::$stores[$id]['meta']:[]; }
    public static function assertCapability(string $capability): void { $caps=self::store()->capabilities(); if(($caps[Utils::key($capability,64)]??false)!==true) throw new Error('provider_capability_missing','Provider lacks required capability.',503,['capability'=>$capability]); }
}

interface CdnAdapter { public function publish(array $derivative): array; public function purge(array $keys): array; public function health(): array; }
final class CdnRegistry {
    private static array $adapters=[];private static ?string $active=null;
    public static function reset(): void {self::$adapters=[];self::$active=null;}
    public static function register(string $id,CdnAdapter $a): void {self::$adapters[Utils::key($id,64)]=$a;if(self::$active===null)self::$active=Utils::key($id,64);}
    public static function adapter(): CdnAdapter {if(self::$active===null||!isset(self::$adapters[self::$active]))throw new Error('cdn_unavailable','Approved CDN adapter unavailable.',503);$a=self::$adapters[self::$active];if(($a->health()['healthy']??false)!==true)throw new Error('cdn_unhealthy','CDN adapter unhealthy.',503);return $a;}
    public static function available(): bool {try{self::adapter();return true;}catch(\Throwable){return false;}}
}
