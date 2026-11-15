<?php
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('WP_PLUGIN_DIR', dirname(__DIR__));
define('SCM_VERSION', '1.3.0-rc.1');
define('SCM_SCHEMA_VERSION', '1.5.0');
define('SCM_CONTRACT_VERSION', '1.5.0');
$GLOBALS['wp_version']='6.9-test';$GLOBALS['writes']=0;
function get_option(string $key,mixed $default=false): mixed {
    $options=[
        'active_plugins'=>['sabri-central-media/sabri-central-media.php'],
        'scm_schema_version'=>'1.5.0',
        'scm_installed_version'=>'1.3.0-rc.1',
        'scm_contract_version'=>'1.5.0',
        'scm_runtime_activated'=>false,
        'scm_activation_lock'=>['request'=>'PRIVATE_FREEZE_TOKEN_294']];
    return array_key_exists($key,$options)?$options[$key]:$default;
}
final class R294Db {
    public string $prefix='wp_';public string $last_error='';
    public function prepare(string $query,mixed $arg): string {
        return str_replace('%s',"'".$arg."'",$query);
    }
    public function get_var(string $sql): mixed {
        if(str_contains($sql,'SHOW TABLES LIKE'))
            return str_contains($sql,'records')?'wp_scm_records':'wp_scm_audit';
        if(str_contains($sql,'COUNT(*)'))return str_contains($sql,'records')?'25':'4';
        return null;
    }
    public function get_results(string $sql,string $format): array {
        return str_contains($sql,'records')?
            [['Field'=>'record_type'],['Field'=>'id'],['Field'=>'payload']]:
            [['Field'=>'id'],['Field'=>'event_hash']];
    }
}
$GLOBALS['wpdb']=new R294Db();
ob_start();require dirname(__DIR__).'/tools/cf04-live-reality-freeze.php';
$json=(string)ob_get_clean();
$check=static function(bool $pass,string $message):void {
    if(!$pass){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
};
$data=json_decode($json,true,32,JSON_THROW_ON_ERROR);
$check(!str_contains($json,'PRIVATE_FREEZE_TOKEN_294')&&
       !str_contains($json,WP_PLUGIN_DIR.'/'),
       'R294 private activation token and absolute server paths never exposed');
$check($data['deployment']['entrypoint_present']===true&&
       $data['deployment']['php_file_count']>=16&&
       preg_match('/^[a-f0-9]{64}$/D',(string)$data['deployment']['php_tree_sha256'])===1,
       'R294 actual plugin source fingerprint bounded without ZIP parity claim');
$check($data['database']['tables']['scm_records']['row_count']==='25'&&
       in_array('payload',$data['database']['tables']['scm_records']['columns'],true)&&
       $data['wp_options']['scm_runtime_activated']['present']===true&&
       $data['wp_options']['scm_runtime_activated']['value']===false,
       'R294 schema/table counters and false activation option reported accurately');
$check($data['loaded_plugin_version_constant']==='1.3.0-rc.1'&&
       !array_key_exists('repository_source_version',$data),
       'R297 deployed constant is never mislabeled as GitHub source repository state');
$check($data['migration_state']==='unverified_requires_migration_logs_and_runtime_checks'&&
       $data['github_deployment_parity']==='unverified_requires_exact_package_and_manifest'&&
       $data['live_verified']===false&&$data['writes_performed']===false,
       'R294 evidence script cannot claim live, migration or deployment parity success');
echo "REVIEW ROUND 294 LIVE REALITY FREEZE READ-ONLY: PASS\n";
