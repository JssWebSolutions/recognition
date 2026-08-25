<?php
/**
 * Logger class
 *
 * @package Face_Recognition_Login
 * @subpackage Logger
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class intentionally uses $wpdb directly for the plugin's custom log table. Table name is derived from $wpdb->prefix, all user-supplied values are passed through $wpdb->prepare(), and log rows are write-heavy and not eligible for object caching.
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Same justification as above.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; all user-supplied values are bound via $wpdb->prepare() placeholders.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Logger
 *
 * Handles logging for the plugin
 *
 * @since 1.0.0
 */
class FRL_Logger {

    /**
     * Database instance
     *
     * @since 1.0.0
     * @var FRL_Database
     */
    private $database;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->database = new FRL_Database();
    }

    /**
     * Log an event
     *
     * @since 1.0.0
     * @param string $result
     * @param array $data
     * @param bool $force Force logging even if disabled in settings
     * @return int|false
     */
    public function log($result, $data = [], $force = false) {
        // Check if logging is enabled (unless forced)
        if (!$force) {
            $options = FRL_Options::all();
            if (!isset($options['log_authentications']) || !$options['log_authentications']) {
                return false;
            }
        }

        $log_data = [
            'result' => sanitize_text_field($result),
            'user_id' => isset($data['user_id']) ? intval($data['user_id']) : null,
            'username' => isset($data['username']) ? sanitize_text_field($data['username']) : null,
            'confidence' => isset($data['confidence']) ? floatval($data['confidence']) : null,
            'response_time' => isset($data['response_time']) ? floatval($data['response_time']) : null,
        ];

        return $this->database->log_authentication($log_data);
    }

    /**
     * Log successful authentication
     *
     * @since 1.0.0
     * @param int $user_id
     * @param string $username
     * @param float $confidence
     * @param float $response_time
     * @return int|false
     */
    public function log_success($user_id, $username, $confidence = null, $response_time = null) {
        return $this->log('success', [
            'user_id' => $user_id,
            'username' => $username,
            'confidence' => $confidence,
            'response_time' => $response_time,
        ], true); // Force logging for successful authentications
    }

    /**
     * Log failed authentication
     *
     * @since 1.0.0
     * @param string $reason
     * @param float $response_time
     * @return int|false
     */
    public function log_failed($reason = 'unknown', $response_time = null) {
        return $this->log('failed', [
            'reason' => $reason,
            'response_time' => $response_time,
        ], true); // Force logging for failed authentications
    }

    /**
     * Log enrollment
     *
     * @since 1.0.0
     * @param int $user_id
     * @param string $device_name
     * @return int|false
     */
    public function log_enrollment($user_id, $device_name = 'Default') {
        return $this->log('enrolled', [
            'user_id' => $user_id,
            'device_name' => $device_name,
        ]);
    }

    /**
     * Log deletion
     *
     * @since 1.0.0
     * @param int $user_id
     * @param int $face_id
     * @return int|false
     */
    public function log_deletion($user_id, $face_id) {
        return $this->log('deleted', [
            'user_id' => $user_id,
            'face_id' => $face_id,
        ]);
    }

    /**
     * Log security event
     *
     * @since 1.0.0
     * @param string $event
     * @param array $data
     * @return int|false
     */
    public function log_security($event, $data = []) {
        return $this->log('security_' . sanitize_key($event), $data);
    }

    /**
     * Get authentication logs
     *
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function get_logs($args = []) {
        return $this->database->get_auth_logs($args);
    }

    /**
     * Get logs for specific user
     *
     * @since 1.0.0
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public function get_user_logs($user_id, $limit = 50) {
        return $this->database->get_auth_logs([
            'user_id' => $user_id,
            'limit' => $limit,
        ]);
    }

    /**
     * Get recent failed attempts
     *
     * @since 1.0.0
     * @param int $minutes
     * @param int $limit
     * @return array
     */
    public function get_recent_failed($minutes = 15, $limit = 20) {
        global $wpdb;
        $table = $wpdb->prefix . 'face_login_logs';

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE result = 'failed' AND created_at > %s ORDER BY created_at DESC LIMIT %d",
                $since,
                $limit
            )
        );
    }

    /**
     * Clean old logs
     *
     * @since 1.0.0
     * @param int|null $days
     * @return int
     */
    public function clean_old_logs($days = null) {
        if ($days === null) {
            $options = FRL_Options::all();
            $days = isset($options['auto_delete_logs_days']) ? intval($options['auto_delete_logs_days']) : 30;
        }

        if ($days <= 0) {
            return 0;
        }

        return $this->database->clean_old_logs($days);
    }

    /**
     * Clean all logs
     *
     * @since 1.0.0
     * @return int Number of rows deleted
     */
    public function clean_all_logs() {
        return $this->database->clean_all_logs();
    }

    /**
     * Get log statistics
     *
     * @since 1.0.0
     * @param int $days
     * @return array
     */
    public function get_statistics($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'face_login_logs';

        $since = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = [
            'total'           => 0,
            'total_attempts'  => 0,
            'success'         => 0,
            'failed'          => 0,
            'enrolled'        => 0,
            'deleted'         => 0,
            'accuracy'        => 0,
            'avg_confidence'  => 0,
            'avg_response_time' => 0,
            'unique_users'    => 0,
            'last_success'    => null,
            'last_failed'     => null,
        ];

        // Get counts (including enrolled, deleted, average confidence/accuracy)
        $counts = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN result = 'failed'  THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN result = 'enrolled' THEN 1 ELSE 0 END) as enrolled,
                    SUM(CASE WHEN result = 'deleted' THEN 1 ELSE 0 END) as deleted,
                    AVG(CASE WHEN result = 'success' THEN response_time ELSE NULL END) as avg_response_time,
                    AVG(CASE WHEN result = 'success' AND confidence IS NOT NULL THEN confidence ELSE NULL END) as avg_confidence,
                    COUNT(DISTINCT user_id) as unique_users
                FROM {$table}
                WHERE created_at > %s",
                $since
            )
        );

        if ($counts) {
            $stats['total']            = (int) $counts->total;
            $stats['success']          = (int) $counts->success;
            $stats['failed']           = (int) $counts->failed;
            $stats['enrolled']         = (int) $counts->enrolled;
            $stats['deleted']          = (int) $counts->deleted;
            $stats['total_attempts']   = $stats['success'] + $stats['failed'];
            $stats['avg_response_time'] = $counts->avg_response_time ? round($counts->avg_response_time, 2) : 0;
            $stats['unique_users']     = (int) $counts->unique_users;

            // Accuracy = average confidence of successful authentications,
            // expressed as a percentage (0–100). Confidence is stored as a
            // 0–1 float in the database.
            if (!is_null($counts->avg_confidence) && $counts->avg_confidence !== '') {
                $stats['avg_confidence'] = (float) $counts->avg_confidence;
                $stats['accuracy']       = round((float) $counts->avg_confidence * 100, 1);
            } else {
                $stats['avg_confidence'] = 0.0;
                $stats['accuracy']       = 0.0;
            }
        }

        // Get last events
        $stats['last_success'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$table} WHERE result = 'success' AND created_at > %s ORDER BY created_at DESC LIMIT 1",
                $since
            )
        );

        $stats['last_failed'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$table} WHERE result = 'failed' AND created_at > %s ORDER BY created_at DESC LIMIT 1",
                $since
            )
        );

        return $stats;
    }

    /**
     * Get per-day success/failure counts for the dashboard chart.
     *
     * Replaces the inline `global $wpdb` block that previously lived in
     * `admin/templates/dashboard-page.php` (H-3 - 1.0.0). Each entry is
     * keyed by the day's label (e.g. "Jan 5") with integer counts for
     * successful and failed authentications.
     *
     * @since 1.0.0
     * @param int $days Number of days to include (most recent first).
     * @return array<int, array{date:string, success:int, failed:int}>
     */
    public function get_daily_breakdown($days = 7) {
        $days = max(1, (int) $days);
        $breakdown = array();

        for ($i = $days - 1; $i >= 0; $i--) {
            $day_start = gmdate('Y-m-d 00:00:00', strtotime("-{$i} days"));
            $day_end   = gmdate('Y-m-d 23:59:59', strtotime("-{$i} days"));

            $counts = $this->database->get_logs_in_range($day_start, $day_end);

            $breakdown[] = array(
                'date'    => date_i18n('M j', strtotime($day_start)),
                'success' => isset($counts['success']) ? (int) $counts['success'] : 0,
                'failed'  => isset($counts['failed'])  ? (int) $counts['failed']  : 0,
            );
        }

        /**
         * Filter the daily breakdown returned to the dashboard chart.
         *
         * @since 1.0.0
         * @param array $breakdown Daily breakdown entries.
         * @param int   $days       Number of days requested.
         */
        return apply_filters('frl_daily_breakdown', $breakdown, $days);
    }

    /**
     * Export logs
     *
     * @since 1.0.0
     * @param array $args
     * @return array
     */
    public function export_logs($args = []) {
        $defaults = [
            'limit' => 10000,
            'offset' => 0,
            'user_id' => null,
            'result' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        $logs_data = $this->database->get_auth_logs($args);

        $export = [];
        foreach ($logs_data['logs'] as $log) {
            $export[] = [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'username' => $log->username,
                'result' => $log->result,
                'confidence' => $log->confidence,
                'response_time' => $log->response_time,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        }

        return $export;
    }
}
