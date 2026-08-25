<?php
/**
 * Security class
 *
 * @package Face_Recognition_Login
 * @subpackage Security
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Security
 *
 * Handles security operations for the plugin
 *
 * @since 1.0.0
 */
class FRL_Security {

    /**
     * Failed attempts cache key
     *
     * @since 1.0.0
     * @var string
     */
    private $attempts_key = 'frl_failed_attempts';

    /**
     * Get client IP address
     *
     * @since 1.0.0
     * @return string
     */
    public function get_client_ip() {
        $ip = '';

        // S-9 / T2-19: allow site admins / hosting to register trusted reverse
        // proxies. When a request comes from a trusted proxy IP, we trust the
        // X-Forwarded-For / Client-IP headers. Otherwise we fall through to
        // REMOTE_ADDR.
        $remote_addr  = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $trusted_proxies = apply_filters( 'frl_trusted_proxy_ips', array() );
        $is_trusted_proxy = $remote_addr && is_array( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true );

        // Check for forwarded IP (from proxies) - validate to prevent spoofing.
        // Only honoured when the request originated from a trusted proxy.
        if ( $is_trusted_proxy && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded_ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            $first_ip = trim( $forwarded_ips[0] );
            if ( filter_var( $first_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                $ip = sanitize_text_field( wp_unslash( $first_ip ) );
            }
        }

        // Check for direct IP - more reliable
        if ( empty( $ip ) && ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $client_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            if ( filter_var( $client_ip, FILTER_VALIDATE_IP ) ) {
                $ip = $client_ip;
            }
        }

        // Fall back to REMOTE_ADDR (most reliable, cannot be spoofed)
        if ( empty( $ip ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        /**
         * Filters the resolved client IP.
         *
         * @since 1.0.0
         * @param string $ip The resolved client IP.
         */
        return (string) apply_filters( 'frl_client_ip', $ip );
    }

    /**
     * Static accessor for get_client_ip().
     *
     * Allows callers (such as the FRL_Database data layer) to resolve the
     * current request IP without having to instantiate an FRL_Security object.
     * The instance is created only on first use, so per-request overhead is
     * negligible.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_client_ip_static() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance->get_client_ip();
    }

    /**
     * Check if IP is rate limited
     *
     * @since 1.0.0
     * @param string $ip
     * @param int $max_attempts
     * @param int $lockout_minutes
     * @return bool
     */
    public function is_rate_limited($ip, $max_attempts = 5, $lockout_minutes = 15) {
        $attempts = $this->get_failed_attempts($ip);

        if ($attempts['count'] >= $max_attempts) {
            // Check if lockout has expired
            $lockout_time = strtotime($attempts['last_attempt']) + ($lockout_minutes * 60);

            if (time() > $lockout_time) {
                // Lockout expired, reset
                $this->clear_failed_attempts($ip);
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Get failed attempts for IP
     *
     * @since 1.0.0
     * @param string|null $ip
     * @return array
     */
    public function get_failed_attempts($ip = null) {
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }

        $attempts = get_transient($this->attempts_key . '_' . md5($ip));

        if (!$attempts) {
            return [
                'count' => 0,
                'last_attempt' => null,
            ];
        }

        return $attempts;
    }

    /**
     * Increment failed attempts
     *
     * @since 1.0.0
     * @param string|null $ip
     * @return void
     */
    public function increment_failed_attempts($ip = null) {
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }

        $key = $this->attempts_key . '_' . md5($ip);
        $attempts = $this->get_failed_attempts($ip);

        $attempts = [
            'count' => $attempts['count'] + 1,
            'last_attempt' => current_time('mysql'),
        ];

        set_transient($key, $attempts, 15 * MINUTE_IN_SECONDS);
    }

    /**
     * Clear failed attempts
     *
     * @since 1.0.0
     * @param string|null $ip
     * @return void
     */
    public function clear_failed_attempts($ip = null) {
        if ($ip === null) {
            $ip = $this->get_client_ip();
        }

        $key = $this->attempts_key . '_' . md5($ip);
        delete_transient($key);
    }

    /**
     * Verify nonce
     *
     * @since 1.0.0
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public function verify_nonce($nonce, $action = 'frl_nonce') {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Check if user has capability
     *
     * @since 1.0.0
     * @param int $user_id
     * @param string $capability
     * @return bool
     */
    public function user_can($user_id, $capability = 'manage_options') {
        $user = get_user_by('id', $user_id);

        if (!$user) {
            return false;
        }

        return user_can($user, $capability);
    }

    /**
     * Sanitize descriptor input
     *
     * @since 1.0.0
     * @param string $descriptor
     * @return string
     */
    public function sanitize_descriptor($descriptor) {
        // Remove any non-numeric characters except dots, commas, brackets, minus, and space
        return preg_replace('/[^\d.\-,\[\]\s]/', '', $descriptor);
    }

    /**
     * Generate secure random string
     *
     * @since 1.0.0
     * @param int $length
     * @return string
     */
    public function generate_random_string($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }

        return wp_generate_password($length, true, true);
    }

    /**
     * Validate HTTPS connection
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_https() {
        // Check multiple indicators
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        return false;
    }

    /**
     * Check if camera access is available
     *
     * @since 1.0.0
     * @return bool
     */
    public function can_access_camera() {
        // Camera access requires HTTPS (except localhost)
        if (!$this->is_https()) {
            $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
            if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate request origin
     *
     * @since 1.0.0
     * @return bool
     */
    public function validate_request_origin() {
        $referer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $home_url = home_url();

        if (empty($referer)) {
            return false;
        }

        return strpos($referer, $home_url) === 0;
    }

    /**
     * Get browser fingerprint
     *
     * @since 1.0.0
     * @return string
     */
    public function get_browser_fingerprint() {
        $components = [
            isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '',
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '',
            isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_ENCODING'])) : '',
        ];

        return md5(implode('|', $components));
    }

    /**
     * Encrypt data
     *
     * @since 1.0.0
     * @param string $data
     * @param string|null $key
     * @return string|false
     */
    public function encrypt($data, $key = null) {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }

        if ($key === null) {
            $key = $this->get_encryption_key();
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        if ($encrypted === false) {
            return false;
        }

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt data
     *
     * @since 1.0.0
     * @param string $data
     * @param string|null $key
     * @return string|false
     */
    public function decrypt($data, $key = null) {
        if (!function_exists('openssl_decrypt')) {
            return false;
        }

        if ($key === null) {
            $key = $this->get_encryption_key();
        }

        $parts = explode('::', base64_decode($data), 2);

        if (count($parts) !== 2) {
            return false;
        }

        list($encrypted, $iv) = $parts;

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }

    /**
     * Get or generate encryption key
     *
     * @since 1.0.0
     * @return string
     */
    private function get_encryption_key() {
        $key = get_option('frl_encryption_key', '');

        if (empty($key)) {
            $key = $this->generate_random_string(32);
            update_option('frl_encryption_key', $key);
        }

        return $key;
    }

    /**
     * Validate user ID
     *
     * @since 1.0.0
     * @param int $user_id
     * @return bool
     */
    public function is_valid_user($user_id) {
        $user = get_user_by('id', $user_id);
        return $user !== false;
    }

    /**
     * Check if current user owns the face profile
     *
     * @since 1.0.0
     * @param int $face_id
     * @param int|null $user_id
     * @return bool
     */
    public function owns_face_profile($face_id, $user_id = null) {
        global $wpdb;

        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $table = $wpdb->prefix . 'face_login';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, $face_id is sanitized via prepare.
        $owner = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $face_id
            )
        );

        return intval($owner) === intval($user_id);
    }
}
