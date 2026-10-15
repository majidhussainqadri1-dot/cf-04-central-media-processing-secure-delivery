<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{PartStore,ProviderRegistry,RecordStore};

RecordStore::resetMemory();
final class R218FlippingStream {
    public $context;
    private int $rewinds=0;
    private int $position=0;
    private const INITIAL='r218-first-read-A';
    private const CHANGED='r218-other-read-B';
    public function stream_open(string $path,string $mode,int $options,?string &$opened): bool {return true;}
    private function bytes(): string {return $this->rewinds>=3?self::CHANGED:self::INITIAL;}
    public function stream_read(int $count): string {
        $chunk=substr($this->bytes(),$this->position,$count);
        $this->position+=strlen($chunk);
        return $chunk;
    }
    public function stream_eof(): bool {return $this->position>=strlen($this->bytes());}
    public function stream_seek(int $offset,int $whence=SEEK_SET): bool {
        if($offset!==0||$whence!==SEEK_SET)return false;
        $this->position=0;$this->rewinds++;return true;
    }
    public function stream_tell(): int {return $this->position;}
    public function stream_stat(): array {return ['mode'=>0100444,'size'=>strlen(self::INITIAL)];}
    public function url_stat(string $path,int $flags): array {return $this->stream_stat();}
}
ok(stream_wrapper_register('r218-flip',R218FlippingStream::class),
   'R218 can reproduce a provider stream changing between checksum and putStream');
$upload='r218-mutable';
$partId=hash('sha256',$upload.'|1');
$sha=hash('sha256','r218-first-read-A');
$storageKey=hash('sha256','part|'.$upload.'|1|'.$sha);
$stream=fopen('r218-flip://part','rb');
try{
    err(fn()=>PartStore::put($upload,1,$stream,$sha),
        'part_storage_integrity_invalid',
        'R218 changed stored bytes cannot be recorded under first-pass checksum');
}finally{fclose($stream);}
ok(RecordStore::get('upload_part',$partId)===null,
    'R218 source-to-storage mismatch creates no durable part metadata');
ok(!ProviderRegistry::store()->exists($storageKey),
    'R218 mismatched newly written private object is cleaned up');

$upload='r218-stale-replay';
$sha=hash('sha256','unchanged');
$id=hash('sha256',$upload.'|1');
$key=hash('sha256','part|'.$upload.'|1|'.$sha);
RecordStore::put('upload_part',$id,[
    'actor_id'=>11,'upload_id'=>'r218-forged-owner','part_number'=>1,
    'provider_id'=>ProviderRegistry::activeId(),'object_key'=>$key,
    'sha256'=>$sha,'size'=>strlen('unchanged'),'status'=>'stored'
]);
$repeat=stream_of('unchanged');
try{
    err(fn()=>PartStore::put($upload,1,$repeat,$sha),
        'part_replay_identity_invalid',
        'R218 forged parent identity cannot be accepted on same-checksum replay');
}finally{fclose($repeat);}

$upload='r218-missing-replay';
$id=hash('sha256',$upload.'|1');
$key=hash('sha256','part|'.$upload.'|1|'.$sha);
RecordStore::put('upload_part',$id,[
    'actor_id'=>11,'upload_id'=>$upload,'part_number'=>1,
    'provider_id'=>ProviderRegistry::activeId(),'object_key'=>$key,
    'sha256'=>$sha,'size'=>strlen('unchanged'),'status'=>'stored'
]);
$repeat=stream_of('unchanged');
try{
    err(fn()=>PartStore::put($upload,1,$repeat,$sha),
        'part_replay_object_missing',
        'R218 same-hash replay fails when its private object was deleted');
}finally{fclose($repeat);}

$upload='r218-good-replay';
$id=hash('sha256',$upload.'|1');
$repeat=stream_of('unchanged');
try{$first=PartStore::put($upload,1,$repeat,$sha);}
finally{fclose($repeat);}
$repeat=stream_of('unchanged');
try{$second=PartStore::put($upload,1,$repeat,$sha);}
finally{fclose($repeat);}
ok(($first['id']??null)===$id&&($second['id']??null)===$id
    &&($first['version']??null)===($second['version']??null),
   'R218 valid identical replay remains idempotent without a second record version');
echo "REVIEW ROUND 218 PART STORAGE AND REPLAY INTEGRITY: PASS\n";
