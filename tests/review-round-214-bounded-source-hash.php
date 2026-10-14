<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\Validator;

final class R214CountingStream {
    public $context;
    public static int $bytesRead=0;
    private int $offset=0;
    private const TOTAL=8388608;
    public function stream_open(string $path,string $mode,int $options,?string &$opened): bool {
        $this->offset=0;return true;
    }
    public function stream_eof(): bool {return $this->offset>=self::TOTAL;}
    public function stream_read(int $count): string {
        $n=min($count,8192,self::TOTAL-$this->offset);
        if($n<=0)return '';
        $this->offset+=$n;self::$bytesRead+=$n;return str_repeat('X',$n);
    }
    public function stream_seek(int $offset,int $whence=SEEK_SET): bool {
        $target=match($whence){SEEK_SET=>$offset,SEEK_CUR=>$this->offset+$offset,SEEK_END=>self::TOTAL+$offset,default=>-1};
        if($target<0||$target>self::TOTAL)return false;
        $this->offset=$target;return true;
    }
    public function stream_tell(): int{return $this->offset;}
    public function stream_stat(): array{return ['mode'=>0100444,'size'=>self::TOTAL];}
    public function url_stat(string $path,int $flags): array{return $this->stream_stat();}
}
ok(stream_wrapper_register('r214-count',R214CountingStream::class),
    'R214 counting test provider exposes eight megabytes of source data');
$h=fopen('r214-count://media','rb');
try{
    err(fn()=>Validator::inspectStream($h,'oversized.jpg','image/jpeg',['max_size_bytes'=>1024]),
        'source_size_invalid','R214 source is rejected while hashing once it exceeds declared maximum');
    ok(R214CountingStream::$bytesRead>1024
        &&R214CountingStream::$bytesRead<=1048576,
        'R214 rejecting 8 MiB source reads no more than one bounded hash chunk');
    err(fn()=>Validator::inspectStream($h,'badpolicy.jpg','image/jpeg',['max_size_bytes'=>'1024junk']),
        'source_size_invalid','R214 malformed policy size fails closed without reading source');
}finally{fclose($h);}
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-validation.php');
ok(str_contains($source,"Utils::streamHash(\$stream,Utils::integer(")
   &&str_contains($source,"if(\$sizeError->errorCode==='stream_size_exceeded')"),
   'R214 source path has bounded hash before MIME/processing and preserved 413 error');
echo "REVIEW ROUND 214 BOUNDED SOURCE HASH: PASS\n";
