<?php
/**
 * Admin Settings Page Template
 *
 * @package Face_Recognition_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current page for active state
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'frl-settings';

$options = FRL_Options::all();
$database = new FRL_Database();
$enrolled_users = $database->get_users_with_faces();
$total_faces = 0;
foreach ($enrolled_users as $user) {
    $total_faces += $user->face_count;
}
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
        </div>

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title">Settings</h1>
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

            <!-- ============================================================ -->
            <!-- HERO BANNER (Plugin version + tagline)                       -->
            <!-- Mirrors the design used on the Dashboard, Users and          -->
            <!-- Logs pages so the Settings sub-page feels like part of      -->
            <!-- the same calm, focused surface.                             -->
            <!-- ============================================================ -->
            <div class="frl-hero" style="margin-bottom: var(--frl-space-6);">
                <div class="frl-hero-inner" style="display: flex; align-items: center; justify-content: space-between; gap: var(--frl-space-6); flex-wrap: wrap; width: 100%;">
                    <div class="frl-hero-text">
                        <div class="frl-hero-eyebrow">
                            <?php
                            /* translators: %s: plugin version number */
                            printf( esc_html__( 'Recognition v%s', 'recognition' ), esc_html( FRL_PLUGIN_VERSION ) );
                            ?>
                        </div>
                        <h2 class="frl-hero-title"><?php esc_html_e( 'Plugin Configuration', 'recognition' ); ?></h2>
                        <p class="frl-hero-subtitle">
                            <?php esc_html_e( 'Tune recognition thresholds, security policies, and data retention. Every change you make here flows through the entire biometric login experience &mdash; from camera capture to audit logs.', 'recognition' ); ?>
                        </p>
                    </div>
                    <div class="frl-hero-aside" aria-hidden="true">
                        <div class="frl-hero-pill <?php echo ! empty( $options['enabled'] ) ? 'is-on' : 'is-off'; ?>">
                            <span class="frl-hero-pill-dot"></span>
                            <?php echo ! empty( $options['enabled'] ) ? esc_html__( 'Plugin Online', 'recognition' ) : esc_html__( 'Plugin Offline', 'recognition' ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('frl_settings_group'); ?>

                <!-- Status -->
                <!-- Reuses the Dashboard's KPI design (frl-bento + frl-kpi) -->
                <!-- so the Plugin Status card has the same visual weight    -->
                <!-- and information hierarchy as the dashboard tiles.        -->
                <div class="frl-form-section">
                    <div class="frl-form-section-header">
                        <h3 class="frl-form-section-title"><?php esc_html_e( 'Plugin Status', 'recognition' ); ?></h3>
                        <p class="frl-form-section-desc"><?php esc_html_e( 'Real-time snapshot of your biometric login environment', 'recognition' ); ?></p>
                    </div>
                    <div class="frl-form-section-body">
                        <div class="frl-bento frl-bento--in-section">
                            <!-- KPI 1: Plugin Status (enabled / disabled) -->
                            <div class="frl-bento-3">
                                <div class="frl-kpi <?php echo ! empty( $options['enabled'] ) ? 'frl-kpi--success' : 'frl-kpi--warning'; ?>">
                                    <div class="frl-kpi-header">
                                        <span class="frl-kpi-label"><?php esc_html_e( 'Plugin Status', 'recognition' ); ?></span>
                                        <div class="frl-kpi-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <?php if ( ! empty( $options['enabled'] ) ) : ?>
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                                <?php else : ?>
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="12" x2="12" y1="8" y2="12"/>
                                                    <line x1="12" x2="12.01" y1="16" y2="16"/>
                                                <?php endif; ?>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="frl-kpi-value"><?php echo ! empty( $options['enabled'] ) ? esc_html__( 'Active', 'recognition' ) : esc_html__( 'Disabled', 'recognition' ); ?></div>
                                        <div class="frl-kpi-meta">
                                            <?php echo ! empty( $options['enabled'] )
                                                ? esc_html__( 'Ready to authenticate', 'recognition' )
                                                : esc_html__( 'Enable to allow face login', 'recognition' ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI 2: Connection (HTTPS / HTTP) -->
                            <div class="frl-bento-3">
                                <div class="frl-kpi <?php echo is_ssl() ? 'frl-kpi--success' : 'frl-kpi--danger'; ?>">
                                    <div class="frl-kpi-header">
                                        <span class="frl-kpi-label"><?php esc_html_e( 'Connection', 'recognition' ); ?></span>
                                        <div class="frl-kpi-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="frl-kpi-value"><?php echo is_ssl() ? esc_html__( 'HTTPS', 'recognition' ) : esc_html__( 'HTTP', 'recognition' ); ?></div>
                                        <div class="frl-kpi-meta">
                                            <?php echo is_ssl()
                                                ? esc_html__( 'Secure transport', 'recognition' )
                                                : esc_html__( 'SSL required for face capture', 'recognition' ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI 3: Enrolled Users -->
                            <div class="frl-bento-3">
                                <div class="frl-kpi frl-kpi--aurora">
                                    <div class="frl-kpi-header">
                                        <span class="frl-kpi-label"><?php esc_html_e( 'Enrolled Users', 'recognition' ); ?></span>
                                        <div class="frl-kpi-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff;">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( count( $enrolled_users ) ) ); ?></div>
                                        <div class="frl-kpi-meta">
                                            <?php
                                            /* translators: %s: total face profiles */
                                            printf( esc_html__( '%s face profiles', 'recognition' ), esc_html( number_format_i18n( $total_faces ) ) );
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KPI 4: License (Free / Premium) -->
                            <?php $premium_active = class_exists( 'FRL_Premium_Gate' ) && FRL_Premium_Gate::is_premium_active(); ?>
                            <div class="frl-bento-3">
                                <div class="frl-kpi <?php echo $premium_active ? 'frl-kpi--success' : 'frl-kpi--info'; ?>">
                                    <div class="frl-kpi-header">
                                        <span class="frl-kpi-label"><?php esc_html_e( 'License', 'recognition' ); ?></span>
                                        <div class="frl-kpi-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                                <?php if ( $premium_active ) : ?>
                                                    <polyline points="9 12 11 14 15 10"/>
                                                <?php else : ?>
                                                    <circle cx="12" cy="11" r="1"/>
                                                <?php endif; ?>
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="frl-kpi-value"><?php echo $premium_active ? esc_html__( 'Premium', 'recognition' ) : esc_html__( 'Free', 'recognition' ); ?></div>
                                        <div class="frl-kpi-meta">
                                            <?php echo $premium_active
                                                ? esc_html__( 'All features unlocked', 'recognition' )
                                                : esc_html__( 'Activate to unlock more', 'recognition' ); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General Settings -->
                <div class="frl-form-section">
                    <div class="frl-form-section-header">
                        <h3 class="frl-form-section-title">General Settings</h3>
                        <p class="frl-form-section-desc">Basic plugin configuration</p>
                    </div>
                    <div class="frl-form-section-body">
                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Enable Recognition</span>
                                <span class="frl-form-label-hint">Allow users to log in with face recognition</span>
                            </div>
                            <div class="frl-form-control">
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[enabled]" value="1" <?php checked(!empty($options['enabled'])); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Require HTTPS</span>
                                <span class="frl-form-label-hint">Enforce HTTPS connection for security</span>
                            </div>
                            <div class="frl-form-control">
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[require_https]" value="1" <?php checked(!empty($options['require_https'])); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Login Button Text</span>
                                <span class="frl-form-label-hint">Text displayed on the login button</span>
                            </div>
                            <div class="frl-form-control">
                                <input type="text" class="frl-input" name="frl_settings[button_text]" value="<?php echo esc_attr($options['button_text'] ?? 'Login with Face'); ?>" placeholder="Login with Face">
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Max Faces Per User<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Maximum face profiles a user can enroll (default 1; higher values require Premium)</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('max_faces_per_user') : '' ); ?>
                                <input type="number" class="frl-input" name="frl_settings[max_faces_per_user]" value="<?php echo esc_attr($options['max_faces_per_user'] ?? 1); ?>" min="1" max="20" style="max-width: 100px;"<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('max_faces_per_user') : '' ); ?>>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="frl-form-section">
                    <div class="frl-form-section-header">
                        <h3 class="frl-form-section-title">Security Settings</h3>
                        <p class="frl-form-section-desc">Authentication and security options</p>
                    </div>
                    <div class="frl-form-section-body">
                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Match Threshold</span>
                                <span class="frl-form-label-hint">Face matching sensitivity (0.30 - 0.70, lower = stricter)</span>
                            </div>
                            <div class="frl-form-control">
                                <input type="number" class="frl-input" name="frl_settings[match_threshold]" value="<?php echo esc_attr($options['match_threshold'] ?? 0.45); ?>" min="0.30" max="0.70" step="0.01" style="max-width: 100px;">
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Liveness Detection</span>
                                <span class="frl-form-label-hint">Require user to blink for anti-spoofing</span>
                            </div>
                            <div class="frl-form-control">
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[liveness_detection]" value="1" <?php checked(!empty($options['liveness_detection'])); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Rate Limiting<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Limit failed login attempts</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('rate_limit_enabled') : '' ); ?>
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[rate_limit_enabled]" value="1" <?php checked(!empty($options['rate_limit_enabled'])); ?><?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('rate_limit_enabled') : '' ); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Max Failed Attempts<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Failed attempts before temporary lockout</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('max_failed_attempts') : '' ); ?>
                                <input type="number" class="frl-input" name="frl_settings[max_failed_attempts]" value="<?php echo esc_attr($options['max_failed_attempts'] ?? 5); ?>" min="1" max="20" style="max-width: 100px;"<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('max_failed_attempts') : '' ); ?>>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Lockout Duration<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Lockout duration in minutes</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('lockout_minutes') : '' ); ?>
                                <input type="number" class="frl-input" name="frl_settings[lockout_minutes]" value="<?php echo esc_attr($options['lockout_minutes'] ?? 15); ?>" min="1" max="60" style="max-width: 100px;"<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('lockout_minutes') : '' ); ?>>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Encrypt Face Data<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Store face descriptors encrypted (AES-256)</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('encrypt_descriptors') : '' ); ?>
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[encrypt_descriptors]" value="1" <?php checked(!empty($options['encrypt_descriptors'])); ?><?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('encrypt_descriptors') : '' ); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Require Password Fallback</span>
                                <span class="frl-form-label-hint">Allow password login as backup method</span>
                            </div>
                            <div class="frl-form-control">
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[require_password_fallback]" value="1" <?php checked(!empty($options['require_password_fallback'])); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logging Settings -->
                <div class="frl-form-section">
                    <div class="frl-form-section-header">
                        <h3 class="frl-form-section-title">Logging Settings</h3>
                        <p class="frl-form-section-desc">Authentication logging and data retention</p>
                    </div>
                    <div class="frl-form-section-body">
                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Log Authentications<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Record all authentication attempts</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('log_authentications') : '' ); ?>
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[log_authentications]" value="1" <?php checked(!empty($options['log_authentications'])); ?><?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('log_authentications') : '' ); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Auto-Delete Logs<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Automatically delete logs older than specified days</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('auto_delete_logs') : '' ); ?>
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[auto_delete_logs]" value="1" <?php checked(!empty($options['auto_delete_logs'])); ?><?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('auto_delete_logs') : '' ); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>

                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text">Log Retention Days<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::render_pro_badge() : '' ); ?></span>
                                <span class="frl-form-label-hint">Number of days to keep logs</span>
                            </div>
                            <div class="frl-form-control">
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::open_premium_field('auto_delete_logs_days') : '' ); ?>
                                <input type="number" class="frl-input" name="frl_settings[auto_delete_logs_days]" value="<?php echo esc_attr($options['auto_delete_logs_days'] ?? 30); ?>" min="7" max="365" style="max-width: 100px;"<?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::get_disabled_attr('auto_delete_logs_days') : '' ); ?>>
                                <?php echo wp_kses_post( class_exists('FRL_Premium_Gate') ? FRL_Premium_Gate::close_premium_field() : '' ); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Uninstall Settings -->
                <div class="frl-form-section">
                    <div class="frl-form-section-header">
                        <h3 class="frl-form-section-title"><?php esc_html_e('Uninstall Settings', 'recognition'); ?></h3>
                        <p class="frl-form-section-desc"><?php esc_html_e('What happens to your data when the plugin is deleted', 'recognition'); ?></p>
                    </div>
                    <div class="frl-form-section-body">
                        <div class="frl-form-row">
                            <div class="frl-form-label">
                                <span class="frl-form-label-text"><?php esc_html_e('Delete data on uninstall', 'recognition'); ?></span>
                                <span class="frl-form-label-hint"><?php esc_html_e('Remove all face profiles, logs, and plugin options when this plugin is deleted. If disabled, deactivating the plugin keeps your data intact so you can reactivate later.', 'recognition'); ?></span>
                            </div>
                            <div class="frl-form-control">
                                <label class="frl-toggle">
                                    <input type="checkbox" name="frl_settings[remove_data_on_uninstall]" value="1" <?php checked(!empty($options['remove_data_on_uninstall'])); ?>>
                                    <span class="frl-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: var(--frl-space-3); margin-top: var(--frl-space-6);">
                    <button type="submit" class="frl-btn frl-btn-primary frl-btn-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save Settings
                    </button>
                    <a href="?page=recognition" class="frl-btn frl-btn-secondary frl-btn-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php // Theme & sidebar toggles are enqueued via FRL_Admin::enqueue_admin_assets() (frl-admin-shared.js - H-2 - 1.0.0). ?>
