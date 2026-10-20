<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\{RightsPolicy,RightsRevocationService,RecordStore,Utils};

$context=['territory'=>'GLOBAL','audience_type'=>'private'];
$base=rights(['view']);
foreach(['never','1.5','1e3','-1','9223372036854775808',false,[]] as $invalid){
    $candidate=$base;$candidate['expires_at']=$invalid;
    err(fn()=>RightsPolicy::assert($candidate,'view',$context),'rights_integer_invalid','Action-time rights expiry rejects malformed value');
}
$missing=$base;unset($missing['expires_at']);
err(fn()=>RightsPolicy::assert($missing,'view',$context),'rights_policy_incomplete','Action-time rights expiry is mandatory');
$null=$base;$null['expires_at']=null;
err(fn()=>RightsPolicy::assert($null,'view',$context),'rights_policy_incomplete','Action-time null expiry is rejected');
foreach([0,'0'] as $zero){$candidate=$base;$candidate['expires_at']=$zero;RightsPolicy::assert($candidate,'view',$context);}
ok(true,'Canonical zero expiry remains indefinitely valid');
$expired=$base;$expired['expires_at']=Utils::now()-1;
err(fn()=>RightsPolicy::assert($expired,'view',$context),'rights_expired','Action-time valid expired rights are denied');

$asset=static function(string $id,mixed $expires): array {
    $rights=rights(['view']);$rights['expires_at']=$expires;
    return ['asset_id'=>$id,'actor_id'=>11,'status'=>'ready','owner_domain'=>'file17','owner_object'=>'round139-object',
        'object_version'=>1,'policy_hash'=>hash('sha256','round139-policy'),'rights'=>$rights,'processing_status'=>'ready'];
};
RecordStore::put('asset','round139-valid',$asset('round139-valid',0));
RecordStore::put('asset','round139-expired',$asset('round139-expired',Utils::now()-10));
RecordStore::put('asset','round139-invalid',$asset('round139-invalid','never'));
RecordStore::put('asset','round139-missing',$asset('round139-missing',null));
$missingRecord=RecordStore::get('asset','round139-missing');unset($missingRecord['rights']['expires_at']);
RecordStore::put('asset','round139-missing',$missingRecord,(int)$missingRecord['version']);
$first=RightsRevocationService::reconcileExpired(Utils::now(),3);
ok($first['revoked']===3&&$first['failed']===0,'Invalid, missing and expired rights are prioritized and revoked within bounded reconciliation');
foreach(['round139-expired'=>'rights_expired','round139-invalid'=>'rights_invalid','round139-missing'=>'rights_invalid'] as $id=>$reason){
    $fresh=RecordStore::get('asset',$id);
    ok(($fresh['status']??'')==='quarantined'&&($fresh['rights_reconciled_reason']??'')===$reason,'Revocation is persisted for '.$id);
}
ok((RecordStore::get('asset','round139-valid')['status']??'')==='ready','Indefinite valid rights are not revoked');
$again=RightsRevocationService::reconcileExpired(Utils::now(),10);
ok($again['revoked']===0&&$again['failed']===0,'Already reconciled rights do not create repeated revocations');
$fresh=RecordStore::get('asset','round139-invalid');$fresh['rights']['expires_at']=Utils::now()-20;
RecordStore::put('asset','round139-invalid',$fresh,(int)$fresh['version']);
$changed=RightsRevocationService::reconcileExpired(Utils::now(),10);
ok($changed['revoked']===1&&$changed['failed']===0,'Rights changes invalidate the reconciliation fingerprint');
echo "REVIEW ROUND 139 RIGHTS EXPIRY ACTION RECONCILIATION: PASS\n";
