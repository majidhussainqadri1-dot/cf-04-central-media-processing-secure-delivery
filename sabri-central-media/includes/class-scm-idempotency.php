<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Idempotency {
    public static function claim(string $scope,string $key,string $fingerprint): void { if(trim($key)==='') throw new Error('idempotency_key_required','Idempotency key required.',400);$id=Utils::key($scope,48).':'.hash('sha256',$key);$old=RecordStore::get('idempotency',$id);if($old && !hash_equals((string)$old['fingerprint'],$fingerprint)) throw new Error('idempotency_conflict','Idempotency key was reused for a different request.',409);if(!$old)RecordStore::put('idempotency',$id,['actor_id'=>0,'status'=>'claimed','fingerprint'=>$fingerprint,'expires_at'=>Utils::now()+86400]); }
}
