<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-upload.php');
function r123($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 123 FAIL: $m\n");exit(1);}echo "ROUND 123 PASS: $m\n";}
r123(str_contains($s,'private static function reauthorizeActiveUpload'),'active upload mutation has a centralized fresh-authorization gate');
$h=substr($s,strpos($s,'private static function reauthorizeActiveUpload'),2500);
r123(str_contains($h,'Auth::verifiedUser')&&str_contains($h,"DomainRegistry::decision")&&str_contains($h,'domain_object_version_stale'),'fresh upload authorization rechecks account eligibility, canonical owner decision and object version');
$needles=['part'=>"reauthorizeActiveUpload(\$upload,\$actor,'part')",'resume'=>"reauthorizeActiveUpload(\$u,\$actor,'resume')",'complete'=>"reauthorizeActiveUpload(\$u,\$actor,'complete')"];foreach($needles as $phase=>$needle)r123(str_contains($s,$needle),"upload $phase action is freshly authorized");
r123(!str_contains(substr($s,strpos($s,'public static function abort'),1600),'reauthorizeActiveUpload'),'abort remains available as a containment action after credential/actor authentication');
echo "REVIEW ROUND 123 UPLOAD ACTION REAUTH: PASS\n";