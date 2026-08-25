<?php
/**
 * Reusable Sidebar Navigation Partial
 *
 * Centralized sidebar that can be included in any admin page.
 * Centralizes all navigation links in one location for easier maintenance.
 *
 * Available variables (set by the including template before include):
 *  - string $current_page  Current admin page slug for active state (e.g., 'frl-settings')
 *  - string $logo_url      (Optional) Override logo URL. Defaults to FRL_PLUGIN_URL . 'assets/images/frl-icon.svg'
 *  - string $logo_text     (Optional) Override logo text. Defaults to 'Recognition'
 *  - string $logo_link     (Optional) Override logo link href. Defaults to '?page=recognition'
 *  - bool   $show_wc_section (Optional) Show the WooCommerce section. Defaults to auto-detect.
 *  - bool   $is_addon      (Optional) Whether the including file is the WooCommerce addon.
 *
 * Usage:
 *   $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'frl-settings';
 *   include FRL_PLUGIN_PATH . 'admin/templates/partials/sidebar.php';
 *
 * @package Face_Recognition_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

// Setup defaults
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($current_page) ? $current_page : (isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'recognition');
$logo_url     = isset($logo_url) && !empty($logo_url) ? $logo_url : FRL_PLUGIN_URL . 'admin/assets/images/frl-icon.svg';
$logo_text    = isset($logo_text) && !empty($logo_text) ? $logo_text : __('Recognition', 'recognition');
$logo_link    = isset($logo_link) && !empty($logo_link) ? $logo_link : '?page=recognition';

// Determine whether to render the WooCommerce section.
// Auto-detect by default; allow callers to force-enable or force-disable.
if (isset($show_wc_section)) {
    $should_show_wc = (bool) $show_wc_section;
} else {
    $should_show_wc = class_exists('FRL_WooCommerce_Addon');
}

// Determine whether to render the QR Code Login section.
// Auto-detect by default; allow callers to force-enable or force-disable.
if (isset($show_qr_section)) {
    $should_show_qr = (bool) $show_qr_section;
} else {
    $should_show_qr = class_exists('FRL_QR_Login_Addon');
}

// Is the current page a WooCommerce addon page (for highlighting)
$is_wc_addon_page = in_array($current_page, ['frl-woocommerce-addon', 'frl-wc-settings', 'frl-wc-reports', 'frl-wc-logs'], true);

// Is the current page a QR addon page (for highlighting)
$is_qr_addon_page = in_array($current_page, ['frl-qr-dashboard', 'frl-qr-login', 'frl-qr-logs', 'frl-qr-sessions', 'frl-qr-statistics'], true);
?>
<!-- Sidebar (reusable partial) -->
<aside class="frl-sidebar" id="frl-sidebar">
    <div class="frl-sidebar-header">
        <a href="<?php echo esc_url($logo_link); ?>" class="frl-sidebar-logo">
            <div class="frl-sidebar-logo-icon">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($logo_text); ?>" class="frl-sidebar-logo-img">
            </div>
            <span class="frl-sidebar-logo-text"><?php echo esc_html($logo_text); ?></span>
        </a>
    </div>

    <nav class="frl-sidebar-nav">
        <!-- Overview Section -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('Overview', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=recognition" class="frl-nav-link<?php echo $current_page === 'recognition' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/>
                            <rect width="7" height="5" x="14" y="3" rx="1"/>
                            <rect width="7" height="9" x="14" y="12" rx="1"/>
                            <rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Dashboard', 'recognition'); ?></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Management Section -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('Management', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=frl-settings" class="frl-nav-link<?php echo $current_page === 'frl-settings' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Settings', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-users" class="frl-nav-link<?php echo $current_page === 'frl-users' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Enrolled Users', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-enroll-face" class="frl-nav-link<?php echo $current_page === 'frl-enroll-face' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" x2="19" y1="8" y2="14"/>
                            <line x1="22" x2="16" y1="11" y2="11"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Enroll Face', 'recognition'); ?></span>
                        <?php if (class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_page_premium('frl-enroll-face') && !FRL_Premium_Gate::is_premium_active()) : ?>
                            <span class="frl-nav-link-badge"><?php echo FRL_Premium_Gate::render_pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-logs" class="frl-nav-link<?php echo $current_page === 'frl-logs' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" x2="8" y1="13" y2="13"/>
                            <line x1="16" x2="8" y1="17" y2="17"/>
                            <line x1="10" x2="8" y1="9" y2="9"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Auth Logs', 'recognition'); ?></span>
                        <?php if (class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_page_premium('frl-logs') && !FRL_Premium_Gate::is_premium_active()) : ?>
                            <span class="frl-nav-link-badge"><?php echo FRL_Premium_Gate::render_pro_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <?php if ($should_show_wc) : ?>
        <!-- WooCommerce Section (visible only when add-on is active) -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('WooCommerce', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=frl-woocommerce-addon" class="frl-nav-link<?php echo $current_page === 'frl-woocommerce-addon' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/>
                            <rect width="7" height="5" x="14" y="3" rx="1"/>
                            <rect width="7" height="9" x="14" y="12" rx="1"/>
                            <rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('WC Dashboard', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-wc-settings" class="frl-nav-link<?php echo $current_page === 'frl-wc-settings' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('WooCommerce', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-wc-reports" class="frl-nav-link<?php echo $current_page === 'frl-wc-reports' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="m19 9-5 5-4-4-3 3"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('WC Reports', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-wc-logs" class="frl-nav-link<?php echo $current_page === 'frl-wc-logs' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" x2="8" y1="13" y2="13"/>
                            <line x1="16" x2="8" y1="17" y2="17"/>
                            <line x1="10" x2="8" y1="9" y2="9"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('WC Logs', 'recognition'); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($should_show_qr) : ?>
        <!-- QR Code Login Section (visible only when QR add-on is active) -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('QR Login', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=frl-qr-dashboard" class="frl-nav-link<?php echo $current_page === 'frl-qr-dashboard' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="7" height="9" x="3" y="3" rx="1"/>
                            <rect width="7" height="5" x="14" y="3" rx="1"/>
                            <rect width="7" height="9" x="14" y="12" rx="1"/>
                            <rect width="7" height="5" x="3" y="16" rx="1"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('QR Dashboard', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-qr-login" class="frl-nav-link<?php echo $current_page === 'frl-qr-login' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"/>
                            <rect x="14" y="3" width="7" height="7"/>
                            <rect x="3" y="14" width="7" height="7"/>
                            <line x1="14" y1="14" x2="14" y2="17"/>
                            <line x1="17" y1="14" x2="17" y2="17"/>
                            <line x1="20" y1="14" x2="20" y2="17"/>
                            <line x1="14" y1="20" x2="17" y2="20"/>
                            <line x1="20" y1="17" x2="20" y2="20"/>
                            <line x1="17" y1="20" x2="17" y2="21"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('QR Settings', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-qr-statistics" class="frl-nav-link<?php echo $current_page === 'frl-qr-statistics' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="m19 9-5 5-4-4-3 3"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('QR Statistics', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-qr-sessions" class="frl-nav-link<?php echo $current_page === 'frl-qr-sessions' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12a9 9 0 0 1-9 9 9 9 0 0 1-9-9 9 9 0 0 1 9-9 9 9 0 0 1 9 9z"/>
                            <path d="M12 7v5l3 2"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('QR Sessions', 'recognition'); ?></span>
                    </a>
                </li>
                <li class="frl-nav-item">
                    <a href="?page=frl-qr-logs" class="frl-nav-link<?php echo $current_page === 'frl-qr-logs' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" x2="8" y1="13" y2="13"/>
                            <line x1="16" x2="8" y1="17" y2="17"/>
                            <line x1="10" x2="8" y1="9" y2="9"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('QR Logs', 'recognition'); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Extensions Section -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('Extensions', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=frl-extensions" class="frl-nav-link<?php echo $current_page === 'frl-extensions' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('Extensions', 'recognition'); ?></span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- License Section -->
        <div class="frl-nav-section">
            <div class="frl-nav-section-title"><?php esc_html_e('License', 'recognition'); ?></div>
            <ul class="frl-nav-list">
                <li class="frl-nav-item">
                    <a href="?page=frl-license" class="frl-nav-link<?php echo $current_page === 'frl-license' ? ' active' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="8" r="6"/>
                            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                        </svg>
                        <span class="frl-nav-link-text"><?php esc_html_e('License', 'recognition'); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="frl-sidebar-footer">
        <button type="button" class="frl-sidebar-toggle" id="frl-toggle-sidebar" aria-label="Toggle sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m11 17-5-5 5-5"/>
                <path d="m18 17-5-5 5-5"/>
            </svg>
        </button>
    </div>
</aside>
<?php
// Reset to default after use to avoid leaking to other includes
unset($current_page, $logo_url, $logo_text, $logo_link, $show_wc_section, $is_addon);
?>
