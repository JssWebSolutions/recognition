<?php
/**
 * Plugin Uninstaller
 *
 * This file runs when the plugin is uninstalled via the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package Recognition
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Include the installer for database operations
require_once dirname(__FILE__) . '/includes/Installer/class-installer.php';

// Check if user wants to remove all data.
// The "Delete data on uninstall" preference is stored inside the
// `frl_settings` option array (key: `remove_data_on_uninstall`),
// not as its own top-level option. Reading the wrong key here
// silently always returned `false`, which meant data was never
// removed when the user had the box ticked.
$frl_settings = get_option('frl_settings', []);
if (!is_array($frl_settings)) {
    $frl_settings = [];
}
$remove_all = !empty($frl_settings['remove_data_on_uninstall']);

// Create installer instance and uninstall
$installer = new FRL_Installer();
$installer->uninstall($remove_all);

// Remove any transients
delete_transient('frl_activated');
delete_transient('frl_license_throttle_activate');
delete_transient('frl_license_throttle_validate');

// Clear scheduled hooks
wp_clear_scheduled_hook('frl_cleanup_expired_logs');
wp_clear_scheduled_hook('frl_daily_cleanup');
wp_clear_scheduled_hook('frl_license_daily_revalidate');

// Remove license module options
delete_option('frl_license_data');
delete_option('frl_license_status');
delete_option('frl_license_notice_dismissed');
delete_option('frl_license_last_check');
delete_option('frl_license_api_key');

// Flush rewrite rules
flush_rewrite_rules();
