<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$d=json_decode((string)file_get_contents($root.'/contracts/delivery-grant.schema.json'),true,64,JSON_THROW_ON_ERROR);
$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');
function r125($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 125 FAIL: $m\n");exit(1);}echo "ROUND 125 PASS: $m\n";}
r125(($d['properties']['type']['enum']??[])===['delivery-grant']&&in_array('type',$d['required'],true),'grant schema admits and requires the runtime delivery-grant discriminator');
r125(($d['properties']['download_mode']['type']??'')==='boolean'&&in_array('download_mode',$d['required'],true),'grant schema admits and requires the runtime download-mode claim');
$ops=$d['properties']['operation']['enum']??[];
foreach(['view','download','stream','extract_text','ocr'] as $op)r125(in_array($op,$ops,true),"grant schema supports runtime operation $op");
r125(str_contains($s,"'download_mode'=>\$operation==='download'")&&str_contains($s,"['view','download','stream','extract_text','ocr']"),'schema regression is bound to actual signed runtime claims');
echo "REVIEW ROUND 125 DELIVERY GRANT CONTRACT: PASS\n";