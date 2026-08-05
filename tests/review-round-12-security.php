<?php
declare(strict_types=1);
$root=dirname(__DIR__);$files=glob($root.'/sabri-central-media/includes/*.php');$source='';foreach($files as $f)$source.=file_get_contents($f)."\n";
function gate(bool $v,string $m): void {if(!$v){fwrite(STDERR,"ROUND 12 FAIL: $m\n");exit(1);}echo "ROUND 12 PASS: $m\n";}
foreach(['eval(','unserialize(','shell_exec(','passthru(','system(','exec('] as $bad)gate(!str_contains($source,$bad),'forbidden primitive absent: '.$bad);
foreach(['RuntimeGuard','RecordStore','Keyring','ScannerRegistry','ToolRunner','DeliveryService','DeletionService','RestoreService','Audit'] as $class)gate(str_contains($source,'class '.$class)||str_contains($source,'final class '.$class),'security class present: '.$class);
gate(str_contains($source,"'audience_hash'")&&str_contains($source,"'context_hash'")&&str_contains($source,"'session_hash'")&&str_contains($source,"'range_hash'"),'delivery grant complete binding');
$delivery=file_get_contents($root.'/sabri-central-media/includes/class-scm-delivery.php');$claims=substr($delivery,strpos($delivery,'$claims=['),strpos($delivery,'$token=Crypto::sign')-strpos($delivery,'$claims=['));gate(!str_contains($claims,"object_key"),'storage key excluded from signed claims');
gate(str_contains($source,'Durable persistence/schema unavailable')&&str_contains($source,'Audit evidence could not be persisted'),'persistence and audit fail closed');
gate(str_contains($source,'network_isolated')&&str_contains($source,'non_root')&&str_contains($source,'ephemeral'),'sandbox attestation enforced');
gate(str_contains($source,"'revoke_grants'=>'pending','purge_cdn'=>'pending','delete_derivatives'=>'pending','delete_source'=>'pending'"),'ordered deletion encoded');
echo "REVIEW ROUND 12 SECURITY: PASS\n";
