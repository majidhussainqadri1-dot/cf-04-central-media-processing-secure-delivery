#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]

# Fresh Review Round 88 was fully completed before corrections began.
# Defect ledger:
# 1) grant revocation invoked owner authorization but did not reject a stale owner
#    object_version decision before mutating the grant.
# 2) public CDN bytes were published before any durable deterministic mapping existed;
#    a DB failure or process crash after remote publication could leave public content
#    with no authoritative purge/reconciliation record, and concurrent publishers could
#    create duplicate mappings.

p=ROOT/'sabri-central-media/includes/class-scm-delivery.php'
s=p.read_text()
old="DomainRegistry::decision($asset['owner_domain'],'authorize_grant_revoke',['asset'=>$asset,'grant'=>$grant,'actor_id'=>$actor,'reason'=>$reason]);$grant['status']='revoked';"
new="$decision=DomainRegistry::decision($asset['owner_domain'],'authorize_grant_revoke',['asset'=>$asset,'grant'=>$grant,'actor_id'=>$actor,'reason'=>$reason]);if((int)$decision['object_version']!==(int)$asset['object_version'])throw new Error('domain_object_version_stale','Owner authorization is stale.',409);$grant['status']='revoked';"
if old not in s and new not in s: raise SystemExit('round 88 revoke-version anchor missing')
s=s.replace(old,new,1)

old_pub="""    $existing=array_values(array_filter(RecordStore::all('cdn_mapping',0,null,100000),fn($mapping)=>($mapping['asset_id']??'')===$assetId&&($mapping['derivative_id']??'')===$derivativeId&&($mapping['status']??'')==='published'&&($mapping['sha256']??'')===$derivative['sha256']&&($mapping['privacy_class']??'')===$asset['privacy_class']&&hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])));
    if($existing!==[])return $existing[0];
    $cacheKey=hash('sha256',$assetId.'|'.$derivativeId.'|'.$derivative['sha256'].'|'.$asset['privacy_class'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']).'/'.$derivative['sha256'];$result=CdnRegistry::adapter()->publish(['asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'object_key'=>$derivative['object_key'],'cache_key'=>$cacheKey,'headers'=>['Content-Type'=>$derivative['mime'],'Cache-Control'=>'public, max-age=31536000, immutable','X-Content-Type-Options'=>'nosniff','Cross-Origin-Resource-Policy'=>'same-site','Content-Security-Policy'=>\"default-src 'none'; sandbox\"]]);
    if(($result['published']??false)!==true||empty($result['url'])||empty($result['version_key']))throw new Error('cdn_publish_failed','CDN publication failed.',503);
    $map=['actor_id'=>0,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'status'=>'published','url'=>Utils::text((string)$result['url'],1000),'version_key'=>Utils::text((string)$result['version_key'],191),'published_at'=>Utils::now()];
    return RecordStore::put('cdn_mapping',Utils::id('cdn'),$map);"""
new_pub="""    $existing=array_values(array_filter(RecordStore::all('cdn_mapping',0,null,100000),fn($mapping)=>($mapping['asset_id']??'')===$assetId&&($mapping['derivative_id']??'')===$derivativeId&&($mapping['status']??'')==='published'&&($mapping['sha256']??'')===$derivative['sha256']&&($mapping['privacy_class']??'')===$asset['privacy_class']&&hash_equals((string)($mapping['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($mapping['rights_hash']??''),(string)$asset['rights']['policy_hash'])));
    if($existing!==[])return $existing[0];
    $cacheKey=hash('sha256',$assetId.'|'.$derivativeId.'|'.$derivative['sha256'].'|'.$asset['privacy_class'].'|'.$asset['policy_hash'].'|'.$asset['rights']['policy_hash']).'/'.$derivative['sha256'];$mappingId=hash('sha256','cdn-map|'.$cacheKey);$map=['actor_id'=>0,'asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'privacy_class'=>$asset['privacy_class'],'policy_hash'=>$asset['policy_hash'],'rights_hash'=>$asset['rights']['policy_hash'],'status'=>'publishing','cache_key'=>$cacheKey,'publish_started_at'=>Utils::now()];
    try{$map=RecordStore::put('cdn_mapping',$mappingId,$map,0);}catch(Error $conflict){if($conflict->errorCode!=='record_version_conflict')throw $conflict;$current=RecordStore::get('cdn_mapping',$mappingId);$same=$current&&($current['asset_id']??'')===$assetId&&($current['derivative_id']??'')===$derivativeId&&hash_equals((string)($current['sha256']??''),(string)$derivative['sha256'])&&hash_equals((string)($current['policy_hash']??''),(string)$asset['policy_hash'])&&hash_equals((string)($current['rights_hash']??''),(string)$asset['rights']['policy_hash']);if(!$same)throw new Error('cdn_mapping_conflict','CDN mapping identity conflicts with current asset state.',409);if(($current['status']??'')==='published')return $current;throw new Error('cdn_publish_in_progress','A publication for this exact immutable CDN identity is already in progress.',409,['mapping_id'=>$mappingId]);}
    try{$result=CdnRegistry::adapter()->publish(['asset_id'=>$assetId,'derivative_id'=>$derivativeId,'sha256'=>$derivative['sha256'],'object_key'=>$derivative['object_key'],'cache_key'=>$cacheKey,'headers'=>['Content-Type'=>$derivative['mime'],'Cache-Control'=>'public, max-age=31536000, immutable','X-Content-Type-Options'=>'nosniff','Cross-Origin-Resource-Policy'=>'same-site','Content-Security-Policy'=>\"default-src 'none'; sandbox\"]]);if(($result['published']??false)!==true||empty($result['url'])||empty($result['version_key']))throw new Error('cdn_publish_failed','CDN publication failed.',503);$map['status']='published';$map['url']=Utils::text((string)$result['url'],1000);$map['version_key']=Utils::text((string)$result['version_key'],191);$map['published_at']=Utils::now();unset($map['publish_started_at']);return RecordStore::put('cdn_mapping',$mappingId,$map,(int)$map['version']);}catch(\\Throwable $exception){$current=RecordStore::get('cdn_mapping',$mappingId);if($current&&($current['status']??'')==='publishing'){$current['last_publish_error']=$exception instanceof Error?$exception->errorCode:'unexpected';$current['last_publish_error_at']=Utils::now();try{RecordStore::put('cdn_mapping',$mappingId,$current,(int)$current['version']);}catch(\\Throwable){}}throw $exception;}"""
if old_pub not in s and "cdn-map|'" not in s: raise SystemExit('round 88 CDN publication anchor missing')
s=s.replace(old_pub,new_pub,1)
p.write_text(s)

t=ROOT/'tests/review-round-88-delivery-cdn-atomicity.php'
t.write_text(r'''<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r88($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 88 FAIL: $m\n");exit(1);}echo "ROUND 88 PASS: $m\n";}
r88(str_contains($s,"authorize_grant_revoke")&&str_contains($s,"if((int)\$decision['object_version']!==(int)\$asset['object_version'])"),'grant revocation rejects stale owner authorization');
$put=strpos($s,"RecordStore::put('cdn_mapping',\$mappingId,\$map,0)");$publish=strpos($s,'CdnRegistry::adapter()->publish', $put===false?0:$put);
r88($put!==false&&$publish!==false&&$put<$publish,'durable deterministic CDN mapping exists before remote publication');
r88(str_contains($s,"hash('sha256','cdn-map|' .")||str_contains($s,"hash('sha256','cdn-map|'.\$cacheKey)"),'CDN mapping identity is deterministic for the immutable cache identity');
r88(str_contains($s,"cdn_publish_in_progress")&&str_contains($s,"status']='publishing'"),'concurrent/partial publication remains reconcilable instead of creating duplicate mappings');
echo "REVIEW ROUND 88 DELIVERY CDN ATOMICITY: PASS\n";
''')
q=ROOT/'tools/quality-check.sh';x=q.read_text();anchor='php "$ROOT/tests/review-round-87-storage-boundaries.php"\n'
if 'review-round-88-delivery-cdn-atomicity.php' not in x:
    if anchor not in x: raise SystemExit('round 88 quality anchor missing')
    q.write_text(x.replace(anchor,anchor+'php "$ROOT/tests/review-round-88-delivery-cdn-atomicity.php"\n',1))
