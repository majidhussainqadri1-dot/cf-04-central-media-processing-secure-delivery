<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-storage.php');
function r103($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 103 FAIL: $m\n");exit(1);}echo "ROUND 103 PASS: $m\n";}
r103(str_contains($s,'storage_root_unconfigured')&&!str_contains($s,"dirname(defined('ABSPATH')?ABSPATH:sys_get_temp_dir()).'/scm-private'"),'production local storage no longer guesses a path that may sit inside a parent web root');
r103(str_contains($s,'SCM_PRIVATE_ROOT')&&str_contains($s,'SCM_TEST_MODE'),'production requires explicit private root while test mode retains an isolated temporary default');
r103(str_contains($s,'storage_root_symlink_denied')&&str_contains($s,'is_link($root)'),'local storage rejects a symbolic-link root before trusting it as private storage');
echo "REVIEW ROUND 103 PRIVATE STORAGE ROOT: PASS\n";
