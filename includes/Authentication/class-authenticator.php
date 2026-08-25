<?php
/**
 * Authenticator class
 *
 * @package Face_Recognition_Login
 * @subpackage Authentication
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class intentionally uses $wpdb directly for the plugin's custom face-enrollment table. Table name is derived from $wpdb->prefix and all user-supplied values are passed through $wpdb->prepare().
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Same justification as above.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; all user-supplied values are bound via $wpdb->prepare() placeholders.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Authenticator
 *
 * Handles face recognition authentication
 *
 * @since 1.0.0
 */
class FRL_Authenticator {

    /**
     * Database instance
     *
     * @since 1.0.0
     * @var FRL_Database
     */
    private $database;

    /**
     * Security instance
     *
     * @since 1.0.0
     * @var FRL_Security
     */
    private $security;

    /**
     * Logger instance
     *
     * @since 1.0.0
     * @var FRL_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->database = new FRL_Database();
        $this->security = new FRL_Security();
        $this->logger = new FRL_Logger();
    }

    /**
     * Authenticate user by face descriptor
     *
     * @since 1.0.0
     * @param array $descriptor
     * @return \WP_User|\WP_Error
     */
    public function authenticate_by_descriptor($descriptor) {
        $start_time = microtime(true);

        // Check rate limiting
        if (!$this->check_rate_limit()) {
            return new \WP_Error(
                'frl_rate_limited',
                __('Too many failed attempts. Please try again later.', 'recognition')
            );
        }

        // Check HTTPS requirement
        if (!is_ssl()) {
            $options = FRL_Options::all();
            if (isset($options['require_https']) && $options['require_https']) {
                return new \WP_Error(
                    'frl_https_required',
                    __('HTTPS is required for face login.', 'recognition')
                );
            }
        }

        // Parse descriptor
        $input_descriptor = $this->parse_descriptor($descriptor);
        if (!$input_descriptor) {
            $this->log_failed_attempt(null, $start_time, 'invalid_descriptor');
            return new \WP_Error(
                'frl_invalid_descriptor',
                __('Invalid face descriptor.', 'recognition')
            );
        }

        // Get all enrolled descriptors
        $enrolled_faces = $this->database->get_all_descriptors();

        if (empty($enrolled_faces)) {
            $this->log_failed_attempt(null, $start_time, 'no_faces_enrolled');
            return new \WP_Error(
                'frl_no_faces_enrolled',
                __('No faces enrolled. Please enroll your face first.', 'recognition')
            );
        }

        // Find matching user
        $options = FRL_Options::all();
        $threshold = isset($options['match_threshold']) ? floatval($options['match_threshold']) : FRL_DEFAULT_MATCH_THRESHOLD;
        $best_match = null;
        $best_distance = PHP_FLOAT_MAX;

        foreach ($enrolled_faces as $face) {
            $distance = $this->calculate_distance($input_descriptor, $face['descriptor']);

            if ($distance < $best_distance) {
                $best_distance = $distance;
                $best_match = $face;
            }
        }

        // Check if match is within threshold
        if ($best_match && $best_distance <= $threshold) {
            $user = get_user_by('id', $best_match['user_id']);

            if ($user) {
                // Update last used
                $this->database->update_last_used($best_match['id']);

                // Log success
                $this->log_success($user, $best_distance, $start_time);

                return $user;
            }
        }

        // Failed authentication
        $this->log_failed_attempt(null, $start_time, 'no_match');
        $this->security->increment_failed_attempts();

        return new \WP_Error(
            'frl_authentication_failed',
            __('Face recognition failed. Please try again or use password.', 'recognition')
        );
    }

    /**
     * Parse descriptor from JSON string
     *
     * @since 1.0.0
     * @param string $descriptor
     * @return array|false
     */
    private function parse_descriptor($descriptor) {
        // Try JSON first
        $decoded = json_decode($descriptor, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        // Try deserialization
        $deserialized = maybe_unserialize($descriptor);
        if (is_array($deserialized)) {
            return $deserialized;
        }

        return false;
    }

    /**
     * Calculate Euclidean distance between descriptors
     *
     * @since 1.0.0
     * @param array $desc1
     * @param array $desc2
     * @return float
     */
    public function calculate_distance($desc1, $desc2) {
        if (count($desc1) !== count($desc2)) {
            return PHP_FLOAT_MAX;
        }

        $sum = 0;
        for ($i = 0; $i < count($desc1); $i++) {
            $diff = floatval($desc1[$i]) - floatval($desc2[$i]);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Calculate cosine similarity between descriptors
     *
     * @since 1.0.0
     * @param array $desc1
     * @param array $desc2
     * @return float
     */
    public function calculate_cosine_similarity($desc1, $desc2) {
        $dot_product = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($desc1); $i++) {
            $dot_product += floatval($desc1[$i]) * floatval($desc2[$i]);
            $norm1 += floatval($desc1[$i]) * floatval($desc1[$i]);
            $norm2 += floatval($desc2[$i]) * floatval($desc2[$i]);
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dot_product / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Check rate limiting
     *
     * @since 1.0.0
     * @return bool
     */
    private function check_rate_limit() {
        $options = FRL_Options::all();

        if (!isset($options['rate_limit_enabled']) || !$options['rate_limit_enabled']) {
            return true;
        }

        $ip = $this->security->get_client_ip();
        $max_attempts = isset($options['max_failed_attempts']) ? intval($options['max_failed_attempts']) : 5;
        $lockout_minutes = isset($options['lockout_minutes']) ? intval($options['lockout_minutes']) : 15;

        return !$this->security->is_rate_limited($ip, $max_attempts, $lockout_minutes);
    }

    /**
     * Log successful authentication
     *
     * @since 1.0.0
     * @param \WP_User $user
     * @param float $confidence
     * @param float $start_time
     */
    private function log_success($user, $confidence, $start_time) {
        $this->logger->log('success', [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'confidence' => 1 - $confidence, // Convert distance to confidence
            'response_time' => (microtime(true) - $start_time) * 1000,
        ]);

        $this->security->clear_failed_attempts();
    }

    /**
     * Log failed attempt
     *
     * @since 1.0.0
     * @param int|null $user_id
     * @param float $start_time
     * @param string $reason
     */
    private function log_failed_attempt($user_id, $start_time, $reason) {
        $this->logger->log('failed', [
            'user_id' => $user_id,
            'reason' => $reason,
            'response_time' => (microtime(true) - $start_time) * 1000,
        ]);
    }

    /**
     * Validate liveness detection result
     *
     * @since 1.0.0
     * @param array $liveness_data
     * @return bool
     */
    public function validate_liveness($liveness_data) {
        $options = FRL_Options::all();

        if (!isset($options['liveness_detection']) || !$options['liveness_detection']) {
            return true;
        }

        // Check for blink detection
        if (isset($liveness_data['blinked']) && $liveness_data['blinked']) {
            return true;
        }

        // Check for head movement
        if (isset($liveness_data['head_movement_detected']) && $liveness_data['head_movement_detected']) {
            return true;
        }

        // Check for smile
        if (isset($liveness_data['smiled']) && $liveness_data['smiled']) {
            return true;
        }

        // Require at least one liveness indicator
        return false;
    }

    /**
     * Get authentication status
     *
     * @since 1.0.0
     * @return array
     */
    public function get_status() {
        return [
            'enabled'            => FRL_Options::get( 'enabled', false ),
            'https_required'     => FRL_Options::get( 'require_https', true ),
            'liveness_enabled'   => FRL_Options::get( 'liveness_detection', false ),
            'rate_limit_enabled' => FRL_Options::get( 'rate_limit_enabled', true ),
            'total_enrolled_users' => $this->get_enrolled_user_count(),
        ];
    }

    /**
     * Get count of enrolled users
     *
     * @since 1.0.0
     * @return int
     */
    private function get_enrolled_user_count() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}face_login WHERE status = 1"
        );
    }
}
