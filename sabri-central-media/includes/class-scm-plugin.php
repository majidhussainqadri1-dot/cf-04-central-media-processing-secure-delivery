<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;

final class Activator {
    private const CAPS=['manage_sabri_media','operate_sabri_media','audit_sabri_media','media_manage_providers','media_review_safety_signals','media_hold','media_reprocess','media_download','media_transfer'];
    public static function activate(): void {
        $lockRequest=self::claimActivationLock();
        try{
            Schema::install();if(!Schema::ready()&&!(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true))throw new Error('schema_install_failed','CF-04 schema installation failed.',500);
            if(function_exists('get_role')){$role=get_role('administrator');if($role)foreach(self::CAPS as $cap)$role->add_cap($cap);}
            if(function_exists('update_option')){update_option('scm_runtime_activated',false,false);update_option('scm_installed_version',defined('SCM_VERSION')?SCM_VERSION:'unknown',false);update_option('scm_contract_version',defined('SCM_CONTRACT_VERSION')?SCM_CONTRACT_VERSION:'unknown',false);}
            self::schedule();if(function_exists('flush_rewrite_rules'))flush_rewrite_rules(false);Audit::record('plugin_activated',['runtime_enabled'=>false,'version'=>defined('SCM_VERSION')?SCM_VERSION:'unknown']);
        }finally{self::releaseActivationLock($lockRequest);}
    }
    private static function claimActivationLock(): string {
        $request=Utils::id('act');if(!function_exists('add_option')||!function_exists('get_option'))return $request;$row=['at'=>Utils::now(),'request'=>$request];if(add_option('scm_activation_lock',$row,'','no'))return $request;$current=get_option('scm_activation_lock');if(!is_array($current)||(int)($current['at']??0)>Utils::now()-300)throw new Error('activation_in_progress','CF-04 activation is already in progress.',409);
        global $wpdb;if(!isset($wpdb)||!is_object($wpdb)||!isset($wpdb->options)||!method_exists($wpdb,'query')||!method_exists($wpdb,'prepare'))throw new Error('activation_lock_recovery_unavailable','Stale activation lock cannot be recovered atomically.',503);$serialized=function_exists('maybe_serialize')?maybe_serialize($current):serialize($current);$deleted=$wpdb->query($wpdb->prepare('DELETE FROM '.$wpdb->options.' WHERE option_name=%s AND option_value=%s','scm_activation_lock',$serialized));if($deleted!==1||!add_option('scm_activation_lock',$row,'','no'))throw new Error('activation_in_progress','Another activation won stale-lock recovery.',409);return $request;
    }
    private static function releaseActivationLock(string $request): void {if(!function_exists('get_option')||!function_exists('delete_option'))return;$current=get_option('scm_activation_lock');if(is_array($current)&&hash_equals((string)($current['request']??''),$request))delete_option('scm_activation_lock');}
    public static function deactivate(): void {try{Audit::record('plugin_deactivated',['runtime_enabled'=>RuntimeGuard::enabled()]);}catch(\Throwable){}finally{self::unschedule();if(function_exists('flush_rewrite_rules'))flush_rewrite_rules(false);}}
    private static function schedule(): void {
        if(!function_exists('wp_next_scheduled')||!function_exists('wp_schedule_event'))return;
        $available=function_exists('wp_get_schedules')?(array)wp_get_schedules():[];$minute=isset($available['scm_minute'])?'scm_minute':'hourly';
        foreach(['scm_process_jobs'=>$minute,'scm_retention_tick'=>'hourly','scm_integrity_tick'=>'daily','scm_reconcile_deletions'=>'hourly'] as $hook=>$schedule)if(!wp_next_scheduled($hook)){if(wp_schedule_event(time()+60,$schedule,$hook)===false)throw new Error('cron_schedule_failed','CF-04 scheduled task could not be registered.',500,['hook'=>$hook,'schedule'=>$schedule]);}
    }
    private static function unschedule(): void {if(!function_exists('wp_next_scheduled')||!function_exists('wp_unschedule_event'))return;foreach(['scm_process_jobs','scm_retention_tick','scm_integrity_tick','scm_reconcile_deletions'] as $hook){$guard=0;while(($ts=wp_next_scheduled($hook))&&$guard++<100)wp_unschedule_event($ts,$hook);}}
}

final class Admin {
    public static function menu(): void {if(function_exists('add_management_page'))add_management_page(__('CF-04 Media Infrastructure','sabri-central-media'),__('CF-04 Media','sabri-central-media'),'manage_sabri_media','scm-status',[self::class,'page']);}
    public static function page(): void {if(!function_exists('current_user_can')||!current_user_can('manage_sabri_media'))return;$health=Observability::health();echo '<div class="wrap" dir="auto"><h1>'.esc_html__('CF-04 Central Media Processing','sabri-central-media').'</h1><p>'.esc_html__('Runtime remains disabled until approved staging, providers, migration, restore and rollback evidence is recorded.','sabri-central-media').'</p><table class="widefat striped" aria-label="'.esc_attr__('CF-04 health status','sabri-central-media').'"><tbody>';foreach(['status','runtime_enabled','schema_ready','queue_depth','oldest_queue_age_seconds','dead_letters','pending_deletions','audit_chain'] as $key){echo '<tr><th scope="row">'.esc_html($key).'</th><td><code>'.esc_html(is_bool($health[$key]??null)?(($health[$key]??false)?'true':'false'):(string)($health[$key]??'')).'</code></td></tr>';}echo '</tbody></table></div>';}
    public static function notice(): void {if(!function_exists('current_user_can')||!current_user_can('manage_options'))return;if(!RuntimeGuard::enabled())echo '<div class="notice notice-warning"><p>'.esc_html__('CF-04 is installed in fail-closed mode. Production activation requires approved staging/provider/migration/restore/rollback evidence.','sabri-central-media').'</p></div>';}
}

final class Cli {
    public static function register(): void {if(!defined('WP_CLI')||!WP_CLI||!class_exists('WP_CLI'))return;\WP_CLI::add_command('scm health',fn()=>\WP_CLI::log(Utils::json(Observability::health())));\WP_CLI::add_command('scm reconcile-deletions',fn()=>\WP_CLI::success(Utils::json(DeletionService::reconcile())));\WP_CLI::add_command('scm retention',fn()=>\WP_CLI::success(Utils::json(RetentionService::run())));\WP_CLI::add_command('scm recover-jobs',fn()=>\WP_CLI::success((string)JobService::recoverOrphans()));}
}

final class Plugin {
    private static bool $booted=false;private static bool $integrated=false;
    public static function boot(): void {
        if(self::$booted)return;self::$booted=true;
        if(function_exists('add_filter'))add_filter('cron_schedules',[self::class,'cronSchedules']);
        if(function_exists('add_action')){
            add_action('plugins_loaded',[self::class,'integrate'],20);add_action('rest_api_init',[Rest::class,'register']);add_action('init',[StreamingEndpoint::class,'register']);add_action('admin_menu',[Admin::class,'menu']);add_action('admin_notices',[Admin::class,'notice']);add_action('scm_process_jobs',[self::class,'cronJobs']);add_action('scm_retention_tick',[self::class,'cronRetention']);add_action('scm_integrity_tick',[self::class,'cronIntegrity']);add_action('scm_reconcile_deletions',[self::class,'cronDeletions']);add_action('init',[Cli::class,'register']);
            if(function_exists('did_action')&&did_action('plugins_loaded'))self::integrate();
        }else self::integrate();
    }
    public static function cronSchedules(array $schedules): array {$schedules['scm_minute']=['interval'=>60,'display'=>__('Every minute (CF-04)','sabri-central-media')];return $schedules;}
    public static function integrate(): void {
        if(self::$integrated)return;self::$integrated=true;CompanionDomainAdapters::registerWordPressFilters();ScannerRegistry::registerWordPressAdapters();FutureAdapterRegistry::registerWordPressAdapters();
        if(function_exists('apply_filters')){$provider=apply_filters('scm_object_provider',null);if($provider instanceof ObjectStore)ProviderRegistry::register((string)apply_filters('scm_object_provider_id','approved-private'),$provider,(array)apply_filters('scm_object_provider_metadata',[]));$cdn=apply_filters('scm_cdn_adapter',null);if($cdn instanceof CdnAdapter)CdnRegistry::register((string)apply_filters('scm_cdn_adapter_id','approved-cdn'),$cdn);}
        if(defined('SCM_TEST_MODE')&&SCM_TEST_MODE===true&&ProviderRegistry::ids()===[])ProviderRegistry::register('local-private',new LocalObjectStore(),['test'=>true,'region'=>'test']);
    }
    public static function cronJobs(): void {
        if(!RuntimeGuard::enabled())return;
        // Never enter scheduled workflows before all activation gates pass.
        try{RuntimeGuard::requireReady(['streaming']);}
        catch(\Throwable $e){self::cronAlert('runtime_activation_not_ready',$e);return;}
        $token=self::acquire('cron-jobs',300);
        if($token===null)return;
        try{
            JobService::recoverOrphans();
            $cursor=self::maintenanceCursor('processing');
            $after=(string)($cursor['last_id']??'');
            $selected=[];$scanned=0;
            // Progress is keyed by immutable record ID, never the mutable
            // updated_at ordering. Work cannot starve past an offset ceiling.
            while(count($selected)<5&&$scanned<2000){
                $pageLimit=min(200,2000-$scanned);
                $page=RecordStore::scan('asset',$pageLimit,$after);
                if($page===[]){$after='';break;}
                foreach($page as $asset){
                    $after=(string)$asset['id'];$scanned++;
                    if(($asset['processing_status']??'')==='queued'){
                        $selected[]=$after;
                        if(count($selected)>=5)break;
                    }
                }
                if(count($page)<$pageLimit&&count($selected)<5){$after='';break;}
            }
            foreach($selected as $assetId){
                try{ProcessingService::execute($assetId,'wp-cron-'.substr($token,0,12));}
                catch(\Throwable $e){Observability::alert('critical','processing_job_failed',
                    ['asset_id'=>$assetId,'exception'=>get_class($e)]);}
            }
            self::saveMaintenanceCursor('processing',$cursor,$after,$scanned,count($selected));
        }finally{self::release('cron-jobs',$token);}
    }
    public static function cronRetention(): void {
        if(!RuntimeGuard::enabled())return;
        // Never enter scheduled workflows before all activation gates pass.
        try{RuntimeGuard::requireReady(['streaming']);}
        catch(\Throwable $e){self::cronAlert('runtime_activation_not_ready',$e);return;}
        $token=self::acquire('retention',1800);if($token===null)return;
        try {
            $failure=null;
            // A broken rights/retention reconciliation must not permanently
            // prevent expiry of unrelated abandoned and quota-reserving uploads.
            foreach([
                'rights_reconciliation_failed'=>static fn()=>RightsRevocationService::reconcileExpired(),
                'retention_reconciliation_failed'=>static fn()=>RetentionService::run(0,false),
                'upload_expiry_reconciliation_failed'=>static fn()=>UploadService::cleanupExpired(),
            ] as $code=>$operation){
                try{$operation();}
                catch(\Throwable $e){self::cronAlert($code,$e);$failure??=$e;}
            }
            if($failure!==null)throw $failure;
        }finally{self::release('retention',$token);}
    }
    public static function cronDeletions(): void {
        if(!RuntimeGuard::enabled())return;
        // Never enter scheduled workflows before all activation gates pass.
        try{RuntimeGuard::requireReady(['streaming']);}
        catch(\Throwable $e){self::cronAlert('runtime_activation_not_ready',$e);return;}
        $token=self::acquire('deletions',1800);
        if($token===null)return;
        try{
            $failure=null;
            // Each independent security/lifecycle reconciler must run even if
            // another record family has a corrupted cursor or a provider fault.
            foreach([
                'deletion_reconciliation_failed'=>static fn()=>DeletionService::reconcile(),
                'revocation_dispatch_reconciliation_failed'=>static fn()=>RevocationDispatchService::reconcile(),
                'cdn_purge_reconciliation_failed'=>static fn()=>DeliveryService::reconcilePublicPurges(),
                'rotation_cleanup_reconciliation_failed'=>static fn()=>KeyRotationService::reconcileDeferredCleanup(),
            ] as $code=>$operation){
                try{$operation();}
                catch(\Throwable $e){self::cronAlert($code,$e);$failure??=$e;}
            }
            if($failure!==null)throw $failure;
        }finally{self::release('deletions',$token);}
    }
    public static function cronIntegrity(): void {
        if(!RuntimeGuard::enabled())return;
        // Never enter scheduled workflows before all activation gates pass.
        try{RuntimeGuard::requireReady(['streaming']);}
        catch(\Throwable $e){self::cronAlert('runtime_activation_not_ready',$e);return;}
        $token=self::acquire('integrity',7200);
        if($token===null)return;
        try{
            $cursor=self::maintenanceCursor('integrity');
            // Legacy offset-only evidence restarts from the beginning once.
            $after=(string)($cursor['last_id']??'');
            $selected=[];$scanned=0;
            while(count($selected)<20&&$scanned<2000){
                $pageLimit=min(100,2000-$scanned);
                $page=RecordStore::scan('asset',$pageLimit,$after);
                if($page===[]){$after='';break;}
                foreach($page as $asset){
                    $after=(string)$asset['id'];$scanned++;
                    if(($asset['status']??'')==='ready'){
                        $selected[]=$after;
                        if(count($selected)>=20)break;
                    }
                }
                if(count($page)<$pageLimit&&count($selected)<20){$after='';break;}
            }
            foreach($selected as $assetId){
                try{IntegrityService::sample($assetId);}
                catch(\Throwable $e){Observability::alert('critical','integrity_check_failed',
                    ['asset_id'=>$assetId,'exception'=>get_class($e)]);}
            }
            self::saveMaintenanceCursor('integrity',$cursor,$after,$scanned,count($selected));
        }finally{self::release('integrity',$token);}
    }
    private static function maintenanceCursor(string $name): ?array {
        $cursor=RecordStore::get('cron_cursor',$name);
        if($cursor===null)return null;
        $last=$cursor['last_id']??null;
        $legacy=$name==='integrity'&&!array_key_exists('last_id',$cursor)
            &&is_int($cursor['offset']??null)&&$cursor['offset']>=0;
        if(($cursor['record_type']??null)!=='cron_cursor'
            ||($cursor['id']??null)!==$name
            ||($cursor['actor_id']??null)!==0
            ||($cursor['status']??null)!=='active'
            ||!is_int($cursor['version']??null)||$cursor['version']<1
            ||(!$legacy&&(!is_string($last)||strlen($last)>96
                ||Utils::text($last,96)!==$last)))
            throw new Error('maintenance_cursor_invalid','Stored maintenance cursor is invalid.',500,
                ['name'=>$name]);
        return $cursor;
    }
    private static function saveMaintenanceCursor(string $name,?array $prior,string $last,int $scanned,int $selected): void {
        RecordStore::put('cron_cursor',$name,[
            'actor_id'=>0,'status'=>'active','name'=>$name,'last_id'=>$last,
            'last_scanned'=>$scanned,'last_selected'=>$selected,'updated_at'=>Utils::now()
        ],$prior?(int)$prior['version']:0);
    }
    private static function acquire(string $name,int $ttl): ?string {$id=Utils::key($name,48);$now=Utils::now();$current=RecordStore::get('runtime_lock',$id);if($current&&(int)($current['expires_at']??0)>$now)return null;$token=Utils::id('lock');try{RecordStore::put('runtime_lock',$id,['actor_id'=>0,'status'=>'held','token_hash'=>hash('sha256',$token),'expires_at'=>$now+$ttl,'created_at'=>$now],$current?(int)$current['version']:0);return $token;}catch(Error $e){if($e->errorCode==='record_version_conflict')return null;self::cronAlert('runtime_lock_acquire_failed',$e);throw $e;}}
    private static function release(string $name,string $token): void {$id=Utils::key($name,48);$current=RecordStore::get('runtime_lock',$id);if(!$current||!hash_equals((string)($current['token_hash']??''),hash('sha256',$token)))return;try{$current['status']='released';$current['expires_at']=Utils::now();RecordStore::put('runtime_lock',$id,$current,(int)$current['version']);}catch(\Throwable $e){self::cronAlert('runtime_lock_release_failed',$e);}}
    private static function cronAlert(string $code,\Throwable $e): void {try{Observability::alert('critical',$code,['exception'=>get_class($e)]);}catch(\Throwable){}}
}
