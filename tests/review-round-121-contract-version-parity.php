<?php
declare(strict_types=1);
$root=dirname(__DIR__);$plugin=file_get_contents($root.'/sabri-central-media/sabri-central-media.php');
function r121($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 121 FAIL: $m\n");exit(1);}echo "ROUND 121 PASS: $m\n";}
preg_match("/SCM_CONTRACT_VERSION','([^']+)'/",$plugin,$m);r121(isset($m[1]),'runtime contract version is declared');$version=$m[1];
foreach(glob($root.'/contracts/*.json') as $path){$d=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);r121(str_ends_with((string)($d['$id']??''),'-'.$version.'.json'),basename($path).' public schema id matches runtime contract version');}
echo "REVIEW ROUND 121 CONTRACT VERSION PARITY: PASS\n";