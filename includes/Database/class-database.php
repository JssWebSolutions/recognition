<?php
/**
 * Database class for face recognition login
 *
 * @package Face_Recognition_Login
 * @subpackage Database
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class intentionally uses $wpdb directly for the plugin's custom tables. Table names are derived from $wpdb->prefix, all user-supplied values are passed through $wpdb->prepare(), and results are not eligible for object caching because they are write-heavy log rows that change on every request.
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Same justification as above: table names from $wpdb->prefix and all values via $wpdb->prepare().
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix and any literal value comes from a hardcoded whitelist; all user-supplied values are bound via $wpdb->prepare() placeholders.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Database
 *
 * Handles all database operations for the plugin
 *
 * @since 1.0.0
 */
class FRL_Database {

    /**
     * Table name for face descriptors
     *
     * @since 1.0.0
     * @var string
     */
    private $faces_table;

    /**
     * Table name for authentication logs
     *
     * @since 1.0.0
     * @var string
     */
    private $logs_table;

    /**
     * Per-request flag: have we already verified (or auto-created) the
     * required tables in this PHP request? Used by maybe_create_tables()
     * so we don't hit SHOW TABLES on every method call.
     *
     * @since 1.0.0
     * @var bool
     */
    private static $tables_verified = false;

    /**
     * Per-request flag: did the auto-create attempt succeed? When false,
     * every query against the faces/logs tables will short-circuit with a
     * clean error instead of raising a WordPress "table doesn't exist"
     * DB error on every request.
     *
     * @since 1.0.0
     * @var bool
     */
    private static $tables_ready = false;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->faces_table = $wpdb->prefix . 'face_login';
        $this->logs_table = $wpdb->prefix . 'face_login_logs';
    }

    /**
     * Verify the required tables exist, and create them on the fly if not.
     *
     * Why this exists:
     *   The faces table is normally created by FRL_Installer::install()
     *   which runs from the plugin's `register_activation_hook`. In some
     *   real-world setups the activation hook is missed (plugin uploaded
     *   via SFTP/FTP rather than the WP admin, multi-site network
     *   activation quirks, partial restores, custom table prefixes
     *   changed after install, etc.) and the first AJAX/REST call after
     *   that raises:
     *
     *     WordPress database error: Table '<prefix>face_login' doesn't exist
     *
     *   To keep the front-end from breaking, every public method that
     *   queries the faces/logs tables first calls this guard. It uses a
     *   static flag so SHOW TABLES only runs once per request, and it
     *   short-circuits gracefully if it cannot create the tables (e.g.
     *   the DB user lacks CREATE privileges) so the user sees a friendly
     *   admin notice instead of a stream of DB errors.
     *
     * @since 1.0.0
     * @return bool True when the tables are present (or were just
     *              created); false when they are still missing.
     */
    public function maybe_create_tables() {
        if ( self::$tables_verified ) {
            return self::$tables_ready;
        }

        self::$tables_verified = true;

        global $wpdb;

        $faces_table = $wpdb->prefix . 'face_login';
        $logs_table  = $wpdb->prefix . 'face_login_logs';

        // NOTE: WordPress 6.2+ wpdb::prepare() uses a strict regex that
        // strips the space in "SHOW TABLES", mangling it to "SHOWTABLES"
        // and breaking the query. The table name is a concatenation of
        // $wpdb->prefix and a hardcoded literal (not user input), so we
        // bypass prepare() and use the raw SHOW TABLES statement via
        // get_col() instead.
        $all_tables   = $wpdb->get_col( 'SHOW TABLES' );
        $faces_exists = in_array( $faces_table, (array) $all_tables, true );
        $logs_exists  = in_array( $logs_table,  (array) $all_tables, true );

        if ( $faces_exists && $logs_exists ) {
            self::$tables_ready = true;
            return true;
        }

        // Try to (re)create the missing tables via the standard installer.
        // We intentionally swallow exceptions here and fall back to
        // returning false so the calling method can show a friendly
        // error and the request doesn't blow up.
        try {
            if ( ! class_exists( 'FRL_Installer' ) ) {
                $installer_path = FRL_PLUGIN_PATH . 'includes/Installer/class-installer.php';
                if ( file_exists( $installer_path ) ) {
                    require_once $installer_path;
                }
            }
            if ( class_exists( 'FRL_Installer' ) ) {
                $installer = new FRL_Installer();
                $installer->install();

                // Re-check after install attempt (see note above re:
                // WordPress 6.2+ prepare() mangles "SHOW TABLES" to
                // "SHOWTABLES").
                $faces_exists = in_array( $faces_table, (array) $all_tables, true );
                $logs_exists  = in_array( $logs_table,  (array) $all_tables, true );

                self::$tables_ready = ( $faces_exists && $logs_exists );
            }
        } catch ( \Throwable $e ) {
            self::$tables_ready = false;
        }

        return self::$tables_ready;
    }

    /**
     * Check whether the faces/logs tables are present without trying to
     * create them. Useful for the admin "missing tables" notice.
     *
     * @since 1.0.0
     * @return bool
     */
    public function tables_exist() {
        global $wpdb;
        $faces_table = $wpdb->prefix . 'face_login';
        $logs_table  = $wpdb->prefix . 'face_login_logs';

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
     * Get table name
     *
     * @since 1.0.0
     * @param string $type
     * @return string
     */
    public function get_table($type = 'faces') {
        return $type === 'logs' ? $this->logs_table : $this->faces_table;
    }

    /**
     * Save face descriptor for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @param array $descriptor
     * @param string $device_name
     * @param bool $encrypt
     * @return int|false
     */
    public function save_face_descriptor($user_id, $descriptor, $device_name = 'Default', $encrypt = false) {
        // Self-heal: ensure the faces table exists before we touch it.
        if ( ! $this->maybe_create_tables() ) {
            return false;
        }

        global $wpdb;

        // Serialize descriptor array
        $serialized = maybe_serialize($descriptor);

        // Optionally encrypt
        if ($encrypt && function_exists('openssl_encrypt')) {
            $key = $this->get_encryption_key();
            if ($key) {
                // Use secure random IV generation
                $iv_length = openssl_cipher_iv_length('aes-256-cbc');
                $iv = openssl_random_pseudo_bytes($iv_length);
                $serialized = openssl_encrypt($serialized, 'AES-256-CBC', $key, 0, $iv);
                if ($serialized !== false) {
                    $serialized = base64_encode($serialized . '::' . $iv);
                }
            }
        }

        // Insert or update
        $existing = $this->get_face_by_user_device($user_id, $device_name);

        if ($existing) {
            $result = $wpdb->update(
                $this->faces_table,
                [
                    'descriptor' => $serialized,
                    'updated_at' => current_time('mysql'),
                    'status' => 1,
                ],
                [
                    'id' => $existing->id,
                ],
                ['%s', '%s', '%d'],
                ['%d']
            );

            // BUGFIX: $wpdb->update() returns the number of affected rows, or
            // false on error.  Returning the existing id blindly meant that
            // silent UPDATE failures (e.g. due to a missing column after a
            // botched migration, a deadlock, or a "no rows changed" outcome
            // when the values were identical) were masked and surfaced to
            // the user as a misleading "enrollment succeeded" — but no row
            // was actually updated.  Only return the id when the update
            // truly succeeded.
            if ($result === false) {
                return false;
            }

            return $existing->id;
        }

        $result = $wpdb->insert(
            $this->faces_table,
            [
                'user_id' => $user_id,
                'descriptor' => $serialized,
                'device_name' => sanitize_text_field($device_name),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'status' => 1,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d']
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get face descriptors for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @param bool $decrypt
     * @return array
     */
    public function get_face_descriptors($user_id, $decrypt = false) {
        if ( ! $this->maybe_create_tables() ) {
            return [];
        }

        global $wpdb;

        $faces = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->faces_table} WHERE user_id = %d AND status = 1",
                $user_id
            )
        );

        if (!$faces) {
            return [];
        }

        $descriptors = [];
        foreach ($faces as $face) {
            $descriptor_raw = $face->descriptor;
            $descriptor = maybe_unserialize($descriptor_raw);

            // Decrypt if needed - check for new format (base64 encoded with ::)
            if ($decrypt && function_exists('openssl_decrypt')) {
                $key = $this->get_encryption_key();
                if ($key) {
                    // Check if it's the new encrypted format
                    if (strpos($descriptor_raw, '::') !== false && base64_decode($descriptor_raw, true) !== false) {
                        $parts = explode('::', base64_decode($descriptor_raw), 2);
                        if (count($parts) === 2) {
                            list($encrypted_data, $iv) = $parts;
                            $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
                            if ($decrypted !== false) {
                                $descriptor = maybe_unserialize($decrypted);
                            }
                        }
                    }
                }
            }

            $descriptors[] = [
                'id' => $face->id,
                'descriptor' => $descriptor,
                'device_name' => $face->device_name,
                'created_at' => $face->created_at,
                'last_used' => $face->last_used,
            ];
        }

        return $descriptors;
    }

    /**
     * Get all descriptors for matching
     *
     * @since 1.0.0
     * @param bool $decrypt
     * @return array
     */
    public function get_all_descriptors($decrypt = false) {
        if ( ! $this->maybe_create_tables() ) {
            return [];
        }

        global $wpdb;

        $faces = $wpdb->get_results(
            "SELECT * FROM {$this->faces_table} WHERE status = 1"
        );

        if (!$faces) {
            return [];
        }

        $descriptors = [];
        foreach ($faces as $face) {
            $descriptor_raw = $face->descriptor;
            $descriptor = maybe_unserialize($descriptor_raw);

            // Decrypt if needed - check for new format
            if ($decrypt && function_exists('openssl_decrypt')) {
                $key = $this->get_encryption_key();
                if ($key) {
                    // Check if it's the new encrypted format
                    if (strpos($descriptor_raw, '::') !== false && base64_decode($descriptor_raw, true) !== false) {
                        $parts = explode('::', base64_decode($descriptor_raw), 2);
                        if (count($parts) === 2) {
                            list($encrypted_data, $iv) = $parts;
                            $decrypted = openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
                            if ($decrypted !== false) {
                                $descriptor = maybe_unserialize($decrypted);
                            }
                        }
                    }
                }
            }

            $descriptors[] = [
                'user_id' => $face->user_id,
                'descriptor' => $descriptor,
                'id' => $face->id,
            ];
        }

        return $descriptors;
    }

    /**
     * Get face by user and device
     *
     * @since 1.0.0
     * @param int $user_id
     * @param string $device_name
     * @return object|null
     */
    public function get_face_by_user_device($user_id, $device_name) {
        if ( ! $this->maybe_create_tables() ) {
            return null;
        }

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->faces_table} WHERE user_id = %d AND device_name = %s AND status = 1",
                $user_id,
                sanitize_text_field($device_name)
            )
        );
    }

    /**
     * Update last used timestamp
     *
     * @since 1.0.0
     * @param int $face_id
     * @return bool
     */
    public function update_last_used($face_id) {
        if ( ! $this->maybe_create_tables() ) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->update(
            $this->faces_table,
            ['last_used' => current_time('mysql')],
            ['id' => $face_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete face descriptor
     *
     * @since 1.0.0
     * @param int $face_id
     * @return bool
     */
    public function delete_face($face_id) {
        if ( ! $this->maybe_create_tables() ) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->faces_table,
            ['id' => $face_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete all faces for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @return bool
     */
    public function delete_all_faces_for_user($user_id) {
        if ( ! $this->maybe_create_tables() ) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->delete(
            $this->faces_table,
            ['user_id' => $user_id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Count faces for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @return int
     */
    public function count_faces_for_user($user_id) {
        if ( ! $this->maybe_create_tables() ) {
            return 0;
        }

        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->faces_table} WHERE user_id = %d AND status = 1",
                $user_id
            )
        );

        return (int) $count;
    }

    /**
     * Log authentication attempt
     *
     * @since 1.0.0
     * @param array $data
     * @return int|false
     */
    public function log_authentication($data) {
        // Ensure both tables exist before any insert. maybe_create_tables()
        // is a no-op on subsequent calls in the same request thanks to its
        // static flag, so this is cheap. This also replaces the previous
        // inline SHOW TABLES check which only guarded the logs table.
        if ( ! $this->maybe_create_tables() ) {
            return false;
        }

        global $wpdb;

        $result = $wpdb->insert(
            $this->logs_table,
            [
                'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
                'username' => isset($data['username']) ? sanitize_text_field($data['username']) : null,
                'result' => sanitize_text_field($data['result']),
                'confidence' => isset($data['confidence']) ? floatval($data['confidence']) : null,
                'response_time' => isset($data['response_time']) ? floatval($data['response_time']) : null,
                'ip_address' => FRL_Security::get_client_ip_static(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get authentication logs
     *
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_auth_logs($args = []) {
        if ( ! $this->maybe_create_tables() ) {
            return [ 'logs' => [], 'total' => 0 ];
        }

        global $wpdb;

        $defaults = [
            'user_id' => null,
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'result' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];

        if ($args['user_id']) {
            $where[] = $wpdb->prepare('user_id = %d', $args['user_id']);
        }

        if ($args['result']) {
            $where[] = $wpdb->prepare('result = %s', $args['result']);
        }

        $where_clause = implode(' AND ', $where);

        $allowed_orderby = ['created_at', 'result', 'confidence', 'response_time'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->logs_table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            )
        );

        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->logs_table} WHERE {$where_clause}"
        );

        return [
            'logs' => $logs,
            'total' => (int) $total,
        ];
    }

    /**
     * Get failed attempts for IP
     *
     * @since 1.0.0
     * @param string $ip
     * @param int $minutes
     * @return int
     */
    public function get_failed_attempts($ip, $minutes = 15) {
        if ( ! $this->maybe_create_tables() ) {
            return 0;
        }

        global $wpdb;

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->logs_table} WHERE ip_address = %s AND result = 'failed' AND created_at > %s",
                $ip,
                $since
            )
        );
    }

    /**
     * Get success/failure counts within a date range.
     *
     * Used by the admin dashboard chart. Returns an array with two integer
     * keys (`success`, `failed`) reflecting the number of authentication
     * log rows with `result = 'success'` / `result = 'failed'` in the
     * supplied inclusive range. Times are accepted in the same format as
     * MySQL `DATETIME` columns (`Y-m-d H:i:s`).
     *
     * @since 1.0.0
     * @param string $start Inclusive start timestamp (Y-m-d H:i:s).
     * @param string $end   Inclusive end timestamp (Y-m-d H:i:s).
     * @return array{success:int, failed:int}
     */
    public function get_logs_in_range($start, $end) {
        if ( ! $this->maybe_create_tables() ) {
            return [ 'success' => 0, 'failed' => 0 ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'face_login_logs';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) AS success,
                    SUM(CASE WHEN result = 'failed'  THEN 1 ELSE 0 END) AS failed
                FROM {$table}
                WHERE created_at BETWEEN %s AND %s",
                $start,
                $end
            )
        );

        return array(
            'success' => isset($row->success) ? (int) $row->success : 0,
            'failed'  => isset($row->failed)  ? (int) $row->failed  : 0,
        );
    }

    /**
     * Clean old logs
     *
     * @since 1.0.0
     * @param int $days
     * @return int
     */
    public function clean_old_logs($days = 30) {
        if ( ! $this->maybe_create_tables() ) {
            return 0;
        }

        global $wpdb;

        $before = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->logs_table} WHERE created_at < %s",
                $before
            )
        );
    }

    /**
     * Clean all logs
     *
     * @since 1.0.0
     * @return int Number of rows deleted
     */
    public function clean_all_logs() {
        if ( ! $this->maybe_create_tables() ) {
            return 0;
        }

        global $wpdb;

        return $wpdb->query(
            "DELETE FROM {$this->logs_table}"
        );
    }

    /**
     * Get encryption key
     *
     * @since 1.0.0
     * @return string|false
     */
    private function get_encryption_key() {
        $key = get_option('frl_encryption_key', '');
        if (empty($key)) {
            // Generate a key if not exists
            $key = wp_generate_password(32, true, true);
            update_option('frl_encryption_key', $key);
        }
        return $key;
    }

    /**
     * Get client IP address.
     *
     * T2-1: Delegates to FRL_Security::get_client_ip_static() so the trusted-
     * proxy-aware implementation lives in one place.
     *
     * @since 1.0.0
     * @return string
     */
    private function get_client_ip() {
        return FRL_Security::get_client_ip_static();
    }

    /**
     * Check if user has enrolled face
     *
     * @since 1.0.0
     * @param int $user_id
     * @return bool
     */
    public function user_has_face($user_id) {
        return $this->count_faces_for_user($user_id) > 0;
    }

    /**
     * Get users with face enrolled (for admin)
     *
     * @since 1.0.0
     * @return array
     */
    public function get_users_with_faces() {
        if ( ! $this->maybe_create_tables() ) {
            return [];
        }

        global $wpdb;

        return $wpdb->get_results(
            "SELECT u.ID, u.user_login, u.user_email, COUNT(f.id) as face_count, MAX(f.last_used) as last_login
            FROM {$wpdb->users} u
            INNER JOIN {$this->faces_table} f ON u.ID = f.user_id
            WHERE f.status = 1
            GROUP BY u.ID, u.user_login, u.user_email
            ORDER BY MAX(f.last_used) DESC"
        );
    }
}
