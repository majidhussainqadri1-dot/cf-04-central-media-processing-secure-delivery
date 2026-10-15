<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\Validator;

// A remote/object-backed source may change between the digest pass and
// the scan-copy pass. A separately failing copy must never leave a temp file.
final class R216MutableStream {
    public $context;
    private int $position=0;
    private int $rewinds=0;
    private string $mode='change';
    private const ORIGINAL="%PDF-1.7\n/Type /Page\n%%EOF";
    private const REPLACED="%PDF-1.7\n/Type /Page\n%%E0F";
    public function stream_open(string $path,string $mode,int $options,?string &$opened): bool {
        $this->mode=str_contains($path,'stall')?'stall':'change';
        return true;
    }
    private function bytes(): string {
        return $this->rewinds>=3&&$this->mode==='change'?self::REPLACED:self::ORIGINAL;
    }
    public function stream_read(int $count): string {
        if($this->mode==='stall'&&$this->rewinds>=3)return '';
        $chunk=substr($this->bytes(),$this->position,$count);
        $this->position+=strlen($chunk);
        return $chunk;
    }
    public function stream_eof(): bool {
        return !($this->mode==='stall'&&$this->rewinds>=3)
            &&$this->position>=strlen($this->bytes());
    }
    public function stream_seek(int $offset,int $whence=SEEK_SET): bool {
        if($whence!==SEEK_SET||$offset!==0)return false;
        $this->position=0;
        ++$this->rewinds;
        return true;
    }
    public function stream_tell(): int {return $this->position;}
    public function stream_stat(): array {return ['mode'=>0100444,'size'=>strlen(self::ORIGINAL)];}
    public function url_stat(string $path,int $flags): array {return $this->stream_stat();}
}
ok(stream_wrapper_register('r216-src',R216MutableStream::class),
    'R216 registers dynamic source stream with second-pass mutation and read-stall modes');
$policy=['max_size_bytes'=>10000,'allowed_mime_types'=>['application/pdf'],
    'allowed_extensions'=>['pdf']];
$before=glob(sys_get_temp_dir().'/scm-inspect-*')?:[];
foreach(['change'=>'source_changed_during_inspection',
          'stall'=>'stream_stalled'] as $mode=>$code){
    $stream=fopen('r216-src://'.$mode,'rb');
    try{
        err(fn()=>Validator::inspectStream($stream,'test.pdf','application/pdf',$policy),
            $code,'R216 '.$mode.' source is rejected and never accepted under its first-pass digest');
    }finally{fclose($stream);}
    $after=glob(sys_get_temp_dir().'/scm-inspect-*')?:[];
    ok(array_diff($after,$before)===[],
        'R216 '.$mode.' failure leaves no inspection tempfile behind');
}
$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-validation.php');
ok(str_contains($source,"source_changed_during_inspection")
    &&str_contains($source,"$copiedHash=hash_file('sha256',$temp)")
    &&str_contains($source,"finally{@unlink($temp);}"),
    'R216 current validator copies atomically under both digest and cleanup checks');
echo "REVIEW ROUND 216 INSPECTION REPLAY INTEGRITY: PASS\n";
