<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{ProviderExitInventoryStage,ProviderRegistry,RecordStore};

RecordStore::resetMemory();
for($i=0;$i<2301;$i++){
    $id=sprintf('r273-source-%05d',$i);
    RecordStore::put('asset',$id,[
        'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','object_version'=>1,
        'storage'=>['provider_id'=>'source-private','key_id'=>'test-v1'],
        'object_key'=>hash('sha256','r273-cipher-'.$i),
        'sha256'=>hash('sha256','r273-plain-'.$i),'size'=>12
    ]);
}
$initialActive=ProviderRegistry::activeId();
$stage=ProviderExitInventoryStage::begin('source-private','target-private',11);
$loops=0;
while(($stage['status']??'')==='planning'&&$loops++<100){
    $stage=ProviderExitInventoryStage::advance($stage['id'],11);
}
ok(($stage['status']??'')==='inventory_complete_unfenced'
   &&($stage['eligible_items']??null)===2301
   &&($stage['visited_rows']??null)===2301
   &&($stage['segments']??0)>=11,
   'R273 >2000 provider source objects have durable, complete paginated inventory without JSON monolith');
$segments=RecordStore::all('provider_exit_inventory_segment',0,null,500);
ok(count($segments)===$stage['segments'],
   'R273 every source page has a distinct persistent segment/checkpoint record');
$total=0;
foreach($segments as $seg){
    ok(count($seg['items'])<=250&&($seg['status']??null)==='planned_unfenced'
       &&is_string($seg['page_hash']??null)&&strlen($seg['page_hash'])===64,
       'R273 segment items remain individually bounded and page-hash attested');
    $total+=count($seg['items']);
}
ok($total===2301&&ProviderRegistry::activeId()===$initialActive
   &&RecordStore::all('provider_exit')===[]
   &&RecordStore::all('key_rotation')===[],
   'R273 inventory does not cut over providers, rotate keys or claim destructive migration');
$same=ProviderExitInventoryStage::advance($stage['id'],11);
ok($same['version']===$stage['version']&&$same['chain_hash']===$stage['chain_hash'],
   'R273 completed inventory is a stable idempotent read, not a new segment');
$another=ProviderExitInventoryStage::begin('source-private','target-private',11);
$first=ProviderExitInventoryStage::advance($another['id'],11);
$firstSegId=hash('sha256',$another['id'].'|asset|');
$firstSegment=RecordStore::get('provider_exit_inventory_segment',$firstSegId);
ok(is_array($firstSegment)&&count($firstSegment['items'])===250,
   'R273 first page is durably journaled before its parent checkpoint');
// Model a crash immediately after segment creation but before stage CAS commit.
$rollback=$first;$rollback['record_family']='asset';$rollback['after_id']='';
$rollback['segments']=0;$rollback['eligible_items']=0;$rollback['visited_rows']=0;
$rollback['chain_hash']=str_repeat('0',64);
$rollback=RecordStore::put('provider_exit_inventory',$first['id'],$rollback,(int)$first['version']);
$replayed=ProviderExitInventoryStage::advance($first['id'],11);
ok($replayed['segments']===1
   &&RecordStore::get('provider_exit_inventory_segment',$firstSegId)['version']===$firstSegment['version'],
   'R273 interrupted page checkpoint reuses exact existing segment instead of duplicating it');
$reset=$replayed;$reset['record_family']='asset';$reset['after_id']='';
$reset['segments']=0;$reset['eligible_items']=0;$reset['visited_rows']=0;
$reset['chain_hash']=str_repeat('0',64);
$reset=RecordStore::put('provider_exit_inventory',$first['id'],$reset,(int)$replayed['version']);
$changed=RecordStore::get('asset','r273-source-00000');
$changed['status']='deleted';
RecordStore::put('asset',$changed['id'],$changed,(int)$changed['version']);
err(fn()=>ProviderExitInventoryStage::advance($first['id'],11),
   'provider_exit_inventory_drift',
   'R273 a changed page cannot silently overwrite a previously checkpointed inventory segment');
echo "REVIEW ROUND 273 PROVIDER EXIT DURABLE SEGMENTED INVENTORY: PASS\n";
