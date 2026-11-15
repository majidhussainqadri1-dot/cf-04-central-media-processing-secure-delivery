<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ObjectStore,ObjectCipherKeyInspector,Keyring,
    ProviderRegistry,KeyRotationInventoryStage,KeyRotationShadowCoordinator,
    RecordStore,Utils,Error};

final class R305InMemoryCipherStore implements ObjectStore,ObjectCipherKeyInspector {
    public array $objects=[];
    public function putStream(string $key,$stream,array $meta=[]):array {
        $stats=Utils::streamHash($stream,1073741824);
        $bytes=stream_get_contents($stream);
        if(!is_string($bytes))throw new Error('stream_read_failed','Failed to read source.',500);
        if(isset($this->objects[$key])){
            if($this->objects[$key]['bytes']!==$bytes)
                throw new Error('object_key_collision','Object key collision.',409);
            $kid=$this->objects[$key]['key_id'];$reused=true;
        }else{
            $kid=Keyring::activeId();$reused=false;
            $this->objects[$key]=['bytes'=>$bytes,'key_id'=>$kid];
        }
        return ['object_key'=>$key,'sha256'=>$stats['sha256'],
            'size'=>$stats['size'],'key_id'=>$kid,'reused'=>$reused];
    }
    public function openStream(string $key) {
        if(!isset($this->objects[$key]))
            throw new Error('object_not_found','Object unavailable.',404);
        $s=Utils::tempStream();Utils::writeAll($s,$this->objects[$key]['bytes']);rewind($s);
        return $s;
    }
    public function objectEncryptionKeyId(string $objectKey): string {
        if(!isset($this->objects[$objectKey]))
            throw new Error('object_not_found','Object unavailable.',404);
        return $this->objects[$objectKey]['key_id'];
    }
    public function delete(string $key):bool {unset($this->objects[$key]);return true;}
    public function exists(string $key):bool{return isset($this->objects[$key]);}
    public function health():array{return ['healthy'=>true];}
    public function capabilities():array{return [
        'private'=>true,'streaming'=>true,'copy'=>true,'delete'=>true,
        'client_side_chunk_gcm'=>true,'max_object_bytes'=>1073741824
    ];}
    public function copyTo(string $key,ObjectStore $target,string $targetKey):array{
        $stream=$this->openStream($key);
        try{return $target->putStream($targetKey,$stream);}
        finally{fclose($stream);}
    }
}
RecordStore::resetMemory();
$store=new R305InMemoryCipherStore();
ProviderRegistry::register('r305-memory',$store,['region'=>'test','class'=>'private']);
$sourceKey=hash('sha256','r305-shared-stale-physical');
$bytes='r305-actual-verified-ciphertext';
$stream=Utils::tempStream();Utils::writeAll($stream,$bytes);rewind($stream);
try{$store->putStream($sourceKey,$stream);}finally{fclose($stream);}
$sha=hash('sha256',$bytes);
for($i=0;$i<2001;$i++){
    RecordStore::put('asset',sprintf('r305-alias-%04d',$i),[
        'actor_id'=>11,'owner_domain'=>'file17','status'=>'ready',
        'storage'=>['provider_id'=>'r305-memory','key_id'=>'test-v1'],
        'object_key'=>$sourceKey,'sha256'=>$sha,'size'=>strlen($bytes)
    ]);
}
Keyring::setTestKeys(['test-v1'=>str_repeat('k',64),
    'test-v2'=>str_repeat('m',64)],'test-v2');
$inventory=KeyRotationInventoryStage::begin(11);
$loops=0;
while($inventory['status']==='planning'&&$loops++<100)
    $inventory=KeyRotationInventoryStage::advance($inventory['id'],11);
ok($inventory['stale_references']===2001&&$inventory['segments']===10,
    'R305 more than 2,000 stale aliases durably inventoried across 10 segments');
$run=KeyRotationShadowCoordinator::begin($inventory['id'],11);
$loops=0;
while($run['status']==='copying_unfenced'&&$loops++<500)
    $run=KeyRotationShadowCoordinator::advance($run['id'],11,25);
ok($loops<500&&$run['status']==='shadow_verified_unfenced'&&
   $run['candidates_copied']===2001&&$run['segments_done']===10,
   'R305 all 2,001 stale aliases shadow re-encrypted with bounded persistent cursors');
$proof=KeyRotationShadowCoordinator::verifyComplete($run['id'],11);
ok($proof['verified_aliases']===2001&&$proof['segments']===10&&
   $proof['old_ciphertext_preserved']===true,
   'R305 complete independent physical proof covers every affected alias');
ok(count($store->objects)===2&&
   $store->objectEncryptionKeyId($sourceKey)==='test-v1',
   'R305 one shared original plus exactly one active-key shadow object exist');
$store->objects[$sourceKey]['key_id']='test-v2';
err(fn()=>KeyRotationShadowCoordinator::verifyComplete($run['id'],11),
    'key_shadow_cipher_key_mismatch',
    'R305 physical source key drift blocks a formerly valid 2,001-alias proof');
$store->objects[$sourceKey]['key_id']='test-v1';
ok(KeyRotationShadowCoordinator::verifyComplete($run['id'],11)['verified_aliases']===2001,
    'R305 recovered original ciphertext authenticates full fleet again');
foreach(['r305-alias-0000','r305-alias-2000'] as $id){
    $original=RecordStore::get('asset',$id);
    ok($original['object_key']===$sourceKey&&
       $original['storage']['key_id']==='test-v1',
       'R305 early and late native owner references remain unmapped');
}
ok(RecordStore::all('key_rotation')===[],
    'R305 2,001-alias source test cannot falsely claim KMS cutover completion');
echo "REVIEW ROUND 305 OVER-2000 STALE ALIAS SHADOW QA: PASS\n";
