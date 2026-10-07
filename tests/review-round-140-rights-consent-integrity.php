<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\RightsPolicy;

$context=['territory'=>'GLOBAL','audience_type'=>'private'];
$normalized=RightsPolicy::normalize(rights(['view']));
RightsPolicy::assert($normalized,'view',$context);
ok(true,'Canonical hashed rights permit authorized private view');

$revoked=$normalized;$revoked['consent_status']='revoked';
err(fn()=>RightsPolicy::assert($revoked,'view',$context),'consent_not_granted','Action-time revoked consent is denied');
$missing=$normalized;unset($missing['consent_status']);
err(fn()=>RightsPolicy::assert($missing,'view',$context),'rights_policy_incomplete','Action-time missing consent is denied');
$malformed=$normalized;$malformed['rights_version']='1.5';
err(fn()=>RightsPolicy::assert($malformed,'view',$context),'rights_integer_invalid','Action-time rights version must be canonical');
$public=$normalized;$public['allowed_audiences'][]='public';
err(fn()=>RightsPolicy::assert($public,'view',['territory'=>'GLOBAL','audience_type'=>'public']),'clinical_public_audience_denied','Action-time confidential public audience is denied');
$tampered=$normalized;$tampered['allowed_operations'][]='download';
err(fn()=>RightsPolicy::assert($tampered,'view',$context),'rights_policy_hash_mismatch','Action-time rights expansion cannot bypass stored hash');
$invalidHash=$normalized;$invalidHash['policy_hash']=[];
err(fn()=>RightsPolicy::assert($invalidHash,'view',$context),'rights_policy_hash_mismatch','Non-string stored rights hash is rejected');
$raw=rights(['view']);RightsPolicy::assert($raw,'view',$context);
ok(true,'Raw canonical rights without a stored hash remain compatible with explicit checks');
echo "REVIEW ROUND 140 RIGHTS CONSENT INTEGRITY: PASS\n";
