<?php
declare(strict_types=1);
$root=dirname(__DIR__);$p=file_get_contents($root.'/sabri-central-media/includes/class-scm-persistence.php');
function r83($ok,$m){if(!$ok){fwrite(STDERR,"ROUND 83 FAIL: $m\n");exit(1);}echo "ROUND 83 PASS: $m\n";}
$columns=<<<'PATTERN'
SHOW COLUMNS FROM '.$name
PATTERN;
r83(str_contains($p,$columns),'schema readiness verifies physical columns');
r83(str_contains($p,"'records'=>['record_type','id','actor_id','status','version','expires_at','payload','updated_at']"),'records critical shape is explicit');
r83(str_contains($p,"'audit'=>['id','event_id','event_key','actor_id','previous_hash','event_hash','payload','created_at']"),'audit critical shape is explicit');
echo "REVIEW ROUND 83 SCHEMA SHAPE: PASS\n";
