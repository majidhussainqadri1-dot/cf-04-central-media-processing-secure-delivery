<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RestoreService,RecordStore,ProviderRegistry,ObjectStore};

RecordStore::resetMemory();
final class R271WatchingStore implements ObjectStore {
    public bool $observe=false;
    public bool $mutate=false;
    public array $gateStates=[];
    public function __construct(private ObjectStore $delegate) {}
    public function putStream(string $key,$stream,array $meta=[]): array {return $this->delegate->putStream($key,$stream,$meta);}
    public function openStream(string $key){return $this->delegate->openStream($key);}
    public function delete(string $key): bool{return $this->delegate->delete($key);}
    public function exists(string $key): bool{return $this->delegate->exists($key);}
    public function capabilities(): array{return $this->delegate->capabilities();}
    public function copyTo(string $key,ObjectStore $target,string $targetKey): array {
        return $this->delegate->copyTo($key,$target,$targetKey);
    }
    public function health(): array {
        if($this->observe){
            $this->gateStates[]=(string)(RecordStore::get('restore_gate','current')['status']??'absent');
            if($this->mutate){
                $this->mutate=false;
                RecordStore::put('asset','r271-late-touched',[
                    'actor_id'=>11,'status'=>'deleted','reason'=>'concurrent change during restore validation'
                ]);
            }
        }
        return $this->delegate->health();
    }
}
$store=new R271WatchingStore(ProviderRegistry::get('source-private'));
ProviderRegistry::register('r271-watcher',$store,['region'=>'test-a','class'=>'private']);
ProviderRegistry::activate('r271-watcher');
$restore=RestoreService::start(11,[
    'database_snapshot'=>'r271-db','object_inventory'=>'r271-objects',
    'keyring_version'=>'test-v1','policy_snapshot'=>'r271-policies',
    'manifest_snapshot'=>'r271-manifests','tombstone_snapshot'=>'r271-tombstones'
]);
$first=RestoreService::reconcile($restore['id']);
$before=$first['source_fingerprint']??null;
ok($first['status']==='reconciled'&&($first['checks']['inventory_stable']??false)===true
   &&is_string($before)&&strlen($before)===64,
   'R271 first restore records stable full-source version fingerprint');
ok((RecordStore::get('restore_gate','current')['source_fingerprint']??null)===$before,
   'R271 restore and blocking gate share one source inventory witness');
RestoreService::authorizeServe($restore['id']);
ok((RecordStore::get('restore_gate','current')['status']??null)==='serve_authorized',
   'R271 stable snapshot witness supports serving after fresh reconciliation');
$store->observe=true;$store->mutate=true;
$interrupted=RestoreService::reconcile($restore['id']);
ok(($store->gateStates[0]??null)==='rebuilding',
   'R271 fresh reconciliation revokes an earlier serve authorization BEFORE inspecting provider');
ok(($interrupted['status']??null)==='blocked'
   &&($interrupted['checks']['inventory_stable']??true)===false
   &&(RecordStore::get('restore_gate','current')['status']??null)==='blocked',
   'R271 mutation during provider verification is detected and serving is blocked');
err(fn()=>RestoreService::authorizeServe($restore['id']),
   'restore_gate_blocked',
   'R271 cannot release a gate with conflicting snapshot fingerprints');
$store->observe=false;
$stable=RestoreService::reconcile($restore['id']);
ok(($stable['status']??null)==='reconciled'
    &&($stable['source_fingerprint']??null)!==$before,
    'R271 new fingerprint reflects the actual changed inventory after stable recheck');
RestoreService::authorizeServe($restore['id']);
ok((RecordStore::get('restore_gate','current')['status']??null)==='serve_authorized',
   'R271 stable revalidated inventory can explicitly reopen serving');
echo "REVIEW ROUND 271 RESTORE INVENTORY FINGERPRINT FENCE: PASS\n";
