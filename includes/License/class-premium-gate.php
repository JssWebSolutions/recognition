<?php
/**
 * Premium Feature Gate
 *
 * Centralised, modular controller for the plugin's premium-only
 * settings and admin pages. Provides a single registry of every
 * premium field, page, action, and section so additional gated
 * content can be added in the future by simply listing the key
 * here - no other code changes are required.
 *
 * The gate is fully compatible with the existing License module
 * (FRL_License_Manager) and re-uses the same status checks
 * (active, grace, offline). It also re-uses the existing
 * admin notice infrastructure so users always see consistent
 * messaging about why something is locked.
 *
 * Behaviour
 * ---------
 * 1. Premium settings remain visible on the Settings page so the
 *    user can see what is included in the premium plan, but the
 *    inputs are wrapped in a .frl-premium-field container,
 *    marked as disabled, given a "PRO" badge next to the title,
 *    and accompanied by a short notice explaining how to unlock
 *    the feature. A server-side sanitisation pass additionally
 *    ignores any values submitted for those fields, so even a
 *    direct POST to options.php cannot save premium values.
 *
 * 2. Premium admin pages (Enroll Face, Authentication Logs)
 *    remain reachable from the menu but the page content is
 *    wrapped in a blurred overlay. The overlay shows a centered
 *    "🔒 This is a Premium Feature" notice plus an "Activate
 *    License" button that links to the License Activation page.
 *    Direct URL access is also blocked at the server level: any
 *    request without a valid license is redirected to the License
 *    Activation page with an admin notice.
 *
 * 3. As soon as the license becomes valid, all disabled states,
 *    blur overlays, and badges are removed automatically - no
 *    cache flush or re-install is required.
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
 * Class FRL_Premium_Gate
 *
 * @since 1.0.0
 */
class FRL_Premium_Gate {

    /**
     * Singleton instance.
     *
     * @since 1.0.0
     * @var FRL_Premium_Gate
     */
    private static $instance = null;

    /**
     * Capability required to view / interact with the gated UI.
     *
     * @since 1.0.0
     * @var string
     */
    const CAPABILITY = 'manage_options';

    /**
     * Settings group used for the frl_settings option.
     *
     * @since 1.0.0
     * @var string
     */
    const SETTINGS_OPTION = 'frl_settings';

    /**
     * Slug for the License Activation admin page.
     *
     * @since 1.0.0
     * @var string
     */
    const LICENSE_PAGE_SLUG = 'frl-license';

    /**
     * Transient key used to remember an admin notice when a user
     * is redirected from a locked page to the License page.
     *
     * @since 1.0.0
     * @var string
     */
    const REDIRECT_NOTICE_KEY = 'frl_premium_redirect_notice';

    /**
     * Flag that suppresses the "stripped premium-only setting keys"
     * error_log() entry for the duration of an internal save.
     *
     * Internal callers (e.g. the plugin installer seeding default
     * options on activation) flip this on with
     * {@see self::silence_premium_log()} before they call
     * update_option() and back off when they are done. The
     * sanitize_callback is still invoked (and still strips any
     * premium values that happen to be present) - the flag only
     * silences the diagnostic log line and the
     * `frl_premium_settings_stripped` action, so a real user
     * attempt to save premium values via the settings form is
     * unaffected.
     *
     * @since 1.0.0
     * @var bool
     */
    private static $silence_premium_log = false;

    /**
     * Registry of premium setting field keys.
     *
     * Each entry is the array key used inside the frl_settings
     * option. To make a NEW field premium, add its key here - that
     * is the only change required.
     *
     * @since 1.0.0
     * @var string[]
     */
    private static $premium_fields = [
        // Security - advanced rate limiting.
        'rate_limit_enabled',
        'max_failed_attempts',
        'lockout_minutes',

        // Security - at-rest encryption of biometric descriptors.
        'encrypt_descriptors',

        // User - allow more than one face profile per account.
        'max_faces_per_user',

        // Logging - authentication audit trail + retention.
        'log_authentications',
        'auto_delete_logs',
        'auto_delete_logs_days',
    ];

    /**
     * Registry of premium admin page slugs.
     *
     * Each entry is the page slug used in add_submenu_page.
     * Adding a new entry here is enough to lock down a new
     * premium-only page (the render callback will be wrapped
     * automatically and direct access will be redirected).
     *
     * @since 1.0.0
     * @var string[]
     */
    private static $premium_pages = [
        'frl-enroll-face',   // Enroll Face.
        'frl-logs',          // Authentication Logs.
    ];

    /**
     * Registry of premium admin actions.
     *
     * The actions listed here are blocked (return WP_Error) when
     * the license is not active, preventing AJAX / REST clients
     * from circumventing the UI lock.
     *
     * @since 1.0.0
     * @var string[]
     */
    private static $premium_actions = [
        'frl_admin_enroll_face',  // Admin enroll face for a user.
        'frl_admin_clean_logs',   // Clean all logs.
        'frl_admin_clean_old_logs', // Clean old logs.
        'frl_delete_user_faces',  // Bulk delete user faces.
        'frl_delete_face',        // Delete a single face from the user-profile
                                  // page (user-edit.php). Without a license,
                                  // the AJAX is short-circuited so the JS
                                  // never sees a half-state where the
                                  // "Are you sure?" dialog accepts but the
                                  // server silently no-ops.
    ];

    /**
     * Get singleton instance.
     *
     * @since 1.0.0
     * @return FRL_Premium_Gate
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Registers all hooks.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action('admin_init', [$this, 'handle_locked_page_redirect']);
        add_action('admin_notices', [$this, 'maybe_render_redirect_notice']);

        // Enqueue the gate UI assets on the plugin's admin pages.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Register the AJAX endpoint that powers the in-page
        // "Premium unlocked" re-check (the JS polls the server
        // for license changes so the UI can react immediately
        // after the user activates their license in another tab).
        add_action('wp_ajax_frl_premium_status', [$this, 'ajax_get_status']);

        // Block premium AJAX actions when the license is invalid.
        foreach (self::$premium_actions as $action) {
            add_action('wp_ajax_' . $action, [$this, 'block_premium_ajax'], 0);
        }
    }

    /**
     * Get the License Manager singleton.
     *
     * @since 1.0.0
     * @return FRL_License_Manager
     */
    protected static function manager() {
        return FRL_License_Manager::get_instance();
    }

    /**
     * Whether the current site has a valid premium license.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function is_premium_active() {
        if (!class_exists('FRL_License_Manager')) {
            return false;
        }
        return FRL_License_Manager::get_instance()->has_premium();
    }

    /**
     * Register an additional premium field at runtime.
     *
     * Third-party add-ons (and this plugin's own future code) can
     * call this method to mark one of their setting keys as
     * premium without having to subclass the gate.
     *
     * @since 1.0.0
     * @param string $field_key Setting key.
     * @return void
     */
    public static function register_premium_field($field_key) {
        $field_key = (string) $field_key;
        if ('' === $field_key) {
            return;
        }
        if (!in_array($field_key, self::$premium_fields, true)) {
            self::$premium_fields[] = $field_key;
        }
    }

    /**
     * Register an additional premium page at runtime.
     *
     * @since 1.0.0
     * @param string $page_slug Page slug (matches add_submenu_page).
     * @return void
     */
    public static function register_premium_page($page_slug) {
        $page_slug = (string) $page_slug;
        if ('' === $page_slug) {
            return;
        }
        if (!in_array($page_slug, self::$premium_pages, true)) {
            self::$premium_pages[] = $page_slug;
        }
    }

    /**
     * Get the list of premium field keys.
     *
     * @since 1.0.0
     * @return string[]
     */
    public static function get_premium_fields() {
        return self::$premium_fields;
    }

    /**
     * Get the list of premium page slugs.
     *
     * @since 1.0.0
     * @return string[]
     */
    public static function get_premium_pages() {
        return self::$premium_pages;
    }

    /**
     * Determine whether a settings field is premium.
     *
     * @since 1.0.0
     * @param string $field_key Setting key.
     * @return bool
     */
    public static function is_field_premium($field_key) {
        return in_array((string) $field_key, self::$premium_fields, true);
    }

    /**
     * Determine whether an admin page is premium.
     *
     * @since 1.0.0
     * @param string $page_slug Page slug.
     * @return bool
     */
    public static function is_page_premium($page_slug) {
        return in_array((string) $page_slug, self::$premium_pages, true);
    }

    /**
     * Build the URL of the License Activation page.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_license_page_url() {
        return add_query_arg(
            ['page' => self::LICENSE_PAGE_SLUG],
            admin_url('admin.php')
        );
    }

    /**
     * Render the "PRO" badge that goes next to a setting title.
     *
     * Output is safe to echo directly inside a label or table
     * cell. The badge is rendered as an inline <span> so it
     * inherits the surrounding font / color rules.
     *
     * @since 1.0.0
     * @return string
     */
    public static function render_pro_badge() {
        return '<span class="frl-pro-badge" aria-label="' . esc_attr__('Premium feature', 'recognition') . '">' . esc_html__('PRO', 'recognition') . '</span>';
    }

    /**
     * Render the short "this is premium" notice used inside the
     * disabled setting wrapper.
     *
     * @since 1.0.0
     * @return string
     */
    public static function render_field_notice() {
        $url = esc_url(self::get_license_page_url());
        return sprintf(
            /* translators: %s: license activation URL */
            __('Available in the Premium version. <a href="%s">Activate your license</a> to unlock this feature.', 'recognition'),
            $url
        );
    }

    /**
     * Open a premium field wrapper.
     *
     * Any setting that is registered as premium should be wrapped
     * with open_premium_field() / close_premium_field(). The
     * wrapper adds the disabled visual state and injects the PRO
     * badge + the short unlock notice.
     *
     * When the license IS active the wrapper collapses to an empty
     * string so the markup stays clean.
     *
     * @since 1.0.0
     * @param string $field_key Setting key (used for stable IDs/hooks).
     * @return string
     */
    public static function open_premium_field($field_key) {
        if (self::is_premium_active()) {
            return '';
        }

        $field_key = sanitize_key($field_key);
        $badge     = self::render_pro_badge();
        $notice    = self::render_field_notice();
        $notice_id = 'frl-pro-notice-' . $field_key;

        $html  = '<div class="frl-premium-field" data-frl-premium-field="' . esc_attr($field_key) . '" data-frl-premium="1">';
        $html .= '<div class="frl-premium-field-notice" id="' . esc_attr( $notice_id ) . '">';
        $html .= $badge;
        $html .= '<span class="frl-premium-field-notice-text">' . wp_kses(
            $notice,
            [
                'a' => [
                    'href' => [],
                ],
            ]
        ) . '</span>';
        $html .= '</div>';
        $html .= '<div class="frl-premium-field-body" aria-hidden="true" inert="inert">';
        return $html;
    }

    /**
     * Return the id of the upgrade notice rendered by open_premium_field()
     * for a given field key. Useful for adding `aria-describedby` to a
     * disabled form control.
     *
     * @since 1.0.0
     * @param string $field_key Field key passed to open_premium_field().
     * @return string Empty when premium is active (no notice is rendered).
     */
    public static function get_premium_notice_id( $field_key ) {
        if ( self::is_premium_active() ) {
            return '';
        }
        return 'frl-pro-notice-' . sanitize_key( $field_key );
    }

    /**
     * Return the full attribute string to put on a premium-disabled form
     * control: `disabled="disabled" aria-disabled="true" aria-describedby="..."`.
     *
     * Empty string when the field is not premium or premium is active.
     *
     * @since 1.0.0
     * @param string $field_key Field key passed to open_premium_field().
     * @return string
     */
    public static function get_disabled_attr( $field_key ) {
        if ( ! self::is_field_premium( $field_key ) ) {
            return '';
        }
        if ( self::is_premium_active() ) {
            return '';
        }
        $notice_id = self::get_premium_notice_id( $field_key );
        return sprintf(
            ' disabled="disabled" aria-disabled="true" aria-describedby="%s"',
            esc_attr( $notice_id )
        );
    }

    /**
     * Close a premium field wrapper opened with open_premium_field().
     *
     * @since 1.0.0
     * @return string
     */
    public static function close_premium_field() {
        if (self::is_premium_active()) {
            return '';
        }
        return '</div></div>';
    }

    /**
     * Render the full-page lock overlay used on premium admin
     * pages when the license is not active.
     *
     * The returned HTML can be echoed at the very top of a
     * template - the page content is still rendered for SEO / UX
     * purposes but the overlay sits on top of it.
     *
     * @since 1.0.0
     * @param string $title   Optional page title override.
     * @return string
     */
    public static function render_page_lock_overlay($title = '') {
        if (self::is_premium_active()) {
            return '';
        }

        if ('' === $title) {
            $title = __('Premium Feature', 'recognition');
        }

        $url          = esc_url(self::get_license_page_url());
        $message      = esc_html__('This is a Premium Feature. Activate your plugin license to access this page.', 'recognition');
        $button_label = esc_html__('Activate License', 'recognition');

        $html  = '<div class="frl-premium-locked-overlay" role="dialog" aria-modal="true" aria-labelledby="frl-premium-locked-title">';
        $html .= '<div class="frl-premium-locked-card">';
        $html .= '<div class="frl-premium-locked-icon" aria-hidden="true">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">';
$html .= '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>';
        $html .= '<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<h2 class="frl-premium-locked-title" id="frl-premium-locked-title">' . esc_html($title) . '</h2>';
        $html .= '<p class="frl-premium-locked-message">' . $message . '</p>';
        $html .= '<a class="frl-btn frl-btn-primary frl-premium-locked-cta" href="' . $url . '">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">';
        $html .= '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>';
        $html .= '</svg>';
        $html .= $button_label;
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Wrap an admin page render in a blurred lock overlay.
     *
     * The returned string is the original page content with a
     * "locked" class on the root element and the overlay markup
     * prepended. The CSS will handle the actual blur effect.
     *
     * @since 1.0.0
     * @param string $content    Original page HTML.
     * @param string $page_title Title to show inside the overlay.
     * @return string
     */
    public static function wrap_page_with_lock($content, $page_title = '') {
        if (self::is_premium_active()) {
            return $content;
        }
        $overlay = self::render_page_lock_overlay($page_title);
        // Inject a marker class so the CSS can target the page
        // body when the gate is active.
        $content = preg_replace(
            '/<body([^>]*)>/i',
            '<body$1 class="frl-premium-locked-body">',
            $content,
            1
        );
        // If the body tag isn't present, prepend the class to
        // the wrapper element.
        if (false === strpos($content, 'frl-premium-locked-body') && false === strpos($content, '<body')) {
            $content = '<div class="frl-premium-locked-body">' . $content . '</div>';
        }
        return $overlay . $content;
    }

    /**
     * Sanitize a settings array, stripping any values submitted
     * for premium fields when the license is not active.
     *
     * Hooked into the register_setting sanitize_callback, so this
     * method is called every time the frl_settings option is saved
     * via the WordPress Settings API. It is defensive: even if a
     * malicious client manages to send a value for a premium
     * field, that value is silently dropped on the way to the
     * database.
     *
     * T2-12: When a value is stripped, an `frl_premium_settings_stripped`
     * action is fired with the field keys and the requesting user
     * (when known) so external loggers / audit pipelines can record
     * the attempt. A `error_log()` entry is also written when
     * `WP_DEBUG_LOG` is on, to aid site administrators in diagnosing
     * "my premium settings are not saving" reports.
     *
     * @since 1.0.0
     * @param array $input Raw input from the form.
     * @return array Cleaned input, with premium keys removed.
     */
    public static function sanitize_premium_settings($input) {
        if (!is_array($input)) {
            return [];
        }

        if (self::is_premium_active()) {
            return $input;
        }

        $stripped = [];
        foreach (self::$premium_fields as $key) {
            if (array_key_exists($key, $input)) {
                $stripped[] = $key;
                unset($input[$key]);
            }
        }

        if (!empty($stripped)) {
            // Skip the diagnostic log + action when an internal
            // caller (e.g. the plugin installer seeding the
            // initial defaults on activation) has explicitly
            // silenced it. The strip itself still happens - this
            // only suppresses the side-effects (error_log +
            // do_action), so real user attempts to save premium
            // values via the settings form are still logged.
            if (self::$silence_premium_log) {
                return $input;
            }

            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            $ip      = class_exists('FRL_Security') ? FRL_Security::get_client_ip_static() : '';

            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            }

            /**
             * Fires when the premium gate strips premium-only values
             * from a settings save attempt.
             *
             * @since 1.0.0
             * @param string[] $stripped Field keys that were dropped.
             * @param array    $input    The original input (before stripping).
             * @param int      $user_id  Current user ID (0 if not logged in).
             */
            do_action('frl_premium_settings_stripped', $stripped, $input, (int) $user_id);
        }

        return $input;
    }

    /**
     * Silence the "stripped premium-only setting keys" error_log
     * entry and the accompanying `frl_premium_settings_stripped`
     * action for internal save paths.
     *
     * Use this around any internal call to update_option() that
     * is known to be free of premium values (e.g. the plugin
     * installer seeding the initial defaults on first activation).
     * The sanitize_callback is still invoked and still strips
     * premium keys from the saved array, but the diagnostic
     * log line and the `frl_premium_settings_stripped` action
     * are suppressed.
     *
     * Real user attempts to save premium values via the
     * settings form are unaffected - the flag must be
     * explicitly flipped on by trusted PHP code, and the
     * flag defaults to off.
     *
     * The flag uses a simple static boolean (no nesting counter)
     * because the only intended callers are top-level entry
     * points that bracket a single update_option() call. If a
     * nested caller flips the flag, the outer caller's state
     * is lost - which is the correct behaviour for a
     * "speak now or forever hold your peace" silence flag.
     *
     * @since 1.0.0
     * @param bool $silence True to silence, false to re-enable.
     * @return void
     */
    public static function silence_premium_log($silence) {
        self::$silence_premium_log = (bool) $silence;
    }

    /**
     * Filter individual settings field output, disabling premium
     * fields by appending the disabled attribute to inputs.
     *
     * @since 1.0.0
     * @param string $html      Field HTML.
     * @param string $field_key Settings field key (best effort).
     * @return string
     */
    public static function filter_field_html($html, $field_key = '') {
        if (self::is_premium_active() || '' === $field_key) {
            return $html;
        }
        if (!self::is_field_premium($field_key)) {
            return $html;
        }

        // Inject the disabled attribute on all relevant form
        // elements. We use a regex to avoid breaking the markup
        // of the input - the first opening tag of an input,
        // select, button or textarea is targeted.
        $pattern = '/(<(?:input|select|textarea|button)\b)([^>]*?>)/i';

        $callback = function ($matches) {
            $tag    = $matches[1];
            $attrs  = $matches[2];
            if (false !== stripos($attrs, ' disabled')) {
                return $matches[0];
            }
            // Skip the settings_fields nonce + submit button.
            if (false !== stripos($attrs, 'name="_wpnonce"')
                || false !== stripos($attrs, 'name="_wp_http_referer"')
                || false !== stripos($attrs, 'name="option_page"')
                || false !== stripos($attrs, 'type="submit"')
            ) {
                return $matches[0];
            }
            return $tag . ' disabled="disabled" aria-disabled="true"' . $attrs;
        };

        $html = preg_replace_callback($pattern, $callback, $html);
        return $html;
    }

    /**
     * Decide whether a particular page render should be locked.
     *
     * This is the single source of truth used by the admin class
     * when deciding whether to show the lock overlay.
     *
     * @since 1.0.0
     * @param string $page_slug Page slug.
     * @return bool
     */
    public static function should_lock_page($page_slug) {
        return self::is_page_premium($page_slug) && !self::is_premium_active();
    }

    /**
     * AJAX handler: return the current premium status.
     *
     * The JS polls this endpoint so the UI can react instantly
     * after the user activates a license in another tab.
     *
     * @since 1.0.0
     */
    public function ajax_get_status() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(
                ['message' => __('Permission denied.', 'recognition')],
                403
            );
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'frl_premium_status')) {
            wp_send_json_error(
                ['message' => __('Security check failed.', 'recognition')],
                403
            );
        }

        wp_send_json_success([
            'premium'   => self::is_premium_active(),
            'status'    => self::manager()->get_status(),
            'plan'      => self::manager()->get_license_data()['plan'] ?? '',
            'timestamp' => time(),
        ]);
    }

    /**
     * AJAX/REST request blocker: when a premium-only AJAX action
     * is invoked without a valid license, return a JSON error and
     * stop further processing.
     *
     * @since 1.0.0
     */
    public function block_premium_ajax() {
        if (self::is_premium_active()) {
            return; // The real handler will run.
        }

        // Self-deletion carve-out for frl_delete_face.
        //
        // The same action is wired up on both `profile.php`
        // (the user editing their OWN profile) and
        // `user-edit.php?user_id=X` (an admin editing another
        // user). The face section is rendered by the same
        // template for both hooks, so the action name is shared.
        //
        // A user (or admin) must always be able to delete
        // their own face - it is their own biometric data and
        // there is no multi-tenant boundary to enforce. Only
        // cross-user deletion (admin deleting someone else's
        // face) is a premium operation.
        //
        // We evaluate the carve-out FIRST - before any
        // capability / role check - so it applies uniformly
        // to every user, regardless of whether they have
        // `manage_options`. This avoids the previous ordering
        // bug where the manage_options short-circuit returned
        // early for subscribers, hiding the carve-out and
        // forcing the request to fall through to the
        // ownership check in the real handler - which was
        // fine for subscribers but inconsistent with the
        // admin path. By placing the carve-out first we get
        // a single, predictable rule: deleting your own face
        // is always free, deleting someone else's face is
        // always premium.
        //
        // NOTE: `current_action()` returns the FULL action
        // name passed to `do_action()`, which for logged-in
        // AJAX requests is `wp_ajax_<action>` (and
        // `wp_ajax_nopriv_<action>` for non-logged-in
        // requests). The previous check compared against
        // the bare action slug `frl_delete_face`, which
        // never matched - so the self-deletion carve-out
        // was dead code, and every admin on a site without
        // an active license got a 403 even when deleting
        // their own face on profile.php. We now match the
        // full action name and accept both the auth and
        // nopriv variants for forward compatibility.
        $current_action = current_action();
        if ( 'wp_ajax_frl_delete_face' === $current_action
            || 'wp_ajax_nopriv_frl_delete_face' === $current_action ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside the AJAX handler (handle_delete_user_faces) before this gate runs.
            $face_id = isset( $_POST['face_id'] ) ? absint( wp_unslash( $_POST['face_id'] ) ) : 0;
            if ( $face_id > 0 ) {
                $current = get_current_user_id();
                if ( $current <= 0 ) {
                    // Not logged in - the real handler will
                    // 401 anyway; let the gate fall through.
                } else {
                    $owner_id = self::get_face_owner_id( $face_id );

                    // Primary check: face's user_id in the DB
                    // matches the logged-in user. This is the
                    // most secure signal because it is the
                    // source-of-truth from the database.
                    $is_self = ( $owner_id > 0 && (int) $owner_id === (int) $current );

                    // Fallback: when the face's DB user_id
                    // does NOT match (typically because the
                    // face was enrolled before the user-id-on-
                    // enrolment fix and was stored against a
                    // wrong owner), trust the target_user_id
                    // that the JS sent in the AJAX payload.
                    // This value is rendered server-side from
                    // $target_user_id at page-render time, so
                    // it always reflects the user whose
                    // profile is currently being rendered.
                    // The face is only displayed for the
                    // target user, so a JS click on the
                    // delete button from this page can only
                    // mean the target user is deleting one
                    // of their own faces.
                    if ( ! $is_self ) {
                        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified inside the AJAX handler (handle_delete_user_faces) before this gate runs.
                        $posted_target = isset( $_POST['target_user_id'] ) ? absint( wp_unslash( $_POST['target_user_id'] ) ) : 0;
                        if ( $posted_target > 0 && (int) $posted_target === (int) $current ) {
                            $is_self = true;
                            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                            }
                        }
                    }

                    if ( $is_self ) {
                        // The current user is deleting their
                        // OWN face. This is not a premium
                        // operation, so let the real handler
                        // run regardless of the user's role
                        // / capabilities.
                        return;
                    }
                }
            }
        }

        if (!current_user_can(self::CAPABILITY)) {
            // Non-admin (e.g. subscriber) trying to perform a
            // premium-only action that is NOT a self-deletion
            // carve-out. Let the real handler do its own
            // capability / ownership check so the user gets
            // a meaningful error message.
            return;
        }

        wp_send_json_error(
            [
                'message' => __('This is a premium feature. Please activate your license to use it.', 'recognition'),
                'code'    => 'frl_premium_required',
            ],
            403
        );
    }

    /**
     * Look up the user_id that owns a given face row.
     *
     * Used by {@see self::block_premium_ajax()} to decide
     * whether a `frl_delete_face`request is a self-deletion
     * (always allowed) or a cross-user deletion (premium-only).
     *
     * @since 1.0.0
     * @param int $face_id Face row id from {prefix}face_login.
     * @return int Owner user_id, or 0 if the row does not exist.
     */
    protected static function get_face_owner_id( $face_id ) {
        $face_id = (int) $face_id;
        if ( $face_id <= 0 ) {
            return 0;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'face_login';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix, $face_id is sanitized via prepare().
        $owner = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare( "SELECT user_id FROM {$table} WHERE id = %d", $face_id )
        );
        return $owner ? (int) $owner : 0;
    }

    /**
     * Enqueue the gate UI assets on plugin admin pages.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook) {
        // Only enqueue on our own pages.
        //
        // Plugin admin page hook names (as built by WordPress from the
        // menu slugs) all contain "recognition" in some form:
        //   - top-level dashboard  : "toplevel_page_recognition"
        //   - submenu pages        : "recognition_page_frl-settings", ...
        // The older "frl" / "face-recognition" substrings are kept for
        // backwards compatibility with any old hooks/themes.
        $hook = (string) $hook;
        if (false === strpos($hook, 'recognition')
            && false === strpos($hook, 'face-recognition')
            && false === strpos($hook, 'frl')
        ) {
            return;
        }

        if (!current_user_can(self::CAPABILITY)) {
            return;
        }

        wp_enqueue_style(
            'frl-premium-gate',
            FRL_PLUGIN_URL . 'admin/assets/css/premium-gate.css',
            ['frl-admin'],
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'frl-premium-gate',
            FRL_PLUGIN_URL . 'admin/assets/js/premium-gate.js',
            ['jquery'],
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0',
            true
        );

        wp_localize_script('frl-premium-gate', 'frlPremiumConfig', [
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'statusNonce'    => wp_create_nonce('frl_premium_status'),
            'isPremium'      => self::is_premium_active(),
            'licensePageUrl' => self::get_license_page_url(),
            'i18n'           => [
                'unlocked' => __('Your license was activated. All premium features are now available.', 'recognition'),
                'locked'   => __('Your license is no longer active. Premium features have been disabled.', 'recognition'),
            ],
        ]);

        // T2-7 / T5-15: load .json translation files for any strings
        // wrapped via wp.i18n.__() in premium-gate.js (e.g. labels rendered
        // via data-attributes) and for the i18n object above.
        wp_set_script_translations(
            'frl-premium-gate',
            'recognition',
            FRL_PLUGIN_PATH . 'languages'
        );
    }

    /**
     * If the user is on a premium-locked page and the license is
     * not active, redirect them to the License Activation page
     * with an admin notice. This is the "prevent direct URL
     * access" requirement of the spec.
     *
     * The redirect only happens for direct navigation to the
     * locked page; the user can still see a blurred preview if
     * they explicitly want to. To allow that, we only redirect
     * when the request was an initial GET (not an AJAX or REST
     * request and not a POST).
     *
     * @since 1.0.0
     */
    public function handle_locked_page_redirect() {
        if (!is_admin()) {
            return;
        }
        if (!current_user_can(self::CAPABILITY)) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only guard: bail out if any form submission is in flight, to avoid redirecting away from legitimate form posts.
        if (!empty($_POST)) {
            return; // Don't redirect form submissions.
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug used for premium-gate routing; no state is changed.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('' === $page) {
            return;
        }

        if (!self::is_page_premium($page)) {
            return;
        }

        if (self::is_premium_active()) {
            return;
        }

        // Allow ?frl_preview=1 to keep the user on the page so
        // the blurred preview remains visible - this is the UX
        // requirement of the spec.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag used to bypass premium lock; no state is changed.
        $preview = isset($_GET['frl_preview']) ? sanitize_key(wp_unslash($_GET['frl_preview'])) : '';
        if ('1' === $preview) {
            return;
        }

        // Otherwise, redirect to the License Activation page and
        // remember to render a one-shot admin notice once there.
        set_transient(self::REDIRECT_NOTICE_KEY, $page, MINUTE_IN_SECONDS);

        $redirect = self::get_license_page_url();
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Clear any pending redirect-notice transient.
     *
     * The previous per-page admin notice (one banner per locked
     * premium page - "The \"Enroll Face\" page is a premium
     * feature. Please activate your license...") has been
     * removed in favour of the SINGLE GLOBAL admin notice
     * rendered by {@see FRL_License_Manager::maybe_render_license_notice()},
     * which appears on every wp-admin screen and includes a
     * "Get a License" CTA pointing to https://license.jsswebsolutions.com/.
     *
     * The direct-URL-to-locked-page redirect itself is preserved
     * (see {@see self::handle_locked_page_redirect()}), so the
     * user is still bounced to the License Activation page when
     * they try to deep-link to a premium page. The transient is
     * still cleared here so flags from older plugin versions
     * do not accumulate in the database.
     *
     * @since 1.0.0
     */
    public function maybe_render_redirect_notice() {
        if (!is_admin() || !current_user_can(self::CAPABILITY)) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $notice_key  = self::REDIRECT_NOTICE_KEY . '_' . $user_id;
        $locked_page = get_transient($notice_key);
        if (!$locked_page) {
            // Fall back to the global transient (for backwards compat).
            $locked_page = get_transient(self::REDIRECT_NOTICE_KEY);
        }

        // No notice is rendered here - the global license notice
        // handles user messaging. We just clear any stale flag
        // left over from previous plugin versions.
        delete_transient($notice_key);
        delete_transient(self::REDIRECT_NOTICE_KEY);
    }
}
