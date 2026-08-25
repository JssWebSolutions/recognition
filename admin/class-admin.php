<?php
/**
 * Admin class
 *
 * @package Face_Recognition_Login
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Admin
 *
 * Handles admin functionality
 *
 * @since 1.0.0
 */
class FRL_Admin {

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Block direct URL access to premium-locked admin pages.
        // The Premium Gate runs in admin_init (priority 1) so we
        // hook into it here too, but at a later priority, so the
        // redirect logic only fires after our own admin page has
        // been registered.
        add_action('admin_init', [$this, 'maybe_block_premium_page'], 5);

        // AJAX handlers for admin actions
        add_action('wp_ajax_frl_admin_clean_logs', [$this, 'handle_clean_logs']);
        add_action('wp_ajax_frl_admin_clean_all_logs', [$this, 'handle_clean_all_logs']);
        add_action('wp_ajax_frl_admin_clean_old_logs', [$this, 'handle_clean_old_logs']);
        add_action('wp_ajax_frl_delete_user_faces', [$this, 'handle_delete_user_faces']);

        // Prompt the user to enroll their face from any admin page
        // when face login is enabled but they have not yet enrolled a face.
        add_action('admin_notices', [$this, 'maybe_render_enroll_face_notice']);
    }

    /**
     * Server-side guard for premium-locked admin pages.
     *
     * Called on every admin_init request. When the user is on
     * one of the premium pages (Enroll Face, Auth Logs) and the
     * license is not valid, we either:
     *   - Redirect them to the License Activation page
     *     (default behaviour), OR
     *   - Allow them to keep viewing the blurred preview
     *     (when ?frl_preview=1 is in the URL).
     *
     * The redirect layer is implemented in FRL_Premium_Gate
     * for the cross-cutting case; this method exists so the
     * admin module can centralise the page-list and so the
     * premium pages can detect the lock state from inside the
     * template (e.g. to render the blurred preview rather than
     * the full page).
     *
     * @since 1.0.0
     */
    public function maybe_block_premium_page() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (!class_exists('FRL_Premium_Gate')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page slug used for premium-gate routing; no state is changed.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('' === $page) {
            return;
        }

        if (!FRL_Premium_Gate::is_page_premium($page)) {
            return;
        }

        if (FRL_Premium_Gate::is_premium_active()) {
            return;
        }

        // Allow ?frl_preview=1 to keep the user on the page so
        // the blurred preview is visible. The redirect happens
        // inside FRL_Premium_Gate::handle_locked_page_redirect()
        // otherwise.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview flag used to bypass premium lock; no state is changed.
        $preview = isset($_GET['frl_preview']) ? sanitize_key(wp_unslash($_GET['frl_preview'])) : '';
        if ('1' === $preview) {
            return;
        }

        // The premium gate redirect handles the actual wp_safe_redirect().
        // We expose a small filter so other modules can hook in.
        do_action('frl_premium_page_blocked', $page);
    }

    /**
     * Handle delete user faces AJAX
     *
     * @since 1.0.0
     */
    public function handle_delete_user_faces() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'frl_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'recognition')]);
            wp_die();
        }

        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'recognition')]);
            wp_die();
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user ID.', 'recognition')]);
            wp_die();
        }

        $database = new FRL_Database();
        $result = $database->delete_all_faces_for_user($user_id);
        
        if ($result) {
            /* translators: %d: user ID. */
            wp_send_json_success(['message' => sprintf(__('All face profiles deleted for user #%d.', 'recognition'), $user_id)]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete face profiles.', 'recognition')]);
        }
        wp_die();
    }

    /**
     * Handle clean all logs AJAX
     *
     * @since 1.0.0
     */
    public function handle_clean_logs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'frl_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'recognition')]);
            wp_die();
        }

        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'recognition')]);
            wp_die();
        }

        global $wpdb;

        $logs_table = $wpdb->prefix . 'face_login_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; no user-supplied values; admin-only clean operation.
        $result = $wpdb->query("DELETE FROM {$logs_table}");

        if ($result !== false) {
            /* translators: %d: number of logs deleted. */
            wp_send_json_success(['message' => sprintf(__('All %d logs deleted.', 'recognition'), $result)]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete logs.', 'recognition')]);
        }
        wp_die();
    }

    /**
     * Handle clean ALL logs AJAX (used by the "Clean All" button on the
     * Authentication Logs page).
     *
     * Distinct from `handle_clean_logs()` so we have a dedicated AJAX action
     * `frl_admin_clean_all_logs` and a corresponding JS handler. Behaviour
     * is identical to `handle_clean_logs()` — we keep them as separate
     * routes for clarity and so future "clean all" customisations (e.g.
     * archival, soft-delete, secondary tables) do not affect the
     * legacy "clean logs" action.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_clean_all_logs() {
        // Verify nonce.
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'frl_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'recognition' ) ) );
            wp_die();
        }

        // Verify admin.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'recognition' ) ) );
            wp_die();
        }

        // Use the logger helper so we go through the same code path as
        // the "Clean Old" action — this also takes care of any
        // soft-delete / archive hooks the logger may add in the future.
        $logger  = new FRL_Logger();
        $deleted = $logger->clean_all_logs();

        if ( false === $deleted ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete logs.', 'recognition' ) ) );
            wp_die();
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    /* translators: %d: number of log rows deleted */
                    _n( 'Deleted %d log entry.', 'Deleted %d log entries.', (int) $deleted, 'recognition' ),
                    (int) $deleted
                ),
                'deleted' => (int) $deleted,
            )
        );
        wp_die();
    }

    /**
     * Handle clean old logs AJAX
     *
     * @since 1.0.0
     */
    public function handle_clean_old_logs() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'frl_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'recognition')]);
            wp_die();
        }


        // Verify admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'recognition')]);
            wp_die();
        }

        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        $logger = new FRL_Logger();
        $deleted = $logger->clean_old_logs($days);
        
        if ($deleted !== false) {
            /* translators: %d: number of logs deleted. */
            wp_send_json_success(['message' => sprintf(__('Deleted %d old logs.', 'recognition'), $deleted)]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete old logs.', 'recognition')]);
        }
        wp_die();
    }

    /**
     * Add admin menu items
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Main menu item - Dashboard
        add_menu_page(
            __('Recognition', 'recognition'),
            __('Recognition', 'recognition'),
            'manage_options',
            'recognition',
            [$this, 'render_dashboard_page'],
            'dashicons-admin-generic',
            80
        );

        // Dashboard submenu
        add_submenu_page(
            'recognition',
            __('Dashboard', 'recognition'),
            __('Dashboard', 'recognition'),
            'manage_options',
            'recognition',
            [$this, 'render_dashboard_page']
        );

        // Settings submenu
        add_submenu_page(
            'recognition',
            __('Settings', 'recognition'),
            __('Settings', 'recognition'),
            'manage_options',
            'frl-settings',
            [$this, 'render_settings_page']
        );

        // Users submenu
        add_submenu_page(
            'recognition',
            __('Enrolled Users', 'recognition'),
            __('Enrolled Users', 'recognition'),
            'manage_options',
            'frl-users',
            [$this, 'render_users_page']
        );

        // Face Enrollment submenu
        add_submenu_page(
            'recognition',
            __('Enroll Face', 'recognition'),
            __('Enroll Face', 'recognition'),
            'manage_options',
            'frl-enroll-face',
            [$this, 'render_enroll_face_page']
        );

        // Logs submenu
        add_submenu_page(
            'recognition',
            __('Authentication Logs', 'recognition'),
            __('Auth Logs', 'recognition'),
            'manage_options',
            'frl-logs',
            [$this, 'render_logs_page']
        );

        // Extensions submenu
        add_submenu_page(
            'recognition',
            __('Extensions', 'recognition'),
            __('Extensions', 'recognition'),
            'manage_options',
            'frl-extensions',
            [$this, 'render_extensions_page']
        );
    }

    /**
     * Render dashboard page
     *
     * @since 1.0.0
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include FRL_PLUGIN_PATH . 'admin/templates/dashboard-page.php';
    }

    /**
     * Register settings
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting('frl_settings_group', 'frl_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        // General settings
        add_settings_section(
            'frl_general_section',
            __('General Settings', 'recognition'),
            [$this, 'render_general_section'],
            'frl_settings'
        );

        add_settings_field('frl_enabled', __('Enable Recognition', 'recognition'), [$this, 'render_enabled_field'], 'frl_settings', 'frl_general_section');

        add_settings_field('frl_require_https', __('Require HTTPS', 'recognition'), [$this, 'render_https_field'], 'frl_settings', 'frl_general_section');

        add_settings_field('frl_button_text', __('Login Button Text', 'recognition'), [$this, 'render_button_text_field'], 'frl_settings', 'frl_general_section');

        // Security settings
        add_settings_section(
            'frl_security_section',
            __('Security Settings', 'recognition'),
            [$this, 'render_security_section'],
            'frl_settings'
        );

        add_settings_field('frl_match_threshold', __('Match Threshold', 'recognition'), [$this, 'render_threshold_field'], 'frl_settings', 'frl_security_section');

        add_settings_field('frl_liveness', __('Liveness Detection', 'recognition'), [$this, 'render_liveness_field'], 'frl_settings', 'frl_security_section');

        add_settings_field('frl_rate_limit', $this->maybe_add_pro_badge('Rate Limiting'), [$this, 'render_rate_limit_field'], 'frl_settings', 'frl_security_section');

        add_settings_field('frl_encrypt', $this->maybe_add_pro_badge('Encrypt Descriptors'), [$this, 'render_encrypt_field'], 'frl_settings', 'frl_security_section');

        // User settings
        add_settings_section(
            'frl_user_section',
            __('User Settings', 'recognition'),
            [$this, 'render_user_section'],
            'frl_settings'
        );

        add_settings_field('frl_max_faces', $this->maybe_add_pro_badge('Max Faces Per User', 'max_faces_per_user'), [$this, 'render_max_faces_field'], 'frl_settings', 'frl_user_section');

        add_settings_field('frl_password_fallback', __('Password Fallback', 'recognition'), [$this, 'render_password_fallback_field'], 'frl_settings', 'frl_user_section');

        // Logging settings
        add_settings_section(
            'frl_logging_section',
            __('Logging Settings', 'recognition'),
            [$this, 'render_logging_section'],
            'frl_settings'
        );

        add_settings_field('frl_log_auth', $this->maybe_add_pro_badge('Log Authentications'), [$this, 'render_log_auth_field'], 'frl_settings', 'frl_logging_section');

        add_settings_field('frl_log_days', $this->maybe_add_pro_badge('Auto-delete Logs After'), [$this, 'render_log_days_field'], 'frl_settings', 'frl_logging_section');
    }

    /**
     * Append a "PRO" badge to a settings field title when the
     * field is registered as premium and the license is not
     * currently active. The badge is rendered as a small inline
     * <span> so it can sit next to the title inside the <th> of
     * the settings table.
     *
     * @since 1.0.0
     * @param string $title     Field title.
     * @param string $field_key Settings field key (used to look up
     *                          the premium registry).
     * @return string Title with optional badge appended.
     */
    private function maybe_add_pro_badge($title, $field_key = '') {
        if (!class_exists('FRL_Premium_Gate')) {
            return $title;
        }
        if (FRL_Premium_Gate::is_premium_active()) {
            return $title;
        }

        // Allow the caller to pass a field key to look up; if
        // none is provided we mark the title as premium
        // whenever the helper is called at all. This keeps the
        // registration site readable.
        if ('' === $field_key) {
            // Best effort: any title passed through this helper
            // is considered premium.
            return $title . ' ' . FRL_Premium_Gate::render_pro_badge();
        }

        if (!FRL_Premium_Gate::is_field_premium($field_key)) {
            return $title;
        }

        return $title . ' ' . FRL_Premium_Gate::render_pro_badge();
    }

    /**
     * Enqueue admin assets
     *
     * @since 1.0.0
     * @param string $hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin admin pages.
        //
        // WordPress generates the $hook string from the menu slug:
        //   - The top-level dashboard (slug "recognition") becomes
        //     "toplevel_page_recognition"  -> must match "recognition".
        //   - Submenu pages (slugs like "frl-settings", "frl-users", ...)
        //     become "recognition_page_frl-settings" -> must match "frl".
        //   - The historical "face-recognition" string is kept for
        //     backwards compatibility with any old hooks/themes.
        if (
            strpos($hook, 'face-recognition') === false
            && strpos($hook, 'frl') === false
            && strpos($hook, 'recognition') === false
        ) {
            return;
        }

        // Enqueue premium admin CSS
        wp_enqueue_style(
            'frl-admin-premium',
            FRL_PLUGIN_URL . 'admin/assets/css/frl-admin.css',
            [],
            FRL_PLUGIN_VERSION
        );

        // Legacy admin CSS (for fallback/compatibility)
        wp_enqueue_style(
            'frl-admin',
            FRL_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            FRL_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'frl-admin',
            FRL_PLUGIN_URL . 'admin/assets/js/admin.js',
            ['jquery'],
            FRL_PLUGIN_VERSION,
            true
        );

        // Shared theme/sidebar script - used by every admin page.
        // Replaces the inline <script> blocks that previously lived in
        // dashboard-page.php and settings-page.php (H-2 - 1.0.0).
        wp_enqueue_script(
            'frl-admin-shared',
            FRL_PLUGIN_URL . 'admin/assets/js/frl-admin-shared.js',
            [],
            FRL_PLUGIN_VERSION,
            true
        );

        wp_localize_script('frl-admin', 'frlAdminConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('frl_admin_nonce'),
            'restUrl' => rest_url('frl/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * Sanitize settings
     *
     * Strips premium-only fields from the input when the license
     * is not active. This is the "server-side" half of the
     * Premium Feature Gate: even if a user crafts a POST to
     * options.php, the values for premium fields are dropped on
     * the way into the database.
     *
     * @since 1.0.0
     * @param array $input
     * @return array
     */
    public function sanitize_settings($input) {
        // Drop premium-only values when the license is invalid.
        // FRL_Premium_Gate::sanitize_premium_settings() returns
        // the same array when the license is active, so the
        // happy path is unaffected.
        if (class_exists('FRL_Premium_Gate')) {
            $input = FRL_Premium_Gate::sanitize_premium_settings($input);
        }

        if (!is_array($input)) {
            $input = [];
        }

        $sanitized = [];

        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['require_https'] = !empty($input['require_https']);
        $sanitized['button_text'] = sanitize_text_field($input['button_text'] ?? __('Login with Face', 'recognition'));

        $sanitized['match_threshold'] = isset($input['match_threshold']) ? floatval($input['match_threshold']) : FRL_DEFAULT_MATCH_THRESHOLD;
        $sanitized['match_threshold'] = max(FRL_DEFAULT_MIN_THRESHOLD, min(FRL_DEFAULT_MAX_THRESHOLD, $sanitized['match_threshold']));

        $sanitized['liveness_detection'] = !empty($input['liveness_detection']);
        $sanitized['rate_limit_enabled'] = !empty($input['rate_limit_enabled']);
        $sanitized['max_failed_attempts'] = isset($input['max_failed_attempts']) ? intval($input['max_failed_attempts']) : 5;
        $sanitized['lockout_minutes'] = isset($input['lockout_minutes']) ? intval($input['lockout_minutes']) : 15;
        $sanitized['encrypt_descriptors'] = !empty($input['encrypt_descriptors']);

        $sanitized['max_faces_per_user'] = isset($input['max_faces_per_user']) ? max(1, intval($input['max_faces_per_user'])) : 1;
        $sanitized['require_password_fallback'] = !empty($input['require_password_fallback']);

        $sanitized['log_authentications'] = !empty($input['log_authentications']);
        $sanitized['auto_delete_logs'] = !empty($input['auto_delete_logs']);
        $sanitized['auto_delete_logs_days'] = isset($input['auto_delete_logs_days']) ? intval($input['auto_delete_logs_days']) : 30;
        $sanitized['remove_data_on_uninstall'] = !empty($input['remove_data_on_uninstall']);

        return $sanitized;
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include FRL_PLUGIN_PATH . 'admin/templates/settings-page.php';
    }

    /**
     * Render users page
     *
     * @since 1.0.0
     */
    public function render_users_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $database = new FRL_Database();
        $users = $database->get_users_with_faces();

        include FRL_PLUGIN_PATH . 'admin/templates/users-page.php';
    }

    /**
     * Render face enrollment page
     *
     * @since 1.0.0
     */
    public function render_enroll_face_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // The premium gate will redirect the user away from this
        // page (to the License Activation page) when the license
        // is invalid, unless ?frl_preview=1 is in the URL. In the
        // preview case we still want to render the page so the
        // user can see what is included in the premium plan; the
        // page template emits the lock overlay.

        // Get plugin options for settings
        $options = FRL_Options::all();

        // Enqueue face-api.js for admin enrollment
        // Using local copy for better reliability
        wp_enqueue_script(
            'face-api-js-admin',
            FRL_PLUGIN_URL . 'admin/assets/js/face-api.min.js',
            [],
            '0.22.2',
            true
        );

        // Localize script with enrollment-specific config
        wp_localize_script('frl-admin', 'frlAdminConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('frl_admin_nonce'),
            'restUrl' => rest_url('frl/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isAdminEnrollment' => true,
            'modelsUrl' => FRL_PLUGIN_URL . 'public/models/',
            'settings' => [
                'threshold' => isset($options['match_threshold']) ? floatval($options['match_threshold']) : FRL_DEFAULT_MATCH_THRESHOLD,
                'livenessDetection' => isset($options['liveness_detection']) ? (bool)$options['liveness_detection'] : false,
            ],
            'i18n' => [
                'initializing' => __('Initializing camera...', 'recognition'),
                'cameraReady' => __('Camera ready', 'recognition'),
                'detectingFace' => __('Detecting face...', 'recognition'),
                'faceDetected' => __('Face detected', 'recognition'),
                'noFace' => __('No face detected', 'recognition'),
                'captureSamples' => __('Capturing samples...', 'recognition'),
                'processing' => __('Processing...', 'recognition'),
                'enrollmentComplete' => __('Face enrollment complete!', 'recognition'),
                'enrollmentFailed' => __('Face enrollment failed', 'recognition'),
                'selectUser' => __('Please select a user first', 'recognition'),
                'cameraError' => __('Camera error', 'recognition'),
                'permissionDenied' => __('Camera permission denied', 'recognition'),
                'loadingModels' => __('Loading face detection models...', 'recognition'),
                'modelsError' => __('Failed to load face detection models', 'recognition'),
            ],
        ]);

        // Enqueue admin enrollment script (depends on face-api.js)
        wp_enqueue_script(
            'frl-admin-enroll',
            FRL_PLUGIN_URL . 'admin/assets/js/admin-enroll.js',
            ['jquery', 'face-api-js-admin'],
            FRL_PLUGIN_VERSION,
            true
        );

        include FRL_PLUGIN_PATH . 'admin/templates/enroll-face-page.php';
    }

    /**
     * Render logs page
     *
     * @since 1.0.0
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // The premium gate will redirect the user away from this
        // page when the license is invalid. We still load the
        // log data here so the blurred preview (when the user
        // passes ?frl_preview=1) has something to display
        // underneath the lock overlay.

        $logger = new FRL_Logger();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter used to render the logs table; no state is changed.
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;

        // Whitelist of allowed rows-per-page values. Anything outside
        // this list is rejected so a hand-crafted URL cannot ask the
        // server for 1,000,000 rows (DoS) or a non-integer limit. The
        // matching dropdown is rendered in logs-page.php using the
        // same array so the user-facing options stay in lockstep.
        $frl_allowed_per_page = [ 10, 20, 50, 100 ];
        $per_page = 50; // default
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter used to render the logs table; value is whitelisted against $frl_allowed_per_page.
        if ( isset( $_GET['per_page'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only parameter as the surrounding block; whitelisted against $frl_allowed_per_page.
            $requested_per_page = absint( wp_unslash( $_GET['per_page'] ) );
            if ( in_array( $requested_per_page, $frl_allowed_per_page, true ) ) {
                $per_page = $requested_per_page;
            }
        }

        $offset = ($page - 1) * $per_page;

        // FRL_Logger::get_logs() returns the structured array
        // [ 'logs' => <row objects>, 'total' => <row count> ].
        // The logs-page.php template iterates $logs directly, so we
        // hand it the inner row array. The full count is exposed via
        // $total_logs so the pager math below (and the template's
        // fallback) can rely on it.
        $logs_result = $logger->get_logs([
            'limit' => $per_page,
            'offset' => $offset,
        ]);

        $logs       = ! empty( $logs_result['logs'] ) ? $logs_result['logs'] : [];
        $total_logs = isset( $logs_result['total'] ) ? (int) $logs_result['total'] : 0;

        $total_pages = ( $total_logs > 0 ) ? (int) ceil( $total_logs / $per_page ) : 1;

        // $logs already contains ONLY the rows for the current page
        // (the database query uses LIMIT $per_page OFFSET $offset). The
        // template's fallback would otherwise call
        //     array_slice( $logs, $offset, $per_page )
        // on a window that is already the right size, returning an
        // empty array for any page > 1 and hiding the entire card
        // (including its pagination controls). Hand the rows to the
        // template as $paginated_logs so the fallback is bypassed.
        $paginated_logs = $logs;

        $stats = $logger->get_statistics();

        include FRL_PLUGIN_PATH . 'admin/templates/logs-page.php';
    }

    /**
     * Render extensions page
     *
     * @since 1.0.0
     */
    public function render_extensions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        include FRL_PLUGIN_PATH . 'admin/templates/extensions-page.php';
    }

    /**
     * Render section callbacks
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general face login settings.', 'recognition') . '</p>';
    }

    public function render_security_section() {
        echo '<p>' . esc_html__('Configure security settings for face authentication.', 'recognition') . '</p>';
    }

    public function render_user_section() {
        echo '<p>' . esc_html__('Configure user enrollment settings.', 'recognition') . '</p>';
    }

    public function render_logging_section() {
        echo '<p>' . esc_html__('Configure logging and data retention.', 'recognition') . '</p>';
    }

    /**
     * Render field callbacks
     */
    public function render_enabled_field() {
        $options = FRL_Options::all();
        $checked = isset($options['enabled']) && $options['enabled'];
        ?>
        <input type="checkbox" id="frl_enabled" name="frl_settings[enabled]" value="1" <?php checked($checked, true); ?>>
        <label for="frl_enabled"><?php esc_html_e('Enable face recognition login', 'recognition'); ?></label>
        <?php
    }

    public function render_https_field() {
        $options = FRL_Options::all();
        $checked = !isset($options['require_https']) || $options['require_https'];
        ?>
        <input type="checkbox" id="frl_require_https" name="frl_settings[require_https]" value="1" <?php checked($checked, true); ?>>
        <label for="frl_require_https"><?php esc_html_e('Require HTTPS connection for face login', 'recognition'); ?></label>
        <p class="description"><?php esc_html_e('Camera access requires HTTPS in most browsers.', 'recognition'); ?></p>
        <?php
    }

    public function render_button_text_field() {
        $options = FRL_Options::all();
        $value = $options['button_text'] ?? __('Login with Face', 'recognition');
        ?>
        <input type="text" id="frl_button_text" name="frl_settings[button_text]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <?php
    }

    public function render_threshold_field() {
        $options = FRL_Options::all();
        $value = $options['match_threshold'] ?? FRL_DEFAULT_MATCH_THRESHOLD;
        ?>
        <input type="number" id="frl_match_threshold" name="frl_settings[match_threshold]" value="<?php echo esc_attr($value); ?>" step="0.01" min="0.30" max="0.70">
        <p class="description"><?php esc_html_e('Lower values are more strict (0.30-0.70, default: 0.45)', 'recognition'); ?></p>
        <?php
    }

    public function render_liveness_field() {
        $options = FRL_Options::all();
        $checked = isset($options['liveness_detection']) && $options['liveness_detection'];
        ?>
        <input type="checkbox" id="frl_liveness" name="frl_settings[liveness_detection]" value="1" <?php checked($checked, true); ?>>
        <label for="frl_liveness"><?php esc_html_e('Enable liveness detection (blink/smile)', 'recognition'); ?></label>
        <p class="description"><?php esc_html_e('Prevents photo-based authentication attacks.', 'recognition'); ?></p>
        <?php
    }

    public function render_rate_limit_field() {
        $options = FRL_Options::all();
        $enabled = isset($options['rate_limit_enabled']) && $options['rate_limit_enabled'];
        $max_attempts = $options['max_failed_attempts'] ?? 5;
        $lockout = $options['lockout_minutes'] ?? 15;

        $is_premium = class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_field_premium('rate_limit_enabled');
        echo FRL_Premium_Gate::open_premium_field('rate_limit_enabled'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML.
        // T1-16 / A-3: aria-describedby points screen readers at the upgrade
        // notice rendered by open_premium_field() so users know why the
        // controls are disabled and how to unlock them.
        $rate_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('rate_limit_enabled') : '';
        $rate_attempts_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('max_failed_attempts') : '';
        $rate_lockout_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('lockout_minutes') : '';
        ?>
        <input type="checkbox" id="frl_rate_limit" name="frl_settings[rate_limit_enabled]" value="1" <?php checked($enabled, true); ?><?php echo $rate_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <label for="frl_rate_limit"><?php esc_html_e('Enable rate limiting', 'recognition'); ?></label>
        <br><br>
        <label><?php esc_html_e('Max failed attempts:', 'recognition'); ?></label>
        <input type="number" name="frl_settings[max_failed_attempts]" value="<?php echo esc_attr($max_attempts); ?>" min="1" max="20" style="width: 60px;"<?php echo $rate_attempts_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <br><br>
        <label><?php esc_html_e('Lockout duration (minutes):', 'recognition'); ?></label>
        <input type="number" name="frl_settings[lockout_minutes]" value="<?php echo esc_attr($lockout); ?>" min="1" max="60" style="width: 60px;"<?php echo $rate_lockout_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <?php
        echo FRL_Premium_Gate::close_premium_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_encrypt_field() {
        $options = FRL_Options::all();
        $checked = isset($options['encrypt_descriptors']) && $options['encrypt_descriptors'];

        $is_premium = class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_field_premium('encrypt_descriptors');
        echo FRL_Premium_Gate::open_premium_field('encrypt_descriptors'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $encrypt_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('encrypt_descriptors') : '';
        ?>
        <input type="checkbox" id="frl_encrypt" name="frl_settings[encrypt_descriptors]" value="1" <?php checked($checked, true); ?><?php echo $encrypt_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <label for="frl_encrypt"><?php esc_html_e('Encrypt stored face descriptors', 'recognition'); ?></label>
        <p class="description"><?php esc_html_e('Uses AES-256 encryption for additional security.', 'recognition'); ?></p>
        <?php
        echo FRL_Premium_Gate::close_premium_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_max_faces_field() {
        $options = FRL_Options::all();
        $value = $options['max_faces_per_user'] ?? 1;

        $is_premium = class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_field_premium('max_faces_per_user');
        echo FRL_Premium_Gate::open_premium_field('max_faces_per_user'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $max_faces_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('max_faces_per_user') : '';
        ?>
        <input type="number" id="frl_max_faces" name="frl_settings[max_faces_per_user]" value="<?php echo esc_attr($value); ?>" min="1" max="20"<?php echo $max_faces_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <p class="description"><?php esc_html_e('Maximum number of face profiles a user can enroll (1-20). The default is 1 and higher values require a Premium license.', 'recognition'); ?></p>
        <?php
        echo FRL_Premium_Gate::close_premium_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_password_fallback_field() {
        $options = FRL_Options::all();
        $checked = !isset($options['require_password_fallback']) || $options['require_password_fallback'];
        ?>
        <input type="checkbox" id="frl_password_fallback" name="frl_settings[require_password_fallback]" value="1" <?php checked($checked, true); ?>>
        <label for="frl_password_fallback"><?php esc_html_e('Allow password login as fallback', 'recognition'); ?></label>
        <p class="description"><?php esc_html_e('Users can still log in with username/password if face login fails.', 'recognition'); ?></p>
        <?php
    }

    public function render_log_auth_field() {
        $options = FRL_Options::all();
        $checked = isset($options['log_authentications']) && $options['log_authentications'];

        $is_premium = class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_field_premium('log_authentications');
        echo FRL_Premium_Gate::open_premium_field('log_authentications'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $log_auth_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('log_authentications') : '';
        ?>
        <input type="checkbox" id="frl_log_auth" name="frl_settings[log_authentications]" value="1" <?php checked($checked, true); ?><?php echo $log_auth_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <label for="frl_log_auth"><?php esc_html_e('Log authentication attempts', 'recognition'); ?></label>
        <?php
        echo FRL_Premium_Gate::close_premium_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function render_log_days_field() {
        $options = FRL_Options::all();
        $value = $options['auto_delete_logs_days'] ?? 30;

        $is_premium = class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_field_premium('auto_delete_logs_days');
        echo FRL_Premium_Gate::open_premium_field('auto_delete_logs_days'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $log_days_disabled = $is_premium ? FRL_Premium_Gate::get_disabled_attr('auto_delete_logs_days') : '';
        ?>
        <input type="number" id="frl_log_days" name="frl_settings[auto_delete_logs_days]" value="<?php echo esc_attr($value); ?>" min="0" max="365"<?php echo $log_days_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe HTML. ?>>
        <p class="description"><?php esc_html_e('Number of days to keep authentication logs (0 to disable auto-deletion)', 'recognition'); ?></p>
        <?php
        echo FRL_Premium_Gate::close_premium_field(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Render a one-time admin notice prompting the user to enroll their face
     * if face login is enabled but they have not yet enrolled a face profile.
     *
     * Shown across the full WordPress admin (except on profile.php /
     * user-edit.php where the enrollment UI already lives, and except
     * on the plugin's own admin pages so it never fights the dashboard UI).
     * The link points to profile.php with `?frl_open_enroll=1`; the
     * profile-enrollment controller (frl-profile-enroll.js) detects this
     * flag and auto-opens the enrollment modal — the same popup that the
     * "Enroll Face" button on the profile page opens manually.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function maybe_render_enroll_face_notice() {
        if (!is_admin() || !is_user_logged_in()) {
            return;
        }

        // Don't compete with the enrollment UI on the screens that already
        // host the form, and skip our own plugin pages.
        global $pagenow;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only pagenow check used to gate the notice; no state is changed.
        $current_page = isset($pagenow) ? (string) $pagenow : '';
        $frl_page     = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $skip_pagenow = ['profile.php', 'user-edit.php', 'user-new.php', 'users.php'];
        if (in_array($current_page, $skip_pagenow, true)) {
            return;
        }
        if (strpos($frl_page, 'frl-') === 0) {
            return;
        }

        // Face login must be enabled for this notice to make sense.
        if (!class_exists('FRL_Options')) {
            return;
        }
        $options = FRL_Options::all();
        if (empty($options['enabled'])) {
            return;
        }

        // Only show to users who can manage their own profile.
        if (!current_user_can('read')) {
            return;
        }

        // Skip if the user has already enrolled at least one face.
        $user_id  = get_current_user_id();
        $database = new FRL_Database();
        $faces    = $database->get_face_descriptors($user_id, false);
        if (is_array($faces) && !empty($faces)) {
            return;
        }

        // Skip if the user has already dismissed this notice on this site.
        $dismiss_key = 'frl_dismiss_enroll_notice_' . $user_id;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only dismissal flag; the dismiss handler (admin_init) is what actually toggles the option.
        if (!empty($_GET['frl_dismiss_enroll_notice']) && check_admin_referer('frl_dismiss_enroll_notice')) {
            update_user_meta($user_id, $dismiss_key, 1);
            return;
        }
        if ((int) get_user_meta($user_id, $dismiss_key, true) === 1) {
            return;
        }

        $profile_url = add_query_arg('frl_open_enroll', '1', admin_url('profile.php'));
        $dismiss_url = wp_nonce_url(
            add_query_arg('frl_dismiss_enroll_notice', '1', $current_page ? admin_url($current_page) : admin_url()),
            'frl_dismiss_enroll_notice'
        );

        echo '<div class="notice notice-info is-dismissible frl-enroll-face-notice" data-frl-enroll-notice="1">';
        echo '<p>';
        echo '<strong>' . esc_html__('Recognition:', 'recognition') . '</strong> ';
        echo esc_html__('No face enrolled yet. Click "Enroll Face" to get started.', 'recognition');
        echo ' <a href="' . esc_url($profile_url) . '" class="button button-secondary" style="margin-left:6px;">';
        echo esc_html__('Enroll Face', 'recognition');
        echo '</a>';
        echo ' <a href="' . esc_url($dismiss_url) . '" class="frl-enroll-notice-dismiss" style="margin-left:6px;text-decoration:none;">';
        echo '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>';
        echo '<span class="screen-reader-text">' . esc_html__('Dismiss this notice.', 'recognition') . '</span>';
        echo '</a>';
        echo '</p>';
        echo '</div>';
    }
}
