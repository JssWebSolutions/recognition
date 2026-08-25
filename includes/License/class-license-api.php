<?php
/**
 * License API Client
 *
 * Handles HTTPS communication with the remote license server.
 * Performs all HTTP transport, payload construction, and response
 * normalization for license activation, validation, info lookup,
 * and deactivation requests.
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
 * Class FRL_License_API
 *
 * Stateless HTTP transport wrapper for license server endpoints.
 *
 * @since 1.0.0
 */
class FRL_License_API {

    /**
     * License server base URL.
     *
     * @since 1.0.0
     * @var string
     */
    const ENDPOINT_BASE = 'https://license.jsswebsolutions.com';

    /**
     * Default HTTP request timeout (seconds).
     *
     * @since 1.0.0
     * @var int
     */
    const DEFAULT_TIMEOUT = 15;

    /**
     * Maximum retries for transient network failures.
     *
     * @since 1.0.0
     * @var int
     */
    const MAX_RETRIES = 1;

    /**
     * Build the full URL for a named endpoint.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint name (e.g. 'validate', 'check').
     * @return string
     */
    public static function get_endpoint_url($endpoint) {
        $endpoint = ltrim($endpoint, '/');
        $base = untrailingslashit( self::get_endpoint_base() );
        return $base . '/api/' . $endpoint . '.php';
    }

    /**
     * Return the license-server base URL.
     *
     * T2-9: Exposes a `frl_license_endpoint_base` filter so installations
     * behind a firewall, on a private network, or routing through a
     * mirror can override the host without forking the plugin.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_endpoint_base() {
        /**
         * Filters the license-server base URL.
         *
         * @since 1.0.0
         * @param string $base Default license server base URL.
         */
        return (string) apply_filters( 'frl_license_endpoint_base', self::ENDPOINT_BASE );
    }

    /**
     * Validate a license key + domain + email with the license server.
     *
     * The full set of attributes (license_key, domain, email) is sent
     * on every call so the server can enforce the complete validation
     * rules. The previous implementation only sent the key and the
     * domain, which allowed a stolen key to validate against an
     * unrelated email/domain. The server is authoritative: if any of
     * the three do not match, the response carries a non-`valid` code
     * (e.g. `email_mismatch`, `domain_mismatch`).
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @param string $domain      Domain being validated.
     * @param string $email       Customer email (registered against the license).
     * @return array {
     *     @type bool   $success Whether the HTTP request completed.
     *     @type array  $data    Normalized response payload (server body if available).
     *     @type string $error   Error message on failure (network only when status=0).
     *     @type int    $status  HTTP status code (0 for network failure).
     * }
     */
    public static function validate($license_key, $domain, $email = '') {
        $payload = [
            'license_key' => $license_key,
            'domain'      => $domain,
            'email'       => $email,
        ];

        $result = self::post('validate', $payload);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => $result->get_error_message(),
                'status'  => 0,
            ];
        }

        $body   = self::parse_response_body($result);
        $code   = (int) ($result['response']['code'] ?? 0);
        $server_message = self::extract_server_message($body, $code);

        return [
            'success' => $code >= 200 && $code < 300,
            'data'    => $body,
            'error'   => $server_message,
            'status'  => $code,
        ];
    }

    /**
     * Fetch license information by key (no domain binding).
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @return array Response envelope.
     */
    public static function check($license_key) {
        $result = self::get('check', ['license_key' => $license_key]);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => $result->get_error_message(),
                'status'  => 0,
            ];
        }

        $body = self::parse_response_body($result);
        $code = (int) ($result['response']['code'] ?? 0);
        $server_message = self::extract_server_message($body, $code);

        return [
            'success' => $code >= 200 && $code < 300,
            'data'    => $body,
            'error'   => $server_message,
            'status'  => $code,
        ];
    }

    /**
     * Activate a license for a domain on the license server.
     *
     * This endpoint requires authentication (customer session or master
     * API key). The plugin will attempt the call with a master API key
     * if one is configured; otherwise it falls back to local-only
     * activation which is the common path for the client plugin.
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @param string $domain      Domain being activated.
     * @param string $email       Customer email (registered against the license).
     * @return array Response envelope.
     */
    public static function activate($license_key, $domain, $email = '') {
        $payload = [
            'license_key' => $license_key,
            'domain'      => $domain,
            'email'       => $email,
        ];

        $result = self::post('activate', $payload, true);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => $result->get_error_message(),
                'status'  => 0,
            ];
        }

        $body = self::parse_response_body($result);
        $code = (int) ($result['response']['code'] ?? 0);
        $server_message = self::extract_server_message($body, $code);

        return [
            'success' => $code >= 200 && $code < 300,
            'data'    => $body,
            'error'   => $server_message,
            'status'  => $code,
        ];
    }

    /**
     * Deactivate a license for a domain on the license server.
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @param string $domain      Domain being deactivated.
     * @param string $email       Customer email (registered against the license).
     * @return array Response envelope.
     */
    public static function deactivate($license_key, $domain, $email = '') {
        $payload = [
            'license_key' => $license_key,
            'domain'      => $domain,
            'email'       => $email,
        ];

        $result = self::post('deactivate', $payload, true);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => $result->get_error_message(),
                'status'  => 0,
            ];
        }

        $body = self::parse_response_body($result);
        $code = (int) ($result['response']['code'] ?? 0);
        $server_message = self::extract_server_message($body, $code);

        return [
            'success' => $code >= 200 && $code < 300,
            'data'    => $body,
            'error'   => $server_message,
            'status'  => $code,
        ];
    }

    /**
     * Perform a POST request to a license server endpoint.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint name.
     * @param array  $payload  Request body.
     * @param bool   $auth     Whether to attach the master API key header.
     * @return array|WP_Error
     */
    protected static function post($endpoint, $payload, $auth = false) {
        $url = self::get_endpoint_url($endpoint);

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept'       => 'application/json',
            'User-Agent'   => self::get_user_agent(),
        ];

        if ($auth) {
            $api_key = self::get_master_api_key();
            if (!empty($api_key)) {
                $headers['X-API-Key'] = $api_key;
            }
        }

        $args = [
            'method'      => 'POST',
            'timeout'     => self::DEFAULT_TIMEOUT,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => $headers,
            'body'        => wp_json_encode($payload),
            'sslverify'   => true,
        ];

        $attempts = 0;
        $result   = null;

        while ($attempts <= self::MAX_RETRIES) {
            $result = wp_remote_post($url, $args);
            $attempts++;

            if (!is_wp_error($result)) {
                $code = (int) wp_remote_retrieve_response_code($result);
                if ($code >= 200 && $code < 500) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Perform a GET request to a license server endpoint.
     *
     * @since 1.0.0
     * @param string $endpoint Endpoint name.
     * @param array  $params   Query string parameters.
     * @return array|WP_Error
     */
    protected static function get($endpoint, $params = []) {
        $url = self::get_endpoint_url($endpoint);

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = [
            'timeout'     => self::DEFAULT_TIMEOUT,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => [
                'Accept'     => 'application/json',
                'User-Agent' => self::get_user_agent(),
            ],
            'sslverify'   => true,
        ];

        return wp_remote_get($url, $args);
    }

    /**
     * Decode a JSON response body into a normalized array.
     *
     * @since 1.0.0
     * @param array $result Raw wp_remote_post / wp_remote_get result.
     * @return array
     */
    protected static function parse_response_body($result) {
        $body = wp_remote_retrieve_body($result);
        if (empty($body)) {
            return [];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return ['raw' => $body];
        }

        return $decoded;
    }

    /**
     * Extract a human-readable error message from a server response body.
     *
     * Looks for `message`, `error`, or `code` fields in the JSON body.
     * Falls back to a generic HTTP-status based message if the body
     * is empty or unparseable.
     *
     * @since 1.0.0
     * @param array $body Decoded response body.
     * @param int   $code HTTP status code.
     * @return string
     */
    protected static function extract_server_message($body, $code) {
        if (is_array($body) && !empty($body['message']) && is_string($body['message'])) {
            return trim($body['message']);
        }

        if (is_array($body) && !empty($body['error']) && is_string($body['error'])) {
            return trim($body['error']);
        }

        // Generic fallback by HTTP status.
        if ($code >= 500) {
            return sprintf(
                /* translators: %d: HTTP status code */
                __('License server returned an error (HTTP %d). Please try again later.', 'recognition'),
                $code
            );
        }

        if ($code >= 400) {
            return sprintf(
                /* translators: %d: HTTP status code */
                __('License server rejected the request (HTTP %d).', 'recognition'),
                $code
            );
        }

        return '';
    }

    /**
     * Build the User-Agent string sent to the license server.
     *
     * @since 1.0.0
     * @return string
     */
    protected static function get_user_agent() {
        return sprintf(
            'FRL-LicenseClient/%1$s; WordPress/%2$s; PHP/%3$s; Site/%4$s',
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0',
            get_bloginfo('version'),
            PHP_VERSION,
            home_url('/')
        );
    }

    /**
     * Retrieve the configured master API key, if any.
     *
     * @since 1.0.0
     * @return string
     */
    protected static function get_master_api_key() {
        $key = get_option('frl_license_api_key', '');
        return is_string($key) ? trim($key) : '';
    }
}
