<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DeletionService {
    public static function delete(array $asset,int $actor,string $reason): array {
        Utils::requireRuntime();
        if($actor<1) throw new Error('deletion_actor_required','Authenticated actor required.',401);
        $reasonCode=Utils::key($reason,64);
        if($reasonCode==='') throw new Error('deletion_reason_required','Deletion reason required.',400);
        $assetId=Utils::text((string)($asset['asset_id']??''),80);
        if($assetId==='') throw new Error('deletion_asset_required','Asset identifier is required.',400);
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset) throw new Error('asset_not_found','Asset not found.',404);
        if(($asset['status']??'')==='deleted') return self::tombstone($asset);
        if(!empty($asset['legal_hold'])) throw new Error('asset_legal_hold','Asset is under legal or policy hold.',409);
        foreach(['owner_domain','object_version','object_key'] as $field) if(empty($asset[$field])) throw new Error('asset_deletion_incomplete','Asset deletion record is incomplete.',503,['field'=>$field]);
        $decision=Auth::domainDecision((string)$asset['owner_domain'],'authorize_deletion',['asset'=>$asset,'actor_id'=>$actor,'reason_code'=>$reasonCode]);
        if((int)$decision['object_version']!==(int)$asset['object_version']) throw new Error('domain_object_version_stale','Owning domain authorization is stale.',409);
        $objectKey=(string)$asset['object_key'];
        if(!ProviderRegistry::store()->delete($objectKey)) throw new Error('provider_delete_failed','Provider deletion failed.',500);
        $asset['status']='deleted';
        $asset['scan_status']='revoked';
        $asset['deleted_at']=Utils::now();
        $asset['deleted_by']=$actor;
        $asset['deletion_reason_code']=$reasonCode;
        $asset['provider_purged']=true;
        $asset['cdn_purge_status']='not_configured';
        $asset['deleted_object_hash']=Utils::hashReference($objectKey);
        unset($asset['object_key']);
        $asset=RecordStore::put('asset',$assetId,$asset,(int)$asset['version']);
        foreach(RecordStore::list('grant') as $grant){
            if(($grant['asset_id']??'')!==$assetId || ($grant['status']??'')!=='active') continue;
            $grant['status']='revoked';
            $grant['revoked_at']=Utils::now();
            $grant['revocation_reason']='asset_deleted';
            RecordStore::put('grant',(string)$grant['id'],$grant,(int)$grant['version']);
        }
        $tombstone=self::tombstone($asset);
        Audit::record('asset_deleted',$tombstone+['actor_id'=>$actor]);
        return $tombstone;
    }
    private static function tombstone(array $asset): array {
        return [
            'asset_id'=>(string)($asset['asset_id']??$asset['id']??''),
            'status'=>(string)($asset['status']??'deleted'),
            'deleted_at'=>(int)($asset['deleted_at']??Utils::now()),
            'reason_code'=>(string)($asset['deletion_reason_code']??''),
            'provider_purged'=>(bool)($asset['provider_purged']??false),
            'cdn_purged'=>($asset['cdn_purge_status']??'')==='purged',
            'cdn_purge_status'=>(string)($asset['cdn_purge_status']??'unknown'),
        ];
    }
}
