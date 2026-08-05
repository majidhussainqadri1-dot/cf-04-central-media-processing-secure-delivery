<?php
declare(strict_types=1);
define('SCM_VERSION','1.1.0-rc.3');
define('SCM_SCHEMA_VERSION','1.3.1');
define('SCM_CONTRACT_VERSION','1.3.1');
define('SCM_RUNTIME_ENABLED',true);
define('SCM_HASH_KEY',str_repeat('h',32));
define('SCM_PRIVATE_ROOT',sys_get_temp_dir().'/scm-tests-'.getmypid());
define('SCM_MAX_IN_MEMORY_ASSEMBLY_BYTES',8388608);
$GLOBALS['scm_test_filters']=[];
if(!function_exists('add_filter')){ function add_filter(string $tag,callable $callback): void { $GLOBALS['scm_test_filters'][$tag][]=$callback; } }
if(!function_exists('apply_filters')){ function apply_filters(string $tag,mixed $value,mixed ...$args): mixed { foreach($GLOBALS['scm_test_filters'][$tag]??[] as $callback)$value=$callback($value,...$args);return $value; } }
if(!function_exists('get_current_user_id')){ function get_current_user_id(): int { return 11; } }
$base=dirname(__DIR__).'/sabri-central-media/includes/';
foreach([
    'class-scm-error.php','class-scm-utils.php','class-scm-audit.php','class-scm-crypto.php','class-scm-validator.php','class-scm-policy.php','class-scm-scanner-registry.php','class-scm-domain-registry.php','class-scm-companion-domain-adapters.php','class-scm-integration-registry.php','class-scm-auth.php','class-scm-db.php','class-scm-schema.php','class-scm-record-store.php','class-scm-idempotency.php','class-scm-rate-limiter.php','class-scm-provider-registry.php','class-scm-object-store.php','class-scm-local-object-store.php','class-scm-part-store.php','class-scm-upload-service.php','class-scm-workspace-upload-service.php','class-scm-processing-service.php','class-scm-delivery-service.php','class-scm-download-manager-service.php','class-scm-transfer-service.php','class-scm-retention-service.php','class-scm-deletion-service.php'
] as $file) require_once $base.$file;
function ok(bool $value,string $message): void { if(!$value){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n"; }
function err(callable $callback,string $code,string $message): void { try{$callback();}catch(Sabri\CentralMedia\Error $error){ok($error->errorCode===$code,$message);return;}catch(Throwable $error){fwrite(STDERR,"UNEXPECTED ".get_class($error).": {$error->getMessage()}\n");exit(1);}ok(false,$message); }
function policy(string $mediaClass='document',int $maxSize=1073741824): array { return Sabri\CentralMedia\Policy::validate([
    'policy_id'=>'p1','policy_version'=>1,'domain'=>'file17','purpose'=>'verified-user-transfer','privacy_class'=>'C3','media_class'=>$mediaClass,'max_size_bytes'=>$maxSize,'max_part_size_bytes'=>min(8388608,$maxSize),'allowed_mime_types'=>['application/pdf'],'required_scans'=>['hash','magic','mime','malware','archive','polyglot','decompression_bomb','metadata'],'max_upload_parts'=>10000,'max_archive_ratio'=>100,'delivery_modes'=>['token_endpoint']
],true); }
