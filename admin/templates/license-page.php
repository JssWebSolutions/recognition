<?php
/**
 * License Activation Page Template
 *
 * Renders the License Activation admin page. Provides both an
 * activation form (for unactivated sites) and a license details
 * panel (for activated sites), plus a feature comparison grid
 * so the user can see what is included in their plan.
 *
 * Available template variables:
 *  - $manager FRL_License_Manager The license manager instance.
 *  - $data    array               The stored license data.
 *  - $domain  string              The current site domain.
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// If the template was not included via the admin class, the globals
// populated there will be available; otherwise the local variables
// provided through extract() will be used.
$manager = isset($manager) ? $manager : (isset($GLOBALS['frl_license_manager']) ? $GLOBALS['frl_license_manager'] : null);
$data    = isset($data) ? $data : (isset($GLOBALS['frl_license_data']) ? $GLOBALS['frl_license_data'] : []);
$domain  = isset($domain) ? $domain : (isset($GLOBALS['frl_license_domain']) ? $GLOBALS['frl_license_domain'] : '');

if (!$manager instanceof FRL_License_Manager) {
    $manager = FRL_License_Manager::get_instance();
}

$status        = isset($data['status']) ? $data['status'] : 'inactive';
$is_active     = $manager->is_license_valid(true);
$status_labels = [
    'active'          => __('Active', 'recognition'),
    'grace'           => __('In Grace Period', 'recognition'),
    'offline'         => __('Offline (Cached)', 'recognition'),
    'inactive'        => __('Not Activated', 'recognition'),
    'invalid'         => __('Invalid', 'recognition'),
    'expired'         => __('Expired', 'recognition'),
    'revoked'         => __('Revoked', 'recognition'),
    'suspended'       => __('Suspended', 'recognition'),
    'domain_mismatch' => __('Domain Mismatch', 'recognition'),
    'limit_exceeded'  => __('Activation Limit Exceeded', 'recognition'),
];
$status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;

// Allow URL-based feedback (e.g. after non-AJAX form submission).
$flash_message = '';
$flash_type    = '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feedback parameter from a redirect; no state is changed.
if (isset($_GET['frl_msg'])) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only feedback parameter; value is wp_unslash()'d here and then sanitize_text_field()'d on the next line.
    $frl_msg_raw   = wp_unslash($_GET['frl_msg']);
    $flash_message = sanitize_text_field(rawurldecode($frl_msg_raw));
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only feedback parameter; value is whitelisted to 'success' vs 'error'.
    $raw_status    = isset($_GET['frl_status']) ? sanitize_text_field(wp_unslash($_GET['frl_status'])) : '';
    $flash_type    = ('success' === $raw_status) ? 'success' : 'error';
}

$free_features    = FRL_License_Manager::get_free_features();
$premium_features = FRL_License_Manager::get_premium_features();

// Group premium features into attractive categories for the comparison grid.
$feature_groups = [
    [
        'key'   => 'security',
        'title' => __('Security &amp; Protection', 'recognition'),
        'icon'  => 'shield',
        'color' => 'blue',
        'items' => [
            ['key' => 'rate_limiting',     'label' => __('Rate Limiting', 'recognition'),         'free' => false, 'premium' => true],
            ['key' => 'max_attempts',      'label' => __('Failed Attempt Limits', 'recognition'), 'free' => false, 'premium' => true],
            ['key' => 'lockout',           'label' => __('Account Lockout', 'recognition'),       'free' => false, 'premium' => true],
            ['key' => 'encrypt_descriptors','label' => __('Encrypted Face Descriptors', 'recognition'),'free' => false, 'premium' => true],
        ],
    ],
    [
        'key'   => 'analytics',
        'title' => __('Analytics &amp; Insights', 'recognition'),
        'icon'  => 'chart',
        'color' => 'purple',
        'items' => [
            ['key' => 'auth_logs',      'label' => __('Authentication Logs', 'recognition'),   'free' => true,  'premium' => true],
            ['key' => 'auto_delete',    'label' => __('Auto-Delete Old Logs', 'recognition'), 'free' => false, 'premium' => true],
            ['key' => 'log_retention',  'label' => __('Custom Log Retention', 'recognition'),'free' => false, 'premium' => true],
            ['key' => 'reports',        'label' => __('Activity Reports', 'recognition'),     'free' => false, 'premium' => true],
        ],
    ],
    [
        'key'   => 'experience',
        'title' => __('User Experience', 'recognition'),
        'icon'  => 'sparkles',
        'color' => 'emerald',
        'items' => [
            ['key' => 'face_enrollment',     'label' => __('Face Enrollment', 'recognition'),    'free' => true,  'premium' => true],
            ['key' => 'face_login',          'label' => __('Recognition', 'recognition'),       'free' => true,  'premium' => true],
            ['key' => 'password_fallback',   'label' => __('Password Fallback', 'recognition'),  'free' => true,  'premium' => true],
            ['key' => 'user_management',     'label' => __('User Management', 'recognition'),    'free' => true,  'premium' => true],
        ],
    ],
    [
        'key'   => 'support',
        'title' => __('Support &amp; Updates', 'recognition'),
        'icon'  => 'life-buoy',
        'color' => 'amber',
        'items' => [
            ['key' => 'basic_settings', 'label' => __('Basic Settings', 'recognition'),     'free' => true,  'premium' => true],
            ['key' => 'priority_support','label' => __('Priority Support', 'recognition'), 'free' => false, 'premium' => true],
            ['key' => 'updates',        'label' => __('Premium Updates', 'recognition'),   'free' => false, 'premium' => true],
            ['key' => 'multi_site',     'label' => __('Multisite Support', 'recognition'), 'free' => false, 'premium' => true],
        ],
    ],
];

// Get current page for active state
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'frl-license';
?>

<!-- App Container -->
<div class="frl-app frl-license-page" id="frl-app">
    <!-- Sidebar (reusable partial) -->
    <?php include FRL_PLUGIN_PATH . 'admin/templates/partials/sidebar.php'; ?>
    <main class="frl-main">
        <!-- Mobile Header -->
        <div class="frl-mobile-header">
            <button type="button" class="frl-mobile-menu-btn" id="frl-mobile-menu-btn" aria-label="Open menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" x2="20" y1="12" y2="12"/>
                    <line x1="4" x2="20" y1="6" y2="6"/>
                    <line x1="4" x2="20" y1="18" y2="18"/>
                </svg>
            </button>
            <span class="frl-header-title">Recognition</span>
        </div>

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title"><?php esc_html_e('License Activation', 'recognition'); ?></h1>
                <p class="frl-header-subtitle">
                    <?php
                    if ($is_active) {
                        esc_html_e('Manage your license and unlock premium features.', 'recognition');
                    } else {
                        esc_html_e('Activate your license to unlock all premium features.', 'recognition');
                    }
                    ?>
                </p>
            </div>
            <div class="frl-header-right">
                <button type="button" class="frl-theme-toggle" id="frl-theme-toggle-header" aria-label="Toggle theme">
                    <svg class="frl-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2"/>
                        <path d="M12 20v2"/>
                        <path d="m4.93 4.93 1.41 1.41"/>
                        <path d="m17.66 17.66 1.41 1.41"/>
                        <path d="M2 12h2"/>
                        <path d="M20 12h2"/>
                        <path d="m6.34 17.66-1.41 1.41"/>
                        <path d="m19.07 4.93-1.41 1.41"/>
                    </svg>
                    <svg class="frl-icon-moon" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                    </svg>
                </button>
            </div>
        </header>

        <!-- Content -->
        <div class="frl-content">

            <?php if (!empty($flash_message)) : ?>
                <div class="notice notice-<?php echo esc_attr($flash_type); ?> is-dismissible" style="margin-bottom: var(--frl-space-4);">
                    <p><?php echo esc_html($flash_message); ?></p>
                </div>
            <?php endif; ?>

            <div class="frl-license-grid">
                <!-- License Card -->
                <div class="frl-license-card">
                    <div class="frl-license-card__header">
                        <div class="frl-license-card__title-wrap">
                            <div class="frl-license-card__icon frl-license-card__icon--<?php echo $is_active ? 'active' : 'inactive'; ?>" aria-hidden="true">
                                <?php if ($is_active) : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 12l2 2 4-4"/>
                                        <path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z"/>
                                    </svg>
                                <?php else : ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                                        <line x1="2" x2="22" y1="10" y2="10"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 class="frl-license-card__title">
                                    <?php
                                    if ($is_active) {
                                        esc_html_e('Your License', 'recognition');
                                    } else {
                                        esc_html_e('Activate Your License', 'recognition');
                                    }
                                    ?>
                                </h2>
                                <p class="frl-license-card__subtitle">
                                    <?php
                                    if ($is_active) {
                                        esc_html_e('License details and management', 'recognition');
                                    } else {
                                        esc_html_e('Enter your license key to unlock premium features', 'recognition');
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        <span class="frl-license-status frl-license-status--<?php echo esc_attr($status); ?>">
                            <span class="frl-license-status__dot" aria-hidden="true"></span>
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </div>

                    <div class="frl-license-card__body">
                        <?php if ($is_active) : ?>
                            <?php
                            $plan         = !empty($data['plan']) ? $data['plan'] : __('Standard', 'recognition');
                            $email        = !empty($data['email']) ? $data['email'] : '';
                            $activated    = !empty($data['activated_at']) ? $data['activated_at'] : '';
                            $expires      = !empty($data['expires_at']) ? $data['expires_at'] : '';
                            $last_check   = !empty($data['last_check_at']) ? $data['last_check_at'] : '';
                            $masked_key   = '';
                            if (!empty($data['license_key'])) {
                                $key          = $data['license_key'];
                                $visible      = substr($key, -4);
                                $masked_key   = str_repeat('•', max(0, strlen($key) - 4)) . $visible;
                            }
                            $date_format = get_option('date_format', 'F j, Y');
                            $time_format = get_option('time_format', 'g:i a');
                            ?>

                            <div class="frl-license-details-list">
                                <div class="frl-license-detail-row">
                                    <div class="frl-license-detail-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 7h-7"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>
                                        </svg>
                                        <?php esc_html_e('Plan', 'recognition'); ?>
                                    </div>
                                    <div class="frl-license-detail-value">
                                        <span class="frl-license-pill frl-license-pill--plan"><?php echo esc_html($plan); ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($email)) : ?>
                                    <div class="frl-license-detail-row">
                                        <div class="frl-license-detail-label">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                            </svg>
                                            <?php esc_html_e('Registered Email', 'recognition'); ?>
                                        </div>
                                        <div class="frl-license-detail-value"><?php echo esc_html($email); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="frl-license-detail-row">
                                    <div class="frl-license-detail-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                        </svg>
                                        <?php esc_html_e('License Key', 'recognition'); ?>
                                    </div>
                                    <div class="frl-license-detail-value"><code class="frl-license-key"><?php echo esc_html($masked_key); ?></code></div>
                                </div>
                                <div class="frl-license-detail-row">
                                    <div class="frl-license-detail-label">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                        </svg>
                                        <?php esc_html_e('Site Domain', 'recognition'); ?>
                                    </div>
                                    <div class="frl-license-detail-value"><code class="frl-license-domain"><?php echo esc_html($domain); ?></code></div>
                                </div>
                                <?php if (!empty($activated)) : ?>
                                    <div class="frl-license-detail-row">
                                        <div class="frl-license-detail-label">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                            <?php esc_html_e('Activated', 'recognition'); ?>
                                        </div>
                                        <div class="frl-license-detail-value">
                                            <?php
                                            $activated_ts = strtotime($activated);
                                            if ($activated_ts) {
                                                echo esc_html(wp_date($date_format . ' ' . $time_format, $activated_ts));
                                            } else {
                                                echo esc_html($activated);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($expires) && '0000-00-00 00:00:00' !== $expires) : ?>
                                    <div class="frl-license-detail-row">
                                        <div class="frl-license-detail-label">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                            </svg>
                                            <?php esc_html_e('Expires', 'recognition'); ?>
                                        </div>
                                        <div class="frl-license-detail-value">
                                            <?php
                                            $expires_ts = strtotime($expires);
                                            if ($expires_ts) {
                                                echo esc_html(wp_date($date_format, $expires_ts));
                                            } else {
                                                echo esc_html($expires);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($last_check)) : ?>
                                    <div class="frl-license-detail-row">
                                        <div class="frl-license-detail-label">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/>
                                            </svg>
                                            <?php esc_html_e('Last Verified', 'recognition'); ?>
                                        </div>
                                        <div class="frl-license-detail-value">
                                            <?php
                                            $last_check_ts = strtotime($last_check);
                                            if ($last_check_ts) {
                                                /* translators: %s: time ago string */
                                                echo esc_html(sprintf(__('%s ago', 'recognition'), human_time_diff($last_check_ts, current_time('timestamp'))));
                                            } else {
                                                echo esc_html($last_check);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="frl-license-actions">
                                <button type="button" class="frl-btn frl-btn-secondary" id="frl-validate-license-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                                        <path d="M21 3v5h-5"/>
                                    </svg>
                                    <?php esc_html_e('Re-validate Now', 'recognition'); ?>
                                </button>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="frl-license-deactivate-form" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to deactivate this license on this site?', 'recognition')); ?>');">
                                    <?php wp_nonce_field('frl_license_form_action'); ?>
                                    <input type="hidden" name="action" value="frl_deactivate_license">
                                    <button type="submit" class="frl-btn frl-btn-danger">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6 6 18"/>
                                            <path d="m6 6 12 12"/>
                                        </svg>
                                        <?php esc_html_e('Deactivate License', 'recognition'); ?>
                                    </button>
                                </form>
                            </div>

                        <?php else : ?>

                            <p class="frl-license-intro">
                                <?php esc_html_e('Enter the license key and email address you used at the time of purchase to activate premium features.', 'recognition'); ?>
                            </p>

                            <?php if (!empty($data['message']) && in_array($status, ['invalid', 'expired', 'revoked', 'suspended', 'domain_mismatch', 'limit_exceeded'], true)) : ?>
                                <div class="frl-license-message frl-license-message--error">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" x2="12" y1="9" y2="13"/>
                                        <line x1="12" x2="12.01" y1="17" y2="17"/>
                                    </svg>
                                    <?php echo esc_html($data['message']); ?>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="frl-license-activate-form" id="frl-license-activate-form">
                                <?php wp_nonce_field('frl_license_form_action'); ?>
                                <input type="hidden" name="action" value="frl_activate_license">

                                <div class="frl-license-form-grid">
                                    <div class="frl-license-form-field">
                                        <label for="frl-license-key" class="frl-license-form-label">
                                            <span class="frl-license-form-label-text"><?php esc_html_e('License Key', 'recognition'); ?></span>
                                            <span class="frl-license-form-label-hint"><?php esc_html_e('You can find your license key in the purchase confirmation email or in your customer dashboard.', 'recognition'); ?></span>
                                        </label>
                                        <input
                                            type="text"
                                            id="frl-license-key"
                                            name="frl_license_key"
                                            value="<?php echo esc_attr(isset($data['license_key']) ? $data['license_key'] : ''); ?>"
                                            class="frl-input frl-input--license-key"
                                            placeholder="FRL-XXXX-XXXX-XXXX-XXXX-XXXX"
                                            autocomplete="off"
                                            spellcheck="false"
                                            required
                                        />
                                    </div>

                                    <div class="frl-license-form-field">
                                        <label for="frl-license-email" class="frl-license-form-label">
                                            <span class="frl-license-form-label-text"><?php esc_html_e('Email Address', 'recognition'); ?></span>
                                            <span class="frl-license-form-label-hint"><?php esc_html_e('The email address you used at the time of purchase.', 'recognition'); ?></span>
                                        </label>
                                        <input
                                            type="email"
                                            id="frl-license-email"
                                            name="frl_license_email"
                                            value="<?php echo esc_attr(isset($data['email']) ? $data['email'] : ''); ?>"
                                            class="frl-input frl-input--email"
                                            placeholder="<?php esc_attr_e('mail@website.com', 'recognition'); ?>"
                                            autocomplete="email"
                                            required
                                        />
                                    </div>

                                    <div class="frl-license-form-field">
                                        <label class="frl-license-form-label">
                                            <span class="frl-license-form-label-text"><?php esc_html_e('Site Domain', 'recognition'); ?></span>
                                            <span class="frl-license-form-label-hint"><?php esc_html_e('This domain will be associated with your license activation.', 'recognition'); ?></span>
                                        </label>
                                        <code class="frl-license-domain frl-license-domain--readonly"><?php echo esc_html($domain); ?></code>
                                    </div>

                                    <div class="frl-license-form-actions">
                                        <button type="submit" class="frl-btn frl-btn-primary frl-btn-lg" id="frl-activate-license-btn">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect width="20" height="14" x="2" y="5" rx="2"/>
                                                <line x1="2" x2="22" y1="10" y2="10"/>
                                            </svg>
                                            <span class="frl-btn-label"><?php esc_html_e('Activate License', 'recognition'); ?></span>
                                            <span class="frl-btn-spinner" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                                                </svg>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>

                        <?php endif; ?>

                        <div id="frl-license-ajax-message" class="frl-license-message" style="display:none;"></div>
                    </div>
                </div>

                <!-- System Information Card -->
                <div class="frl-license-card frl-license-card--sidebar">
                    <div class="frl-license-card__header">
                        <div class="frl-license-card__title-wrap">
                            <div class="frl-license-card__icon frl-license-card__icon--info" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="16" x2="12" y2="12"/>
                                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="frl-license-card__title"><?php esc_html_e('System Information', 'recognition'); ?></h2>
                                <p class="frl-license-card__subtitle"><?php esc_html_e('Server and plugin details', 'recognition'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="frl-license-card__body">
                        <div class="frl-license-details-list">
                            <div class="frl-license-detail-row">
                                <div class="frl-license-detail-label"><?php esc_html_e('Plugin Version', 'recognition'); ?></div>
                                <div class="frl-license-detail-value frl-license-detail-value--mono"><?php echo esc_html(defined('FRL_PLUGIN_VERSION') ? FRL_PLUGIN_VERSION : '—'); ?></div>
                            </div>
                            <div class="frl-license-detail-row">
                                <div class="frl-license-detail-label"><?php esc_html_e('WordPress Version', 'recognition'); ?></div>
                                <div class="frl-license-detail-value frl-license-detail-value--mono"><?php echo esc_html(get_bloginfo('version')); ?></div>
                            </div>
                            <div class="frl-license-detail-row">
                                <div class="frl-license-detail-label"><?php esc_html_e('PHP Version', 'recognition'); ?></div>
                                <div class="frl-license-detail-value frl-license-detail-value--mono"><?php echo esc_html(PHP_VERSION); ?></div>
                            </div>
                            <div class="frl-license-detail-row">
                                <div class="frl-license-detail-label"><?php esc_html_e('Site Domain', 'recognition'); ?></div>
                                <div class="frl-license-detail-value frl-license-detail-value--mono"><?php echo esc_html($domain); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan Features Card -->
            <div class="frl-license-card frl-license-card--features">
                <div class="frl-license-card__header frl-license-card__header--features">
                    <div class="frl-license-card__title-wrap">
                        <div class="frl-license-card__icon frl-license-card__icon--features" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="frl-license-card__title"><?php esc_html_e('Plan Features', 'recognition'); ?></h2>
                            <p class="frl-license-card__subtitle">
                                <?php
                                if ($is_active) {
                                    esc_html_e('All features included in your plan are unlocked on this site.', 'recognition');
                                } else {
                                    esc_html_e('Activate your license to unlock all premium features. The free plan includes the basics below.', 'recognition');
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                    <div class="frl-license-plan-legend" aria-hidden="true">
                        <span class="frl-license-plan-pill frl-license-plan-pill--free">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            <?php esc_html_e('Free', 'recognition'); ?>
                        </span>
                        <span class="frl-license-plan-pill frl-license-plan-pill--premium">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            <?php esc_html_e('Premium', 'recognition'); ?>
                        </span>
                    </div>
                </div>

                <div class="frl-license-card__body">
                    <div class="frl-license-features-grid">
                        <?php foreach ($feature_groups as $group_index => $group) : ?>
                            <div class="frl-license-feature-group frl-license-feature-group--<?php echo esc_attr($group['color']); ?>">
                                <div class="frl-license-feature-group__header">
                                    <div class="frl-license-feature-group__icon" aria-hidden="true">
                                        <?php if ('shield' === $group['icon']) : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                            </svg>
                                        <?php elseif ('chart' === $group['icon']) : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>
                                            </svg>
                                        <?php elseif ('sparkles' === $group['icon']) : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M3 12h3M18 12h3M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                        <?php else : ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/>
                                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="frl-license-feature-group__title"><?php echo wp_kses($group['title'], ['br' => []]); ?></h3>
                                </div>
                                <ul class="frl-license-feature-list">
                                    <?php foreach ($group['items'] as $item) : ?>
                                        <li class="frl-license-feature-item">
                                            <span class="frl-license-feature-item__name"><?php echo esc_html($item['label']); ?></span>
                                            <span class="frl-license-feature-item__badges">
                                                <?php if ($item['free']) : ?>
                                                    <span class="frl-license-feature-badge frl-license-feature-badge--free" title="<?php esc_attr_e('Included in Free', 'recognition'); ?>">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                                    </span>
                                                <?php else : ?>
                                                    <span class="frl-license-feature-badge frl-license-feature-badge--na" title="<?php esc_attr_e('Not in Free', 'recognition'); ?>">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($item['premium']) : ?>
                                                    <?php if ($is_active) : ?>
                                                        <span class="frl-license-feature-badge frl-license-feature-badge--premium" title="<?php esc_attr_e('Included in Premium', 'recognition'); ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M20 6 9 17l-5-5"/>
                                                            </svg>
                                                        </span>
                                                    <?php else : ?>
                                                        <span class="frl-license-feature-badge frl-license-feature-badge--locked" title="<?php esc_attr_e('Premium feature - activate your license', 'recognition'); ?>">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                                            </svg>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else : ?>
                                                    <span class="frl-license-feature-badge frl-license-feature-badge--na-premium" title="<?php esc_attr_e('Not included', 'recognition'); ?>">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!$is_active) : ?>
                        <div class="frl-license-cta">
                            <div class="frl-license-cta__content">
                                <div class="frl-license-cta__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                                        <line x1="2" x2="22" y1="10" y2="10"/>
                                    </svg>
                                </div>
                                <div class="frl-license-cta__text">
                                    <h3 class="frl-license-cta__title"><?php esc_html_e('Ready to unlock all features?', 'recognition'); ?></h3>
                                    <p class="frl-license-cta__desc"><?php esc_html_e('Get a license key to access all premium features, priority support, and future updates.', 'recognition'); ?></p>
                                </div>
                                <a href="https://license.jsswebsolutions.com" class="frl-btn frl-btn-primary frl-btn-lg" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Get a License', 'recognition'); ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                        <polyline points="15 3 21 3 21 9"/>
                                        <line x1="10" x2="21" y1="14" y2="3"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

<?php // Theme & sidebar toggles are enqueued via FRL_Admin::enqueue_admin_assets() (frl-admin-shared.js - H-2 - 1.0.0). ?>
