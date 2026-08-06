<?php
/**
 * Plugin Name: CF-04 Sabri Central Media Processing and Secure Delivery
 * Description: Fail-closed, purpose-bound media ingest, quarantine, validation, processing, encrypted storage, secure delivery, retention, deletion and provider operations for the Sabri Social Homeopathy Platform.
 * Version: 1.2.0-rc.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * Text Domain: sabri-central-media
 */
declare(strict_types=1);
if(!defined('ABSPATH'))exit;
define('SCM_VERSION','1.2.0-rc.2');
define('SCM_SCHEMA_VERSION','1.4.0');
define('SCM_CONTRACT_VERSION','1.4.0');
define('SCM_PLUGIN_FILE',__FILE__);
define('SCM_PLUGIN_DIR',__DIR__.'/');
if(!defined('SCM_RUNTIME_ENABLED'))define('SCM_RUNTIME_ENABLED',false);
foreach(['class-scm-core.php','class-scm-persistence.php','class-scm-storage.php','class-scm-contracts.php','class-scm-upload.php','class-scm-validation.php','class-scm-processing.php','class-scm-delivery.php','class-scm-transfer.php','class-scm-lifecycle.php','class-scm-operations.php','class-scm-rest.php','class-scm-plugin.php'] as $file)require_once SCM_PLUGIN_DIR.'includes/'.$file;
register_activation_hook(__FILE__,[Sabri\CentralMedia\Activator::class,'activate']);
register_deactivation_hook(__FILE__,[Sabri\CentralMedia\Activator::class,'deactivate']);
Sabri\CentralMedia\Plugin::boot();
