<?php
/**
 * Helper class
 *
 * @package Face_Recognition_Login
 * @subpackage Helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Helper
 *
 * Utility helper functions
 *
 * @since 1.0.0
 */
class FRL_Helper {

    /**
     * Get plugin settings
     *
     * @since 1.0.0
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_setting($key, $default = null) {
        $options = FRL_Options::all();
        return $options[$key] ?? $default;
    }

    /**
     * Update plugin setting
     *
     * @since 1.0.0
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_setting($key, $value) {
        $options = FRL_Options::all();
        $options[$key] = $value;
        return update_option('frl_settings', $options);
    }

    /**
     * Get all settings
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_all_settings() {
        return FRL_Options::all();
    }

    /**
     * Check if plugin is enabled
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_enabled() {
        return (bool) self::get_setting('enabled', true);
    }

    /**
     * Check if HTTPS is required
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_https_required() {
        return (bool) self::get_setting('require_https', true);
    }

    /**
     * Check if current connection is secure
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_secure_connection() {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Get user-friendly error message
     *
     * @since 1.0.0
     * @param string $error_code
     * @return string
     */
    public static function get_error_message($error_code) {
        $messages = [
            'frl_invalid_nonce' => __('Security check failed. Please try again.', 'recognition'),
            'frl_invalid_descriptor' => __('Invalid face data. Please try again.', 'recognition'),
            'frl_no_faces_enrolled' => __('No face enrolled. Please enroll your face first.', 'recognition'),
            'frl_authentication_failed' => __('Face recognition failed. Please try again or use password.', 'recognition'),
            'frl_rate_limited' => __('Too many failed attempts. Please wait a few minutes.', 'recognition'),
            'frl_https_required' => __('HTTPS is required for face login.', 'recognition'),
            'frl_permission_denied' => __('You do not have permission to perform this action.', 'recognition'),
            'frl_user_not_found' => __('User not found.', 'recognition'),
            'frl_max_faces_reached' => __('Maximum number of face profiles reached.', 'recognition'),
            'frl_save_failed' => __('Failed to save face data.', 'recognition'),
            'frl_face_not_found' => __('Face profile not found.', 'recognition'),
            'frl_unauthorized' => __('Unauthorized access.', 'recognition'),
        ];

        return $messages[$error_code] ?? __('An error occurred. Please try again.', 'recognition');
    }

    /**
     * Format response for AJAX/REST
     *
     * @since 1.0.0
     * @param bool $success
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return array
     */
    public static function format_response($success, $data = null, $message = '', $status = 200) {
        $response = [
            'success' => $success,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if ($status >= 400) {
            $response['error'] = true;
        }

        return $response;
    }

    /**
     * Send JSON response
     *
     * @since 1.0.0
     * @param array $response
     * @param int $status
     */
    public static function send_json($response, $status = 200) {
        status_header($status);
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode($response);
        wp_die();
    }

    /**
     * Get current user ID or 0
     *
     * @since 1.0.0
     * @return int
     */
    public static function get_current_user_id() {
        $user_id = get_current_user_id();
        return $user_id ? $user_id : 0;
    }

    /**
     * Check if user is logged in
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_user_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Get user by ID
     *
     * @since 1.0.0
     * @param int $user_id
     * @return \WP_User|false
     */
    public static function get_user($user_id) {
        return get_user_by('id', $user_id);
    }

    /**
     * Get device name from user agent
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_device_name() {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $ua)) {
            if (preg_match('/ipad/i', $ua)) {
                return 'iPad';
            }
            if (preg_match('/iphone/i', $ua)) {
                return 'iPhone';
            }
            if (preg_match('/android/i', $ua)) {
                return 'Android Device';
            }
            return 'Mobile Device';
        }

        if (preg_match('/windows/i', $ua)) {
            return 'Windows PC';
        }

        if (preg_match('/macintosh|mac os/i', $ua)) {
            return 'Mac';
        }

        if (preg_match('/linux/i', $ua)) {
            return 'Linux PC';
        }

        return 'Unknown Device';
    }

    /**
     * Sanitize descriptor array
     *
     * @since 1.0.0
     * @param array $descriptor
     * @return array
     */
    public static function sanitize_descriptor($descriptor) {
        if (!is_array($descriptor)) {
            return [];
        }

        return array_map(function ($value) {
            return floatval($value);
        }, array_slice($descriptor, 0, 128));
    }

    /**
     * Format datetime for display
     *
     * @since 1.0.0
     * @param string $datetime
     * @param string $format
     * @return string
     */
    public static function format_datetime($datetime, $format = '') {
        if (empty($datetime)) {
            return '-';
        }

        if (empty($format)) {
            $format = get_option('date_format') . ' ' . get_option('time_format');
        }

        return wp_date($format, strtotime($datetime));
    }

    /**
     * Get time ago string
     *
     * @since 1.0.0
     * @param string $datetime
     * @return string
     */
    public static function time_ago($datetime) {
        if (empty($datetime)) {
            return __('Never', 'recognition');
        }

        $time_ago = strtotime($datetime);
        $current_time = current_time('timestamp');

        $difference = $current_time - $time_ago;

        $intervals = [
            31536000 => __('year', 'recognition'),
            2592000 => __('month', 'recognition'),
            604800 => __('week', 'recognition'),
            86400 => __('day', 'recognition'),
            3600 => __('hour', 'recognition'),
            60 => __('minute', 'recognition'),
            1 => __('second', 'recognition'),
        ];

        foreach ($intervals as $seconds => $label) {
            $remaining = $difference / $seconds;

            if ($remaining >= 1) {
                $rounded = round($remaining);
                return sprintf(
                    /* translators: 1: number of time units, 2: time unit label (e.g. hour, day). */
                    __('%1$d %2$s ago', 'recognition'),
                    $rounded,
                    $label . ($rounded > 1 ? 's' : '')
                );
            }
        }

        return __('Just now', 'recognition');
    }

    /**
     * Check if on login page
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_login_page() {
        if ( isset( $GLOBALS['pagenow'] ) ) {
            $page_now = $GLOBALS['pagenow'];
            if ( in_array( $page_now, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get login URL
     *
     * @since 1.0.0
     * @param string $redirect
     * @return string
     */
    public static function get_login_url($redirect = '') {
        return wp_login_url($redirect);
    }

    /**
     * Get logout URL
     *
     * @since 1.0.0
     * @param string $redirect
     * @return string
     */
    public static function get_logout_url($redirect = '') {
        return wp_logout_url($redirect);
    }

    /**
     * Debug log
     *
     * @since 1.0.0
     * @param mixed $message
     */
    public static function debug_log($message) {
    }

    /**
     * Get plugin version
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_version() {
        return FRL_PLUGIN_VERSION;
    }

    /**
     * Get plugin URL
     *
     * @since 1.0.0
     * @param string $path
     * @return string
     */
    public static function plugin_url($path = '') {
        return FRL_PLUGIN_URL . ltrim($path, '/');
    }

    /**
     * Get plugin path
     *
     * @since 1.0.0
     * @param string $path
     * @return string
     */
    public static function plugin_path($path = '') {
        return FRL_PLUGIN_PATH . ltrim($path, '/');
    }
}
