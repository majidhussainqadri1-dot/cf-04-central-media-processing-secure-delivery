<?php
declare(strict_types=1);
namespace Sabri\CentralMedia;
final class Schema {
    public static function install(): void {
        global $wpdb; if(!isset($wpdb)) return; if(!function_exists('dbDelta') && defined('ABSPATH')) { $upgrade=ABSPATH.'wp-admin/includes/upgrade.php'; if(is_file($upgrade)) require_once $upgrade; } if(!function_exists('dbDelta')) return;
        $c=$wpdb->get_charset_collate();
        foreach([
            'assets'=>'id varchar(80) NOT NULL, owner_domain varchar(64) NOT NULL, owner_object varchar(191) NOT NULL, object_version bigint unsigned NOT NULL, status varchar(40) NOT NULL, privacy_class varchar(4) NOT NULL, manifest longtext NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), KEY owner (owner_domain,owner_object)',
            'uploads'=>'id varchar(80) NOT NULL, actor_id bigint unsigned NOT NULL, policy_hash char(64) NOT NULL, expected_size bigint unsigned NOT NULL, received_size bigint unsigned NOT NULL DEFAULT 0, status varchar(40) NOT NULL, expires_at datetime NOT NULL, payload longtext NULL, PRIMARY KEY (id), KEY actor_status (actor_id,status)',
            'grants'=>'id varchar(80) NOT NULL, asset_id varchar(80) NOT NULL, status varchar(32) NOT NULL, expires_at datetime NOT NULL, payload longtext NULL, PRIMARY KEY (id), KEY asset_status (asset_id,status)',
            'jobs'=>'id varchar(80) NOT NULL, asset_id varchar(80) NOT NULL, job_type varchar(64) NOT NULL, status varchar(32) NOT NULL, attempts int unsigned NOT NULL DEFAULT 0, payload longtext NULL, PRIMARY KEY (id), KEY status_type (status,job_type)',
            'audit'=>'id bigint unsigned NOT NULL AUTO_INCREMENT, event_key varchar(96) NOT NULL, actor_id bigint unsigned NOT NULL DEFAULT 0, payload longtext NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY event_time (event_key,created_at)',
            'records'=>'record_type varchar(48) NOT NULL, id varchar(96) NOT NULL, actor_id bigint unsigned NOT NULL DEFAULT 0, status varchar(40) NOT NULL, version bigint unsigned NOT NULL DEFAULT 1, expires_at datetime NULL, payload longtext NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (record_type,id), KEY type_actor_status (record_type,actor_id,status), KEY expiry (expires_at)' 
        ] as $name=>$body) dbDelta('CREATE TABLE '.Db::table($name).' ('.$body.') '.$c.';');
        if(function_exists('update_option')) update_option('scm_schema_version',defined('SCM_SCHEMA_VERSION')?SCM_SCHEMA_VERSION:'1.3.1',false);
    }
}
