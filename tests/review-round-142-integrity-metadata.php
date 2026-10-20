<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{IntegrityService,ProviderRegistry,RecordStore};

$bytes='round-142-integrity-bytes';
$key=hash('sha256','round-142-immutable-object');
$stored=ProviderRegistry::get('source-private')->putStream($key,stream_of($bytes));
$counter=0;
function r142_asset(array $changes=[]): string {
    global $counter,$stored,$key;
    $id='r142-'.(++$counter);
    $asset=array_replace(['actor_id'=>11,'asset_id'=>$id,'id'=>$id,'status'=>'ready','object_key'=>$key,'storage'=>['provider_id'=>'source-private'],'sha256'=>$stored['sha256'],'size'=>$stored['size']],$changes);
    RecordStore::put('asset',$id,$asset);
    return $id;
}
function r142_reject(string $id,string $code,string $message): void {
    err(fn()=>IntegrityService::sample($id),$code,$message);
    ok((RecordStore::get('asset',$id)['status']??'')==='quarantined',$message.' quarantines and revokes delivery');
}
$valid=r142_asset(['size'=>(string)$stored['size']]);
$results=IntegrityService::sample($valid);
ok(count($results)===1&&$results[0]['ok']===true,'Canonical decimal source size passes integrity sampling');
foreach(['1.5','1e3','20junk','-1','9223372036854775808',false,[]] as $bad)
    r142_reject(r142_asset(['size'=>$bad]),'integrity_size_invalid','Malformed source size denied');
r142_reject(r142_asset(['size'=>null]),'integrity_size_invalid','Missing source size denied');
r142_reject(r142_asset(['sha256'=>[]]),'integrity_hash_invalid','Malformed source digest denied');
r142_reject(r142_asset(['object_key'=>[]]),'integrity_target_invalid','Malformed object key denied');
r142_reject(r142_asset(['storage'=>[]]),'integrity_target_invalid','Missing storage provider denied');
r142_reject(r142_asset(['active_manifest_id'=>'missing-manifest']),'integrity_manifest_invalid','Missing active manifest denied');
$missing=r142_asset(['active_manifest_id'=>'r142-manifest-missing-derivative']);
RecordStore::put('manifest','r142-manifest-missing-derivative',['actor_id'=>11,'asset_id'=>$missing,'status'=>'active','derivatives'=>[['derivative_id'=>'missing-derivative']]]);
r142_reject($missing,'integrity_derivative_invalid','Missing active derivative denied');
$wrong=r142_asset(['active_manifest_id'=>'r142-manifest-wrong-owner']);
RecordStore::put('manifest','r142-manifest-wrong-owner',['actor_id'=>11,'asset_id'=>'another-asset','status'=>'active','derivatives'=>[]]);
r142_reject($wrong,'integrity_manifest_invalid','Cross-asset active manifest denied');
$badDerivative=r142_asset(['active_manifest_id'=>'r142-manifest-bad-size']);
$derivativeId='r142-derivative';
RecordStore::put('derivative',$derivativeId,['actor_id'=>11,'id'=>$derivativeId,'asset_id'=>$badDerivative,'status'=>'validated','object_key'=>$key,'storage'=>['provider_id'=>'source-private'],'sha256'=>$stored['sha256'],'size'=>'20junk']);
RecordStore::put('manifest','r142-manifest-bad-size',['actor_id'=>11,'asset_id'=>$badDerivative,'status'=>'active','derivatives'=>[['derivative_id'=>$derivativeId]]]);
r142_reject($badDerivative,'integrity_size_invalid','Malformed derivative size denied');
$goodDerivative=r142_asset(['active_manifest_id'=>'r142-manifest-good']);
$goodId='r142-derivative-good';
RecordStore::put('derivative',$goodId,['actor_id'=>11,'id'=>$goodId,'asset_id'=>$goodDerivative,'status'=>'validated','object_key'=>$key,'storage'=>['provider_id'=>'source-private'],'sha256'=>$stored['sha256'],'size'=>(string)$stored['size']]);
RecordStore::put('manifest','r142-manifest-good',['actor_id'=>11,'asset_id'=>$goodDerivative,'status'=>'active','derivatives'=>[['derivative_id'=>$goodId]]]);
$sample=IntegrityService::sample($goodDerivative);
ok(count($sample)===2&&$sample[0]['ok']&&$sample[1]['ok'],'Canonical source and derivative metadata pass');
echo "REVIEW ROUND 142 INTEGRITY METADATA: PASS\n";
