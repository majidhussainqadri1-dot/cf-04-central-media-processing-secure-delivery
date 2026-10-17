<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{DeliveryService,Rest,RecordStore,Utils,Crypto};

$audience=new ReflectionMethod(DeliveryService::class,'audience');
foreach(['user','recipient'] as $type){
    foreach(['11.5','1e3','-1','0','9223372036854775808',false,[]] as $bad)
        err(fn()=>$audience->invoke(null,['type'=>$type,'user_id'=>$bad]),'audience_invalid','Noncanonical '.$type.' identity denied');
    ok($audience->invoke(null,['type'=>$type,'user_id'=>'11'])['user_id']===11,'Canonical audience identity allowed');
}
foreach(['1.5',0,false,[],null] as $bad)
    err(fn()=>$audience->invoke(null,['type'=>'group','group_id'=>'g','user_id'=>$bad]),'audience_invalid','Malformed optional group identity denied');
ok(!isset($audience->invoke(null,['type'=>'group','group_id'=>'g'])['user_id']),'Group without optional identity allowed');

$grantCheck=new ReflectionMethod(DeliveryService::class,'assertGrantUsable');
$valid=['expires_at'=>Utils::now()+300,'uses'=>0,'max_uses'=>2];
ok($grantCheck->invoke(null,$valid)===0,'Canonical grant counters allowed');
foreach(['expires_at'=>['1.5','1e3','-1','9223372036854775808',false,[]],
         'uses'=>['1.5','-1','1e3',false,[]],
         'max_uses'=>['1.5','0','1001',false,[]]] as $field=>$values){
    foreach($values as $bad){$g=$valid;$g[$field]=$bad;err(fn()=>$grantCheck->invoke(null,$g),'grant_integer_invalid','Malformed '.$field.' denied');}
    $g=$valid;unset($g[$field]);err(fn()=>$grantCheck->invoke(null,$g),'grant_integer_invalid','Missing '.$field.' denied');
}
$expired=$valid;$expired['expires_at']=Utils::now()-1;
err(fn()=>$grantCheck->invoke(null,$expired),'grant_expired','Expired grant denied');
$exhausted=$valid;$exhausted['uses']=2;
err(fn()=>$grantCheck->invoke(null,$exhausted),'grant_use_limit','Exhausted grant denied');

$target=new ReflectionMethod(DeliveryService::class,'target');
$asset=['asset_id'=>'r141-a','sha256'=>hash('sha256','x'),'size'=>3,'object_key'=>'key','storage'=>['provider_id'=>'local-private'],'mime'=>'application/pdf','status'=>'ready'];
foreach(['1.5','1e3','0','-1','9223372036854775808',false,[]] as $bad){
    $candidate=$asset;$candidate['size']=$bad;
    err(fn()=>$target->invoke(null,$candidate,null),'delivery_target_size_invalid','Noncanonical target size denied');
}
$canonical=$asset;$canonical['size']='3';
ok($target->invoke(null,$canonical,null)['size']===3,'Canonical target size allowed');

final class R141Request {
    public function __construct(private array $payload){}
    public function get_params(): array {return $this->payload;}
    public function get_json_params(): array {return [];}
    public function get_header(string $key): string {return $key==='x-scm-contract-version'?SCM_CONTRACT_VERSION:'';}
}
foreach(['ttl','max_uses'] as $field)foreach(['1.5','1e3','0','-1','9223372036854775808',false,[]] as $bad){
    $response=Rest::issueGrant(new R141Request(['asset_id'=>'r141-a','session_id'=>'session','ttl'=>300,'max_uses'=>20,$field=>$bad]));
    ok(($response['code']??'')==='delivery_grant_integer_invalid','REST noncanonical '.$field.' denied');
}
$p=policy();
$ready=$asset+['actor_id'=>11,'owner_domain'=>'file17','owner_object'=>'message:r141','object_version'=>1,'privacy_class'=>'C3','policy'=>$p,'policy_hash'=>$p['policy_hash'],'rights'=>$p['rights'],'declared_name'=>'x.pdf','scan_status'=>'passed','processing_status'=>'completed'];
RecordStore::put('asset','r141-a',$ready);
$ctx=['territory'=>'GLOBAL','audience_type'=>'user'];$aud=['type'=>'user','user_id'=>11];
foreach(['1.5','1e3','0','-1','9223372036854775808',false,[]] as $bad)
    err(fn()=>DeliveryService::issue('r141-a',null,11,'file17',$aud,$ctx,'view',['allow_ranges'=>true,'max_range_bytes'=>$bad],'r141-session'),'delivery_range_integer_invalid','Noncanonical range maximum denied');
$token=DeliveryService::issue('r141-a',null,11,'file17',$aud,$ctx,'view',['allow_ranges'=>true,'max_range_bytes'=>'1024'],'r141-session',300,2);
$claims=Crypto::verify($token);$id=$claims['grant_id'];
foreach(['expires_at'=>'9999999999garbage','uses'=>'1.5','max_uses'=>'1.5'] as $field=>$bad){
    $g=RecordStore::get('grant',$id);$old=$g[$field];$g[$field]=$bad;
    RecordStore::put('grant',$id,$g,(int)$g['version']);
    err(fn()=>DeliveryService::serve($token,11,'file17',$aud,$ctx,'r141-session'),'grant_integer_invalid','Persisted noncanonical '.$field.' denied');
    $g=RecordStore::get('grant',$id);$g[$field]=$old;RecordStore::put('grant',$id,$g,(int)$g['version']);
}
$source=file_get_contents(__DIR__.'/../sabri-central-media/includes/class-scm-delivery.php');
ok(substr_count($source,'self::assertGrantUsable(')>=2,'CAS grant consumption rechecks canonical counters');
echo "REVIEW ROUND 141 DELIVERY NUMERIC CONTRACTS: PASS\n";
