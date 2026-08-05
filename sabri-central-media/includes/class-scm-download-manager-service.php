<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DownloadManagerService {
    public static function create(int $user,array $asset): array {
        Utils::requireRuntime();
        if($user<1) throw new Error('download_login_required','Login required.',401);
        $assetId=Utils::text((string)($asset['asset_id']??''),80);
        if($assetId==='') throw new Error('download_asset_required','Asset identifier is required.',400);
        $asset=RecordStore::get('asset',$assetId);
        if(!$asset) throw new Error('asset_not_found','Asset not found.',404);
        if(($asset['status']??'')!=='ready'||($asset['scan_status']??'')!=='passed') throw new Error('asset_not_downloadable','Asset has not passed safety gates.',409);
        $decision=Auth::domainDecision((string)$asset['owner_domain'],'authorize_download',['actor_id'=>$user,'asset'=>$asset]);
        if((int)$decision['object_version']!==(int)$asset['object_version']) throw new Error('domain_object_version_stale','Owning domain authorization is stale.',409);
        $id=Utils::id('dl');
        $download=RecordStore::put('download',$id,['actor_id'=>$user,'user_id'=>$user,'asset_id'=>$asset['asset_id'],'asset_version'=>(int)$asset['object_version'],'status'=>'queued','progress'=>0,'attempts'=>0,'created_at'=>Utils::now()]);
        Audit::record('download_created',['download_id'=>$id,'asset_id'=>$asset['asset_id'],'actor_id'=>$user]);
        return $download;
    }
    public static function progress(string $id,int $user,int $progress): array { Utils::requireRuntime();$s=self::get($id,$user);if(!in_array($s['status'],['queued','downloading','paused'],true))throw new Error('download_state_invalid','Download state does not accept progress.',409);$s['status']=$progress>=100?'complete':'downloading';$s['progress']=max(0,min(100,$progress));return RecordStore::put('download',$id,$s,(int)$s['version']);}
    public static function retry(string $id,int $user): array { Utils::requireRuntime();$s=self::get($id,$user);if(!in_array($s['status'],['failed','paused','cancelled'],true))throw new Error('download_retry_state_invalid','Download cannot be retried in current state.',409);$s['status']='queued';$s['attempts']=(int)$s['attempts']+1;return RecordStore::put('download',$id,$s,(int)$s['version']);}
    public static function cancel(string $id,int $user): void { Utils::requireRuntime();$s=self::get($id,$user);if(in_array($s['status'],['complete','cancelled'],true))return;$s['status']='cancelled';RecordStore::put('download',$id,$s,(int)$s['version']);Audit::record('download_cancelled',['download_id'=>$id,'actor_id'=>$user]);}
    public static function list(int $user): array { Utils::requireRuntime();if($user<1)throw new Error('download_login_required','Login required.',401);return RecordStore::list('download',$user);}
    private static function get(string $id,int $user): array {if($user<1)throw new Error('download_login_required','Login required.',401);$s=RecordStore::get('download',$id);if(!$s)throw new Error('download_not_found','Download not found.',404);if((int)$s['user_id']!==$user)throw new Error('download_owner_denied','Download owner mismatch.',403);return $s;}
}
