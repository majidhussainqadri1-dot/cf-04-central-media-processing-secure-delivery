<?php
/**
 * Plugin Name: CF-04 Sabri Central Media Processing and Secure Delivery
 * Description: Fail-closed media ingest, quarantine, processing, encrypted storage and secure delivery infrastructure for the Sabri Social Homeopathy Platform.
 * Version: 1.1.0-rc.2
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * Text Domain: sabri-central-media
 */
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('SCM_VERSION', '1.1.0-rc.2');
define('SCM_SCHEMA_VERSION', '1.3.1');
define('SCM_CONTRACT_VERSION', '1.3.1');
define('SCM_PLUGIN_FILE', __FILE__);
define('SCM_PLUGIN_DIR', __DIR__ . '/');
if (!defined('SCM_RUNTIME_ENABLED')) { define('SCM_RUNTIME_ENABLED', false); }

$scm_files = [
    'class-scm-error.php','class-scm-utils.php','class-scm-audit.php','class-scm-crypto.php',
    'class-scm-policy.php','class-scm-validator.php','class-scm-scanner-registry.php','class-scm-domain-registry.php',
    'class-scm-companion-domain-adapters.php','class-scm-integration-registry.php','class-scm-auth.php',
    'class-scm-db.php','class-scm-schema.php','class-scm-record-store.php','class-scm-idempotency.php','class-scm-rate-limiter.php',
    'class-scm-provider-registry.php','class-scm-object-store.php','class-scm-local-object-store.php','class-scm-part-store.php',
    'class-scm-upload-service.php','class-scm-workspace-upload-service.php','class-scm-processing-service.php',
    'class-scm-delivery-service.php','class-scm-download-manager-service.php','class-scm-transfer-service.php',
    'class-scm-retention-service.php','class-scm-deletion-service.php','class-scm-rest.php',
    'class-scm-activator.php','class-scm-plugin.php'
];
foreach ($scm_files as $scm_file) { require_once SCM_PLUGIN_DIR . 'includes/' . $scm_file; }
register_activation_hook(__FILE__, [Sabri\CentralMedia\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Sabri\CentralMedia\Activator::class, 'deactivate']);
Sabri\CentralMedia\Plugin::boot();
