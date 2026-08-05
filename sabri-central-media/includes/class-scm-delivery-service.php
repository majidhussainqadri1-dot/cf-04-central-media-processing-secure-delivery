<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DeliveryService {
    public static function issue(array $asset,array $audience,int $ttl=300): string {
        Utils::requireRuntime();
        $assetId=Utils::text((string)($asset['asset_id']??''),80);
        if($assetId==='') throw new Error('asset_delivery_incomplete','Asset identifier is required.',400,['field'=>'asset_id']);
        $current=RecordStore::get('asset',$assetId);
        if(!$current) throw new Error('asset_not_found','Asset not found.',404);
        $asset=$current;
        foreach(['asset_id','owner_domain','owner_object','object_version','object_key','privacy_class'] as $field) if(empty($asset[$field])) throw new Error('asset_delivery_incomplete','Asset delivery record incomplete.',503,['field'=>$field]);
        if(($asset['status']??'')!=='ready'||($asset['scan_status']??'')!=='passed') throw new Error('asset_not_deliverable','Asset has not passed quarantine and required scans.',409);
        $decision=Auth::domainDecision((string)$asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'audience'=>$audience,'operation'=>'issue']);
        if((int)$decision['object_version']!==(int)$asset['object_version']) throw new Error('domain_object_version_stale','Owning domain authorization is stale.',409);
        $grantId=Utils::id('gr');
        $audienceHash=Utils::hashReference(Utils::canonicalJson($audience));
        $claims=['grant_id'=>$grantId,'asset_id'=>$asset['asset_id'],'owner_domain'=>$asset['owner_domain'],'owner_object'=>$asset['owner_object'],'object_version'=>(int)$asset['object_version'],'privacy_class'=>$asset['privacy_class'],'audience_hash'=>$audienceHash];
        $token=Crypto::sign($claims,$ttl);
        RecordStore::put('grant',$grantId,[
            'actor_id'=>Auth::currentUser(),
            'grant_id'=>$grantId,
            'asset_id'=>$asset['asset_id'],
            'status'=>'active',
            'object_version'=>(int)$asset['object_version'],
            'audience_hash'=>$audienceHash,
            'token_hash'=>hash('sha256',$token),
            'expires_at'=>Utils::now()+$ttl,
        ]);
        Audit::record('delivery_grant_issued',['grant_id'=>$grantId,'asset_id'=>$asset['asset_id'],'actor_id'=>Auth::currentUser(),'expires_in'=>$ttl]);
        return $token;
    }
    public static function consume(string $token,array $audience): string {
        Utils::requireRuntime();
        $claims=Crypto::verify($token);
        foreach(['grant_id','asset_id','owner_domain','owner_object','object_version','privacy_class','audience_hash'] as $field) if(!isset($claims[$field]) || $claims[$field]==='') throw new Error('grant_claims_invalid','Delivery grant claims are incomplete.',403,['field'=>$field]);
        $grant=RecordStore::get('grant',(string)$claims['grant_id']);
        if(!$grant || ($grant['status']??'')!=='active') throw new Error('grant_revoked','Delivery grant is unavailable or revoked.',403);
        if((int)($grant['expires_at']??0)<=Utils::now()) throw new Error('grant_expired','Delivery grant expired.',403);
        if(!hash_equals((string)($grant['token_hash']??''),hash('sha256',$token))) throw new Error('grant_record_mismatch','Delivery grant record mismatch.',403);
        $asset=RecordStore::get('asset',(string)$claims['asset_id']);
        if(!$asset) throw new Error('grant_asset_missing','Asset no longer exists.',403);
        foreach(['object_key','owner_domain','owner_object','object_version','privacy_class'] as $field) if(empty($asset[$field])) throw new Error('grant_asset_state_changed','Asset state is incomplete.',403,['field'=>$field]);
        if(($asset['status']??'')!=='ready'||($asset['scan_status']??'')!=='passed'||(int)$asset['object_version']!==(int)$claims['object_version']||!hash_equals((string)$asset['owner_domain'],(string)$claims['owner_domain'])||!hash_equals((string)$asset['owner_object'],(string)$claims['owner_object'])||!hash_equals((string)$asset['privacy_class'],(string)$claims['privacy_class'])) throw new Error('grant_asset_state_changed','Asset state changed after grant issuance.',403);
        $audienceHash=Utils::hashReference(Utils::canonicalJson($audience));
        if(!hash_equals((string)$claims['audience_hash'],$audienceHash) || !hash_equals((string)($grant['audience_hash']??''),$audienceHash)) throw new Error('grant_audience_mismatch','Delivery audience mismatch.',403);
        $decision=Auth::domainDecision((string)$asset['owner_domain'],'authorize_delivery',['asset'=>$asset,'audience'=>$audience,'operation'=>'consume','grant_id'=>$claims['grant_id']]);
        if((int)$decision['object_version']!==(int)$asset['object_version']) throw new Error('domain_object_version_stale','Owning domain authorization is stale.',409);
        $bytes=ProviderRegistry::store()->get((string)$asset['object_key']);
        Audit::record('delivery_grant_consumed',['grant_id'=>$claims['grant_id'],'asset_id'=>$asset['asset_id'],'actor_id'=>Auth::currentUser()]);
        return $bytes;
    }
    public static function revoke(string $grantId): void {
        Utils::requireRuntime();
        $grant=RecordStore::get('grant',$grantId);
        if(!$grant) throw new Error('grant_not_found','Delivery grant not found.',404);
        if(($grant['status']??'')==='revoked') return;
        $grant['status']='revoked';
        $grant['revoked_at']=Utils::now();
        RecordStore::put('grant',$grantId,$grant,(int)$grant['version']);
        Audit::record('delivery_grant_revoked',['grant_id'=>$grantId,'asset_id'=>$grant['asset_id']??'','actor_id'=>Auth::currentUser()]);
    }
}
