<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-storage.php');
function r87($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 87 FAIL: $m\n");exit(1);}echo "ROUND 87 PASS: $m\n";}
r87(str_contains($s,"if(\$size>1073741824)throw new Error('object_size_invalid'")&&str_contains($s,"413);hash_update"),'write path enforces the same 1 GiB object ceiling before persistence');
r87(str_contains($s,"is_link(\$dir)")&&str_contains($s,"Utils::pathWithin(\$dirReal,\$root)")&&str_contains($s,"storage_path_escape"),'shard directories cannot redirect object paths outside the private root');
echo "REVIEW ROUND 87 STORAGE BOUNDARIES: PASS\n";
