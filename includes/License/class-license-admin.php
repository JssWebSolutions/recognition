<?php
/**
 * License Admin UI
 *
 * Adds the License Activation submenu page and registers
 * the assets needed by the page. Page rendering is delegated
 * to a template file under admin/templates/.
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
 * Class FRL_License_Admin
 *
 * @since 1.0.0
 */
class FRL_License_Admin {

    /**
     * Page slug for the license page.
     *
     * @since 1.0.0
     * @var string
     */
    const PAGE_SLUG = 'frl-license';

    /**
     * Parent slug under which the page is registered.
     *
     * @since 1.0.0
     * @var string
     */
    const PARENT_SLUG = 'recognition';

    /**
     * Capability required to access the page.
     *
     * @since 1.0.0
     * @var string
     */
    const CAPABILITY = 'manage_options';

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_frl_activate_license', [$this, 'handle_form_activation']);
        add_action('admin_post_frl_deactivate_license', [$this, 'handle_form_deactivation']);
    }

    /**
     * Register the License Activation submenu.
     *
     * @since 1.0.0
     */
    public function register_menu() {
        add_submenu_page(
            self::PARENT_SLUG,
            __('License Activation', 'recognition'),
            __('License', 'recognition'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue assets.
     *
     * The CSS and the lightweight "dismiss handler" script are
     * loaded on EVERY wp-admin screen (gated by capability) because
     * the SINGLE GLOBAL admin notice rendered by
     * {@see FRL_License_Manager::maybe_render_license_notice()}
     * can appear on any admin page - not just the plugin's own
     * pages. Without the dismiss handler being available
     * everywhere, the WordPress core `is-dismissible` handler
     * would only visually fade the notice out and the dismissal
     * would not be persisted, so the notice would reappear on
     * the next page load.
     *
     * The full License Activation page JavaScript and its
     * localised config are still only enqueued on the License
     * Activation page itself.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook) {
        // Capability check applies to both code paths.
        if (!current_user_can(self::CAPABILITY)) {
return;
        }

        // Always enqueue the admin CSS so the global notice
        // (which appears on every wp-admin screen) renders
        // correctly. Skipping the CSS on non-plugin pages would
        // leave the notice visually broken.
        wp_enqueue_style(
            'frl-license-admin',
            FRL_PLUGIN_URL . 'admin/assets/css/license-admin.css',
            [],
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0'
        );

        // Always enqueue the lightweight license-notice script so
        // the "X" button on the global notice persists the
        // dismissal via AJAX on every wp-admin screen (not just
        // the plugin's own pages). The script is a no-op when no
        // notice is present in the DOM, so the overhead on pages
        // without a license notice is a single delegated event
        // binding.
        wp_enqueue_script(
            'frl-license-notice',
            FRL_PLUGIN_URL . 'admin/assets/js/license-notice.js',
            ['jquery'],
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0',
            true
        );

        wp_localize_script('frl-license-notice', 'frlLicenseNoticeConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ]);

        // Page-specific JS only on the License page.
        if (false === strpos((string) $hook, self::PAGE_SLUG) && false === strpos((string) $hook, self::PARENT_SLUG)) {
            return;
        }

        wp_enqueue_script(
            'frl-license-admin',
            FRL_PLUGIN_URL . 'admin/assets/js/license-admin.js',
            ['jquery'],
            defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '1.0.0',
            true
        );

        wp_localize_script('frl-license-admin', 'frlLicenseConfig', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('frl_license_nonce'),
            'i18n'         => [
                'activating'       => __('Activating…', 'recognition'),
                'deactivating'     => __('Deactivating…', 'recognition'),
                'validating'       => __('Validating…', 'recognition'),
                'genericError'     => __('An unexpected error occurred. Please try again.', 'recognition'),
                'activatedRecently'=> __('License was just activated. Please wait a few seconds before re-validating.', 'recognition'),
                'cooldown'         => __('A reload just happened. Please wait a minute before re-validating again.', 'recognition'),
            ],
        ]);
    }

    /**
     * Render the License Activation page.
     *
     * @since 1.0.0
     */
    public function render_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'recognition'));
        }

        $manager = FRL_License_Manager::get_instance();
        $data    = $manager->get_license_data();
        $domain  = FRL_License_Manager::get_site_domain();

        // Allow templates in themes to override.
        $template = locate_template('frl/license-page.php');
        if (!$template) {
            $template = FRL_PLUGIN_PATH . 'admin/templates/license-page.php';
        }

        /**
         * Filters the template used to render the License Activation page.
         *
         * @since 1.0.0
         * @param string $template Absolute path to the template file.
         */
        $template = apply_filters('frl_license_page_template', $template);

        $vars = [
            'manager' => $manager,
            'data'    => $data,
            'domain'  => $domain,
        ];

        // Make variables available to template.
        $GLOBALS['frl_license_data']    = $data;
        $GLOBALS['frl_license_manager'] = $manager;
        $GLOBALS['frl_license_domain']  = $domain;

        // H-5 / WC-1 fix: replace extract() with explicit variable assignment
        // (Plugin Check fails on extract() in templates).
        $manager = $vars['manager'];
        $data    = $vars['data'];
        $domain  = $vars['domain'];

        include $template;
    }

    /**
     * Handle non-AJAX form POST submission for activation (fallback path).
     *
     * @since 1.0.0
     */
    public function handle_form_activation() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'recognition'));
        }

        check_admin_referer('frl_license_form_action');

        $license_key = isset($_POST['frl_license_key']) ? sanitize_text_field(wp_unslash($_POST['frl_license_key'])) : '';
        $email       = isset($_POST['frl_license_email']) ? sanitize_email(wp_unslash($_POST['frl_license_email'])) : '';

        $manager = FRL_License_Manager::get_instance();
        $result  = $manager->activate_license($license_key, $email);

        $status  = $result['success'] ? 'success' : 'error';
        $message = $result['message'];

        $redirect = add_query_arg(
            [
                'page'        => self::PAGE_SLUG,
                'frl_msg'     => rawurlencode($message),
                'frl_status'  => $status,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Handle non-AJAX form POST submission for deactivation.
     *
     * @since 1.0.0
     */
    public function handle_form_deactivation() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Permission denied.', 'recognition'));
        }

        check_admin_referer('frl_license_form_action');

        $manager = FRL_License_Manager::get_instance();
        $result  = $manager->deactivate_license();

        $redirect = add_query_arg(
            [
                'page'        => self::PAGE_SLUG,
                'frl_msg'     => rawurlencode($result['message']),
                'frl_status'  => 'success',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }
}
