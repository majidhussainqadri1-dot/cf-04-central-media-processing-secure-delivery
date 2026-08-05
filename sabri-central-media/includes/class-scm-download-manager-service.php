<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class DownloadManagerService {
    public static function create(int $user,array $asset): array { Utils::requireRuntime();$current=RecordStore::get('asset',(string)($asset['asset_id']??''));if($current)$asset=$current;if($user<1)throw new Error('download_login_required','Login required.',401);if(($asset['status']??'')!=='ready'||($asset['scan_status']??'')!=='passed')throw new Error('asset_not_downloadable','Asset has not passed safety gates.',409);Auth::domainDecision((string)$asset['owner_domain'],'authorize_download',['actor_id'=>$user,'asset'=>$asset]);$id=Utils::id('dl');return RecordStore::put('download',$id,['actor_id'=>$user,'user_id'=>$user,'asset_id'=>$asset['asset_id'],'status'=>'queued','progress'=>0,'attempts'=>0,'created_at'=>Utils::now()]);}
    public static function progress(string $id,int $user,int $progress): array {$s=self::get($id,$user);if(!in_array($s['status'],['queued','downloading','paused'],true))throw new Error('download_state_invalid','Download state does not accept progress.',409);$s['status']=$progress>=100?'complete':'downloading';$s['progress']=max(0,min(100,$progress));return RecordStore::put('download',$id,$s,(int)$s['version']);}
    public static function retry(string $id,int $user): array {$s=self::get($id,$user);if(!in_array($s['status'],['failed','paused','cancelled'],true))throw new Error('download_retry_state_invalid','Download cannot be retried in current state.',409);$s['status']='queued';$s['attempts']=(int)$s['attempts']+1;return RecordStore::put('download',$id,$s,(int)$s['version']);}
    public static function cancel(string $id,int $user): void {$s=self::get($id,$user);$s['status']='cancelled';RecordStore::put('download',$id,$s,(int)$s['version']);}
    public static function list(int $user): array {return RecordStore::list('download',$user);}
    private static function get(string $id,int $user): array {$s=RecordStore::get('download',$id);if(!$s)throw new Error('download_not_found','Download not found.',404);if((int)$s['user_id']!==$user)throw new Error('download_owner_denied','Download owner mismatch.',403);return $s;}
}
