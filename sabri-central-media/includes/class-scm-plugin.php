<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Plugin {
    private static bool $booted=false;
    public static function boot(): void { if(self::$booted)return;self::$booted=true; CompanionDomainAdapters::register(); ScannerRegistry::registerBuiltins(); ProviderRegistry::register('local-private',new LocalObjectStore()); IntegrationRegistry::register(); if(function_exists('add_action')){add_action('rest_api_init',[Rest::class,'register']);add_action('admin_notices',[self::class,'notice']);} }
    public static function notice(): void { if(!function_exists('current_user_can')||!current_user_can('manage_options'))return; if(!(defined('SCM_RUNTIME_ENABLED')&&SCM_RUNTIME_ENABLED===true)) echo '<div class="notice notice-warning"><p>'.esc_html__('CF-04 is installed in fail-closed mode. Runtime activation requires approved staging evidence, provider configuration, migration and rollback acceptance.','sabri-central-media').'</p></div>'; }
}
