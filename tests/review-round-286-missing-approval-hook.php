<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
// Isolated non-test CLI simulation: the WordPress filter is intentionally absent.
// The gate must deny activation rather than silently treating missing hooks as approval.
\define('SCM_RUNTIME_ENABLED',true);
if(\defined('SCM_TEST_MODE') && SCM_TEST_MODE===true){
    fwrite(STDERR,"ROUND 286 FAIL: runtime bypass flag must be absent\n");exit(1);
}
if(\function_exists('apply_filters')){
    fwrite(STDERR,"ROUND 286 FAIL: approval filter must be absent in this process\n");exit(1);
}
final class Error extends \RuntimeException {
    public function __construct(public string $errorCode,string $message='',int $http=500,array $context=[]){
        parent::__construct($message,$http);
    }
}
final class Utils {
    public static function key(string $value,int $max=64): string {return substr($value,0,$max);}
    public static function text(string $value,int $max=128): string {return substr($value,0,$max);}
}
final class RecordStore {public static function requirePersistent(): void {}}
final class Keyring {public static function assertReady(): void {}}
final class ProviderRegistry {
    public static function store(): object {return new \stdClass();}
    public static function assertCapability(string $capability): void {}
}
require_once dirname(__DIR__).'/sabri-central-media/includes/class-scm-contracts.php';
foreach(['file00','file17'] as $owner){
    DomainRegistry::register($owner,'1.0.0',['check'=>static fn(array $context): array=>['allowed'=>true]]);
}
$denied=false;
try{RuntimeGuard::requireReady();}
catch(Error $e){$denied=$e->errorCode==='activation_evidence_missing';}
if(!$denied){fwrite(STDERR,"ROUND 286 FAIL: missing approval filter did not deny runtime\n");exit(1);}
echo "REVIEW ROUND 286 MISSING APPROVAL FILTER FAIL-CLOSED: PASS\n";
