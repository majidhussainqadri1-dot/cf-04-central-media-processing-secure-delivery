<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeliveryResilienceService,RecordStore};
$p=policy('document','C0',['view','download']);
$a=['actor_id'=>11,'status'=>'ready','id'=>'r178','asset_id'=>'r178',
'owner_domain'=>'file17','owner_object'=>'message:r178','object_version'=>1,
'sha256'=>hash('sha256','r178'),'size'=>1024,'media_class'=>'document',
'mime'=>'application/pdf','privacy_class'=>'C0',
'policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights']];
RecordStore::put('asset','r178',$a);
$valid=[['id'=>'cdn-a','approved'=>true,'healthy'=>true,'priority'=>1,'region'=>'PK']];
err(fn()=>DeliveryResilienceService::routeCdn('r178',[
['id'=>'cdn-a','approved'=>true,'healthy'=>true,'priority'=>'1','region'=>'PK']]),
'cdn_candidates_invalid','Round 178 rejects coerced priority');
err(fn()=>DeliveryResilienceService::routeCdn('r178',[$valid[0],$valid[0]]),
'cdn_candidates_invalid','Round 178 rejects duplicate candidates');
ok(DeliveryResilienceService::routeCdn('r178',$valid)['provider']==='cdn-a',
'Round 178 approved public route');
err(fn()=>DeliveryResilienceService::originShield('r178',['ttl_seconds'=>'600']),
'origin_shield_invalid','Round 178 rejects coerced TTL');
ok(DeliveryResilienceService::originShield('r178',['ttl_seconds'=>600])['enabled']===true,
'Round 178 approved origin shield');
echo "REVIEW ROUND 178 DELIVERY CONTROL POLICY: PASS\n";
