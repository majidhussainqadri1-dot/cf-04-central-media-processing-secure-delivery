<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class RateLimiter {
    public static function consume(string $bucket,int $limit,int $window): void { $now=Utils::now();$id=hash('sha256',$bucket);$x=RecordStore::get('rate',$id)??['start'=>$now,'count'=>0,'version'=>0];if($now-(int)$x['start']>=$window)$x=['start'=>$now,'count'=>0,'version'=>(int)($x['version']??0)];$x['count']=(int)$x['count']+1;if($x['count']>$limit) throw new Error('rate_limit_exceeded','Too many requests.',429,['retry_after'=>max(1,$window-($now-(int)$x['start']))]);RecordStore::put('rate',$id,['actor_id'=>0,'status'=>'active','start'=>(int)$x['start'],'count'=>$x['count'],'expires_at'=>(int)$x['start']+$window],(int)($x['version']??0)); }
}
