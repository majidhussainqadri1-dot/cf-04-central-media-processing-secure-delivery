<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Activator {
    public static function activate(): void { Schema::install(); if(function_exists('get_role')){ $r=get_role('administrator'); if($r) foreach(['manage_sabri_media','operate_sabri_media','audit_sabri_media','media_manage_providers','media_review_safety_signals','media_hold','media_reprocess','media_download','media_transfer'] as $c)$r->add_cap($c); } if(function_exists('update_option')) update_option('scm_runtime_activated',false,false); Audit::record('plugin_activated',['runtime_enabled'=>false]); }
    public static function deactivate(): void { Audit::record('plugin_deactivated'); }
}
