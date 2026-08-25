<?php
/**
 * AJAX Handler class
 *
 * @package Face_Recognition_Login
 * @subpackage Ajax
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Ajax_Handler
 *
 * Handles all AJAX requests for the plugin
 *
 * @since 1.0.0
 */
class FRL_Ajax_Handler {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        // Public AJAX actions.
        // Note: `frl_enroll_face` is intentionally NOT registered with
        // `wp_ajax_nopriv_` because the handler requires the caller to be
        // logged in (enrolment is an authenticated action). Registering a
        // nopriv version would expose a public endpoint that always 403s
        // and is flagged by Plugin Check (fixes C-3).
        add_action('wp_ajax_frl_enroll_face', [$this, 'handle_enroll_face']);

        add_action('wp_ajax_frl_authenticate', [$this, 'handle_authenticate']);
        add_action('wp_ajax_nopriv_frl_authenticate', [$this, 'handle_authenticate']);

        add_action('wp_ajax_frl_check_status', [$this, 'handle_check_status']);

        // Private AJAX actions (logged in users)
        add_action('wp_ajax_frl_delete_face', [$this, 'handle_delete_face']);
        add_action('wp_ajax_frl_get_faces', [$this, 'handle_get_faces']);
        add_action('wp_ajax_frl_get_logs', [$this, 'handle_get_logs']);
        add_action('wp_ajax_frl_export_data', [$this, 'handle_export_data']);
        add_action('wp_ajax_frl_update_device_name', [$this, 'handle_update_device_name']);

        // Admin AJAX actions
        add_action('wp_ajax_frl_admin_enroll_face', [$this, 'handle_admin_enroll_face']);
        add_action('wp_ajax_frl_delete_user_faces', [$this, 'handle_admin_delete_user_faces']);
    }

    /**
     * Handle face enrollment
     *
     * @since 1.0.0
     */
    public function handle_enroll_face() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in to enroll your face.', 'recognition'),
            ], 401);
        }

        // Get descriptor from request. The descriptor is a JSON string
        // produced by JSON.stringify(Array.from(faceDescriptor)) on the
        // client (a 128-element array of floats). We deliberately do NOT
        // pass it through sanitize_text_field() here because that function
        // is designed for plain text fields and would corrupt the JSON
        // structure (stripping characters used by the numeric/array
        // syntax). wp_unslash() alone is enough to undo the magic-quote
        // slashes WordPress adds to $_POST; downstream validation in
        // FRL_User_Profile::validate_descriptor() enforces the 128-element
        // numeric contract and rejects anything that does not parse.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- descriptor is JSON and is validated via json_decode + 128-element numeric contract below; sanitize_text_field() would corrupt the array/numeric syntax.
        $descriptor = isset($_POST['descriptor']) ? wp_unslash($_POST['descriptor']) : '';

        if (empty($descriptor)) {
            wp_send_json_error([
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        // Parse descriptor
        $descriptor_array = json_decode($descriptor, true);

        if (!is_array($descriptor_array) || count($descriptor_array) !== 128) {
            wp_send_json_error([
                'message' => __('Invalid face data format.', 'recognition'),
            ], 400);
        }

        // Get device name - sanitize_text_field() is appropriate here
        // because device_name is a short human-readable string.
        $device_name = isset($_POST['device_name']) ? sanitize_text_field(wp_unslash($_POST['device_name'])) : FRL_Helper::get_device_name();

        // Check HTTPS
        if (!FRL_Helper::is_secure_connection() && FRL_Helper::is_https_required()) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_https_required'),
            ], 403);
        }

        // Determine the target user id for the enrolment.
        //
        // The default (no `user_id` POSTed) is the currently
        // logged-in user - this preserves the original
        // behaviour for self-enrolment (e.g. from the front-end
        // registration / login flows).
        //
        // When a `user_id` IS posted and differs from the
        // current user, this is an admin enrolling on behalf
        // of another user (e.g. from `user-edit.php?user_id=X`
        // in wp-admin). We verify the caller has the WP
        // `edit_user` capability for the target so a regular
        // user can never hijack the AJAX to enrol a face for
        // somebody else. The previous bug was that this path
        // hard-coded `get_current_user_id()` and saved the
        // face under the admin's own id, which is fixed here.
        $target_user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $current_user_id = get_current_user_id();

        if ( $target_user_id > 0 && $target_user_id !== $current_user_id ) {
            if ( ! current_user_can( 'edit_user', $target_user_id ) ) {
                wp_send_json_error(
                    [
                        'message' => __('You do not have permission to enroll a face for this user.', 'recognition'),
                    ],
                    403
                );
            }
            $user_id = $target_user_id;
        } else {
            $user_id = $current_user_id;
        }

        // Enroll face for the resolved target user.
        $user_profile = new FRL_User_Profile();
        $result = $user_profile->enroll_face( $user_id, $descriptor_array, $device_name );

        if (is_wp_error($result)) {
            $error_data = [
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ];
            // Surface the underlying DB error to the browser console when
            // WP_DEBUG is on. The frl_save_failed error code maps to a DB
            // failure in save_face_descriptor(); developers can read the
            // $wpdb->last_error in the error log to see the actual cause.
            if (defined('WP_DEBUG') && WP_DEBUG && $result->get_error_code() === 'frl_save_failed') {
                $error_data['debug'] = 'Enable WP_DEBUG_LOG to see the underlying database error in wp-content/debug.log.';
            }
            wp_send_json_error($error_data, 400);
        }

        wp_send_json_success([
            'message' => __('Face enrolled successfully!', 'recognition'),
            'face_id' => $result,
            'device_name' => $device_name,
        ]);
    }

    /**
     * Handle face authentication
     *
     * @since 1.0.0
     */
    public function handle_authenticate() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Get descriptor from request. JSON data must not be passed through
        // sanitize_text_field() because that function strips characters used
        // by the JSON array/numeric syntax and corrupts the payload. See
        // handle_enroll_face() for the full rationale.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- descriptor is JSON and is validated via json_decode + 128-element numeric contract below; sanitize_text_field() would corrupt the array/numeric syntax.
        $descriptor = isset($_POST['descriptor']) ? wp_unslash($_POST['descriptor']) : '';

        if (empty($descriptor)) {
            wp_send_json_error([
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        // Check HTTPS
        if (!FRL_Helper::is_secure_connection() && FRL_Helper::is_https_required()) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_https_required'),
            ], 403);
        }

        // Authenticate
        $authenticator = new FRL_Authenticator();
        $user = $authenticator->authenticate_by_descriptor($descriptor);

        if (is_wp_error($user)) {
            // Note: Logging is already handled in FRL_Authenticator::authenticate_by_descriptor()
            
            wp_send_json_error([
                'message' => $user->get_error_message(),
            ], 401);
        }

        // Log the user in - use remember=true to maintain session properly
        // Note: Logging is already handled in FRL_Authenticator::authenticate_by_descriptor()
        wp_set_current_user($user->ID);
        $secure_cookie = is_ssl();
        wp_set_auth_cookie($user->ID, true, $secure_cookie);

        // Get redirect URL - be more robust, use esc_url_raw for sanitization
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        
        // If redirect_to is empty, invalid, or points to login page, default to admin
        if (empty($redirect_to) || stripos($redirect_to, 'wp-login') !== false || $redirect_to === wp_login_url()) {
            $redirect_to = admin_url();
        } else {
            $redirect_to = apply_filters('login_redirect', $redirect_to, '', $user);
        }

        // Final safety check - ensure we have a valid redirect
        if (empty($redirect_to) || stripos($redirect_to, 'wp-login') !== false) {
            $redirect_to = admin_url();
        }

        wp_send_json_success([
            'message' => __('Login successful!', 'recognition'),
            'redirect_to' => $redirect_to,
            'user_id' => $user->ID,
            'username' => $user->user_login,
        ]);
    }

    /**
     * Handle status check
     *
     * @since 1.0.0
     */
    public function handle_check_status() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        $authenticator = new FRL_Authenticator();
        $status = $authenticator->get_status();

        // Add user-specific status if logged in
        if (is_user_logged_in()) {
            $user_profile = new FRL_User_Profile();
            $status['user_status'] = $user_profile->get_enrollment_status(get_current_user_id());
        }

        wp_send_json_success([
            'status' => $status,
            'is_https' => FRL_Helper::is_secure_connection(),
        ]);
    }

    /**
     * Handle face deletion
     *
     * @since 1.0.0
     */
    public function handle_delete_face() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'recognition'),
            ], 401);
        }

        // Get face ID
        $face_id = isset($_POST['face_id']) ? intval($_POST['face_id']) : 0;

        if (!$face_id) {
            wp_send_json_error([
                'message' => __('Invalid face ID.', 'recognition'),
            ], 400);
        }

        // Delete face. The `target_user_id` posted by the JS is
        // the id of the user whose profile is currently being
        // rendered (admin's own id on `profile.php`, the OTHER
        // user's id on `user-edit.php?user_id=X`). We pass it
        // through to `delete_face()` so its ownership check can
        // use this authoritative value when the face row's
        // `user_id` column is stale (older rows enrolled before
        // the user-id-on-enrolment fix landed). For an admin
        // deleting another user's face on `user-edit.php` with
        // an active licence, this lets the deletion succeed even
        // when the DB user_id is wrong.
        $target_user_id = isset( $_POST['target_user_id'] ) ? absint( wp_unslash( $_POST['target_user_id'] ) ) : 0;

        // Delete face
        $user_profile = new FRL_User_Profile();
        $result = $user_profile->delete_face( $face_id, $target_user_id > 0 ? $target_user_id : null );

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ], 400);
        }

        wp_send_json_success([
            'message' => __('Face profile deleted.', 'recognition'),
        ]);
    }

    /**
     * Handle get faces
     *
     * @since 1.0.0
     */
    public function handle_get_faces() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'recognition'),
            ], 401);
        }

        $user_profile = new FRL_User_Profile();
        $faces = $user_profile->get_user_faces(get_current_user_id());

        // Remove actual descriptors from response (for privacy).
        // Note: previously included `descriptor_size` (the embedding dimensionality)
        // which leaked information about the ML model in use. That field has been
        // removed from the public response; the descriptor count is an internal
        // detail and not needed by the client.
        $faces_safe = array_map(function ($face) {
            return [
                'id' => $face['id'],
                'device_name' => $face['device_name'],
                'created_at' => $face['created_at'],
                'last_used' => $face['last_used'],
            ];
        }, $faces);

        wp_send_json_success([
            'faces' => $faces_safe,
            'count' => count($faces),
        ]);
    }

    /**
     * Handle get logs
     *
     * @since 1.0.0
     */
    public function handle_get_logs() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'recognition'),
            ], 401);
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 20;
        $limit = min($limit, 100); // Max 100

        $user_profile = new FRL_User_Profile();
        $logs = $user_profile->get_auth_history(get_current_user_id(), $limit);

        // Format logs
        $formatted_logs = array_map(function ($log) {
            return [
                'id' => $log->id,
                'result' => $log->result,
                'confidence' => $log->confidence,
                'response_time' => $log->response_time,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
                'time_ago' => FRL_Helper::time_ago($log->created_at),
            ];
        }, $logs['logs']);

        wp_send_json_success([
            'logs' => $formatted_logs,
            'total' => $logs['total'],
        ]);
    }

    /**
     * Handle data export
     *
     * @since 1.0.0
     */
    public function handle_export_data() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'recognition'),
            ], 401);
        }

        $user_profile = new FRL_User_Profile();
        $data = $user_profile->export_user_data(get_current_user_id());

        if (is_wp_error($data)) {
            wp_send_json_error([
                'message' => $data->get_error_message(),
            ], 400);
        }

        wp_send_json_success([
            'data' => $data,
        ]);
    }

    /**
     * Handle device name update
     *
     * @since 1.0.0
     */
    public function handle_update_device_name() {
        // Verify nonce
        if (!check_ajax_referer('frl_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => __('You must be logged in.', 'recognition'),
            ], 401);
        }

        $face_id = isset($_POST['face_id']) ? intval(wp_unslash($_POST['face_id'])) : 0;
        $device_name = isset($_POST['device_name']) ? sanitize_text_field(wp_unslash($_POST['device_name'])) : '';

        if (!$face_id || empty($device_name)) {
            wp_send_json_error([
                'message' => __('Invalid parameters.', 'recognition'),
            ], 400);
        }

        $security = new FRL_Security();
        if (!$security->owns_face_profile($face_id)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_unauthorized'),
            ], 403);
        }

        $user_profile = new FRL_User_Profile();
        $result = $user_profile->update_device_name($face_id, $device_name);

        if (!$result) {
            wp_send_json_error([
                'message' => __('Failed to update device name.', 'recognition'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('Device name updated.', 'recognition'),
        ]);
    }

    /**
     * Handle admin face enrollment for a specific user
     *
     * @since 1.0.0
     */
    public function handle_admin_enroll_face() {
        // Verify nonce
        if (!check_ajax_referer('frl_admin_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'recognition'),
            ], 403);
        }

        // Get user ID from request
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error([
                'message' => __('Invalid user ID.', 'recognition'),
            ], 400);
        }

        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error([
                'message' => __('User not found.', 'recognition'),
            ], 404);
        }

        // Get descriptor from request. Same rationale as handle_enroll_face():
        // JSON data must not be run through sanitize_text_field() because it
        // would corrupt the array/numeric syntax.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- descriptor is JSON and is validated via json_decode + 128-element numeric contract below; sanitize_text_field() would corrupt the array/numeric syntax.
        $descriptor = isset($_POST['descriptor']) ? wp_unslash($_POST['descriptor']) : '';

        if (empty($descriptor)) {
            wp_send_json_error([
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        // Parse descriptor
        $descriptor_array = json_decode($descriptor, true);

        if (!is_array($descriptor_array) || count($descriptor_array) !== 128) {
            wp_send_json_error([
                'message' => __('Invalid face data format.', 'recognition'),
            ], 400);
        }

        // Enroll face for the specified user
        $user_profile = new FRL_User_Profile();
        $result = $user_profile->enroll_face($user_id, $descriptor_array, 'Admin Enrolled (' . FRL_Helper::get_device_name() . ')');

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ], 400);
        }

        wp_send_json_success([
            'message' => __('Face enrolled successfully!', 'recognition'),
            'face_id' => $result,
            'user_id' => $user_id,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
        ]);
    }

    /**
     * Handle admin delete all faces for a user
     *
     * @since 1.0.0
     */
    public function handle_admin_delete_user_faces() {
        // Verify nonce
        if (!check_ajax_referer('frl_admin_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => FRL_Helper::get_error_message('frl_invalid_nonce'),
            ], 403);
        }

        // Verify admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to perform this action.', 'recognition'),
            ], 403);
        }

        // Get user ID from request
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$user_id) {
            wp_send_json_error([
                'message' => __('Invalid user ID.', 'recognition'),
            ], 400);
        }

        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error([
                'message' => __('User not found.', 'recognition'),
            ], 404);
        }

        // Delete all faces for the user
        $user_profile = new FRL_User_Profile();
        $result = $user_profile->delete_all_faces($user_id);

        if (!$result) {
            wp_send_json_error([
                'message' => __('Failed to delete faces.', 'recognition'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('All face profiles deleted for user.', 'recognition'),
            'user_id' => $user_id,
            'user_name' => $user->display_name,
        ]);
    }
}
