<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class Error extends \RuntimeException {
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus=400,
        public readonly array $context=[]
    ){ parent::__construct($message); }
    public function asArray(): array { return ['code'=>$this->errorCode,'message'=>$this->getMessage(),'status'=>$this->httpStatus,'context'=>Utils::redact($this->context)]; }
}

final class Utils {
    public static function key(string $value,int $max=64): string { $v=strtolower(trim($value)); $v=preg_replace('/[^a-z0-9_.:-]+/','-',$v)??''; return substr(trim($v,'-'),0,$max); }
    public static function text(string $value,int $max=255): string { $v=trim(preg_replace('/[\x00-\x1F\x7F]+/u',' ',strip_tags($value))??''); return function_exists('mb_substr')?mb_substr($v,0,$max):substr($v,0,$max); }
    public static function filename(string $name): string { $name=basename(str_replace('\\','/',$name)); $name=preg_replace('/[^\pL\pN._ -]+/u','-',$name)??'file'; $name=preg_replace('/\s+/u','-',$name)??$name; $name=trim($name,'.- '); return self::text($name!==''?$name:'file',180); }
    public static function id(string $prefix='scm'): string { return self::key($prefix,16).'-'.bin2hex(random_bytes(16)); }
    public static function now(): int { return time(); }
    public static function bool(mixed $v): bool { return $v===true||$v===1||$v==='1'||$v==='true'; }
    public static function canonicalize(mixed $v): mixed { if(!is_array($v))return $v; if(array_is_list($v))return array_map([self::class,'canonicalize'],$v); ksort($v,SORT_STRING); foreach($v as $k=>$i)$v[$k]=self::canonicalize($i); return $v; }
    public static function json(array $data): string { return json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    public static function canonicalJson(array $data): string { return self::json(self::canonicalize($data)); }
    public static function b64url(string $raw): string { return rtrim(strtr(base64_encode($raw),'+/','-_'),'='); }
    public static function b64urlDecode(string $value): string { $pad=(4-strlen($value)%4)%4; $out=base64_decode(strtr($value,'-_','+/').str_repeat('=',$pad),true); if($out===false)throw new Error('encoding_invalid','Invalid encoded value.',400); return $out; }
    public static function hashReference(string $value): string { return hash_hmac('sha256',$value,Keyring::hashKey()); }
    public static function redact(mixed $v): mixed { if(!is_array($v))return $v; $out=[]; foreach($v as $k=>$i){$lk=strtolower((string)$k);$sensitive=(bool)preg_match('/(?:^|_)(?:secret|token|password|credential|key_material|raw_path|filesystem_path|object_key|recipient_reference|authorization|cookie|session_id|signature)(?:$|_)/',$lk);$out[$k]=$sensitive?'[redacted]':(is_array($i)?self::redact($i):$i);} return $out; }
    public static function requireFields(array $data,array $fields,string $code='field_missing'): void { foreach($fields as $f)if(!array_key_exists($f,$data)||$data[$f]===''||$data[$f]===null)throw new Error($code,'Required field is missing.',400,['field'=>$f]); }
    public static function tempStream(){ $h=fopen('php://temp','w+b'); if($h===false)throw new Error('stream_open_failed','Temporary stream unavailable.',500); return $h; }
    public static function streamHash($stream): array { if(!is_resource($stream))throw new Error('stream_invalid','Invalid stream.',500); rewind($stream); $ctx=hash_init('sha256');$size=0;while(!feof($stream)){ $chunk=fread($stream,1048576); if($chunk===false)throw new Error('stream_read_failed','Stream read failed.',500);if($chunk===''&&!feof($stream))throw new Error('stream_stalled','Stream produced no data before EOF.',500);$size+=strlen($chunk);hash_update($ctx,$chunk);}rewind($stream);return ['sha256'=>hash_final($ctx),'size'=>$size]; }
    public static function constantTimeEquals(string $a,string $b): bool { return strlen($a)===strlen($b)&&hash_equals($a,$b); }
    public static function writeAll($stream,string $data): void { if(!is_resource($stream))throw new Error('stream_invalid','Invalid output stream.',500);$offset=0;$length=strlen($data);while($offset<$length){$written=fwrite($stream,substr($data,$offset));if($written===false||$written===0)throw new Error('stream_write_failed','Stream write failed.',500);$offset+=$written;} }
    public static function copyStream($source,$target,?int $maxBytes=null): int { if(!is_resource($source)||!is_resource($target))throw new Error('stream_invalid','Invalid stream.',500);$total=0;while(!feof($source)){$chunk=fread($source,1048576);if($chunk===false)throw new Error('stream_read_failed','Stream read failed.',500);if($chunk===''&&!feof($source))throw new Error('stream_stalled','Stream produced no data before EOF.',500);if($chunk==='')continue;$total+=strlen($chunk);if($maxBytes!==null&&$total>$maxBytes)throw new Error('stream_size_exceeded','Stream exceeds allowed size.',413);self::writeAll($target,$chunk);}return $total; }
    public static function pathWithin(string $candidate,string $base): bool {
        $normalize=static function(string $path): string {
            $path=str_replace('\\','/',$path);$drive='';
            if(preg_match('/^[A-Za-z]:/',$path,$match)){$drive=strtoupper($match[0]);$path=substr($path,2);}
            $absolute=str_starts_with($path,'/');$parts=[];
            foreach(explode('/',$path) as $part){if($part===''||$part==='.')continue;if($part==='..'){if($parts!==[])array_pop($parts);continue;}$parts[]=$part;}
            $prefix=$drive!==''?$drive.($absolute?'/':''):($absolute?'/':'');
            return rtrim($prefix.implode('/',$parts),'/')?:($prefix!==''?rtrim($prefix,'/'):'/');
        };
        $candidateReal=realpath($candidate);$baseReal=realpath($base);
        $candidate=$normalize($candidateReal!==false?$candidateReal:$candidate);
        $base=$normalize($baseReal!==false?$baseReal:$base);
        if($candidate===''||$base==='')return false;
        return $candidate===$base||str_starts_with($candidate,$base.'/');
    }
}
