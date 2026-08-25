<?php
/**
 * Admin Addons Page Template
 *
 * @package Face_Recognition_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current page for active state
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'frl-extensions';

// Check addon statuses
$wc_addon_active = class_exists('FRL_WooCommerce_Addon');
$wc_addon_path = WP_PLUGIN_DIR . '/frl-woocommerce-addon/frl-woocommerce-addon.php';
$wc_addon_installed = file_exists($wc_addon_path);

$qr_addon_active = class_exists('FRL_QR_Login_Addon');
$qr_addon_path = WP_PLUGIN_DIR . '/frl-qr-login-addon/frl-qr-login-addon.php';
$qr_addon_installed = file_exists($qr_addon_path);

// For future addons - placeholder paths
$hand_gesture_path = WP_PLUGIN_DIR . '/frl-hand-gesture/frl-hand-gesture.php';
$face_gesture_path = WP_PLUGIN_DIR . '/frl-face-gesture/frl-face-gesture.php';

$hand_gesture_installed = file_exists($hand_gesture_path);
$face_gesture_installed = file_exists($face_gesture_path);
?>

<!-- App Container -->
<div class="frl-app" id="frl-app">
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
            <button type="button" class="frl-theme-toggle" id="frl-theme-toggle" aria-label="Toggle theme">
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

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title">Addons</h1>
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
            <div class="frl-addons-grid">
                
                <!-- WooCommerce Addon -->
                <div class="frl-addon-card">
                    <div class="frl-addon-icon" style="background: #96588a; color: white;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                    </div>
                    <div class="frl-addon-content">
                        <h3 class="frl-addon-title">WooCommerce Addon</h3>
                        <p class="frl-addon-description">
                            Secure your WooCommerce checkout with face recognition. Enable biometric verification for high-value orders, account verification, and fraud prevention.
                        </p>
                        <ul class="frl-addon-features">
                            <li>Face verification at checkout</li>
                            <li>Account takeover protection</li>
                            <li>Order fraud detection</li>
                            <li>WooCommerce My Account integration</li>
                        </ul>
                    </div>
                    <div class="frl-addon-footer">
                        <?php if ($wc_addon_active) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed & Active
                            </span>
                        <?php elseif ($wc_addon_installed) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed (Inactive)
                            </span>
                            <a href="<?php echo esc_url( admin_url('plugins.php') ); ?>" class="frl-btn frl-btn-primary frl-btn-sm">Activate</a>
                        <?php else : ?>
                            <span class="frl-addon-status not-installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8v8"/>
                                    <path d="M8 12h8"/>
                                </svg>
                                Not Installed
                            </span>
                            <a href="https://www.jsswebsolutions.com/frl-woocommerce-addon" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary frl-btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" x2="12" y1="15" y2="3"/>
                                </svg>
                                Download
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- QR Code Login -->
                <div class="frl-addon-card">
                    <div class="frl-addon-icon" style="background: #d63638; color: white;">
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
                    </div>
                    <div class="frl-addon-content">
                        <h3 class="frl-addon-title">QR Code Login</h3>
                        <p class="frl-addon-description">
                            Add a secure, single-use, time-limited QR Code login flow. Lets users sign in on devices without a webcam by using their smartphone camera for face authentication.
                        </p>
                        <ul class="frl-addon-features">
                            <li>QR code login on any device</li>
                            <li>Single-use, time-limited sessions</li>
                            <li>Mobile face authentication fallback</li>
                            <li>WooCommerce My Account integration</li>
                        </ul>
                    </div>
                    <div class="frl-addon-footer">
                        <?php if ($qr_addon_active) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed &amp; Active
                            </span>
                        <?php elseif ($qr_addon_installed) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed (Inactive)
                            </span>
                            <a href="<?php echo esc_url( admin_url('plugins.php') ); ?>" class="frl-btn frl-btn-primary frl-btn-sm">Activate</a>
                        <?php else : ?>
                            <span class="frl-addon-status not-installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 8v8"/>
                                    <path d="M8 12h8"/>
                                </svg>
                                Not Installed
                            </span>
                            <a href="https://www.jsswebsolutions.com/frl-qr-login-addon" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary frl-btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" x2="12" y1="15" y2="3"/>
                                </svg>
                                Download
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hand Gesture Controls -->
                <div class="frl-addon-card">
                    <div class="frl-addon-icon" style="background: #007cba; color: white;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/>
                            <path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v2"/>
                            <path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/>
                            <path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>
                        </svg>
                    </div>
                    <div class="frl-addon-content">
                        <h3 class="frl-addon-title">Hand Gesture Controls</h3>
                        <p class="frl-addon-description">
                            Control your WordPress interface with hand gestures. Navigate pages, scroll content, click buttons, and perform actions using intuitive hand movements detected by your camera.
                        </p>
                        <ul class="frl-addon-features">
                            <li>Gesture-based navigation</li>
                            <li>Scroll control with hand movements</li>
                            <li>Custom gesture mapping</li>
                            <li>Accessibility support</li>
                        </ul>
                    </div>
                    <div class="frl-addon-footer">
                        <?php if ($hand_gesture_installed) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed
                            </span>
                        <?php else : ?>
                            <span class="frl-addon-status coming-soon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6l4 2"/>
                                </svg>
                                Coming Soon
                            </span>
                            <button type="button" class="frl-btn frl-btn-secondary frl-btn-sm" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" x2="12" y1="15" y2="3"/>
                                </svg>
                                Download
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Face Gestures -->
                <div class="frl-addon-card">
                    <div class="frl-addon-icon" style="background: #00a32a; color: white;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                            <line x1="9" x2="9.01" y1="9" y2="9"/>
                            <line x1="15" x2="15.01" y1="9" y2="9"/>
                        </svg>
                    </div>
                    <div class="frl-addon-content">
                        <h3 class="frl-addon-title">Face Gestures</h3>
                        <p class="frl-addon-description">
                            Unlock advanced face-based interactions. Use facial expressions like smiles, winks, or head nods to trigger actions, navigate content, or control your WordPress dashboard hands-free.
                        </p>
                        <ul class="frl-addon-features">
                            <li>Smile to scroll/like</li>
                            <li>Head nod navigation</li>
                            <li>Wink to select/click</li>
                            <li>Custom gesture training</li>
                        </ul>
                    </div>
                    <div class="frl-addon-footer">
                        <?php if ($face_gesture_installed) : ?>
                            <span class="frl-addon-status installed">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Installed
                            </span>
                        <?php else : ?>
                            <span class="frl-addon-status coming-soon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6l4 2"/>
                                </svg>
                                Coming Soon
                            </span>
                            <button type="button" class="frl-btn frl-btn-secondary frl-btn-sm" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" x2="12" y1="15" y2="3"/>
                                </svg>
                                Download
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

            </div>



            <!-- ====================================================== -->

            <!-- EXISTING: "Want to Build Your Own Addon?" CTA strip    -->

            <!-- ====================================================== -->

            <div class="frl-more-addons">

                <h3 class="frl-more-addons-title"><?php esc_html_e('Want to Build Your Own Addon?', 'recognition'); ?></h3>

                <p class="frl-more-addons-desc">

                    <?php esc_html_e('Create custom extensions for Recognition. Build integrations, add new recognition methods, or create specialized features for your business.', 'recognition'); ?>

                </p>

                <a href="https://www.jsswebsolutions.com/" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-outline">

                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">

                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>

                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>

                    </svg>

                    <?php esc_html_e('Hire Us &mdash; Get Your Own Addon', 'recognition'); ?>

                </a>

            </div>



            <!-- ====================================================== -->

            <!-- PROMO CARDS: HIRE A DEVELOPER + WANT A CUSTOM PLUGIN   -->

            <!-- ====================================================== -->

            <div class="frl-extensions-promo-grid">



                <!-- Hire a Developer (matches dashboard) -->

                <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=extensions&utm_campaign=hire-dev"

                   target="_blank" rel="noopener noreferrer"

                   class="frl-promo">

                    <div>

                        <div class="frl-promo-tag">

                            <span class="frl-promo-tag-dot"></span>

                            <?php esc_html_e('Hire Expert Help', 'recognition'); ?>

                        </div>

                        <h3 class="frl-promo-title"><?php esc_html_e('Hire a Developer', 'recognition'); ?></h3>

                        <p class="frl-promo-desc">

                            <?php esc_html_e('Need a hand with custom integrations, WooCommerce flows, mobile-first auth, or scaling biometric login? Get a vetted WordPress engineer from JSS Web Solutions on demand.', 'recognition'); ?>

                        </p>

                        <ul class="frl-promo-features">

                            <li><?php esc_html_e('WordPress', 'recognition'); ?></li>

                            <li><?php esc_html_e('WooCommerce', 'recognition'); ?></li>

                            <li><?php esc_html_e('PHP / JS', 'recognition'); ?></li>

                            <li><?php esc_html_e('AI / ML', 'recognition'); ?></li>

                            <li><?php esc_html_e('From $40/hr', 'recognition'); ?></li>

                        </ul>

                    </div>

                    <div class="frl-promo-cta">

                        <span><?php esc_html_e('Talk to JSS', 'recognition'); ?></span>

                        <span class="frl-promo-cta-arrow">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>

                        </span>

                    </div>

                </a>



                <!-- Want a Custom Plugin? (matches dashboard) -->

                <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=extensions&utm_campaign=custom-plugin"

                   target="_blank" rel="noopener noreferrer"

                   class="frl-promo frl-promo--warm">

                    <div>

                        <div class="frl-promo-tag">

                            <span class="frl-promo-tag-dot" style="background: var(--frl-aurora-4); box-shadow: 0 0 8px var(--frl-aurora-4);"></span>

                            <?php esc_html_e('Custom Build', 'recognition'); ?>

                        </div>

                        <h3 class="frl-promo-title"><?php esc_html_e('Want a Custom Plugin?', 'recognition'); ?></h3>

                        <p class="frl-promo-desc">

                            <?php esc_html_e('Have an idea that goes beyond face login? JSS Web Solutions designs and ships bespoke WordPress plugins &mdash; from biometric auth to complex SaaS workflows &mdash; built for performance and security.', 'recognition'); ?>

                        </p>

                        <ul class="frl-promo-features">

                            <li><?php esc_html_e('Bespoke UX', 'recognition'); ?></li>

                            <li><?php esc_html_e('Secure Code', 'recognition'); ?></li>

                            <li><?php esc_html_e('Long-term Support', 'recognition'); ?></li>

                            <li><?php esc_html_e('Discovery &rarr; Ship', 'recognition'); ?></li>

                        </ul>

                    </div>

                    <div class="frl-promo-cta">

                        <span><?php esc_html_e('Start Your Project', 'recognition'); ?></span>

                        <span class="frl-promo-cta-arrow">

                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>

                        </span>

                    </div>

                </a>



            </div>




            <!-- ====================================================== -->

            <!-- "NEED SOMETHING CUSTOM?" BRAND STRIP (matches dashboard)-->

            <!-- ====================================================== -->

            <div class="frl-extensions-cta">

                <div>

                    <div class="frl-extensions-cta-eyebrow">

                        <?php esc_html_e('Built by JSS Web Solutions', 'recognition'); ?>

                    </div>

                    <h3 class="frl-extensions-cta-title"><?php esc_html_e('Need something custom?', 'recognition'); ?></h3>

                    <p class="frl-extensions-cta-desc">

                        <?php esc_html_e('Whether it is hiring a developer or commissioning a bespoke WordPress plugin, JSS Web Solutions is the team behind Recognition &mdash; and we build for the long haul.', 'recognition'); ?>

                    </p>

                </div>

                <div class="frl-extensions-cta-actions">

                    <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=extensions-strip&utm_campaign=hire-dev" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary">

                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>

                        <?php esc_html_e('Hire a Developer', 'recognition'); ?>

                    </a>

                    <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=extensions-strip&utm_campaign=custom-plugin" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-secondary">

                        <?php esc_html_e('Custom Plugin &rarr;', 'recognition'); ?>

                    </a>

                    <a href="https://jsswebsolutions.com/contact/?utm_source=frl-plugin&utm_medium=extensions-strip&utm_campaign=contact" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-ghost">

                        <?php esc_html_e('Contact Us', 'recognition'); ?>

                    </a>

                </div>

            </div>

        </div>
    </main>
</div>

<?php // Theme & sidebar toggles are enqueued via FRL_Admin::enqueue_admin_assets() (frl-admin-shared.js - H-2 - 1.0.0). ?>
