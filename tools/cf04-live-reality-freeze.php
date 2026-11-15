<?php
/** Read-only CF-04 deployment evidence; run only as WP-CLI eval-file. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(!defined('ABSPATH')||!defined('WP_PLUGIN_DIR')||!function_exists('get_option')){
    fwrite(STDERR,"CF04_FREEZE_REQUIRES_WORDPRESS_CLI\n");return;
}
$cf04Option=static function(string $key): array {
    $missing='__CF04_FREEZE_OPTION_ABSENT__';
    $value=get_option($key,$missing);
    if($value===$missing)return ['present'=>false,'value'=>null];
    if(is_bool($value))return ['present'=>true,'value'=>$value];
    if(is_string($value)&&preg_match('/^[A-Za-z0-9._-]{1,96}$/D',$value))
        return ['present'=>true,'value'=>$value];
    return ['present'=>true,'value'=>'redacted_or_invalid'];
};
$plugin=rtrim((string)WP_PLUGIN_DIR,'/').'/sabri-central-media';
$entry=$plugin.'/sabri-central-media.php';
$deployment=['entrypoint_present'=>is_file($entry)&&!is_link($entry),
    'entry_sha256'=>null,'php_tree_sha256'=>null,'php_file_count'=>0,
    'checksum_scope'=>'entrypoint_and_includes_php_not_entire_package'];
if($deployment['entrypoint_present']){
    $paths=array_merge([$entry],glob($plugin.'/includes/*.php')?:[]);
    sort($paths,SORT_STRING);$state=hash_init('sha256');$valid=count($paths)<=100;
    foreach($paths as $path){
        if(!is_file($path)||is_link($path)||!is_readable($path)||
            filesize($path)===false||filesize($path)>10485760){$valid=false;break;}
        $digest=hash_file('sha256',$path);
        if(!is_string($digest)){$valid=false;break;}
        hash_update($state,substr($path,strlen($plugin)+1)."\0".$digest."\n");
        $deployment['php_file_count']++;
        if($path===$entry)$deployment['entry_sha256']=$digest;
    }
    if($valid)$deployment['php_tree_sha256']=hash_final($state);
    else $deployment['checksum_failure']='incomplete_or_untrusted_tree';
}
$plugins=(array)get_option('active_plugins',[]);
if(function_exists('get_site_option')){
    $network=get_site_option('active_sitewide_plugins',[]);
    if(is_array($network))$plugins=array_merge($plugins,array_keys($network));
}
$plugins=array_values(array_unique(array_filter($plugins,static fn($v)=>
    is_string($v)&&preg_match('/^[A-Za-z0-9._\/-]{1,200}$/D',$v)===1)));
sort($plugins,SORT_STRING);$plugins=array_slice($plugins,0,200);
$database=['status'=>'not_accessible','tables'=>[]];
global $wpdb,$wp_version;
if(isset($wpdb)&&is_object($wpdb)&&isset($wpdb->prefix)&&
    preg_match('/^[A-Za-z0-9_]{1,96}$/D',(string)$wpdb->prefix)&&
    method_exists($wpdb,'prepare')&&method_exists($wpdb,'get_var')&&
    method_exists($wpdb,'get_results')){
    $database['status']='read_only_probe_executed';
    foreach(['records','audit'] as $kind){
        $table=(string)$wpdb->prefix.'scm_'.$kind;
        $item=['present'=>false,'columns'=>[],'row_count'=>null,'error'=>false];
        try{
            $found=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table));
            if(!empty($wpdb->last_error))throw new RuntimeException('db_error');
            if((string)$found===$table){
                $item['present']=true;
                $columns=$wpdb->get_results('SHOW COLUMNS FROM '.$table,'ARRAY_A');
                if(!is_array($columns)||!empty($wpdb->last_error))
                    throw new RuntimeException('db_error');
                foreach($columns as $col){
                    $field=is_array($col)?($col['Field']??null):null;
                    if(is_string($field)&&preg_match('/^[A-Za-z0-9_]{1,64}$/D',$field))
                        $item['columns'][]=$field;
                }
                sort($item['columns'],SORT_STRING);
                $count=$wpdb->get_var('SELECT COUNT(*) FROM '.$table);
                if(!empty($wpdb->last_error)||!is_scalar($count)||
                    !preg_match('/^[0-9]+$/D',(string)$count))
                    throw new RuntimeException('db_count_error');
                $item['row_count']=(string)$count;
            }
        }catch(Throwable){$item['error']=true;$database['status']='partial_probe_error';}
        $database['tables']['scm_'.$kind]=$item;
    }
}
$report=[
    'tool'=>'cf04_v123_live_reality_freeze_readonly',
    'observed_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    'environment'=>['php'=>PHP_VERSION,
        'wordpress'=>isset($wp_version)?(string)$wp_version:'unverified',
        'active_plugin_ids'=>$plugins],
    'loaded_plugin_version_constant'=>defined('SCM_VERSION')?(string)SCM_VERSION:'not_loaded',
    'deployed_schema_constant'=>defined('SCM_SCHEMA_VERSION')?(string)SCM_SCHEMA_VERSION:'not_loaded',
    'deployed_contract_constant'=>defined('SCM_CONTRACT_VERSION')?(string)SCM_CONTRACT_VERSION:'not_loaded',
    'deployment'=>$deployment,
    'wp_options'=>[
        'scm_schema_version'=>$cf04Option('scm_schema_version'),
        'scm_installed_version'=>$cf04Option('scm_installed_version'),
        'scm_contract_version'=>$cf04Option('scm_contract_version'),
        'scm_runtime_activated'=>$cf04Option('scm_runtime_activated'),
        'activation_lock_present'=>get_option('scm_activation_lock',false)!==false],
    'database'=>$database,
    'migration_state'=>'unverified_requires_migration_logs_and_runtime_checks',
    'runtime_logs'=>'not_collected',
    'github_deployment_parity'=>'unverified_requires_exact_package_and_manifest',
    'live_verified'=>false,
    'writes_performed'=>false
];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
