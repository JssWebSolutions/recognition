<?php
/**
 * REST API Controller
 *
 * @package Face_Recognition_Login
 * @subpackage REST
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_REST_Controller
 *
 * Handles REST API endpoints
 *
 * @since 1.0.0
 */
class FRL_REST_Controller {

    /**
     * REST namespace
     *
     * @since 1.0.0
     * @var string
     */
    private $namespace = 'frl/v1';

    /**
     * Register routes
     *
     * @since 1.0.0
     */
    public function register_routes() {
        // Status check (public)
        register_rest_route($this->namespace, '/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_status'],
            'permission_callback' => '__return_true',
        ]);

        // Authenticate (public)
        register_rest_route($this->namespace, '/authenticate', [
            'methods' => 'POST',
            'callback' => [$this, 'authenticate'],
            'permission_callback' => '__return_true',
        ]);

        // User routes (authenticated)
        register_rest_route($this->namespace, '/user/faces', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_user_faces'],
                'permission_callback' => [$this, 'check_authentication'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'enroll_face'],
                'permission_callback' => [$this, 'check_authentication'],
            ],
        ]);

        register_rest_route($this->namespace, '/user/faces/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_face'],
            'permission_callback' => [$this, 'check_authentication'],
        ]);

        register_rest_route($this->namespace, '/user/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_logs'],
            'permission_callback' => [$this, 'check_authentication'],
        ]);

        register_rest_route($this->namespace, '/user/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_user_data'],
            'permission_callback' => [$this, 'check_authentication'],
        ]);

        // Admin routes
        register_rest_route($this->namespace, '/admin/users', [
            'methods' => 'GET',
            'callback' => [$this, 'get_enrolled_users'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        register_rest_route($this->namespace, '/admin/enroll', [
            'methods' => 'POST',
            'callback' => [$this, 'admin_enroll_face'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        register_rest_route($this->namespace, '/admin/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_all_logs'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        register_rest_route($this->namespace, '/admin/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_statistics'],
            'permission_callback' => [$this, 'check_admin'],
        ]);

        register_rest_route($this->namespace, '/admin/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_admin'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin'],
            ],
        ]);

        register_rest_route($this->namespace, '/admin/user/(?P<user_id>\d+)/faces', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_user_faces'],
            'permission_callback' => [$this, 'check_admin'],
        ]);
    }

    /**
     * Check authentication
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function check_authentication($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_Error(
                'rest_not_logged_in',
                __('You must be logged in.', 'recognition'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Check admin capability
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function check_admin($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                __('You do not have permission.', 'recognition'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Get plugin status
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_status($request) {
        $authenticator = new FRL_Authenticator();
        $status = $authenticator->get_status();

        $user_id = get_current_user_id();
        if ($user_id) {
            $user_profile = new FRL_User_Profile();
            $status['user_status'] = $user_profile->get_enrollment_status($user_id);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $status,
        ], 200);
    }

    /**
     * Authenticate user
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function authenticate($request) {
        $descriptor = $request->get_param('descriptor');

        if (empty($descriptor)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        $authenticator = new FRL_Authenticator();
        $user = $authenticator->authenticate_by_descriptor($descriptor);

        if (is_wp_error($user)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $user->get_error_message(),
            ], 401);
        }

        // Log user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);

        $redirect_to = $request->get_param('redirect_to');
        if (empty($redirect_to)) {
            $redirect_to = admin_url();
        }
        $redirect_to = apply_filters('login_redirect', $redirect_to, '', $user);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Login successful!', 'recognition'),
            'data' => [
                'redirect_to' => $redirect_to,
                'user_id' => $user->ID,
                'username' => $user->user_login,
            ],
        ], 200);
    }

    /**
     * Get user's face profiles
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_user_faces($request) {
        $user_id = get_current_user_id();
        $user_profile = new FRL_User_Profile();
        $faces = $user_profile->get_user_faces($user_id);

        // Remove descriptors for privacy
        $faces_safe = array_map(function ($face) {
            return [
                'id' => $face['id'],
                'device_name' => $face['device_name'],
                'created_at' => $face['created_at'],
                'last_used' => $face['last_used'],
            ];
        }, $faces);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'faces' => $faces_safe,
                'count' => count($faces),
            ],
        ], 200);
    }

    /**
     * Enroll face
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function enroll_face($request) {
        $descriptor = $request->get_param('descriptor');
        $device_name = $request->get_param('device_name');

        if (empty($descriptor)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        $user_id = get_current_user_id();
        $user_profile = new FRL_User_Profile();

        if (empty($device_name)) {
            $device_name = 'Default Device';
        }

        $result = $user_profile->enroll_face($user_id, $descriptor, $device_name);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Face enrolled successfully!', 'recognition'),
            'data' => [
                'face_id' => $result,
            ],
        ], 201);
    }

    /**
     * Delete face profile
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_face($request) {
        $face_id = $request->get_param('id');
        $user_id = get_current_user_id();

        $user_profile = new FRL_User_Profile();
        $result = $user_profile->delete_face($face_id, $user_id);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Face profile deleted.', 'recognition'),
        ], 200);
    }

    /**
     * Get user logs
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_user_logs($request) {
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit') ?: 20;

        $user_profile = new FRL_User_Profile();
        $logs = $user_profile->get_auth_history($user_id, $limit);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'logs' => $logs['logs'],
                'total' => $logs['total'],
            ],
        ], 200);
    }

    /**
     * Export user data
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function export_user_data($request) {
        $user_id = get_current_user_id();
        $user_profile = new FRL_User_Profile();
        $data = $user_profile->export_user_data($user_id);

        if (is_wp_error($data)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $data->get_error_message(),
            ], 400);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], 200);
    }

    /**
     * Get enrolled users (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_enrolled_users($request) {
        $database = new FRL_Database();
        $users = $database->get_users_with_faces();

        return new \WP_REST_Response([
            'success' => true,
            'data' => $users,
        ], 200);
    }

    /**
     * Get all logs (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_all_logs($request) {
        $logger = new FRL_Logger();

        $args = [
            'limit' => $request->get_param('limit') ?: 50,
            'offset' => $request->get_param('offset') ?: 0,
            'user_id' => $request->get_param('user_id') ?: null,
            'result' => $request->get_param('result') ?: null,
        ];

        $logs = $logger->get_logs($args);

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'logs' => $logs['logs'],
                'total' => $logs['total'],
            ],
        ], 200);
    }

    /**
     * Get statistics (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_statistics($request) {
        $days = $request->get_param('days') ?: 30;

        $logger = new FRL_Logger();
        $stats = $logger->get_statistics($days);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $stats,
        ], 200);
    }

    /**
     * Get settings (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_settings($request) {
        $settings = FRL_Options::all();

        // Hide sensitive data
        unset($settings['encryption_key']);

        return new \WP_REST_Response([
            'success' => true,
            'data' => $settings,
        ], 200);
    }

    /**
     * Update settings (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function update_settings($request) {
        $new_settings = $request->get_json_params();

        // Validate and sanitize
        $settings = FRL_Options::all();

        // Allowed keys
        $allowed_keys = [
            'enabled',
            'match_threshold',
            'liveness_detection',
            'require_https',
            'require_password_fallback',
            'rate_limit_enabled',
            'max_failed_attempts',
            'lockout_minutes',
            'encrypt_descriptors',
            'log_authentications',
            'auto_delete_logs_days',
            'button_text',
            'max_faces_per_user',
        ];

        foreach ($allowed_keys as $key) {
            if (isset($new_settings[$key])) {
                $settings[$key] = $new_settings[$key];
            }
        }

        update_option('frl_settings', $settings);

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Settings updated.', 'recognition'),
            'data' => $settings,
        ], 200);
    }

    /**
     * Delete all faces for user (admin)
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function delete_user_faces($request) {
        $user_id = $request->get_param('user_id');

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid user ID.', 'recognition'),
            ], 400);
        }

        $user_profile = new FRL_User_Profile();
        $result = $user_profile->delete_all_faces($user_id);

        return new \WP_REST_Response([
            'success' => $result,
            'message' => $result ? __('User faces deleted.', 'recognition') : __('Failed to delete faces.', 'recognition'),
        ], $result ? 200 : 500);
    }

    /**
     * Admin enroll face for a specific user
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function admin_enroll_face($request) {
        $user_id = $request->get_param('user_id');
        $descriptor = $request->get_param('descriptor');
        $device_name = $request->get_param('device_name');

        if (!$user_id) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('User ID is required.', 'recognition'),
            ], 400);
        }

        if (empty($descriptor)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No face data provided.', 'recognition'),
            ], 400);
        }

        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('User not found.', 'recognition'),
            ], 404);
        }

        // Check capability
        if (!current_user_can('edit_user', $user_id)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('You do not have permission to enroll face for this user.', 'recognition'),
            ], 403);
        }

        $user_profile = new FRL_User_Profile();

        if (empty($device_name)) {
            $device_name = 'Admin Enrolled - ' . gmdate('Y-m-d H:i:s');
        }

        $result = $user_profile->enroll_face($user_id, $descriptor, $device_name);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        // Log the enrollment
        $logger = new FRL_Logger();
        $logger->log_event($user_id, 'enrollment_admin', true, [
            'enrolled_by' => get_current_user_id(),
            'device_name' => $device_name,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            /* translators: %s: user login name. */
            'message' => sprintf(__('Face enrolled successfully for user %s.', 'recognition'), $user->user_login),
            'data' => [
                'face_id' => $result,
                'user_id' => $user_id,
                'username' => $user->user_login,
            ],
        ], 201);
    }
}
