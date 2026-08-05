<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Validator {
    private const MIME_ALIASES=[
        'image/jpg'=>'image/jpeg','image/pjpeg'=>'image/jpeg','application/x-pdf'=>'application/pdf',
        'application/x-zip-compressed'=>'application/zip','audio/x-wav'=>'audio/wav','audio/wave'=>'audio/wav',
        'audio/x-mpeg'=>'audio/mpeg','video/x-m4v'=>'video/mp4','application/ogg'=>'audio/ogg',
    ];
    public static function canonicalMime(string $mime): string {
        $mime=strtolower(trim(explode(';',$mime,2)[0]??''));
        if($mime==='' || preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/',$mime)!==1) return '';
        return self::MIME_ALIASES[$mime]??$mime;
    }
    public static function uploadMetadata(array $in,array $policy): array {
        $size=(int)($in['size']??0);
        if($size<1||$size>(int)$policy['max_size_bytes']) throw new Error('upload_size_invalid','Upload size violates policy.',413);
        $name=Utils::filename((string)($in['name']??'file'));
        $mime=self::canonicalMime((string)($in['mime']??''));
        if($mime==='' || !in_array($mime,(array)$policy['allowed_mime_types'],true)) throw new Error('upload_mime_denied','Declared MIME type is not allowed.',415);
        $lower=strtolower($name);
        if(preg_match('/(^|\.)(php\d*|phtml|pht|phar|cgi|pl|py|rb|sh|bash|exe|dll|com|msi|bat|cmd|js|mjs|html?|svg)(\.|$)/i',$lower)) throw new Error('upload_extension_dangerous','Executable or active-content extension denied.',415);
        $mediaClass=Utils::key((string)($in['media_class']??$policy['media_class']),32);
        if($mediaClass==='' || $mediaClass!==$policy['media_class']) throw new Error('upload_media_class_mismatch','Declared media class does not match policy.',415);
        $sha=strtolower((string)($in['sha256']??''));
        if($sha!=='' && preg_match('/^[a-f0-9]{64}$/',$sha)!==1) throw new Error('upload_checksum_format_invalid','Declared SHA-256 is malformed.',400);
        return ['name'=>$name,'size'=>$size,'mime'=>$mime,'sha256'=>$sha,'media_class'=>$mediaClass];
    }
    public static function detectMime(string $path): string {
        if(!is_file($path) || !is_readable($path)) return '';
        $head=file_get_contents($path,false,null,0,32);
        if($head===false) return '';
        $signature=self::signatureMime($head);
        if($signature!=='') return $signature;
        if(function_exists('finfo_open')){
            $finfo=finfo_open(FILEINFO_MIME_TYPE);
            if($finfo!==false){
                $detected=finfo_file($finfo,$path);
                finfo_close($finfo);
                if(is_string($detected)) return self::canonicalMime($detected);
            }
        }
        return '';
    }
    private static function signatureMime(string $head): string {
        if(str_starts_with($head,"\xFF\xD8\xFF")) return 'image/jpeg';
        if(str_starts_with($head,"\x89PNG\r\n\x1A\n")) return 'image/png';
        if(str_starts_with($head,'GIF87a')||str_starts_with($head,'GIF89a')) return 'image/gif';
        if(strlen($head)>=12 && substr($head,0,4)==='RIFF' && substr($head,8,4)==='WEBP') return 'image/webp';
        if(str_starts_with($head,'%PDF-')) return 'application/pdf';
        if(str_starts_with($head,"PK\x03\x04")||str_starts_with($head,"PK\x05\x06")||str_starts_with($head,"PK\x07\x08")) return 'application/zip';
        if(str_starts_with($head,'ID3') || (strlen($head)>=2 && ord($head[0])===0xFF && (ord($head[1])&0xE0)===0xE0)) return 'audio/mpeg';
        if(strlen($head)>=12 && substr($head,0,4)==='RIFF' && substr($head,8,4)==='WAVE') return 'audio/wav';
        if(str_starts_with($head,'OggS')) return 'audio/ogg';
        if(strlen($head)>=12 && substr($head,4,4)==='ftyp') return 'video/mp4';
        if(str_starts_with($head,"\x1A\x45\xDF\xA3")) return 'video/webm';
        return '';
    }
    public static function magicMatches(string $path,string $mime): bool {
        $declared=self::canonicalMime($mime);
        $detected=self::detectMime($path);
        if($declared==='' || $detected==='') return false;
        if(hash_equals($declared,$detected)) return true;
        return ($declared==='video/mp4' && $detected==='application/mp4') || ($declared==='audio/ogg' && $detected==='video/ogg');
    }
    public static function archiveRatio(int $compressed,int $expanded,int $limit): void {
        if($compressed<1||$expanded<0||$limit<1||$expanded/$compressed>$limit) throw new Error('decompression_bomb_detected','Archive expansion ratio exceeds policy.',415);
    }
}
