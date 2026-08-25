<?php
/**
 * Plugin Name: Recognition
 * Plugin URI: https://www.jsswebsolutions.com/recognition
 * Description: Privacy-first face recognition login for WordPress. No third-party APIs, fully offline operation.
 * Version: 1.0.0
 * Author: JSS Web Solutions
 * Author URI: https://www.jsswebsolutions.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: recognition
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * Icon URI: assets/icon.svg
 *
 * @package Recognition
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
// Derive FRL_PLUGIN_VERSION from the plugin header so the version is defined
// in exactly one place (the file header). This prevents drift between the
// header `Version:` field and the runtime constant.
if ( ! defined( 'FRL_PLUGIN_VERSION' ) ) {
    $frl_plugin_header = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
    define( 'FRL_PLUGIN_VERSION', $frl_plugin_header['Version'] ?? '1.0.0' );
}
if ( ! defined( 'FRL_TEXT_DOMAIN' ) ) {
    define( 'FRL_TEXT_DOMAIN', 'recognition' );
}
if ( ! defined( 'FRL_DEFAULT_MATCH_THRESHOLD' ) ) {
    define( 'FRL_DEFAULT_MATCH_THRESHOLD', 0.45 );
}
if ( ! defined( 'FRL_DEFAULT_MIN_THRESHOLD' ) ) {
    define( 'FRL_DEFAULT_MIN_THRESHOLD', 0.30 );
}
if ( ! defined( 'FRL_DEFAULT_MAX_THRESHOLD' ) ) {
    define( 'FRL_DEFAULT_MAX_THRESHOLD', 0.70 );
}
define('FRL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('FRL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FRL_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 *
 * @since 1.0.0
 */
class Face_Recognition_Login {

    /**
     * Single instance of the class
     *
     * @since 1.0.0
     * @var Face_Recognition_Login
     */
    private static $instance = null;

    /**
     * Plugin components
     *
     * @since 1.0.0
     * @var array
     */
    private $components = [];

    /**
     * Get single instance of the class
     *
     * @since 1.0.0
     * @return Face_Recognition_Login
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Database
        require_once FRL_PLUGIN_PATH . 'includes/Database/class-database.php';
        require_once FRL_PLUGIN_PATH . 'includes/Installer/class-installer.php';

        // License module (load early so other modules can feature-gate)
        require_once FRL_PLUGIN_PATH . 'includes/License/class-license-api.php';
        require_once FRL_PLUGIN_PATH . 'includes/License/class-license-manager.php';
        require_once FRL_PLUGIN_PATH . 'includes/License/class-feature-gate.php';
        require_once FRL_PLUGIN_PATH . 'includes/License/class-premium-gate.php';

        // Core classes
        require_once FRL_PLUGIN_PATH . 'includes/Authentication/class-authenticator.php';
        require_once FRL_PLUGIN_PATH . 'includes/Users/class-user-profile.php';
        require_once FRL_PLUGIN_PATH . 'includes/Security/class-security.php';
        require_once FRL_PLUGIN_PATH . 'includes/Logger/class-logger.php';
        require_once FRL_PLUGIN_PATH . 'includes/Helpers/class-helper.php';
        require_once FRL_PLUGIN_PATH . 'includes/Options/class-options.php';

        // AJAX handlers
        require_once FRL_PLUGIN_PATH . 'includes/Ajax/class-ajax-handler.php';

        // License AJAX (load after main AJAX to keep consistent order)
        require_once FRL_PLUGIN_PATH . 'includes/License/class-license-ajax.php';

        // REST API
        require_once FRL_PLUGIN_PATH . 'includes/REST/class-rest-controller.php';

        // Admin
        if (is_admin()) {
            require_once FRL_PLUGIN_PATH . 'admin/class-admin.php';
            require_once FRL_PLUGIN_PATH . 'includes/License/class-license-admin.php';
        }
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialize components after plugins loaded (priority 1 to run before add-ons)
        add_action('plugins_loaded', [$this, 'init_components'], 1);

        // Register REST API routes on rest_api_init
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Note: Plugin text domain is loaded automatically by WordPress 4.6+

        // Enqueue scripts and styles
        add_action('login_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Add login form
        add_action('login_form', [$this, 'render_face_login_button']);
        add_filter('authenticate', [$this, 'maybe_authenticate_face'], 1, 3);

        // Add user profile page
        add_action('init', [$this, 'register_user_routes']);

        // Registration page hooks - always show checkbox when face login is enabled
        add_action('register_form', [$this, 'render_registration_face_option']);
        add_action('login_form_register', [$this, 'handle_registration_enrollment']);
        add_action('user_register', [$this, 'registration_complete_redirect'], 10, 1);
        add_action('init', [$this, 'register_registration_routes']);

        // Track WordPress core "set password" email so we can guarantee it is sent
        // before we redirect the user to the face-enrollment page. Core fires
        // wp_new_user_notification() inside register_new_user() before the
        // user_register action, but in some environments the email can be silently
        // dropped (mail queue back-pressure, SMTP auth issues, etc.). Marking the
        // email as "in flight" via this filter lets us avoid sending a duplicate
        // when it does go out, while still being able to re-send it if it didn't.
        add_filter('wp_new_user_notification_email', [$this, 'mark_new_user_email_in_flight'], 10, 3);
        add_filter('wp_new_user_notification_email_admin', [$this, 'mark_new_user_email_in_flight'], 10, 3);

        // Password reset/set page hooks - Face enrollment after email verification
        add_action('login_form_resetpass', [$this, 'render_password_set_face_option']);
        add_action('login_form_rp', [$this, 'render_password_set_face_option']);
        add_action('login_footer', [$this, 'render_password_set_face_option']);

        // User profile page hooks - Add face enrollment to user profile
        add_action('show_user_profile', [$this, 'render_user_profile_face_section']);
        add_action('edit_user_profile', [$this, 'render_user_profile_face_section']);

        // Intercept enrollment request on login page BEFORE the login form is shown
        add_action('login_init', [$this, 'intercept_login_enrollment'], 1);
    }

    /**
     * Plugin activation
     *
     * @since 1.0.0
     */
    public function activate() {
        // Run installer
        $installer = new FRL_Installer();
        $installer->install();

        // Flush rewrite rules to add new routes
        $this->register_user_routes();
        $this->register_registration_routes();
        flush_rewrite_rules();

        // Set activation flag for redirect
        set_transient('frl_activated', true, 30);

        // Register license module cron
        FRL_License_Manager::register_cron();
    }

    /**
     * Plugin deactivation
     *
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('frl_cleanup_expired_logs');
        FRL_License_Manager::unregister_cron();
    }

    /**
     * Initialize plugin components
     *
     * @since 1.0.0
     */
    public function init_components() {
        $this->components = [
            'database' => new FRL_Database(),
            'security' => new FRL_Security(),
            'logger' => new FRL_Logger(),
            'authenticator' => new FRL_Authenticator(),
            'user_profile' => new FRL_User_Profile(),
            'ajax' => new FRL_Ajax_Handler(),
            'rest' => new FRL_REST_Controller(),
        ];

        if (is_admin()) {
            $this->components['admin'] = new FRL_Admin();
            $this->components['license_admin'] = new FRL_License_Admin();
            // Initialise the premium feature gate. Registering
            // the singleton here means all admin-side hooks are
            // available before any admin page renders.
            FRL_Premium_Gate::get_instance();
        }

        // License AJAX is always available; admin-only endpoints are
        // capability-checked inside the handler.
        $this->components['license_ajax'] = new FRL_License_Ajax();
    }

    /**
     * Register REST API routes
     *
     * @since 1.0.0
     */
    public function register_rest_routes() {
        if (!isset($this->components['rest'])) {
            $this->components['rest'] = new FRL_REST_Controller();
        }
        $this->components['rest']->register_routes();
    }

    /**
     * Enqueue scripts and styles
     *
     * @since 1.0.0
     */
    public function enqueue_assets() {
        // Only load on login page or if user dashboard is active
        if ( ! $this->should_enqueue_face_api() ) {
            return;
        }

        $options = FRL_Options::all();

        // Register face-api.js from the local model directory. We use a single
        // canonical source (public/models/face-api.min.js) so the model is
        // consistent across login, profile, and password-reset flows.
        wp_register_script(
            'face-api-js',
            FRL_PLUGIN_URL . 'public/models/face-api.min.js',
            [],
            '0.22.2',
            true
        );

        // Main plugin JS
        wp_register_script(
            'frl-public',
            FRL_PLUGIN_URL . 'public/js/frl-public.js',
            ['face-api-js', 'jquery'],
            FRL_PLUGIN_VERSION,
            true
        );

        // Main plugin CSS
        wp_register_style(
            'frl-public',
            FRL_PLUGIN_URL . 'public/css/frl-public.css',
            [],
            FRL_PLUGIN_VERSION
        );

        // Localize script
        wp_localize_script('frl-public', 'frlConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('frl/v1/'),
            'nonce' => wp_create_nonce('frl_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'isLoginPage' => is_login(),
            'modelsUrl' => FRL_PLUGIN_URL . 'public/models/',
            'settings' => [
                'threshold' => isset($options['match_threshold']) ? floatval($options['match_threshold']) : FRL_DEFAULT_MATCH_THRESHOLD,
                'livenessDetection' => isset($options['liveness_detection']) ? (bool)$options['liveness_detection'] : false,
                'requireHttps' => isset($options['require_https']) ? (bool)$options['require_https'] : true,
            ],
            'i18n' => [
                'initializing' => __('Initializing camera...', 'recognition'),
                'cameraReady' => __('Camera ready', 'recognition'),
                'detectingFace' => __('Detecting face...', 'recognition'),
                'faceDetected' => __('Face detected', 'recognition'),
                'noFace' => __('No face detected', 'recognition'),
                'multipleFaces' => __('Multiple faces detected', 'recognition'),
                'alignFace' => __('Please align your face', 'recognition'),
                'authenticating' => __('Authenticating...', 'recognition'),
                'success' => __('Login successful!', 'recognition'),
                'failed' => __('Authentication failed', 'recognition'),
                'cameraError' => __('Camera error', 'recognition'),
                'permissionDenied' => __('Camera permission denied', 'recognition'),
                'unsupportedBrowser' => __('Your browser does not support camera access', 'recognition'),
                'httpsRequired' => __('HTTPS is required for face login', 'recognition'),
                'livenessCheck' => __('Please blink', 'recognition'),
                'processing' => __('Processing...', 'recognition'),
                'enrollmentComplete' => __('Face enrollment complete!', 'recognition'),
                'enrollmentFailed' => __('Face enrollment failed', 'recognition'),
                'captureSamples' => __('Capturing samples...', 'recognition'),
                // Premium camera overlay strings (unified across main + addons)
                'cameraOn' => __('Camera On', 'recognition'),
                'cameraAdjusting' => __('Adjusting...', 'recognition'),
                'switchingCamera' => __('Switching camera...', 'recognition'),
                'switchCamera' => __('Switch camera', 'recognition'),
                'positionFace' => __('Position your face here', 'recognition'),
                'trustHeading' => __('Why this is safe', 'recognition'),
                'trustSecure' => __('Secure', 'recognition'),
                'trustPrivate' => __('Private', 'recognition'),
                'trustFast' => __('Fast', 'recognition'),
                'trustPasswordless' => __('Passwordless', 'recognition'),
            ],
        ]);

        // Enqueue styles
        wp_enqueue_style('frl-public');

        // Enqueue scripts
        wp_enqueue_script('face-api-js');
        wp_enqueue_script('frl-public');
    }

    /**
     * Decide whether the public face-api.min.js bundle should be loaded on
     * the current request.
     *
     * face-api.min.js is ~3.4 MB and we never want to ship it on a normal
     * front-end page just because the plugin is active. It is only needed
     * on:
     *   1. The wp-login.php screen (face-login modal).
     *   2. Pages where the [face_login_button] shortcode is rendered.
     *
     * Other consumers (the user dashboard, the profile page, the
     * password-reset flow, the enrollment modal on the registration page)
     * load the script via dedicated enqueue helpers
     * (enqueue_dashboard_assets / enqueue_profile_assets) which are called
     * from more specific hooks and never touch the generic
     * wp_enqueue_scripts action.
     *
     * The decision is wrapped in the frl_load_face_api filter so themes
     * and add-ons can opt in to extra contexts (e.g. a custom Elementor
     * widget) without forking the plugin (fixes C-6).
     *
     * @since 1.0.0
     * @return bool
     */
    public function should_enqueue_face_api() {
		// 1. The login page (wp-login.php).
		if ( function_exists( 'is_login' ) && is_login() ) {
			$should_enqueue = true;
		} elseif ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'] ) {
			// is_login() is not available early in some contexts; fall
			// back to the pagenow global.
			$should_enqueue = true;
		} else {
			$should_enqueue = false;
		}

		// 2. A page with the [face_login_button] shortcode.
		if ( ! $should_enqueue && is_singular() && function_exists( 'has_shortcode' ) ) {
			$post = get_post();

			if ( $post && has_shortcode( (string) $post->post_content, 'face_login_button' ) ) {
				$should_enqueue = true;
			}
		}

        /**
         * Filter whether face-api.min.js should be loaded on the current request.
         *
         * Returning true forces the script to load, returning false
         * blocks it. Use this to opt in to additional contexts (e.g. a
         * custom shortcode, an Elementor widget, a WooCommerce checkout
         * flow) without forking the plugin.
         *
         * @since 1.0.0
         *
         * @param bool $should_enqueue Current decision.
         */
        return (bool) apply_filters( 'frl_load_face_api', $should_enqueue );
    }

    /**
     * Render face login button
     *
     * @since 1.0.0
     */
    public function render_face_login_button() {
        $options = FRL_Options::all();

        // Check if face login is enabled
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }

        // Check HTTPS requirement
        if (isset($options['require_https']) && $options['require_https'] && !is_ssl()) {
            return;
        }

        $button_text = isset($options['button_text']) && !empty($options['button_text'])
            ? sanitize_text_field($options['button_text'])
            : __('Login with Face', 'recognition');

        echo '<div class="frl-face-login-container">';
        echo '<button type="button" id="frl-face-login-btn" class="frl-face-btn">';
        echo '<span class="frl-btn-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>';
        echo esc_html($button_text);
        echo '</button>';
        echo '<div id="frl-face-modal" class="frl-modal" style="display: none;">';
        echo '<div class="frl-modal-content">';
        // Header with close button
        echo '<div class="frl-modal-header">';
        echo '<h2 class="frl-modal-title">';
        echo '<span class="frl-modal-title-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></span>';
        echo esc_html__( 'Recognition', 'recognition' );
        echo '</h2>';
        echo '<button type="button" class="frl-close" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>';
        echo '</div>';
        // Modal body with video
        echo '<div class="frl-modal-body">';
        echo '<div id="frl-video-container">';
        echo '<video id="frl-video" autoplay playsinline></video>';
        echo '<canvas id="frl-canvas"></canvas>';
        echo '<div class="frl-face-guide"></div>';
        echo '</div>';
        echo '<div id="frl-status" class="frl-status frl-status-info"></div>';
        echo '<p id="frl-instructions" class="frl-instructions">' . esc_html__('Position your face in the camera and look at the screen', 'recognition') . '</p>';
        echo '<button type="button" id="frl-start-login-btn" class="frl-btn frl-btn-primary frl-btn-lg" style="display:none;">' . esc_html__('Login with Face', 'recognition') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Maybe authenticate with face
     *
     * @since 1.0.0
     * @param mixed $user
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function maybe_authenticate_face($user, $username, $password) {
        // Check if this is a face login request
        if (!isset($_POST['frl_face_login']) || !isset($_POST['frl_descriptor'])) {
            return $user;
        }

        // Verify nonce - check POST value exists first
        $nonce = isset($_POST['frl_nonce']) ? sanitize_text_field(wp_unslash($_POST['frl_nonce'])) : '';
        if (empty($nonce) || !wp_verify_nonce($nonce, 'frl_face_login')) {
            return new WP_Error('frl_invalid_nonce', __('Security check failed.', 'recognition'));
        }

        // Authenticate - sanitize descriptor input
        $descriptor = isset($_POST['frl_descriptor']) ? sanitize_text_field(wp_unslash($_POST['frl_descriptor'])) : '';
        $authenticator = $this->components['authenticator'];
        $result = $authenticator->authenticate_by_descriptor($descriptor);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Register user routes for face dashboard
     *
     * @since 1.0.0
     */
    public function register_user_routes() {
        add_rewrite_rule('^face-login-dashboard/?$', 'index.php?frl_page=1', 'top');
        add_filter('query_vars', function ($vars) {
            $vars[] = 'frl_page';
            return $vars;
        });
        add_filter('template_redirect', function () {
            if (get_query_var('frl_page')) {
                $this->render_user_dashboard();
                exit;
            }
        });
    }

    /**
     * Render user dashboard
     *
     * @since 1.0.0
     */
    private function render_user_dashboard() {
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        include FRL_PLUGIN_PATH . 'public/templates/user-dashboard.php';
    }

    /**
     * Get plugin component
     *
     * @since 1.0.0
     * @param string $name
     * @return mixed|null
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }

    /**
     * Get plugin options
     *
     * @since 1.0.0
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_option($key, $default = null) {
        $options = FRL_Options::all();
        return $options[$key] ?? $default;
    }

    /**
     * Update plugin option
     *
     * @since 1.0.0
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function update_option($key, $value) {
        $options = FRL_Options::all();
        $options[$key] = $value;
        return update_option('frl_settings', $options);
    }

    /**
     * Render face option on registration form
     *
     * @since 1.0.0
     */
    public function render_registration_face_option() {
        $options = FRL_Options::all();

        // Check if face login is enabled (not the registration-specific setting)
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }

        // Get pending enrollment from URL or session
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint; nonces are verified when the enrollment action is consumed.
        $pending_user_id = isset($_GET['enroll_user']) ? intval(wp_unslash($_GET['enroll_user'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint; nonces are verified when the enrollment action is consumed.
        $pending_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        // If this is a direct face enrollment redirect, don't show the checkbox
        if ($pending_user_id && $pending_nonce) {
            return;
        }

        echo '<div class="frl-registration-option" style="margin: 20px 0; padding: 15px; background: #f0f6fc; border-radius: 5px;">';
        echo '<label for="frl_enroll_face" style="display: flex; align-items: center; cursor: pointer;">';
        echo '<input type="checkbox" id="frl_enroll_face" name="frl_enroll_face" value="1" style="margin-right: 10px;" checked>';
        echo esc_html__('Enroll my face for quick login next time', 'recognition');
        echo '</label>';
        echo '<p class="description" style="margin: 5px 0 0 25px;">';
        echo esc_html__('After setting up your account, you can enroll your face to login without passwords.', 'recognition');
        echo '</p>';
        echo '</div>';

        // Store in session/localStorage that user wants to enroll
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var checkbox = document.getElementById("frl_enroll_face");
            if (checkbox) {
                // Default to checked
                checkbox.checked = true;
                sessionStorage.setItem("frl_enroll_on_registration", "1");
                
                checkbox.addEventListener("change", function() {
                    sessionStorage.setItem("frl_enroll_on_registration", this.checked ? "1" : "0");
                });
            }
        });
        </script>';
    }

    /**
     * Add face enrollment option to password reset/set page
     * Hooks into the password reset form - shown when user sets password after registration
     *
     * @since 1.0.0
     */
    public function render_password_set_face_option() {
        // Only on password reset or new user password set pages
        if (!isset($_GET['action']) || !in_array($_GET['action'], ['resetpass', 'rp', 'newcustomerpass'])) {
            return;
        }

        // Check if user wants to enroll (from cookie)
        $enroll_requested = isset($_COOKIE['frl_enroll_requested']) && $_COOKIE['frl_enroll_requested'] === '1';

        // Or check URL parameter (for direct enrollment links)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint; nonce is verified via wp_verify_nonce a few lines below.
        $enroll_user = isset($_GET['enroll_user']) ? intval(wp_unslash($_GET['enroll_user'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint; nonce is verified via wp_verify_nonce a few lines below.
        $enroll_nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        // Also check if user is already logged in (can happen after email verification)
        $logged_in_user = get_current_user_id();

        // Check for transient from registration (most important for new users)
        $pending_enroll_transient = false;
        if ($logged_in_user) {
            $pending_enroll_transient = get_transient('frl_pending_enroll_' . $logged_in_user);
        }

        // If no enrollment request found, don't show the option
        if (!$enroll_requested && !$enroll_user && !$logged_in_user && !$pending_enroll_transient) {
            return;
        }

        // If we have enroll_user in URL, verify nonce
        if ($enroll_user && !wp_verify_nonce($enroll_nonce, 'frl_enroll_' . $enroll_user)) {
            return;
        }

        $options = FRL_Options::all();
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }

        // Enqueue assets
        $this->enqueue_profile_assets();

        // Determine user ID - prefer logged in user (just registered)
        $user_id = 0;

        if ($logged_in_user && $pending_enroll_transient) {
            $user_id = $logged_in_user;
            // Clear the transient since we're using it
            delete_transient('frl_pending_enroll_' . $logged_in_user);
        } elseif ($enroll_user) {
            $user_id = $enroll_user;
        } elseif ($logged_in_user) {
            $user_id = $logged_in_user;
        }

        if (!$user_id) {
            return;
        }

        // Verify this is a valid user
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }

        $database = new FRL_Database();
        $faces = $database->get_face_descriptors($user_id, false);
        $max_faces = isset($options['max_faces_per_user']) ? intval($options['max_faces_per_user']) : 1;
        $can_enroll = count($faces) < $max_faces;

        // Localize config
        wp_localize_script('frl-public', 'frlProfileConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('frl_nonce'),
            'modelsUrl' => FRL_PLUGIN_URL . 'public/models/',
            'i18n' => [
                'initializing' => __('Initializing camera...', 'recognition'),
                'cameraReady' => __('Camera ready', 'recognition'),
                'detectingFace' => __('Detecting face...', 'recognition'),
                'faceDetected' => __('Face detected', 'recognition'),
                'noFace' => __('No face detected', 'recognition'),
                'captureSamples' => __('Capturing samples...', 'recognition'),
                'processing' => __('Processing...', 'recognition'),
                'enrollmentComplete' => __('Face enrolled successfully!', 'recognition'),
                'enrollmentFailed' => __('Face enrollment failed', 'recognition'),
                'cameraError' => __('Camera error', 'recognition'),
                'permissionDenied' => __('Camera permission denied', 'recognition'),
                'modelsError' => __('Failed to load face detection models', 'recognition'),
            ],
        ]);

        ?>
        <div class="frl-password-page-section">
            <h2><?php esc_html_e('Enroll Your Face', 'recognition'); ?></h2>
            <p class="description">
                <?php esc_html_e('Set up face login for quick and secure access. Your face data is stored locally and never shared.', 'recognition'); ?>
            </p>

            <?php if ($can_enroll) : ?>
                <button type="button" id="frl-enroll-face-btn" class="button button-primary">
                    <?php esc_html_e('Enroll Face Now', 'recognition'); ?>
                </button>
                <p class="description" style="margin-top: 10px;">
                    <?php esc_html_e('Or skip this step - you can enroll later from your profile page.', 'recognition'); ?>
                </p>
            <?php else : ?>
                <p class="description">
                    <?php esc_html_e('You have reached the maximum number of enrolled faces.', 'recognition'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Enrollment Modal -->
        <div id="frl-enroll-modal" class="frl-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
            <div class="frl-modal-content" style="background: #fff; margin: 5% auto; padding: 20px; width: 90%; max-width: 500px; border-radius: 5px;">
                <span class="frl-close" style="float: right; font-size: 28px; cursor: pointer;">&times;</span>
                <h2><?php esc_html_e('Enroll Face', 'recognition'); ?></h2>
                <div id="frl-video-container" style="position: relative; margin: 15px 0;">
                    <video id="frl-video" autoplay playsinline style="width: 100%; transform: scaleX(-1);"></video>
                </div>
                <div id="frl-status" class="frl-status"></div>
                <div class="frl-progress" style="margin-top: 15px;">
                    <div id="frl-progress-bar" style="width: 0%; height: 20px; background: #2271b1; transition: width 0.3s;"></div>
                </div>
                <p id="frl-progress-text" style="text-align: center; margin-top: 5px;"></p>
            </div>
        </div>

        <?php
    }

    /**
     * Handle face enrollment checkbox during registration
     * This runs when the registration form is submitted
     *
     * @since 1.0.0
     */
    public function handle_registration_enrollment() {
        // Check if user wants to enroll face during registration
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Hooked into the core `register_post` action; the WP registration form's own nonce is verified by core before this fires.
        if (isset($_POST['frl_enroll_face']) && $_POST['frl_enroll_face'] === '1') {
            // Set a cookie that will be checked after registration is complete
            setcookie('frl_enroll_requested', '1', time() + 3600, '/', '', is_ssl(), true);
        }
    }

    /**
     * Redirect to face enrollment page after successful registration
     * Hooks into 'user_register' action
     *
     * When the user checks "Enroll my face for quick login next time" during registration,
     * we redirect them to the face enrollment page instead of showing the default
     * WordPress "Registration complete" message.
     *
     * Important: We also guarantee that the "set your password" email is delivered
     * before the redirect. WordPress core calls wp_new_user_notification() inside
     * register_new_user() BEFORE the user_register action fires, so on paper the
     * email should always be sent. In practice we have observed that some hosting
     * environments drop the email silently (mail queue back-pressure, SMTP
     * authentication hiccups, plugin conflicts, etc.) when our redirect runs
     * immediately afterwards. To make the delivery reliable we:
     *   1. Set a short-lived "in flight" marker via the wp_new_user_notification_email
     *      filter whenever WordPress core prepares the email.
     *   2. Check that marker here. If it is present and fresh, the email is on
     *      its way and we do nothing. If it is missing (or stale) we re-trigger
     *      wp_new_user_notification() ourselves before redirecting.
     *   3. Always clear the marker so it does not leak into future requests.
     *
     * @since 1.0.0
     * @param int $user_id
     */
    public function registration_complete_redirect($user_id) {
        // Check if user wants to enroll face (from checkbox or cookie)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Hooked into core `user_register` after a successful registration; the WP registration form's own nonce is verified by core before this fires.
        $enroll_face = isset($_POST['frl_enroll_face']) && $_POST['frl_enroll_face'] === '1';
        $enroll_cookie = isset($_COOKIE['frl_enroll_requested']) && $_COOKIE['frl_enroll_requested'] === '1';

        if ($enroll_face || $enroll_cookie) {
            // Clear the cookie
            setcookie('frl_enroll_requested', '', time() - 3600, '/');

            // Make sure the "set your password" email is delivered before we
            // leave the registration page, so the user can always set a password
            // even if they want to use face login instead.
            $this->ensure_new_user_email_sent($user_id);

            // Log the user in immediately so they can access the enrollment page
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);

            // Generate the enrollment URL
            $enrollment_url = $this->get_enrollment_url($user_id, true);

            // Redirect to face enrollment page - this replaces the default
            // WordPress "Registration complete. Please check your email" message
            wp_safe_redirect($enrollment_url);
            exit;
        }
    }

    /**
     * Marker set by the wp_new_user_notification_email(_admin) filter so we
     * know WordPress core is about to (or has just) sent the new-user email.
     *
     * @since 1.0.0
     *
     * @param array   $email    The email arguments (to, subject, message, headers, attachments).
     * @param WP_User $user     The user object.
     * @param string  $blogname The site title.
     *
     * @return array Unmodified email arguments.
     */
    public function mark_new_user_email_in_flight($email, $user, $blogname = '') {
        if (is_object($user) && !empty($user->ID)) {
            update_user_meta((int) $user->ID, 'frl_new_user_email_in_flight', time());
        } elseif (!empty($email['to'])) {
            // Fallback: resolve user by email if the filter didn't pass a user object.
            $maybe_user = get_user_by('email', $email['to']);
            if ($maybe_user) {
                update_user_meta($maybe_user->ID, 'frl_new_user_email_in_flight', time());
            }
        }
        return $email;
    }

    /**
     * Guarantee that the "set your password" email reaches the newly registered
     * user, even when WordPress core's wp_new_user_notification() call has been
     * silently dropped. We only re-send when the in-flight marker is missing or
     * stale, so happy-path users still get exactly one email.
     *
     * @since 1.0.0
     *
     * @param int $user_id
     */
    private function ensure_new_user_email_sent($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $marker     = get_user_meta($user_id, 'frl_new_user_email_in_flight', true);
        $marker_age = $marker ? (time() - (int) $marker) : PHP_INT_MAX;

        // If the marker is missing or older than 30s, assume core's email did
        // not actually go out and re-trigger it. A 30s window is enough to
        // cover any normal in-process delivery without risking duplicates if
        // wp_mail() is slow.
        if ($marker_age > 30) {
            // Remove any default-password nag so the user only sees the new
            // password-set email and not the legacy "check your email" prompt.
            update_user_option($user_id, 'default_password_nag', false, true);

            wp_new_user_notification($user_id, null, 'user');
        }

        // Always clear the marker so it cannot leak into the next request.
        delete_user_meta($user_id, 'frl_new_user_email_in_flight');
    }

    /**
     * Register routes for registration page
     * Handles WordPress subdirectory installations
     *
     * @since 1.0.0
     */
    public function register_registration_routes() {
        // Add query var for registration enrollment
        add_filter('query_vars', function ($vars) {
            $vars[] = 'frl_registration';
            $vars[] = 'frl_enroll';
            return $vars;
        });

        // Intercept enrollment request on init and show enrollment page
        add_action('init', [$this, 'handle_enrollment_request'], 1);
    }

    /**
     * Handle enrollment request - intercepts early and shows enrollment page
     * This works with WordPress subdirectory installations
     *
     * @since 1.0.0
     */
    public function handle_enrollment_request() {
        // Check if this is an enrollment request
        if (!isset($_GET['frl_enroll']) || $_GET['frl_enroll'] !== '1') {
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            // Store enrollment request in cookie/session for after login
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter consumed for cookie storage; nonce is verified via wp_verify_nonce immediately below.
            $enroll_user = isset($_GET['enroll_user']) ? intval(wp_unslash($_GET['enroll_user'])) : 0;
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only URL parameter consumed for nonce verification; checked via wp_verify_nonce immediately below.
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            $registration = isset($_GET['registration']) ? sanitize_text_field(wp_unslash($_GET['registration'])) : '';

            if ($enroll_user && wp_verify_nonce($nonce, 'frl_enroll_' . $enroll_user)) {
                // Store in cookie for after login
                setcookie('frl_pending_enroll', json_encode([
                    'user_id' => $enroll_user,
                    'nonce' => $nonce,
                    'registration' => $registration
                ]), time() + 3600, '/', '', is_ssl(), true);
            }

            // Redirect to login - user needs to be logged in
            wp_safe_redirect(wp_login_url());
            exit;
        }

        // User is logged in - show enrollment page
        $this->render_enrollment_page();
    }

    /**
     * Get the enrollment URL
     *
     * @since 1.0.0
     * @param int $user_id
     * @param bool $is_registration
     * @return string
     */
    private function get_enrollment_url($user_id, $is_registration = false) {
        $nonce = wp_create_nonce('frl_enroll_' . $user_id);
        $args = [
            'frl_enroll' => '1',
            'enroll_user' => $user_id,
            '_wpnonce' => $nonce
        ];
        if ($is_registration) {
            $args['registration'] = '1';
        }
        return add_query_arg($args, wp_login_url());
    }

    /**
     * Intercept login page to show enrollment instead of login form
     * Hooks into login_init with high priority
     *
     * @since 1.0.0
     */
    public function intercept_login_enrollment() {
        // Check if this is an enrollment request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint used to gate the enrollment intercept; no state is changed.
        if (!isset($_GET['frl_enroll']) || $_GET['frl_enroll'] !== '1') {
            return;
        }

        // Check if user is already logged in
        if (!is_user_logged_in()) {
            return; // Let WordPress handle the login form
        }

        // User is logged in and requesting enrollment - show enrollment page directly
        $this->render_enrollment_page();
    }

    /**
     * Render enrollment page
     *
     * @since 1.0.0
     */
    public function render_enrollment_page() {
        // Check if this is a valid enrollment request
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint; nonce is verified at the top of render_enrollment_page.
        $user_id = isset($_GET['enroll_user']) ? intval(wp_unslash($_GET['enroll_user'])) : get_current_user_id();

        // Verify user exists
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_die(esc_html__('User not found.', 'recognition'));
        }

        // Ensure user is logged in - this is the primary security check
        // For enrollment after registration, user should be logged in via wp_set_auth_cookie()
        if (!is_user_logged_in()) {
            // Try to set the user as logged in (handles post-registration redirect)
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
        }

        // Critical security check: Verify the logged-in user matches the requested user_id
        // This prevents one user from enrolling for another
        $current_user_id = get_current_user_id();
        if ($current_user_id !== $user_id) {
            // User is logged in but as a different user - this is suspicious
            // Allow admins to enroll for other users, but redirect regular users
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You cannot enroll a face for another user.', 'recognition'));
            }
            // For admins enrolling for users, use the target user's ID
            $user_id = $current_user_id;
            $user = get_user_by('id', $user_id);
        }

        // Clear the pending enrollment cookie if set
        if (isset($_COOKIE['frl_pending_enroll'])) {
            setcookie('frl_pending_enroll', '', time() - 3600, '/');
        }

        // Is this from registration?
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template hint used to render the post-registration banner; no state is changed.
        $is_from_registration = isset($_GET['registration']) && sanitize_text_field(wp_unslash($_GET['registration'])) === '1';

        $frl_enroll_plugin_url = defined('FRL_PLUGIN_URL') ? FRL_PLUGIN_URL : plugin_dir_url(__FILE__);
        wp_enqueue_style('frl-fonts', $frl_enroll_plugin_url . 'public/css/frl-fonts.css', array(), '1.0.0');
        wp_enqueue_style('frl-public', $frl_enroll_plugin_url . 'public/css/frl-public.css', array('frl-fonts'), '1.0.0');
        wp_enqueue_script('frl-face-api', $frl_enroll_plugin_url . 'public/models/face-api.min.js', array(), '1.0.0', true);
        wp_enqueue_script('frl-public', $frl_enroll_plugin_url . 'public/js/frl-public.js', array('frl-face-api', 'jquery'), '1.0.0', true);

        // Load required files
        require_once FRL_PLUGIN_PATH . 'public/templates/registration-page.php';
        exit;
    }

    /**
     * Render face section on user profile page
     *
     * @since 1.0.0
     * @param WP_User $user
     */
    public function render_user_profile_face_section($user) {
        // Check permissions
        if (!current_user_can('read') || !is_a($user, 'WP_User')) {
            return;
        }

        // The id of the user whose profile is being rendered. On
        // `profile.php` this is the same as the logged-in user, but
        // on `user-edit.php?user_id=X` it is the *target* user the
        // admin is editing. We pass it to the JS via
        // frlProfileConfig.targetUserId so the enrollment AJAX
        // payload includes the right owner id - otherwise the face
        // would be saved against the admin's own id (this was the
        // previous bug).
        $target_user_id = (int) $user->ID;

        $options = FRL_Options::all();

        // Check if face login is enabled
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }

        // Enqueue assets for this page
        $this->enqueue_profile_assets();

        // Get user's enrolled faces
        $database = new FRL_Database();
        $faces_data = $database->get_face_descriptors($user->ID, false);
        $max_faces = isset($options['max_faces_per_user']) ? intval($options['max_faces_per_user']) : 1;
        $can_enroll = count($faces_data) < $max_faces;

        wp_localize_script('frl-public', 'frlProfileConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('frl_nonce'),
            'restUrl' => rest_url('frl/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'modelsUrl' => FRL_PLUGIN_URL . 'public/models/',
            // License state: the JS uses this to short-circuit
            // premium-only actions (e.g. deleting a face from
            // the user-profile screen) and show the same
            // "This is a premium feature" notice used on the
            // Enrolled Users admin page, instead of asking for
            // confirmation and then silently failing.
            'isPremium' => class_exists('FRL_Premium_Gate') ? (bool) FRL_Premium_Gate::is_premium_active() : true,
            // Current user id - the JS uses this together with the
            // data-owner-id attribute on each delete button to skip
            // the premium gate for self-deletions (e.g. when the user
            // is editing their own profile on profile.php). Only
            // cross-user deletion from user-edit.php?user_id=X is a
            // premium operation.
            'currentUserId' => get_current_user_id(),
            // Target user id - the user whose profile is currently
            // being rendered. This is the same as `currentUserId`
            // on `profile.php`, but on `user-edit.php?user_id=X`
            // it is the OTHER user the admin is editing. The JS
            // must include this id in the enrollment AJAX payload
            // so the face is saved against the correct owner. If
            // omitted, frlProfileConfig.currentUserId is used as a
            // fallback for back-compat (the original JS only knew
            // about the current user).
            'targetUserId' => isset( $target_user_id ) ? (int) $target_user_id : get_current_user_id(),
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
                'cameraError' => __('Camera error', 'recognition'),
                'permissionDenied' => __('Camera permission denied', 'recognition'),
                'modelsError' => __('Failed to load face detection models', 'recognition'),
                // Strings used by the extracted profile-enroll.js controller.
                'confirmDelete'    => __('Are you sure you want to delete this face?', 'recognition'),
                'errorDelete'      => __('Error deleting face', 'recognition'),
                'premiumMessage'   => __('This is a premium feature. Please activate your license to use it.', 'recognition'),
            ],
        ]);
        ?>
        <div class="frl-profile-section">
            <h2><?php esc_html_e('Recognition', 'recognition'); ?></h2>
            <p class="description">
                <?php esc_html_e('Enroll your face to login using facial recognition. Your face data is stored securely and never leaves this website.', 'recognition'); ?>
            </p>

            <?php if (!empty($faces_data)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Device', 'recognition'); ?></th>
                            <th><?php esc_html_e('Created', 'recognition'); ?></th>
                            <th><?php esc_html_e('Last Used', 'recognition'); ?></th>
                            <th><?php esc_html_e('Actions', 'recognition'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faces_data as $face) : ?>
                            <tr>
                                <td><?php echo esc_html($face['device_name']); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($face['created_at']))); ?></td>
                                <td><?php echo !empty($face['last_used']) ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($face['last_used']))) : esc_html__('Never', 'recognition'); ?></td>
                                <td>
                                    <button type="button" class="button frl-delete-face-profile" data-face-id="<?php echo esc_attr($face['id']); ?>" data-owner-id="<?php echo esc_attr($target_user_id); ?>">
                                        <?php esc_html_e('Delete', 'recognition'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">
                    <?php
                    /* translators: 1: number of enrolled faces, 2: maximum number of faces allowed. */
                    printf(esc_html__('Enrolled: %1$d / %2$d', 'recognition'), (int) count($faces_data), (int) $max_faces);
                    ?>
                </p>
            <?php else : ?>
                <div class="frl-no-faces">
                    <p><?php esc_html_e('No face enrolled yet. Click "Enroll Face" to get started.', 'recognition'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($can_enroll) : ?>
                <button type="button" id="frl-enroll-face-profile-btn" class="button button-primary">
                    <?php esc_html_e('Enroll Face', 'recognition'); ?>
                </button>
            <?php else : ?>
                <p class="description" style="color: var(--frl-error, #dc2626);">
                    <?php esc_html_e('Maximum number of faces reached. Delete an existing face to add a new one.', 'recognition'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Enrollment Modal for Profile Page - Admin Dashboard Style -->
        <div id="frl-enroll-modal-profile" class="frl-modal" style="display: none;">
            <div class="frl-modal-content">
                <div class="frl-profile-modal-header">
                    <h2 class="frl-profile-modal-title">
                        <span class="frl-profile-modal-title-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </span>
                        <?php esc_html_e('Enroll Face', 'recognition'); ?>
                    </h2>
                    <button type="button" class="frl-close frl-close-profile" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="frl-modal-body">
                    <div id="frl-video-container-profile">
                        <video id="frl-video-profile" autoplay playsinline></video>
                        <canvas id="frl-canvas-profile"></canvas>
                        <div class="frl-face-guide"></div>
                    </div>
                    <div id="frl-status-profile" class="frl-status frl-status-info"></div>
                    <p id="frl-instructions-profile" class="frl-instructions">
                        <?php esc_html_e('Position your face in the camera and look at the screen', 'recognition'); ?>
                    </p>
                    <div class="frl-enroll-progress">
                        <div id="frl-enroll-progress-bar-profile"></div>
                    </div>
                    <p id="frl-enroll-progress-text-profile"></p>
                </div>
            </div>
        </div>

        <?php
    }

    /**
     * Enqueue assets for user profile page
     *
     * @since 1.0.0
     */
    public function enqueue_dashboard_assets() {
        $options = FRL_Options::all();

        // Register face-api.js from the local model directory.
        wp_register_script(
            'face-api-js',
            FRL_PLUGIN_URL . 'public/models/face-api.min.js',
            [],
            '0.22.2',
            true
        );

        // Main plugin JS.
        wp_register_script(
            'frl-public',
            FRL_PLUGIN_URL . 'public/js/frl-public.js',
            [ 'face-api-js', 'jquery' ],
            FRL_PLUGIN_VERSION,
            true
        );

        // Main plugin CSS.
        wp_register_style(
            'frl-public',
            FRL_PLUGIN_URL . 'public/css/frl-public.css',
            [],
            FRL_PLUGIN_VERSION
        );

        // Localize the public script with the same payload used on the
        // login page so the dashboard re-uses the same matching/liveness
        // settings.
        wp_localize_script( 'frl-public', 'frlConfig', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'restUrl'     => rest_url( 'frl/v1/' ),
            'nonce'       => wp_create_nonce( 'frl_nonce' ),
            'restNonce'   => wp_create_nonce( 'wp_rest' ),
            'adminUrl'    => admin_url(),
            'isLoginPage' => false,
            'modelsUrl'   => FRL_PLUGIN_URL . 'public/models/',
            'settings'    => [
                'threshold'         => isset( $options['match_threshold'] ) ? floatval( $options['match_threshold'] ) : FRL_DEFAULT_MATCH_THRESHOLD,
                'livenessDetection' => isset( $options['liveness_detection'] ) ? (bool) $options['liveness_detection'] : false,
                'requireHttps'      => isset( $options['require_https'] ) ? (bool) $options['require_https'] : true,
            ],
        ] );

        wp_enqueue_style( 'frl-public' );
        wp_enqueue_script( 'face-api-js' );
        wp_enqueue_script( 'frl-public' );
    }

    /**
     * Enqueue assets for user profile page
     *
     * @since 1.0.0
     */
    public function enqueue_profile_assets() {
        $options = FRL_Options::all();

        // Check if face login is enabled
        if (!isset($options['enabled']) || !$options['enabled']) {
            return;
        }

        // Register face-api.js from the local model directory. Loading from a
        // third-party CDN (e.g. cdn.jsdelivr.net) was a privacy concern for the
        // face-recognition flow, since the script touches the camera. We always
        // ship face-api.js locally and load it from public/models/.
        wp_register_script(
            'face-api-js',
            FRL_PLUGIN_URL . 'public/models/face-api.min.js',
            [],
            '0.22.2',
            true
        );

        // Main plugin JS
        wp_register_script(
            'frl-public',
            FRL_PLUGIN_URL . 'public/js/frl-public.js',
            ['face-api-js', 'jquery'],
            FRL_PLUGIN_VERSION,
            true
        );

        // Profile-enrollment controller ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ handles both the password-reset
        // enrollment modal and the user-profile enrollment modal. Replaces
        // ~280 lines of inline JS that previously lived inside this file.
        wp_register_script(
            'frl-profile-enroll',
            FRL_PLUGIN_URL . 'public/js/frl-profile-enroll.js',
            ['frl-public', 'jquery'],
            FRL_PLUGIN_VERSION,
            true
        );

        // Profile-enrollment styles ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â€šÂ¬Ã…â€œ replaces the inline <style> blocks
        // formerly rendered by render_password_set_face_option() and
        // render_user_profile_face_section().
        wp_register_style(
            'frl-profile-enroll',
            FRL_PLUGIN_URL . 'public/css/frl-profile-enroll.css',
            ['frl-public'],
            FRL_PLUGIN_VERSION
        );

        // Make wp.i18n (translation runtime) available to the new script.
        wp_set_script_translations( 'frl-profile-enroll', 'recognition' );

        // Main plugin CSS
        wp_register_style(
            'frl-public',
            FRL_PLUGIN_URL . 'public/css/frl-public.css',
            [],
            FRL_PLUGIN_VERSION
        );

        // Enqueue
        wp_enqueue_style('frl-public');
        wp_enqueue_style('frl-profile-enroll');
        wp_enqueue_script('face-api-js');
        wp_enqueue_script('frl-public');
        wp_enqueue_script('frl-profile-enroll');
    }
}

// Initialize plugin
function frl_init() {
    return Face_Recognition_Login::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'frl_init', 0);

/**
 * Helper function to get plugin instance
 *
 * @since 1.0.0
 * @return Face_Recognition_Login
 */
function frl() {
    return Face_Recognition_Login::get_instance();
}
