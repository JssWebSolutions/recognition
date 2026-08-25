<?php
/**
 * License Manager
 *
 * Core class for managing plugin license state. Handles local
 * persistence (WordPress options table), validation against the
 * remote license server, scheduled re-validation, and exposes
 * helpers used by the rest of the plugin to determine whether
 * premium features should be unlocked.
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
 * Class FRL_License_Manager
 *
 * @since 1.0.0
 */
class FRL_License_Manager {

    /**
     * Option key for the license data payload.
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_KEY = 'frl_license_data';

    /**
     * Option key for license status cache (lightweight status used by
     * feature gate / notices).
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_STATUS_KEY = 'frl_license_status';

    /**
     * Option key for the dismissed admin notice.
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_NOTICE_KEY = 'frl_license_notice_dismissed';

    /**
     * Option key for storing the grace period timestamp.
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_LAST_CHECK_KEY = 'frl_license_last_check';

    /**
     * Transient key used to throttle validation requests.
     *
     * @since 1.0.0
     * @var string
     */
    const TRANSIENT_THROTTLE = 'frl_license_throttle_';

    /**
     * Transient key used to throttle revalidation requests site-wide.
     *
     * The previous implementation only throttled the `activate` action.
     * That meant an attacker (or a buggy client) could re-trigger
     * `validate_remote()` in a tight loop. The transient below
     * provides a server-side cooldown that is independent of the
     * client and therefore cannot be bypassed by tampering with the
     * JavaScript.
     *
     * @since 1.0.0
     * @var string
     */
    const TRANSIENT_REVALIDATE_THROTTLE = 'frl_license_revalidate_throttle';

    /**
     * Length of the server-side revalidate throttle, in seconds.
     *
     * @since 1.0.0
     * @var int
     */
    const REVALIDATE_THROTTLE_SECONDS = 30;

    /**
     * Hook name for the daily re-validation cron event.
     *
     * @since 1.0.0
     * @var string
     */
    const CRON_HOOK = 'frl_license_daily_revalidate';

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var FRL_License_Manager
     */
    private static $instance = null;

    /**
     * Cached license data for the current request.
     *
     * @since 1.0.0
     * @var array|null
     */
    private $cache = null;

    /**
     * Get singleton instance.
     *
     * @since 1.0.0
     * @return FRL_License_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Registers cron hook and admin notice handler.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action(self::CRON_HOOK, [$this, 'scheduled_revalidate']);
        add_action('admin_init', [$this, 'handle_notice_dismissal']);
        add_action('wp_ajax_frl_dismiss_license_notice', [$this, 'handle_notice_dismissal']);
        add_action('admin_notices', [$this, 'maybe_render_license_notice']);
    }

    /**
     * Register the daily re-validation cron schedule.
     *
     * @since 1.0.0
     */
    public static function register_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Unregister the daily re-validation cron schedule.
     *
     * @since 1.0.0
     */
    public static function unregister_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Cron callback: re-validate the stored license against the server.
     *
     * Uses a grace period so a transient outage does not immediately
     * downgrade a working license.
     *
     * @since 1.0.0
     */
    public function scheduled_revalidate() {
        $data = $this->get_license_data();
        if (empty($data['license_key']) || empty($data['domain'])) {
            return;
        }

        // Skip if last successful check was very recent.
        $last_check = (int) get_option(self::OPTION_LAST_CHECK_KEY, 0);
        if ($last_check && (time() - $last_check) < 6 * HOUR_IN_SECONDS) {
            return;
        }

        // Pass the stored email so the server-side check is also
        // applied during the daily cron revalidation. Silent = true
        // because we don't want to spam the admin with notices on
        // every cron run.
        $this->validate_remote(
            $data['license_key'],
            $data['domain'],
            $data['email'] ?? '',
            true
        );
    }

    /**
     * Get the full stored license data payload.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_license_data() {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $data = get_option(self::OPTION_KEY, []);

        if (!is_array($data)) {
            $data = [];
        }

        // Backfill defaults.
        $data = wp_parse_args($data, [
            'license_key'      => '',
            'email'            => '',
            'status'           => 'inactive', // inactive | active | expired | revoked | invalid | suspended | grace | offline
            'plan'             => '',
            'plan_id'          => 0,
            'domain'           => '',
            'registered_domain'=> '',
            'issued_at'        => '',
            'activated_at'     => '',
            'expires_at'       => '',
            'last_validated_at' => '',
            'last_check_at'    => '',
            'message'          => '',
            'code'             => '',
            'metadata'         => [],
        ]);

        $this->cache = $data;
        return $data;
    }

    /**
     * Update the stored license data.
     *
     * @since 1.0.0
     * @param array $data Partial or full license data.
     * @return bool
     */
    public function update_license_data($data) {
        $current = $this->get_license_data();
        $merged  = array_merge($current, $data);
        $this->cache = $merged;
        $updated = update_option(self::OPTION_KEY, $merged, false);

        if (!empty($merged['status'])) {
            update_option(self::OPTION_STATUS_KEY, $merged['status'], false);
        }

        return $updated;
    }

    /**
     * Clear all stored license data.
     *
     * @since 1.0.0
     * @return bool
     */
    public function clear_license_data() {
        $this->cache = null;
        delete_option(self::OPTION_KEY);
        delete_option(self::OPTION_STATUS_KEY);
        delete_option(self::OPTION_NOTICE_KEY);
        delete_option(self::OPTION_LAST_CHECK_KEY);
        return true;
    }

    /**
     * Check whether the plugin currently has a valid license.
     *
     * The previous version of this method only checked the local
     * status string. That meant a copy/paste of the `frl_license_data`
     * option value (carrying status='active') from another site would
     * pass the check. The fix below also verifies the signature that
     * was stored alongside the cache: the signature is bound to the
     * license_key + email + domain + a site-specific salt, so it
     * cannot be reproduced outside the site that originally created
     * it.
     *
     * @since 1.0.0
     * @param bool $grace Whether to consider "grace" status as valid.
     * @return bool
     */
    public function is_license_valid($grace = true) {
        $data = $this->get_license_data();
        $status = isset($data['status']) ? $data['status'] : 'inactive';

        if ('active' === $status) {
            return self::is_signature_valid($data);
        }

        if ($grace && 'grace' === $status) {
            return self::is_signature_valid($data);
        }

        return false;
    }

    /**
     * Check whether the license allows premium features.
     *
     * @since 1.0.0
     * @return bool
     */
    public function has_premium() {
        return $this->is_license_valid(true);
    }

    /**
     * Get the current license status string.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_status() {
        $data = $this->get_license_data();
        return isset($data['status']) ? $data['status'] : 'inactive';
    }

    /**
     * Determine the current site domain (host only).
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_site_domain() {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (!$host) {
            $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        }
        $host = preg_replace('/^www\./i', '', strtolower(trim((string) $host)));
        return $host;
    }

    /**
     * Sanitize a license key string.
     *
     * @since 1.0.0
     * @param string $key Raw input.
     * @return string
     */
    public static function sanitize_license_key($key) {
        $key = strtoupper(trim((string) $key));
        $key = preg_replace('/[^A-Z0-9\-]/', '', $key);
        return $key;
    }

    /**
     * Sanitize an email address.
     *
     * @since 1.0.0
     * @param string $email Raw input.
     * @return string
     */
    public static function sanitize_email($email) {
        return sanitize_email(trim((string) $email));
    }

    /**
     * Validate a license key + email against the server.
     *
     * This is the main entry point called from the admin UI.
     * On success, the license data is persisted and premium
     * features become available.
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @param string $email       Customer email.
     * @return array {
     *     @type bool  $success
     *     @type string $message
     *     @type array  $data   Stored license data on success.
     *     @type string $code   Server-provided status code.
     * }
     */
    public function activate_license($license_key, $email) {
        $license_key = self::sanitize_license_key($license_key);
        $email       = self::sanitize_email($email);
        $domain      = self::get_site_domain();

        if ('' === $license_key) {
            return [
                'success' => false,
                'code'    => 'invalid_key',
                'message' => __('Please enter a valid license key.', 'recognition'),
            ];
        }

        if (!is_email($email)) {
            return [
                'success' => false,
                'code'    => 'invalid_email',
                'message' => __('Please enter a valid email address.', 'recognition'),
            ];
        }

        // Throttle: avoid spamming the server from the admin UI.
        if ($this->is_throttled('activate')) {
            return [
                'success' => false,
                'code'    => 'throttled',
                'message' => __('Please wait a few seconds before trying again.', 'recognition'),
            ];
        }
        $this->set_throttle('activate', 5);

        // Send the FULL set of attributes (license_key + email + domain)
        // so the server can enforce the complete validation rules.
        $response = FRL_License_API::validate($license_key, $domain, $email);

        if (!$response['success']) {
            // Network or HTTP failure - allow offline grace for an already active license.
            $existing = $this->get_license_data();
            if ('active' === ($existing['status'] ?? '') && strcasecmp($existing['email'] ?? '', $email) === 0) {
                $this->update_license_data([
                    'status'           => 'offline',
                    'last_check_at'    => current_time('mysql'),
                    'message'          => __('License server unreachable. Using cached license.', 'recognition'),
                ]);
                return [
                    'success' => true,
                    'code'    => 'offline',
                    'message' => __('Could not reach the license server. Your previous activation will be used.', 'recognition'),
                    'data'    => $this->get_license_data(),
                ];
            }

            $this->update_license_data([
                'license_key' => $license_key,
                'email'       => $email,
                'domain'      => $domain,
                'status'      => 'invalid',
                'message'     => $response['error'] ?: __('Could not reach the license server.', 'recognition'),
            ]);

            return [
                'success' => false,
                'code'    => 'network_error',
                'message' => $response['error'] ?: __('Could not reach the license server. Please try again later.', 'recognition'),
            ];
        }

        $body = $response['data'];

        $is_valid = !empty($body['valid']);
        $server_code = isset($body['code']) ? sanitize_key($body['code']) : '';
        $server_message = isset($body['message']) ? sanitize_text_field($body['message']) : '';

        // Server-side authoritative checks. Even when the HTTP
        // response is 200, the server can return a non-valid code
        // (e.g. email_mismatch) which must be treated as a hard
        // DENIED. The previous code only checked $is_valid, which
        // was a strict-server vs client mismatch.
        if (!$is_valid || in_array($server_code, ['email_mismatch', 'invalid_email', 'domain_mismatch'], true)) {
            $mapped = self::map_invalid_status($server_code);
            $user_message = $server_message;
            if ('email_mismatch' === $server_code) {
                $user_message = __('The email address does not match the email registered against this license. Activation denied.', 'recognition');
            } elseif ('invalid_email' === $server_code) {
                $user_message = __('Please enter a valid email address.', 'recognition');
            } elseif ('' === $user_message) {
                $user_message = __('License is not valid.', 'recognition');
            }
            $this->update_license_data([
                'license_key' => $license_key,
                'email'       => $email,
                'domain'      => $domain,
                'status'      => $mapped,
                'code'        => $server_code,
                'message'     => $user_message,
                'last_check_at' => current_time('mysql'),
            ]);

            return [
                'success' => false,
                'code'    => $server_code ?: 'invalid',
                'message' => $user_message,
            ];
        }

        // Build successful activation payload. The server is the
        // authoritative source for the registered email/domain – use
        // them directly, never trust the client.
        $license_info = isset($body['license']) && is_array($body['license']) ? $body['license'] : [];
        $registered_domain = isset($license_info['domain']) && !empty($license_info['domain'])
            ? sanitize_text_field($license_info['domain'])
            : (isset($body['registered_domain']) ? sanitize_text_field($body['registered_domain']) : $domain);
        $registered_email  = isset($body['registered_email']) ? strtolower((string) $body['registered_email']) : strtolower($email);

        // Defence in depth: even though the server returned valid,
        // re-verify locally that the email we sent matches the
        // canonical email on file. The server already does this, but
        // a server compromise or proxy cache between us and the
        // server should not be sufficient to bypass the local check.
        if (strcasecmp($registered_email, $email) !== 0) {
            $this->update_license_data([
                'license_key' => $license_key,
                'email'       => $email,
                'domain'      => $domain,
                'status'      => 'email_mismatch',
                'code'        => 'email_mismatch',
                'message'     => __('The email address does not match the records for this license.', 'recognition'),
            ]);
            return [
                'success' => false,
                'code'    => 'email_mismatch',
                'message' => __('The email address does not match the records for this license.', 'recognition'),
            ];
        }

        // Verify domain matches.
        if (!empty($registered_domain) && strcasecmp($registered_domain, $domain) !== 0) {
            $this->update_license_data([
                'license_key' => $license_key,
                'email'       => $email,
                'domain'      => $domain,
                'status'      => 'domain_mismatch',
                'code'        => 'domain_mismatch',
                'message'     => sprintf(
                    /* translators: 1: registered domain, 2: current domain */
                    __('This license is registered for %1$s but the current site is %2$s.', 'recognition'),
                    $registered_domain,
                    $domain
                ),
            ]);
            return [
                'success' => false,
                'code'    => 'domain_mismatch',
                'message' => sprintf(
                    /* translators: 1: registered domain, 2: current domain */
                    __('This license is registered for %1$s but the current site is %2$s.', 'recognition'),
                    $registered_domain,
                    $domain
                ),
            ];
        }

        $now_mysql = current_time('mysql');
        $status    = 'active';
        $code      = 'valid';

        if (isset($license_info['in_grace']) && $license_info['in_grace']) {
            $status = 'grace';
            $code   = 'grace';
        }

        $this->update_license_data([
            'license_key'       => $license_key,
            'email'             => $registered_email,
            'domain'            => $domain,
            'registered_domain' => $registered_domain,
            'status'            => $status,
            'code'              => $code,
            'plan'              => isset($license_info['plan']) ? sanitize_text_field($license_info['plan']) : '',
            'plan_id'           => isset($license_info['id']) ? (int) $license_info['id'] : 0,
            'issued_at'         => isset($license_info['issued_at']) ? sanitize_text_field($license_info['issued_at']) : '',
            'activated_at'      => $now_mysql,
            'expires_at'        => isset($license_info['expires_at']) ? sanitize_text_field($license_info['expires_at']) : '',
            'last_validated_at' => $now_mysql,
            'last_check_at'     => $now_mysql,
            'message'           => $server_message ?: __('License activated successfully.', 'recognition'),
            'metadata'          => $license_info,
            // Domain-bound signature so a copy/paste of the option
            // value to another site cannot be used tobypass the
            // server check. The signature is recomputed on every
            // successful activation and revalidated on every page
            // load; if it ever stops matching, the local cache is
            // treated as stale and the plugin re-validates against
            // the server.
            'signature'         => self::compute_signature($license_key, $registered_email, $registered_domain),
        ]);

        update_option(self::OPTION_LAST_CHECK_KEY, time(), false);
        delete_option(self::OPTION_NOTICE_KEY);

        // Activate the server-side revalidate throttle so the
        // post-activation page reload cannot immediately trigger
        // another revalidation request.
        $this->set_revalidate_throttle();

        return [
            'success' => true,
            'code'    => $code,
            'message' => $server_message ?: __('License activated successfully.', 'recognition'),
            'data'    => $this->get_license_data(),
        ];
    }

    /**
     * Re-validate the stored license without changing the stored key.
     *
     * The same checks used during activation are enforced here: the
     * server re-confirms the email + domain + status on every call.
     * If any of those fail, the local cache is immediately downgraded
     * to reflect the new state so the feature gate stops working
     * until the user re-activates with the correct credentials.
     *
     * @since 1.0.0
     * @param string $license_key License key (optional; uses stored if empty).
     * @param string $domain      Domain (optional; uses stored if empty).
     * @param string $email       Email (optional; uses stored if empty).
     * @param bool   $silent      If true, do not generate a user-facing message.
     * @return array Response envelope.
     */
    public function validate_remote($license_key = '', $domain = '', $email = '', $silent = false) {
        $stored = $this->get_license_data();
        if ('' === $license_key) {
            $license_key = $stored['license_key'] ?? '';
        }
        if ('' === $domain) {
            $domain = $this->get_site_domain();
        }
        if ('' === $email) {
            $email = $stored['email'] ?? '';
        }

        if ('' === $license_key) {
            return [
                'success' => false,
                'code'    => 'no_license',
                'message' => __('No license key configured.', 'recognition'),
            ];
        }

        // Server-side throttle: if a revalidation just happened (either
        // by the user via the "Re-validate Now" button, by the daily
        // cron, or by the activation flow), short-circuit and return
        // the cached license data instead of hitting the remote server
        // again. This is the last line of defence against the
        // "page keeps refreshing in a loop" bug.
        if ($this->is_revalidate_throttled()) {
            $existing = $this->get_license_data();
            return [
                'success' => $this->is_license_valid(true),
                'code'    => 'throttled',
                'message' => __('License was recently validated. Please wait a moment.', 'recognition'),
                'data'    => $existing,
            ];
        }

        // Mark the throttle BEFORE the network call. If the call
        // hangs, this still prevents another revalidation from
        // running concurrently. The transient is refreshed again on
        // success below.
        $this->set_revalidate_throttle();

        // Send the FULL set of attributes (license_key + email + domain)
        // so the server applies the same rules as during activation.
        $response = FRL_License_API::validate($license_key, $domain, $email);

        if (!$response['success']) {
            // Graceful offline handling: keep current status if previously active.
            $existing = $this->get_license_data();
            if ('active' === ($existing['status'] ?? '')) {
                $this->update_license_data([
                    'status'        => 'offline',
                    'last_check_at' => current_time('mysql'),
                    'message'       => __('License server unreachable. Using cached activation.', 'recognition'),
                ]);
                return [
                    'success' => true,
                    'code'    => 'offline',
                    'message' => __('License server unreachable. Cached activation used.', 'recognition'),
                ];
            }

            if (!$silent) {
                $this->update_license_data([
                    'status'        => 'offline',
                    'last_check_at' => current_time('mysql'),
                    'message'       => $response['error'] ?: __('Could not reach license server.', 'recognition'),
                ]);
            }

            return [
                'success' => false,
                'code'    => 'network_error',
                'message' => $response['error'] ?: __('Could not reach license server.', 'recognition'),
            ];
        }

        $body = $response['data'];
        $is_valid = !empty($body['valid']);
        $server_code = isset($body['code']) ? sanitize_key($body['code']) : '';
        $server_message = isset($body['message']) ? sanitize_text_field($body['message']) : '';
        $license_info = isset($body['license']) && is_array($body['license']) ? $body['license'] : [];
        $now_mysql = current_time('mysql');

        update_option(self::OPTION_LAST_CHECK_KEY, time(), false);

        // Server-side authoritative checks: same deny rules as
        // activate_license(). email_mismatch and domain_mismatch are
        // hard failures regardless of $is_valid.
        if (!$is_valid || in_array($server_code, ['email_mismatch', 'invalid_email', 'domain_mismatch'], true)) {
            $mapped = self::map_invalid_status($server_code);
            $user_message = $server_message;
            if ('email_mismatch' === $server_code) {
                $user_message = __('The email address does not match the records for this license.', 'recognition');
            } elseif ('invalid_email' === $server_code) {
                $user_message = __('Please enter a valid email address.', 'recognition');
            } elseif ('' === $user_message) {
                $user_message = __('License is not valid.', 'recognition');
            }
            $this->update_license_data([
                'license_key'      => $license_key,
                'email'            => $email,
                'status'           => $mapped,
                'code'             => $server_code,
                'message'          => $user_message,
                'last_check_at'    => $now_mysql,
                'last_validated_at' => $now_mysql,
                'metadata'         => $license_info,
            ]);
            return [
                'success' => false,
                'code'    => $server_code ?: 'invalid',
                'message' => $user_message,
            ];
        }

        $registered_domain = isset($license_info['domain']) && !empty($license_info['domain'])
            ? sanitize_text_field($license_info['domain'])
            : (isset($body['registered_domain']) ? sanitize_text_field($body['registered_domain']) : $domain);
        $registered_email  = isset($body['registered_email']) ? strtolower((string) $body['registered_email']) : strtolower($email);
        $status = 'active';
        $code   = 'valid';

        if (isset($license_info['in_grace']) && $license_info['in_grace']) {
            $status = 'grace';
            $code   = 'grace';
        }

        // Email mismatch check.
        if (!empty($email) && strcasecmp($registered_email, $email) !== 0) {
            $this->update_license_data([
                'status'            => 'email_mismatch',
                'code'              => 'email_mismatch',
                'message'           => __('The email address does not match the records for this license.', 'recognition'),
                'last_check_at'     => $now_mysql,
                'last_validated_at' => $now_mysql,
                'metadata'          => $license_info,
            ]);
            return [
                'success' => false,
                'code'    => 'email_mismatch',
                'message' => __('The email address does not match the records for this license.', 'recognition'),
            ];
        }

        if (!empty($registered_domain) && strcasecmp($registered_domain, $domain) !== 0) {
            $this->update_license_data([
                'status'          => 'domain_mismatch',
                'code'            => 'domain_mismatch',
                'message'         => sprintf(
                    /* translators: 1: registered domain, 2: current domain */
                    __('This license is registered for %1$s but the current site is %2$s.', 'recognition'),
                    $registered_domain,
                    $domain
                ),
                'last_check_at'   => $now_mysql,
                'last_validated_at' => $now_mysql,
                'metadata'        => $license_info,
            ]);
            return [
                'success' => false,
                'code'    => 'domain_mismatch',
                'message' => sprintf(
                    /* translators: 1: registered domain, 2: current domain */
                    __('This license is registered for %1$s but the current site is %2$s.', 'recognition'),
                    $registered_domain,
                    $domain
                ),
            ];
        }

        $existing = $this->get_license_data();
        $this->update_license_data([
            'status'            => $status,
            'code'              => $code,
            'email'             => $registered_email,
            'plan'              => isset($license_info['plan']) ? sanitize_text_field($license_info['plan']) : ($existing['plan'] ?? ''),
            'plan_id'           => isset($license_info['id']) ? (int) $license_info['id'] : ($existing['plan_id'] ?? 0),
            'issued_at'         => isset($license_info['issued_at']) ? sanitize_text_field($license_info['issued_at']) : ($existing['issued_at'] ?? ''),
            'expires_at'        => isset($license_info['expires_at']) ? sanitize_text_field($license_info['expires_at']) : ($existing['expires_at'] ?? ''),
            'registered_domain' => $registered_domain,
            'last_validated_at' => $now_mysql,
            'last_check_at'     => $now_mysql,
            'message'           => $server_message ?: __('License is valid.', 'recognition'),
            'metadata'          => $license_info,
            // Refresh the domain-bound signature after every successful
            // validation so the rotated values are always covered.
            'signature'         => self::compute_signature($license_key, $registered_email, $registered_domain),
        ]);

        return [
            'success' => true,
            'code'    => $code,
            'message' => $server_message ?: __('License is valid.', 'recognition'),
        ];
    }

    /**
     * Deactivate the license on this site.
     *
     * Attempts server-side deactivation, then clears local data.
     * Server call failures do not block local clearance.
     *
     * @since 1.0.0
     * @return array Response envelope.
     */
    public function deactivate_license() {
        $data = $this->get_license_data();
        $key    = $data['license_key'] ?? '';
        $domain = $data['domain'] ?? self::get_site_domain();
        $email  = $data['email'] ?? '';

        $server_result = null;
        if (!empty($key)) {
            // Send the email too so the server can confirm the
            // requested deactivation matches the records on file.
            $server_result = FRL_License_API::deactivate($key, $domain, $email);
        }

        $this->clear_license_data();

        return [
            'success' => true,
            'message' => __('License deactivated on this site.', 'recognition'),
            'server'  => $server_result,
        ];
    }

    /**
     * Check if a throttle key is active.
     *
     * @since 1.0.0
     * @param string $key Throttle key suffix.
     * @return bool
     */
    private function is_throttled($key) {
        return (bool) get_transient(self::TRANSIENT_THROTTLE . $key);
    }

    /**
     * Set a throttle.
     *
     * @since 1.0.0
     * @param string $key   Throttle key suffix.
     * @param int    $seconds Duration in seconds.
     */
    private function set_throttle($key, $seconds) {
        set_transient(self::TRANSIENT_THROTTLE . $key, 1, $seconds);
    }

    /**
     * Check if a revalidation request is currently throttled.
     *
     * This is the server-side counterpart of the JavaScript loop
     * prevention. Even if the client is buggy or malicious and
     * triggers revalidations in a tight loop, the server will
     * reject them and return the cached license data instead.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_revalidate_throttled() {
        return (bool) get_transient(self::TRANSIENT_REVALIDATE_THROTTLE);
    }

    /**
     * Activate the server-side revalidate throttle.
     *
     * Called by `activate_license()` and `validate_remote()` whenever
     * a successful (or attempted) validation completes. The transient
     * lives for REVALIDATE_THROTTLE_SECONDS, during which any further
     * `validate_remote()` call is short-circuited.
     *
     * @since 1.0.0
     */
    public function set_revalidate_throttle() {
        set_transient(
            self::TRANSIENT_REVALIDATE_THROTTLE,
            time(),
            self::REVALIDATE_THROTTLE_SECONDS
        );
    }

    /**
     * Map a server-side invalid code to a local status string.
     *
     * @since 1.0.0
     * @param string $code Server-provided code.
     * @return string
     */
    private function map_invalid_status($code) {
        switch ($code) {
            case 'expired':
                return 'expired';
            case 'revoked':
                return 'revoked';
            case 'suspended':
                return 'suspended';
            case 'limit_exceeded':
                return 'limit_exceeded';
            case 'email_mismatch':
            case 'invalid_email':
                return 'email_mismatch';
            case 'domain_mismatch':
                return 'domain_mismatch';
            case 'invalid':
            default:
                return 'invalid';
        }
    }

    /**
     * Compute a domain/email/key bound signature for the local cache.
     *
     * The signature is stored alongside the rest of the license data
     * in frl_license_data. Whenever the plugin reads the cached data
     * it recomputes the signature from the stored values and
     * compares it against the saved one. A mismatch means the cache
     * was tampered with (most commonly by copying the option value
     * from another site) and the plugin must re-validate against the
     * server before granting premium features.
     *
     * The salt is constructed from ABSPATH and AUTH_SALT so it is
     * site-specific and not derivable from values that a copy/paste
     * attacker would have access to.
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @param string $email       Registered email.
     * @param string $domain      Registered domain.
     * @return string
     */
    public static function compute_signature($license_key, $email, $domain) {
        $salt  = ABSPATH . '|' . (defined('AUTH_SALT') ? AUTH_SALT : 'frl-no-salt');
        $payload = strtolower(trim((string) $license_key)) . '|'
                 . strtolower(trim((string) $email)) . '|'
                 . strtolower(trim((string) $domain));
        return hash_hmac('sha256', $payload, $salt);
    }

    /**
     * Verify that the local cache signature matches the stored
     * license_key / email / domain. Returns true on match, false if
     * any of the inputs are missing or the signature has been
     * tampered with.
     *
     * @since 1.0.0
     * @param array $data License data payload.
     * @return bool
     */
    public static function is_signature_valid($data) {
        if (!is_array($data)) {
            return false;
        }
        $key    = (string) ($data['license_key'] ?? '');
        $email  = (string) ($data['email'] ?? '');
        $domain = (string) ($data['registered_domain'] ?? $data['domain'] ?? '');
        $sig    = (string) ($data['signature'] ?? '');
        if ('' === $key || '' === $sig) {
            return false;
        }
        $expected = self::compute_signature($key, $email, $domain);
        return hash_equals($expected, $sig);
    }

    /**
     * Get the list of free features (always available).
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_free_features() {
        return [
            'face_enrollment'   => true,
            'face_login'        => true,
            'basic_settings'    => true,
            'user_management'   => true,
            'authentication_log' => true,
            'password_fallback' => true,
        ];
    }

    /**
     * Get the list of premium features (gated by license).
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_premium_features() {
        return [
            'woocommerce_integration' => __('WooCommerce Integration', 'recognition'),
            'multi_factor'            => __('Multi-Factor Authentication', 'recognition'),
            'advanced_analytics'      => __('Advanced Analytics Dashboard', 'recognition'),
            'priority_support'        => __('Priority Email Support', 'recognition'),
            'custom_branding'         => __('Custom Branding', 'recognition'),
            'auto_updates'            => __('Automatic Plugin Updates', 'recognition'),
            'extended_logs'           => __('Extended Log Retention', 'recognition'),
            'multi_site'              => __('Multisite Network Support', 'recognition'),
        ];
    }

    /**
     * Check if a specific feature is available to the current site.
     *
     * @since 1.0.0
     * @param string $feature Feature slug.
     * @return bool
     */
    public function is_feature_available($feature) {
        $free = self::get_free_features();
        if (isset($free[$feature])) {
            return true;
        }
        return $this->has_premium();
    }

    /**
     * Render the SINGLE GLOBAL admin notice shown across the entire
     * WordPress admin area when the plugin does not have a valid
     * license. This notice replaces the previous per-page notices
     * (the "Recognition License" banner AND the "Recognition Premium"
     * per-feature messages on the Enroll Face / Authentication Logs
     * pages), providing a single, consistent entry point for users
     * to learn about the premium plan and activate their license.
     *
     * The notice:
     *   - Appears on every wp-admin screen (no screen-id check).
     *   - Is hidden on the License Activation page itself, where
     *     the user is already engaged with the license flow.
     *   - Includes a "Get a License" button linking to the public
     *     licensing portal at https://license.jsswebsolutions.com/
     *     so first-time users can purchase a plan without leaving
     *     the WordPress admin.
     *   - Includes a secondary "Activate License" button linking
     *     to the in-plugin License Activation page for users who
     *     already have a key.
     *   - Stays dismissible via the core "X" button so the user
     *     can opt out of seeing it on a per-site basis.
     *
     * @since 1.0.0
     */
    public function maybe_render_license_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!is_admin()) {
            return;
        }

        // Don't show on the license page itself.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page check used to suppress the notice on the license page; no state is changed.
        if (isset($_GET['page']) && 'frl-license' === $_GET['page']) {
            return;
        }

        // Allow user to dismiss the notice.
        if (get_option(self::OPTION_NOTICE_KEY)) {
            return;
        }

        $data = $this->get_license_data();
        $status = $data['status'] ?? 'inactive';

        // No notice for active / grace / offline states.
        $no_notice_states = ['active', 'grace', 'offline'];
        if (in_array($status, $no_notice_states, true)) {
            return;
        }

        $messages = [
            'inactive'        => __('Activate your license to unlock premium features.', 'recognition'),
            'invalid'         => __('Your license key is invalid. Please verify and reactivate.', 'recognition'),
            'expired'         => __('Your license has expired. Renew to continue receiving premium updates.', 'recognition'),
            'revoked'         => __('Your license has been revoked. Please contact support.', 'recognition'),
            'suspended'       => __('Your license is suspended. Please contact support.', 'recognition'),
            'domain_mismatch' => __('The license domain does not match this site. Premium features are disabled.', 'recognition'),
            'limit_exceeded'  => __('Your license has reached its activation limit.', 'recognition'),
        ];

        $message = isset($messages[$status]) ? $messages[$status] : $messages['inactive'];
        $license_url   = admin_url('admin.php?page=frl-license');
        $purchase_url  = 'https://license.jsswebsolutions.com/';
        $dismiss_url   = wp_nonce_url(add_query_arg(['frl_dismiss_license_notice' => 1]), 'frl_dismiss_license_notice');

        // IMPORTANT: The dismiss button MUST be a direct child of the
        // .notice div (a sibling of the <p>), NOT nested inside it.
        // WordPress core styles `.notice-dismiss` with `position: absolute`
        // so the X icon sits in the top-right corner of the notice. If
        // the button is inside the <p>, it collapses to inline text and
        // the click never persists the dismissal (the option below
        // remains unset, so the notice reappears on the next page load).
        echo '<div class="notice notice-warning frl-license-notice frl-license-notice--warning frl-license-notice--global is-dismissible">';
        echo '<div class="frl-license-notice__inner">';
        echo '<div class="frl-license-notice__content">';
        echo '<p class="frl-license-notice__text"><strong>' . esc_html__('Recognition:', 'recognition') . '</strong> ';
        echo esc_html($message) . '</p>';
        echo '<div class="frl-license-notice__actions">';
        echo '<a href="' . esc_url($purchase_url) . '" class="button button-primary frl-license-notice__action frl-license-notice__action--primary" target="_blank" rel="noopener noreferrer">';
        echo '<span class="dashicons dashicons-cart" aria-hidden="true"></span> ';
        echo esc_html__('Get a License', 'recognition') . '</a>';
        echo '<a href="' . esc_url($license_url) . '" class="button button-secondary frl-license-notice__action frl-license-notice__action--secondary">';
        echo esc_html__('Activate License', 'recognition') . '</a>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<button type="button" class="notice-dismiss" data-frl-dismiss-url="' . esc_url($dismiss_url) . '" aria-label="' . esc_attr__('Dismiss this notice.', 'recognition') . '">';
        echo '<span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'recognition') . '</span>';
        echo '</button>';
        echo '</div>';
    }

    /**
     * Handle dismissal of the admin notice.
     *
     * Supports two entry points so the dismissal is always persisted,
     * regardless of whether the user clicked the "X" (which fires
     * an AJAX request via the JS handler in admin.js) or followed
     * the dismiss link as a plain GET (fallback path).
     *
     * AJAX request:  POST admin-ajax.php?action=frl_dismiss_license_notice
     *                body: nonce=<frl_dismiss_license_notice>
     * Link fallback: GET <admin_url>?frl_dismiss_license_notice=1&_wpnonce=...
     *
     * @since 1.0.0
     */
    public function handle_notice_dismissal() {
        $is_ajax = (defined('DOING_AJAX') && DOING_AJAX)
            || (isset($_POST['action']) && 'frl_dismiss_license_notice' === $_POST['action']);

        if (!$is_ajax && !isset($_GET['frl_dismiss_license_notice'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            if ($is_ajax) {
                wp_send_json_error(
                    ['message' => __('Permission denied.', 'recognition')],
                    403
                );
            }
            return;
        }

        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        } elseif (isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
        }

        if (!wp_verify_nonce($nonce, 'frl_dismiss_license_notice')) {
            if ($is_ajax) {
                wp_send_json_error(
                    ['message' => __('Security check failed.', 'recognition')],
                    403
                );
            }
            return;
        }

        update_option(self::OPTION_NOTICE_KEY, time(), false);

        if ($is_ajax) {
            wp_send_json_success(
                ['message' => __('Notice dismissed.', 'recognition')]
            );
        }

        wp_safe_redirect(remove_query_arg(['frl_dismiss_license_notice', '_wpnonce']));
        exit;
    }
}
