<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Crypto {
    public static function sign(array $claims, int $ttl=300): string {
        if($ttl<1 || $ttl>86400) throw new Error('grant_ttl_invalid','Grant lifetime is outside policy.',400);
        $claims['iat']=Utils::now(); $claims['exp']=$claims['iat']+$ttl; $claims['nonce']=Utils::id('n');
        $payload=Utils::b64url(Utils::json($claims));
        return $payload.'.'.Utils::b64url(hash_hmac('sha256',$payload,Utils::hashKey(),true));
    }
    public static function verify(string $token): array {
        $parts=explode('.',$token); if(count($parts)!==2) throw new Error('grant_invalid','Malformed delivery grant.',403);
        [$payload,$sig]=$parts; $expected=Utils::b64url(hash_hmac('sha256',$payload,Utils::hashKey(),true));
        if(!hash_equals($expected,$sig)) throw new Error('grant_signature_invalid','Invalid delivery grant.',403);
        $claims=json_decode(Utils::b64urlDecode($payload),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($claims)||!isset($claims['exp'])||(int)$claims['exp']<Utils::now()) throw new Error('grant_expired','Delivery grant expired.',403);
        return $claims;
    }
    public static function encrypt(string $plaintext, string $aad=''): array {
        if(!function_exists('openssl_encrypt')) throw new Error('crypto_unavailable','Encryption provider unavailable.',503);
        $key=hash('sha256',Utils::hashKey(),true); $iv=random_bytes(12); $tag='';
        $cipher=openssl_encrypt($plaintext,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$aad,16);
        if($cipher===false) throw new Error('encryption_failed','Encryption failed.',500);
        return ['algorithm'=>'AES-256-GCM','ciphertext'=>base64_encode($cipher),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'key_id'=>'runtime-derived-v1'];
    }
    public static function decrypt(array $env,string $aad=''): string {
        $plain=openssl_decrypt(base64_decode((string)$env['ciphertext'],true)?:'','aes-256-gcm',hash('sha256',Utils::hashKey(),true),OPENSSL_RAW_DATA,base64_decode((string)$env['iv'],true)?:'',base64_decode((string)$env['tag'],true)?:'',$aad);
        if($plain===false) throw new Error('decryption_failed','Integrity verification failed.',500); return $plain;
    }
}
