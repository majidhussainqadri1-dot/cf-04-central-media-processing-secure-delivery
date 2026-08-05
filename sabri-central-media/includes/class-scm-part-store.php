<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class PartStore {
    public static function put(string $uploadId,int $part,string $bytes,string $sha256): array { $key=hash('sha256','part|'.$uploadId.'|'.$part.'|'.$sha256);$stored=ProviderRegistry::store()->put($key,$bytes,['kind'=>'quarantine-part','upload_id'=>$uploadId,'part'=>$part]);return ['object_key'=>$key,'sha256'=>$sha256,'size'=>strlen($bytes)]+$stored; }
    public static function get(array $part): string { $bytes=ProviderRegistry::store()->get((string)$part['object_key']);if(!hash_equals((string)$part['sha256'],hash('sha256',$bytes)))throw new Error('upload_part_integrity_failed','Stored upload part integrity failed.',500);return $bytes; }
    public static function delete(array $part): void { if(!ProviderRegistry::store()->delete((string)$part['object_key']))throw new Error('upload_part_cleanup_failed','Upload part cleanup failed.',500); }
}
