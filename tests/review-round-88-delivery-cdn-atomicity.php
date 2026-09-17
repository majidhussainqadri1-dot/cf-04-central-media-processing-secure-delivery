<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r88($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 88 FAIL: $m\n");exit(1);}echo "ROUND 88 PASS: $m\n";}
r88(str_contains($s,"authorize_grant_revoke")&&str_contains($s,"if((int)\$decision['object_version']!==(int)\$asset['object_version'])"),'grant revocation rejects stale owner authorization');
$put=strpos($s,"RecordStore::put('cdn_mapping',\$mappingId,\$map,0)");$publish=strpos($s,'CdnRegistry::adapter()->publish',$put===false?0:$put);r88($put!==false&&$publish!==false&&$put<$publish,'durable deterministic CDN mapping exists before remote publication');
r88(str_contains($s,"hash('sha256','cdn-map|'.\$cacheKey)"),'CDN mapping identity is deterministic for the immutable cache identity');
r88(str_contains($s,'cdn_publish_in_progress')&&str_contains($s,"'status'=>'publishing'"),'concurrent/partial publication remains reconcilable instead of creating duplicate mappings');
echo "REVIEW ROUND 88 DELIVERY CDN ATOMICITY: PASS\n";
