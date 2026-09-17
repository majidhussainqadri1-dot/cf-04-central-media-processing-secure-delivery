<?php
declare(strict_types=1);
$root=dirname(__DIR__);$s=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
function r80($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 80 FAIL: $m\n");exit(1);}echo "ROUND 80 PASS: $m\n";}
r80(str_contains($s,"$lock='scm_audit_'.substr(hash('sha256',Db::table('audit')),0,54);"),'audit lock uses a bounded deterministic table-specific identifier');
r80(!str_contains($s,"$lock='scm_audit_chain_'.hash('sha256',Db::table('audit'));"),'legacy 80-character audit lock construction is absent');
r80(strlen('scm_audit_'.substr(hash('sha256','wp_scm_audit'),0,54))<=64,'representative audit lock stays within the 64-character MySQL user-lock ceiling');
echo "REVIEW ROUND 80 AUDIT LOCK NAME: PASS\n";
