<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use Sabri\CentralMedia\{ObjectStore,PartStore,ProviderRegistry,RecordStore,Validator};

final class R213StalledWrapper {
    public $context;
    public function stream_open(string $path,string $mode,int $options,?string &$opened): bool {return true;}
    public function stream_read(int $count): string {return '';}
    public function stream_eof(): bool {return false;}
    public function stream_stat(): array {return ['mode'=>0100444,'size'=>2048];}
    public function url_stat(string $path,int $flags): array {return $this->stream_stat();}
    public function stream_tell(): int {return 0;}
    public function stream_seek(int $offset,int $whence=SEEK_SET): bool {return $offset===0;}
}
ok(stream_wrapper_register('r213-stall',R213StalledWrapper::class),
    'R213 adversarial source creates a readable never-EOF empty stream');

final class R213StalledProvider implements ObjectStore {
    public function putStream(string $key,$stream,array $meta=[]): array {return [];}
    public function openStream(string $key) {return fopen('r213-stall://media','rb');}
    public function delete(string $key): bool {return true;}
    public function exists(string $key): bool {return true;}
    public function health(): array {return ['healthy'=>true];}
    public function capabilities(): array {return ['private'=>true,'streaming'=>true];}
    public function copyTo(string $key,ObjectStore $target,string $targetKey): array {return [];}
}
ProviderRegistry::register('r213-stalled',new R213StalledProvider());
RecordStore::resetMemory();
RecordStore::put('upload','r213-assemble',[
    'actor_id'=>11,'status'=>'uploading','policy'=>['max_upload_parts'=>1]
]);
RecordStore::put('upload_part',hash('sha256','r213-assemble|1'),[
    'actor_id'=>11,'status'=>'stored','upload_id'=>'r213-assemble',
    'part_number'=>1,'provider_id'=>'r213-stalled','object_key'=>'stalled-media',
    'size'=>4,'sha256'=>hash('sha256','data')
]);
err(fn()=>PartStore::assemble('r213-assemble',4,hash('sha256','data'),1),
    'stream_stalled','R213 stalled private upload-part provider fails closed before infinite loop');

$scanner=new \ReflectionMethod(Validator::class,'scanPdf');
err(fn()=>$scanner->invoke(null,'r213-stall://pdf'),
    'stream_stalled','R213 stalled PDF scanner fails closed before infinite loop');

$source=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-upload.php');
$validation=file_get_contents(dirname(__DIR__).'/sabri-central-media/includes/class-scm-validation.php');
ok(str_contains($source,"if(\$chunk===''&&!feof(\$s))throw new Error('stream_stalled'")
    &&str_contains($validation,"if(\$chunk===''&&!feof(\$h))throw new Error('stream_stalled'"),
    'R213 both provider and PDF read loops contain explicit EOF-sensitive stall guards');
echo "REVIEW ROUND 213 STALLED MEDIA SOURCE: PASS\n";
