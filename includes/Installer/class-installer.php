<?php
/**
 * Plugin installer
 *
 * @package Face_Recognition_Login
 * @subpackage Installer
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class intentionally creates and drops the plugin's custom tables. Table names are derived from $wpdb->prefix and the file is only loaded on install/uninstall activation hooks.
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Same justification as above.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- Installer is the only place schema changes are valid (per WordPress coding standards).
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL statements (CREATE TABLE, DROP TABLE) and the schema constants are hardcoded literals; no user input is interpolated.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Installer
 *
 * Handles plugin installation and database setup
 *
 * @since 1.0.0
 */
class FRL_Installer {

    /**
     * Plugin version option name
     *
     * @since 1.0.0
     * @var string
     */
    private $version_option = 'frl_version';

    /**
     * Run installation
     *
     * @since 1.0.0
     */
    public function install() {
        $this->create_tables();
        $this->set_default_options();
        $this->set_plugin_version();
        $this->maybe_flush_rewrite_rules();
    }

    /**
     * Create database tables
     *
     * @since 1.0.0
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Face descriptors table
        $faces_table = $wpdb->prefix . 'face_login';

        // Check if table already exists. We avoid wpdb::prepare() here
        // because WordPress 6.2+ uses a strict regex that strips the
        // space in "SHOW TABLES", mangling it to "SHOWTABLES". The
        // table name is a concatenation of $wpdb->prefix and a
        // hardcoded literal (not user input), so it is safe to splice
        // it directly.
        $all_tables  = $wpdb->get_col( 'SHOW TABLES' );
        $faces_exists = in_array( $faces_table, (array) $all_tables, true );
        if ( ! $faces_exists ) {
            $faces_sql = "CREATE TABLE $faces_table (
                id bigint(20) unsigned NOT NULL auto_increment,
                user_id bigint(20) unsigned NOT NULL,
                descriptor longtext NOT NULL,
                device_name varchar(100) DEFAULT 'Default',
                created_at datetime NOT NULL default CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
                last_used datetime DEFAULT NULL,
                status tinyint(1) NOT NULL default '1',
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY status (status),
                KEY last_used (last_used)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($faces_sql);
        }

        // Authentication logs table
        $logs_table = $wpdb->prefix . 'face_login_logs';

        // Check if table already exists (see note above re:
        // WordPress 6.2+ prepare() mangles "SHOW TABLES" to
        // "SHOWTABLES").
        $logs_exists = in_array( $logs_table, (array) $all_tables, true );
        if ( ! $logs_exists ) {
            $logs_sql = "CREATE TABLE $logs_table (
                id bigint(20) unsigned NOT NULL auto_increment,
                user_id bigint(20) unsigned DEFAULT NULL,
                username varchar(100) DEFAULT NULL,
                result varchar(20) NOT NULL,
                confidence float DEFAULT NULL,
                response_time float DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text,
                created_at datetime NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY result (result),
                KEY ip_address (ip_address),
                KEY created_at (created_at)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($logs_sql);
        }

        // Store installed tables
        update_option('frl_installed_tables', [
            'faces' => $faces_table,
            'logs' => $logs_table,
        ]);
    }

    /**
     * Set default plugin options
     *
     * Only FREE / non-premium keys are seeded here. Premium keys
     * (rate_limit_enabled, max_failed_attempts, lockout_minutes,
     * encrypt_descriptors, log_authentications, auto_delete_logs,
     * auto_delete_logs_days, max_faces_per_user) are intentionally
     * left out:
     *
     *   1. They belong to the premium plan and must be unavailable
     *      without an active license.
     *   2. Seeding them would cause the registered sanitize_callback
     *      (FRL_Premium_Gate::sanitize_premium_settings) to fire
     *      and write a "stripped premium-only setting keys [...]"
     *      line to the error log on every fresh install - which is
     *      noisy, misleading, and the first thing a developer sees
     *      when activating the plugin.
     *
     * All readers of premium keys already pass a sane default to
     * FRL_Options::get() (e.g. true for rate_limit_enabled, 5 for
     * max_failed_attempts, etc.) so leaving the keys absent is
     * functionally equivalent to seeding them with the previously
     * hard-coded values once the license is inactive.
     *
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_options = [
            'enabled'                   => true,
            'match_threshold'           => 0.45,
            'liveness_detection'        => false,
            'require_https'             => true,
            'require_password_fallback' => true,
            'button_text'               => 'Login with Face',
        ];

        // Only set defaults if not already set
        $existing = FRL_Options::all();
        if (empty($existing)) {
            // Silence the premium-strip log while seeding the
            // initial defaults. Without this guard the sanitiser
            // would still log a "stripped premium-only setting
            // keys" line on first activation because the
            // registered sanitize_callback runs on every
            // update_option() - even when the array we are
            // saving is already free of premium keys.
            if (class_exists('FRL_Premium_Gate')) {
                FRL_Premium_Gate::silence_premium_log(true);
            }
            try {
                update_option('frl_settings', $default_options);
            } finally {
                if (class_exists('FRL_Premium_Gate')) {
                    FRL_Premium_Gate::silence_premium_log(false);
                }
            }
        }
    }

    /**
     * Set plugin version
     *
     * @since 1.0.0
     */
    private function set_plugin_version() {
        update_option($this->version_option, FRL_PLUGIN_VERSION);
    }

    /**
     * Flush rewrite rules if needed
     *
     * @since 1.0.0
     */
    private function maybe_flush_rewrite_rules() {
        if (get_option('frl_needs_flush_rewrite', false)) {
            flush_rewrite_rules();
            delete_option('frl_needs_flush_rewrite');
        }
    }

    /**
     * Check if tables exist
     *
     * @since 1.0.0
     * @return bool
     */
    public function tables_exist() {
        global $wpdb;

        $faces_table = $wpdb->prefix . 'face_login';
        $logs_table = $wpdb->prefix . 'face_login_logs';

        // NOTE: WordPress 6.2+ wpdb::prepare() uses a strict regex that
        // strips the space in "SHOW TABLES", mangling it to "SHOWTABLES"
        // and breaking the query. The table name is a concatenation of
        // $wpdb->prefix and a hardcoded literal (not user input), so we
        // bypass prepare() and use the raw SHOW TABLES statement via
        // get_col() instead.
        $all_tables   = $wpdb->get_col( 'SHOW TABLES' );
        $faces_exists = in_array( $faces_table, (array) $all_tables, true );
        $logs_exists  = in_array( $logs_table,  (array) $all_tables, true );

        return $faces_exists && $logs_exists;
    }

    /**
     * Get current version
     *
     * @since 1.0.0
     * @return string|null
     */
    public function get_version() {
        return get_option($this->version_option, null);
    }

    /**
     * Check if upgrade is needed
     *
     * @since 1.0.0
     * @return bool
     */
    public function needs_upgrade() {
        $current = $this->get_version();
        return version_compare($current, FRL_PLUGIN_VERSION, '<');
    }

    /**
     * Run upgrade procedures
     *
     * @since 1.0.0
     */
    public function upgrade() {
        $current = $this->get_version();

        // Run version-specific upgrades
        if (version_compare($current, '1.0.0', '<')) {
            // Initial version - ensure tables exist
            $this->create_tables();
        }

        // Update to new version
        $this->set_plugin_version();
    }

    /**
     * Uninstall plugin data
     *
     * @since 1.0.0
     * @param bool $remove_all
     */
    public function uninstall($remove_all = true) {
        global $wpdb;

        if ($remove_all) {
            // Drop tables
            $faces_table = $wpdb->prefix . 'face_login';
            $logs_table = $wpdb->prefix . 'face_login_logs';

            $wpdb->query("DROP TABLE IF EXISTS {$faces_table}");
            $wpdb->query("DROP TABLE IF EXISTS {$logs_table}");

            // Remove options
            delete_option('frl_settings');
            delete_option('frl_version');
            delete_option('frl_encryption_key');
            delete_option('frl_installed_tables');
            delete_option('frl_needs_flush_rewrite');
        }
    }
}
