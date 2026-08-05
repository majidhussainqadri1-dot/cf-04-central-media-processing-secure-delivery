<?php
declare(strict_types=1); require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DomainRegistry,Error,ProviderRegistry,RecordStore,DeliveryService,Crypto,Idempotency,UploadService};

$root=dirname(__DIR__);
$read=static fn(string $path): string=>(string)file_get_contents($root.'/'.$path);

ProviderRegistry::reset();
err(fn()=>ProviderRegistry::store(),'storage_provider_unavailable','runtime refuses implicit storage provider');

ProviderRegistry::register('local-private',new Sabri\CentralMedia\LocalObjectStore(SCM_PRIVATE_ROOT));
DomainRegistry::reset();
DomainRegistry::register('file17','1.0.0',[
 'authorize_delivery'=>fn(array $c)=>['authorized'=>true,'allowed'=>true,'object_version'=>2],
]);
RecordStore::resetMemory();
$key=str_repeat('b',64); ProviderRegistry::store()->put($key,'x');
RecordStore::put('asset','stale',['actor_id'=>11,'asset_id'=>'stale','owner_domain'=>'file17','owner_object'=>'m','object_version'=>1,'object_key'=>$key,'content_sha256'=>hash('sha256','x'),'size'=>1,'privacy_class'=>'C3','status'=>'ready','scan_status'=>'passed']);
err(fn()=>DeliveryService::issue(['asset_id'=>'stale'],['user_id'=>12],60),'domain_object_version_stale','stale native-owner decision blocks grant');

DomainRegistry::reset();
DomainRegistry::register('file17','1.0.0',['authorize_delivery'=>fn(array $c)=>['authorized'=>true,'allowed'=>true,'object_version'=>1]]);
$token=DeliveryService::issue(['asset_id'=>'stale'],['user_id'=>12],60);
$claims=Crypto::verify($token); $grant=RecordStore::get('grant',$claims['grant_id']);
ok(!array_key_exists('object_key',$claims)&&is_array($grant)&&strlen((string)$grant['token_hash'])===64,'grant hides storage key and has persistent record');

$f=hash('sha256','concurrency'); Idempotency::claim('fresh','same',$f);
err(fn()=>Idempotency::claim('fresh','same',$f),'idempotency_in_progress','concurrent identical request is rejected');

$policy=policy('document',8388609); $meta=['name'=>'large.pdf','size'=>8388609,'mime'=>'application/pdf','sha256'=>'','media_class'=>'document'];
DomainRegistry::reset(); DomainRegistry::register('file17','1.0.0',['authorize_upload'=>fn(array $c)=>['authorized'=>true,'allowed'=>true,'object_version'=>1]]);
$u=UploadService::create(11,$meta,$policy,'large-create');
err(fn()=>UploadService::complete($u['id'],11,'large-complete'),'upload_incomplete','large object cannot be completed without uploaded parts');

$matrix=$read('docs/runtime/REQUIREMENTS-COMPLETION-MATRIX.md');
$readme=$read('README.md');
$status=$read('docs/runtime/STATUS.md');
$trackedDist=array_values(array_filter(glob($root.'/dist/*')?:[],static fn(string $path): bool=>basename($path)!=='.gitkeep'));
ok(str_contains($matrix,'Complete: **0 / 33**')&&!str_contains($matrix,'all code-level Must requirements have source'),'completion evidence cannot silently broaden subset QA');
ok(str_contains($readme,'Coded: **not complete**')&&str_contains($status,'production activation prohibited'),'public repository status blocks false complete/release claim');
ok($trackedDist===[],'stale generated release evidence is not tracked in source');
echo "REVIEW ROUND 10 FRESH ADVERSARIAL PASSED\n";
