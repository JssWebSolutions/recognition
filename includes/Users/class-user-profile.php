<?php
/**
 * User profile class
 *
 * @package Face_Recognition_Login
 * @subpackage Users
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class intentionally uses $wpdb directly for the plugin's custom face-enrollment table. Table name is derived from $wpdb->prefix and all user-supplied values are passed through $wpdb->prepare().
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Same justification as above.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are derived from $wpdb->prefix; all user-supplied values are bound via $wpdb->prepare() placeholders.

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_User_Profile
 *
 * Handles user face enrollment and profile management
 *
 * @since 1.0.0
 */
class FRL_User_Profile {

    /**
     * Database instance
     *
     * @since 1.0.0
     * @var FRL_Database
     */
    private $database;

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
        $this->logger = new FRL_Logger();
    }

    /**
     * Enroll face for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @param array $descriptor
     * @param string $device_name
     * @return int|\WP_Error
     */
    public function enroll_face($user_id, $descriptor, $device_name = 'Default') {
        // Check if user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('frl_invalid_user', __('Invalid user.', 'recognition'));
        }

        // Validate descriptor
        if (!$this->validate_descriptor($descriptor)) {
            return new \WP_Error('frl_invalid_descriptor', __('Invalid face descriptor.', 'recognition'));
        }

        // Check max faces per user
        $options = FRL_Options::all();
        $max_faces = isset($options['max_faces_per_user']) ? max( 1, min( 50, intval( $options['max_faces_per_user'] ) ) ) : 1;
        $current_count = $this->database->count_faces_for_user($user_id);

        if ($current_count >= $max_faces) {
            return new \WP_Error(
                'frl_max_faces_reached',
                sprintf(
                    /* translators: %d: maximum number of face profiles allowed. */
                    __('Maximum number of face profiles (%d) reached. Please delete an existing profile first.', 'recognition'),
                    $max_faces
                )
            );
        }

        // Check encryption setting
        $encrypt = isset($options['encrypt_descriptors']) && $options['encrypt_descriptors'];

        // Save descriptor
        $face_id = $this->database->save_face_descriptor($user_id, $descriptor, $device_name, $encrypt);

        if (!$face_id) {
            return new \WP_Error('frl_save_failed', __('Failed to save face data.', 'recognition'));
        }

        // Log enrollment
        $this->logger->log_enrollment($user_id, $device_name);

        return $face_id;
    }

    /**
     * Validate face descriptor
     *
     * @since 1.0.0
     * @param mixed $descriptor
     * @return bool
     */
    public function validate_descriptor($descriptor) {
        // Must be array
        if (!is_array($descriptor)) {
            // Try JSON decode
            $decoded = json_decode($descriptor, true);
            if (!is_array($decoded)) {
                return false;
            }
            $descriptor = $decoded;
        }

        // Must have 128 elements (face-api.js descriptor size)
        if (count($descriptor) !== 128) {
            return false;
        }

        // All values must be numeric AND within sane bounds.
        // S-8 / T2-18: bounding each value prevents overflow attacks and
        // rejects obviously-malformed input. Real face-api.js descriptors
        // have values in the range [-1, 1]; a generous bound of [-10, 10]
        // is used here to allow for future model changes.
        $bound = defined( 'FRL_DESCRIPTOR_BOUND' ) ? FRL_DESCRIPTOR_BOUND : 10.0;
        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return false;
            }
            $f = (float) $value;
            if (abs( $f ) > $bound || is_nan( $f ) || is_infinite( $f ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's enrolled faces
     *
     * @since 1.0.0
     * @param int $user_id
     * @param bool $decrypt
     * @return array
     */
    public function get_user_faces($user_id, $decrypt = false) {
        return $this->database->get_face_descriptors($user_id, $decrypt);
    }

    /**
     * Delete user's face profile
     *
     * @since 1.0.0
     * @param int $face_id
     * @param int|null $user_id
     * @return bool|\WP_Error
     */
    public function delete_face($face_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Verify ownership
        global $wpdb;
        $table = $wpdb->prefix . 'face_login';

        $face = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $face_id
            )
        );

        if (!$face) {
            return new \WP_Error('frl_face_not_found', __('Face profile not found.', 'recognition'));
        }

        if (intval($face->user_id) !== intval($user_id)) {
            return new \WP_Error('frl_unauthorized', __('You do not have permission to delete this face profile.', 'recognition'));
        }

        $result = $this->database->delete_face($face_id);

        if ($result) {
            $this->logger->log_deletion($user_id, $face_id);
        }

        return $result;
    }

    /**
     * Delete all faces for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @return bool
     */
    public function delete_all_faces($user_id) {
        $result = $this->database->delete_all_faces_for_user($user_id);

        if ($result) {
            $this->logger->log('all_deleted', [
                'user_id' => $user_id,
            ]);
        }

        return $result;
    }

    /**
     * Check if user has enrolled face
     *
     * @since 1.0.0
     * @param int $user_id
     * @return bool
     */
    public function user_has_face($user_id) {
        return $this->database->user_has_face($user_id);
    }

    /**
     * Get face count for user
     *
     * @since 1.0.0
     * @param int $user_id
     * @return int
     */
    public function get_face_count($user_id) {
        return $this->database->count_faces_for_user($user_id);
    }

    /**
     * Get user's authentication history
     *
     * @since 1.0.0
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public function get_auth_history($user_id, $limit = 20) {
        return $this->logger->get_user_logs($user_id, $limit);
    }

    /**
     * Get last authentication time
     *
     * @since 1.0.0
     * @param int $user_id
     * @return string|null
     */
    public function get_last_auth($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'face_login_logs';

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT created_at FROM {$table} WHERE user_id = %d AND result = 'success' ORDER BY created_at DESC LIMIT 1",
                $user_id
            )
        );
    }

    /**
     * Export user biometric data
     *
     * @since 1.0.0
     * @param int $user_id
     * @return array|\WP_Error
     */
    public function export_user_data($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('frl_invalid_user', __('Invalid user.', 'recognition'));
        }

        $faces = $this->database->get_face_descriptors($user_id, false);
        $logs = $this->logger->get_user_logs($user_id, 100);

        return [
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
            ],
            'faces' => $faces,
            'logs' => $logs['logs'],
            'exported_at' => current_time('mysql'),
        ];
    }

    /**
     * Get enrollment status
     *
     * @since 1.0.0
     * @param int $user_id
     * @return array
     */
    public function get_enrollment_status($user_id) {
        $face_count = $this->database->count_faces_for_user($user_id);
        $last_auth = $this->get_last_auth($user_id);
        $options = FRL_Options::all();
        $max_faces = isset($options['max_faces_per_user']) ? max( 1, min( 50, intval( $options['max_faces_per_user'] ) ) ) : 1;

        return [
            'enrolled' => $face_count > 0,
            'face_count' => $face_count,
            'max_faces' => $max_faces,
            'can_enroll_more' => $face_count < $max_faces,
            'last_authentication' => $last_auth,
        ];
    }

    /**
     * Update face status
     *
     * @since 1.0.0
     * @param int $face_id
     * @param int $status
     * @return bool
     */
    public function update_face_status($face_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'face_login';

        $result = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $face_id],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update device name
     *
     * @since 1.0.0
     * @param int $face_id
     * @param string $device_name
     * @return bool
     */
    public function update_device_name($face_id, $device_name) {
        global $wpdb;
        $table = $wpdb->prefix . 'face_login';

        $result = $wpdb->update(
            $table,
            ['device_name' => sanitize_text_field($device_name)],
            ['id' => $face_id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }
}
