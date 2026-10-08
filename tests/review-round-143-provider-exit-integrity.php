<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use Sabri\CentralMedia\ProviderExitService;
$identity=new ReflectionMethod(ProviderExitService::class,'identity');
$good=['size'=>'16','sha256'=>hash('sha256','bytes'),'object_key'=>hash('sha256','object')];
$canonical=$identity->invoke(null,$good);
ok($canonical['size']===16&&$canonical['sha256']===$good['sha256'],'Canonical provider-exit identity accepted');
foreach(['size'=>['16junk','1e3','1.5','-1',false,[]],
         'sha256'=>['invalid',[],false,null],
         'object_key'=>['invalid',[],false,null]] as $field=>$badValues){
    foreach($badValues as $bad){$candidate=$good;$candidate[$field]=$bad;err(fn()=>$identity->invoke(null,$candidate),'provider_exit_identity_invalid','Malformed provider-exit '.$field.' denied');}
    $candidate=$good;unset($candidate[$field]);err(fn()=>$identity->invoke(null,$candidate),'provider_exit_identity_invalid','Missing provider-exit '.$field.' denied');
}
$source=file_get_contents(__DIR__.'/../sabri-central-media/includes/class-scm-operations.php');
$begin=strpos($source,'final class ProviderExitService {');
$end=strpos($source,'final class KeyRotationService {');
$service=substr($source,$begin,$end-$begin);
ok(substr_count($service,'self::identity(')>=6,'Inventory, copy, shadow verification, and switch validate persisted identity');
ok(str_contains($service,"$"."planned['sha256']!==$"."current['sha256']")&&str_contains($service,"$"."planned['size']!==$"."current['size']"),'Switch rejects content-identity drift before remapping');
echo "REVIEW ROUND 143 PROVIDER EXIT INTEGRITY: PASS\n";
