<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Validator {
    public static function uploadMetadata(array $in,array $policy): array {
        $size=(int)($in['size']??0); if($size<1||$size>(int)$policy['max_size_bytes']) throw new Error('upload_size_invalid','Upload size violates policy.',413);
        $name=Utils::filename((string)($in['name']??'file')); $mime=strtolower(trim((string)($in['mime']??'application/octet-stream')));
        if($policy['allowed_mime_types'] && !in_array($mime,$policy['allowed_mime_types'],true)) throw new Error('upload_mime_denied','Declared MIME type is not allowed.',415);
        if(substr_count(strtolower($name),'.php')>0 || preg_match('/\.(phar|phtml|php\d*)\./i',$name)) throw new Error('upload_extension_dangerous','Executable or double extension denied.',415);
        return ['name'=>$name,'size'=>$size,'mime'=>$mime,'sha256'=>preg_match('/^[a-f0-9]{64}$/',(string)($in['sha256']??''))?(string)$in['sha256']:'','media_class'=>Utils::key((string)($in['media_class']??$policy['media_class']),32)];
    }
    public static function magicMatches(string $path,string $mime): bool {
        if(!is_file($path)) return false; $head=file_get_contents($path,false,null,0,16); if($head===false) return false;
        return match(true){ str_starts_with($mime,'image/jpeg')=>str_starts_with($head,"\xFF\xD8\xFF"), str_starts_with($mime,'image/png')=>str_starts_with($head,"\x89PNG\r\n\x1A\n"), $mime==='application/pdf'=>str_starts_with($head,'%PDF-'), default=>true };
    }
    public static function archiveRatio(int $compressed,int $expanded,int $limit): void { if($compressed<1||$expanded<0||$expanded/$compressed>$limit) throw new Error('decompression_bomb_detected','Archive expansion ratio exceeds policy.',415); }
}
