<?php
/**
 * License AJAX Handler
 *
 * Handles all AJAX requests for the License Activation Module.
 * All endpoints require the caller to have manage_options
 * capability, are nonce-protected, and sanitise/escape inputs
 * and outputs in line with WordPress coding standards.
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_License_Ajax
 *
 * @since 1.0.0
 */
class FRL_License_Ajax {

    /**
     * Nonce action used for all license AJAX requests.
     *
     * @since 1.0.0
     * @var string
     */
    const NONCE_ACTION = 'frl_license_nonce';

    /**
     * Register AJAX hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('wp_ajax_frl_activate_license', [$this, 'handle_activate']);
        add_action('wp_ajax_frl_deactivate_license', [$this, 'handle_deactivate']);
        add_action('wp_ajax_frl_validate_license', [$this, 'handle_validate']);
        add_action('wp_ajax_frl_get_license_status', [$this, 'handle_get_status']);
    }

    /**
     * Send a JSON success response and die.
     *
     * @since 1.0.0
     * @param mixed $data    Optional payload.
     * @param string $message Optional message.
     */
    protected function send_success($data = null, $message = '') {
        $response = ['success' => true];
        if (null !== $data) {
            $response['data'] = $data;
        }
        if ('' !== $message) {
            $response['message'] = $message;
        }
        wp_send_json($response);
    }

    /**
     * Send a JSON error response and die.
     *
     * @since 1.0.0
     * @param string $message Error message.
     * @param int    $status  HTTP-style status code.
     * @param string $code    Optional machine-readable code.
     */
    protected function send_error($message, $status = 400, $code = '') {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ('' !== $code) {
            $response['code'] = $code;
        }
        wp_send_json($response, $status);
    }

    /**
     * Verify the request has a valid nonce and admin capability.
     *
     * @since 1.0.0
     * @return bool True if valid; false and JSON response already sent if not.
     */
    protected function verify_request() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            $this->send_error(
                __('Security check failed. Please reload the page and try again.', 'recognition'),
                403,
                'invalid_nonce'
            );
            return false;
        }

        if (!current_user_can('manage_options')) {
            $this->send_error(
                __('You do not have permission to perform this action.', 'recognition'),
                403,
                'forbidden'
            );
            return false;
        }

        return true;
    }

    /**
     * Read a POST value and apply sanitization.
     *
     * @since 1.0.0
     * @param string $key     POST key.
     * @param string $type    Sanitization type: 'text', 'email', 'key'.
     * @return string
     */
    protected function post($key, $type = 'text') {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified at the AJAX entry point (wp_ajax_* action) before this helper is called.
        if (!isset($_POST[$key])) {
            return '';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is sanitized below; nonce is checked at the AJAX entry point.
        $value = wp_unslash($_POST[$key]);
        if (!is_string($value)) {
            return '';
        }
        switch ($type) {
            case 'email':
                return sanitize_email($value);
            case 'key':
                return FRL_License_Manager::sanitize_license_key($value);
            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * AJAX handler: activate license.
     *
     * @since 1.0.0
     */
    public function handle_activate() {
        if (!$this->verify_request()) {
            return;
        }

        $license_key = $this->post('license_key', 'key');
        $email       = $this->post('email', 'email');

        $manager = FRL_License_Manager::get_instance();
        $result  = $manager->activate_license($license_key, $email);

        if ($result['success']) {
            $this->send_success(
                ['license' => $manager->get_license_data()],
                $result['message']
            );
        } else {
            $this->send_error($result['message'], 400, $result['code']);
        }
    }

    /**
     * AJAX handler: deactivate license.
     *
     * @since 1.0.0
     */
    public function handle_deactivate() {
        if (!$this->verify_request()) {
            return;
        }

        $manager = FRL_License_Manager::get_instance();
        $result  = $manager->deactivate_license();

        $this->send_success(
            ['license' => $manager->get_license_data()],
            $result['message']
        );
    }

    /**
     * AJAX handler: re-validate the stored license.
     *
     * @since 1.0.0
     */
    public function handle_validate() {
        if (!$this->verify_request()) {
            return;
        }

        $manager = FRL_License_Manager::get_instance();
        $result  = $manager->validate_remote();

        if ($result['success']) {
            $this->send_success(
                ['license' => $manager->get_license_data()],
                $result['message']
            );
        } else {
            $this->send_error($result['message'], 400, $result['code']);
        }
    }

    /**
     * AJAX handler: return the current license status payload.
     *
     * @since 1.0.0
     */
    public function handle_get_status() {
        if (!$this->verify_request()) {
            return;
        }

        $manager = FRL_License_Manager::get_instance();
        $this->send_success(
            [
                'license' => $manager->get_license_data(),
                'site'    => [
                    'domain'         => FRL_License_Manager::get_site_domain(),
                    'wp_version'     => get_bloginfo('version'),
                    'php_version'    => PHP_VERSION,
                    'plugin_version' => defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '',
                ],
            ]
        );
    }
}
