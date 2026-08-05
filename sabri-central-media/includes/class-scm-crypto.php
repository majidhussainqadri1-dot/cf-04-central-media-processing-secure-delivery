<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Crypto {
    public static function sign(array $claims, int $ttl=300): string {
        if($ttl<1 || $ttl>86400) throw new Error('grant_ttl_invalid','Grant lifetime is outside policy.',400);
        $claims['iat']=Utils::now();
        $claims['exp']=$claims['iat']+$ttl;
        $claims['nonce']=Utils::id('n');
        $payload=Utils::b64url(Utils::canonicalJson($claims));
        return $payload.'.'.Utils::b64url(hash_hmac('sha256',$payload,Utils::hashKey(),true));
    }
    public static function verify(string $token): array {
        if(strlen($token)<16 || strlen($token)>16384) throw new Error('grant_invalid','Malformed delivery grant.',403);
        $parts=explode('.',$token);
        if(count($parts)!==2 || $parts[0]==='' || $parts[1]==='') throw new Error('grant_invalid','Malformed delivery grant.',403);
        [$payload,$sig]=$parts;
        $expected=Utils::b64url(hash_hmac('sha256',$payload,Utils::hashKey(),true));
        if(!hash_equals($expected,$sig)) throw new Error('grant_signature_invalid','Invalid delivery grant.',403);
        try {
            $claims=json_decode(Utils::b64urlDecode($payload),true,32,JSON_THROW_ON_ERROR);
        } catch(\JsonException $e) {
            throw new Error('grant_claims_invalid','Delivery grant claims are invalid.',403);
        }
        if(!is_array($claims)) throw new Error('grant_claims_invalid','Delivery grant claims are invalid.',403);
        foreach(['iat','exp','nonce'] as $field) if(!array_key_exists($field,$claims)) throw new Error('grant_claims_invalid','Delivery grant claims are incomplete.',403,['field'=>$field]);
        $now=Utils::now();
        $issued=(int)$claims['iat'];
        $expires=(int)$claims['exp'];
        if($issued<1 || $issued>$now+60 || $expires<=$now || $expires<=$issued || $expires-$issued>86400) throw new Error('grant_expired','Delivery grant is expired or outside policy.',403);
        return $claims;
    }
    public static function encrypt(string $plaintext, string $aad=''): array {
        if(!function_exists('openssl_encrypt')) throw new Error('crypto_unavailable','Encryption provider unavailable.',503);
        $key=hash('sha256',Utils::hashKey(),true);
        $iv=random_bytes(12);
        $tag='';
        $cipher=openssl_encrypt($plaintext,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$aad,16);
        if($cipher===false || strlen($tag)!==16) throw new Error('encryption_failed','Encryption failed.',500);
        return ['algorithm'=>'AES-256-GCM','ciphertext'=>base64_encode($cipher),'iv'=>base64_encode($iv),'tag'=>base64_encode($tag),'key_id'=>'runtime-derived-v1'];
    }
    public static function decrypt(array $env,string $aad=''): string {
        if(!function_exists('openssl_decrypt')) throw new Error('crypto_unavailable','Decryption provider unavailable.',503);
        foreach(['algorithm','ciphertext','iv','tag','key_id'] as $field) if(!isset($env[$field]) || !is_string($env[$field]) || $env[$field]==='') throw new Error('envelope_invalid','Encrypted object envelope is incomplete.',500,['field'=>$field]);
        if($env['algorithm']!=='AES-256-GCM' || $env['key_id']!=='runtime-derived-v1') throw new Error('envelope_algorithm_unsupported','Encrypted object envelope is unsupported.',500);
        $cipher=base64_decode($env['ciphertext'],true);
        $iv=base64_decode($env['iv'],true);
        $tag=base64_decode($env['tag'],true);
        if($cipher===false || $iv===false || $tag===false || strlen($iv)!==12 || strlen($tag)!==16) throw new Error('envelope_invalid','Encrypted object envelope is malformed.',500);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',hash('sha256',Utils::hashKey(),true),OPENSSL_RAW_DATA,$iv,$tag,$aad);
        if($plain===false) throw new Error('decryption_failed','Integrity verification failed.',500);
        return $plain;
    }
}
