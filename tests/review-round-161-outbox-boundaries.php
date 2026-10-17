<?php
declare(strict_types=1);
function do_action(string $tag,mixed ...$args): void {
    if($tag==='scm.media.revoked'&&($GLOBALS['r161_block']??false))
        throw new \RuntimeException('Test hook failure');
    $GLOBALS['scm_actions'][]=['tag'=>$tag,'args'=>$args];
}
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RecordStore,RevocationDispatchService,RightsRevocationService,Utils};
foreach(['owner'=>'owner_changed','policy'=>'policy_changed',
    'terminal'=>'asset_terminal','missing'=>'asset_missing','legacy'=>'policy_unbound'] as $mode=>$expected){
    RecordStore::resetMemory();$GLOBALS['scm_actions']=[];$GLOBALS['r161_block']=true;
    $r=rights(['view']);$r['expires_at']=Utils::now()-10;
    $asset=RecordStore::put('asset','r161-'.$mode,[
        'actor_id'=>11,'asset_id'=>'r161-'.$mode,'status'=>'ready',
        'owner_domain'=>'file17','owner_object'=>'r161-owner','object_version'=>1,
        'policy_hash'=>hash('sha256','r161-policy'),'rights'=>$r,
        'storage'=>['provider_id'=>'source-private'],
        'object_key'=>hash('sha256','r161-missing'),
    ]);
    try{RightsRevocationService::invalidate($asset['id'],'rights_expired');}
    catch(\RuntimeException){}
    $notice=RecordStore::all('revocation_dispatch')[0];
    ok($notice['status']==='pending','Round 161 creates pending '.$mode);
    if($mode==='legacy'){
        unset($notice['policy_hash']);
        RecordStore::put('revocation_dispatch',$notice['id'],$notice,(int)$notice['version']);
    }else{
        $current=RecordStore::get('asset',$asset['id']);
        if($mode==='missing')RecordStore::delete('asset',$asset['id'],(int)$current['version']);
        else{
            if($mode==='owner')$current['owner_object']='changed-owner';
            if($mode==='policy')$current['policy_hash']=hash('sha256','changed-policy');
            if($mode==='terminal')$current['status']='deleted';
            RecordStore::put('asset',$asset['id'],$current,(int)$current['version']);
        }
    }
    $GLOBALS['r161_block']=false;
    $out=RevocationDispatchService::reconcile();
    $row=RecordStore::get('revocation_dispatch',$notice['id']);
    ok($out['superseded']===1&&$out['dispatched']===0&&$out['failed']===0
        &&$row['status']==='superseded'&&$row['superseded_reason']===$expected,
        'Round 161 '.$mode.' is durably superseded');
    ok(count($GLOBALS['scm_actions'])===0,
        'Round 161 '.$mode.' does not dispatch obsolete event');
    if($mode==='legacy'){
        RightsRevocationService::invalidate($asset['id'],'rights_expired');
        ok(RecordStore::get('revocation_dispatch',$notice['id'])['status']==='dispatched',
            'Round 161 legacy policy binds to current live asset before dispatch');
    }
}
echo "REVIEW ROUND 161 OUTBOX BOUNDARIES: PASS\n";
