<?php
/**
 * Admin Dashboard Page Template
 *
 * Designed with Swiss + Glassmorphism + Bento + Aurora design system.
 * Adds five new cards: Welcome Message, Hire a Developer, Want a Custom
 * Plugin, System Health, and the "Need something custom?" brand strip.
 *
 * @package Face_Recognition_Login
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get plugin data
$options   = FRL_Options::all();
$database  = new FRL_Database();
$logger    = new FRL_Logger();

$is_enabled     = !empty($options['enabled']);
$is_https       = is_ssl();
$enrolled_users = $database->get_users_with_faces();
$total_faces    = 0;
foreach ($enrolled_users as $user) {
    $total_faces += $user->face_count;
}

$stats           = $logger->get_statistics(30);
$logs            = $logger->get_logs(['limit' => 4]);
$recent_activity = !empty($logs['logs']) ? $logs['logs'] : [];

// Check if WooCommerce addon is active
$wc_addon_active = class_exists('FRL_WooCommerce_Addon');

// Check if Premium license is active (for System Health card)
$premium_active  = (class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_premium_active());

// Current user display name (used by Welcome card)
$current_user    = wp_get_current_user();
$user_first_name = $current_user->display_name ?: $current_user->user_login;

// Get current page for active state
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'recognition';

// Chart data - last 7 days. Powered by the Logger so the template does
// not need to touch $wpdb directly (H-3 - 1.0.0).
$chart_data      = $logger->get_daily_breakdown(7);

$max_chart_value = max(array_column($chart_data, 'success')) ?: 1;
if (max(array_column($chart_data, 'failed')) > $max_chart_value) {
    $max_chart_value = max(array_column($chart_data, 'failed'));
}

// Y-axis scaling (round up to a clean step value so the grid lines
// always land on round numbers, matching the Authentication Logs ->
// Activity Trend chart for visual consistency).
$max_chart_value = max( 1, (int) ceil( $max_chart_value / 5 ) * 5 );
$y_axis_steps    = 4;
$y_step_value    = max( 1, (int) ceil( $max_chart_value / $y_axis_steps ) );

// Success rate calculation
// Use only authentication attempts (success + failed) as the denominator so
// that enrollment / deletion / security events don't dilute the percentage.
$total_attempts = isset($stats['total_attempts']) ? (int) $stats['total_attempts'] : 0;
$success_rate   = $total_attempts > 0 ? round(($stats['success'] / $total_attempts) * 100, 1) : 0;
$failed_count   = isset($stats['failed']) ? (int) $stats['failed'] : 0;
$success_count  = isset($stats['success']) ? (int) $stats['success'] : 0;
$total_count    = isset($stats['total'])   ? (int) $stats['total']   : 0;
$enrolled_count = isset($stats['enrolled']) ? (int) $stats['enrolled'] : 0;
$accuracy       = isset($stats['accuracy']) ? (float) $stats['accuracy'] : 0.0;
$avg_response   = isset($stats['avg_response_time']) ? (float) $stats['avg_response_time'] : 0.0;

// Pre-compute SVG dimensions for the success-rate doughnut.
$dasharray_360 = 2 * M_PI * 60;                                  // full circle (r = 60)
$dash_success  = $dasharray_360 * ($success_rate / 100);         // filled portion
$dash_remaining = $dasharray_360 - $dash_success;
?>

<!-- App Container -->
<div class="frl-app" id="frl-app">
    <!-- Sidebar (reusable partial) -->
    <?php include FRL_PLUGIN_PATH . 'admin/templates/partials/sidebar.php'; ?>

    <main class="frl-main">
        <!-- Mobile Header -->
        <div class="frl-mobile-header">
            <button type="button" class="frl-mobile-menu-btn" id="frl-mobile-menu-btn" aria-label="<?php esc_attr_e('Open menu', 'recognition'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" x2="20" y1="12" y2="12"/>
                    <line x1="4" x2="20" y1="6" y2="6"/>
                    <line x1="4" x2="20" y1="18" y2="18"/>
                </svg>
            </button>
            <span class="frl-header-title"><?php esc_html_e('Recognition', 'recognition'); ?></span>
            <button type="button" class="frl-theme-toggle" id="frl-theme-toggle" aria-label="<?php esc_attr_e('Toggle theme', 'recognition'); ?>">
                <svg class="frl-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2"/><path d="M12 20v2"/>
                    <path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/>
                    <path d="M2 12h2"/><path d="M20 12h2"/>
                    <path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
                </svg>
                <svg class="frl-icon-moon" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
                </svg>
            </button>
        </div>

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title"><?php esc_html_e('Dashboard', 'recognition'); ?></h1>
                <?php if ($is_enabled): ?>
                    <span class="frl-status-badge success">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('Active', 'recognition'); ?>
                    </span>
                <?php else: ?>
                    <span class="frl-status-badge warning">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('Disabled', 'recognition'); ?>
                    </span>
                <?php endif; ?>
                <?php if ($is_https): ?>
                    <span class="frl-status-badge success">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('HTTPS', 'recognition'); ?>
                    </span>
                <?php else: ?>
                    <span class="frl-status-badge warning">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('HTTP', 'recognition'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="frl-header-right">
                <button type="button" class="frl-theme-toggle" id="frl-theme-toggle-header" aria-label="<?php esc_attr_e('Toggle theme', 'recognition'); ?>">
                    <svg class="frl-icon-sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 2v2"/><path d="M12 20v2"/>
                        <path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/>
                        <path d="M2 12h2"/><path d="M20 12h2"/>
                        <path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>
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
            <!-- ============================================================ -->
            <div class="frl-hero" style="margin-bottom: var(--frl-space-6);">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--frl-space-6); flex-wrap: wrap;">
                    <div>
                        <div style="font-size: var(--frl-font-size-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.10em; color: var(--frl-accent); margin-bottom: var(--frl-space-2);">
                            <?php
                            /* translators: %s: plugin version number */
                            printf( esc_html__( 'Recognition v%s', 'recognition' ), esc_html( FRL_PLUGIN_VERSION ) );
                            ?>
                        </div>
                        <h2 class="frl-hero-title" style="font-size: var(--frl-font-size-3xl);"><?php esc_html_e('Your  Recognition Command Center', 'recognition'); ?></h2>
                        <p class="frl-hero-subtitle">
                            <?php esc_html_e('Track authentications, manage enrolled users, and review the health of your biometric login &mdash; all in one calm, focused space.', 'recognition'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Alerts (kept from v1, contextual to site state) -->
            <?php if (!$is_enabled): ?>
            <div class="frl-alert frl-alert-warning">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" x2="12" y1="9" y2="13"/>
                    <line x1="12" x2="12.01" y1="17" y2="17"/>
                </svg>
                <div class="frl-alert-content">
                    <div class="frl-alert-title"><?php esc_html_e('Plugin Disabled', 'recognition'); ?></div>
                    <p><?php esc_html_e('Face Recognition Login is currently disabled. Enable it in Settings to allow users to log in with their face.', 'recognition'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$is_https): ?>
            <div class="frl-alert frl-alert-error">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" x2="12" y1="9" y2="13"/>
                    <line x1="12" x2="12.01" y1="17" y2="17"/>
                </svg>
                <div class="frl-alert-content">
                    <div class="frl-alert-title"><?php esc_html_e('HTTPS Not Detected', 'recognition'); ?></div>
                    <p><?php esc_html_e('For security, face recognition requires HTTPS. Please enable SSL on your site.', 'recognition'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================ -->
            <!-- BENTO GRID                                                    -->
            <!-- ============================================================ -->
            <div class="frl-bento">

                <!-- ============================================ -->
                <!-- CARD 1: WELCOME MESSAGE (7 cols)             -->
                <!-- ============================================ -->
                <div class="frl-bento-7">
                    <div class="frl-glass" style="padding: var(--frl-space-8); min-height: 220px;">
                        <div style="display: flex; flex-direction: column; gap: var(--frl-space-3);">
                            <span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; background: var(--frl-accent-soft); color: var(--frl-accent); border-radius: var(--frl-radius-pill); font-size: var(--frl-font-size-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; width: fit-content;">
                                <span class="frl-status-dot" style="background: var(--frl-accent);"></span>
                                <?php
                                /* translators: greeting shown above the welcome card */
                                $hour = (int) current_time('G');
                                if ($hour < 12) {
                                    $greeting = __('Good morning', 'recognition');
                                } elseif ($hour < 18) {
                                    $greeting = __('Good afternoon', 'recognition');
                                } else {
                                    $greeting = __('Good evening', 'recognition');
                                }
                                echo esc_html($greeting);
                                ?>
                            </span>
                            <h2 style="font-size: var(--frl-font-size-4xl); font-weight: 800; color: var(--frl-text-primary); margin: 0; line-height: 1.05; letter-spacing: -0.03em;">
                                <?php
                                /* translators: %s: current user display name */
                                printf( esc_html__( 'Welcome back, %s', 'recognition' ), esc_html( $user_first_name ) );
                                ?>
                            </h2>
                            <p style="font-size: var(--frl-font-size-md); color: var(--frl-text-secondary); margin: 0; max-width: 520px; line-height: 1.6;">
                                <?php
                                $welcome_msg = sprintf(
                                    /* translators: 1: total number of authentication attempts, 2: success rate percentage. */
                                    _x(
                                        'Here is a snapshot of your biometric login environment. %1$s authentication attempts in the last 30 days, with a %2$s%% success rate &mdash; keep up the great work securing your WordPress site.',
                                        'Welcome message body on dashboard',
                                        'recognition'
                                    ),
                                    esc_html( number_format_i18n( $total_count ) ),
                                    esc_html( number_format_i18n( $success_rate, 1 ) )
                                );
                                echo esc_html( $welcome_msg );
                                ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: var(--frl-space-3); margin-top: var(--frl-space-6); flex-wrap: wrap;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-enroll-face' ) ); ?>" class="frl-btn frl-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                                <?php esc_html_e('Enroll a Face', 'recognition'); ?>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs' ) ); ?>" class="frl-btn frl-btn-secondary"><?php esc_html_e('View Logs', 'recognition'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SUCCESS-RATE DOUGHNUT (5 cols)               -->
                <!-- ============================================ -->
                <div class="frl-bento-5">
                    <div class="frl-glass" style="padding: var(--frl-space-6); min-height: 220px;">
                        <div class="frl-glass-header" style="padding: 0 0 var(--frl-space-4);">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('Success Rate', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Last 30 days', 'recognition'); ?></p>
                            </div>
                        </div>
                        <div class="frl-glass-body" style="padding: 0; justify-content: center;">
                            <div class="frl-doughnut-wrap" style="gap: var(--frl-space-5);">
                                <div class="frl-doughnut">
                                    <svg viewBox="0 0 160 160" width="160" height="160" aria-hidden="true">
                                        <defs>
                                            <linearGradient id="frl-grad-success" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" stop-color="#6366f1"/>
                                                <stop offset="100%" stop-color="#06b6d4"/>
                                            </linearGradient>
                                        </defs>
                                        <circle cx="80" cy="80" r="60" fill="none" stroke="var(--frl-bg-active)" stroke-width="20"/>
                                        <circle cx="80" cy="80" r="60" fill="none" stroke="url(#frl-grad-success)" stroke-width="20"
                                                stroke-dasharray="<?php echo esc_attr( $dash_success . ' ' . $dash_remaining ); ?>"
                                                stroke-linecap="round"/>
                                    </svg>
                                    <div class="frl-doughnut-center">
                                        <div class="frl-doughnut-value"><?php echo esc_html( number_format_i18n( $success_rate, 1 ) ); ?>%</div>
                                        <div class="frl-doughnut-label"><?php esc_html_e('Success', 'recognition'); ?></div>
                                    </div>
                                </div>
                                <div class="frl-legend">
                                    <div class="frl-legend-item">
                                        <span class="frl-legend-color" style="background: linear-gradient(135deg, #6366f1, #06b6d4);"></span>
                                        <span class="frl-legend-label"><?php esc_html_e('Successful', 'recognition'); ?></span>
                                        <span class="frl-legend-value"><?php echo esc_html( number_format_i18n( $success_count ) ); ?></span>
                                    </div>
                                    <div class="frl-legend-item">
                                        <span class="frl-legend-color" style="background: var(--frl-error);"></span>
                                        <span class="frl-legend-label"><?php esc_html_e('Failed', 'recognition'); ?></span>
                                        <span class="frl-legend-value"><?php echo esc_html( number_format_i18n( $failed_count ) ); ?></span>
                                    </div>
                                    <div class="frl-legend-item">
                                        <span class="frl-legend-color" style="background: linear-gradient(135deg, #ec4899, #8b5cf6);"></span>
                                        <span class="frl-legend-label"><?php esc_html_e('Enrolled', 'recognition'); ?></span>
                                        <span class="frl-legend-value"><?php echo esc_html( number_format_i18n( $enrolled_count ) ); ?></span>
                                    </div>
                                    <div class="frl-legend-item">
                                        <span class="frl-legend-color" style="background: var(--frl-success); box-shadow: 0 0 0 3px color-mix(in srgb, var(--frl-success) 18%, transparent);"></span>
                                        <span class="frl-legend-label"><?php esc_html_e('Accuracy', 'recognition'); ?></span>
                                        <span class="frl-legend-value"><?php echo esc_html( number_format_i18n( $accuracy, 1 ) ); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI ROW (4 cards × 3 cols)                    -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--info">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Attempts', 'recognition'); ?></span>
                            <div class="frl-kpi-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Last 30 days', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--success">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Successful', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-success-bg); color: var(--frl-success);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $success_count ) ); ?></div>
                            <div class="frl-kpi-meta">
                                <span class="frl-kpi-trend frl-kpi-trend--up">&#8593;</span>
                                <?php
                                /* translators: %s: success rate */
                                printf( esc_html__( '%s%% success rate', 'recognition' ), esc_html( number_format_i18n( $success_rate, 1 ) ) );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--aurora">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Enrolled', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
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
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--warning">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Avg Response', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-warning-bg); color: var(--frl-warning);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $avg_response, 0 ) ); ?><span class="frl-kpi-value-suffix"><?php esc_html_e('ms', 'recognition'); ?></span></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Processing time', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- CARD 2: HIRE A DEVELOPER (6 cols)            -->
                <!-- ============================================ -->
                <div class="frl-bento-6">
                    <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=dashboard&utm_campaign=hire-dev"
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
                </div>

                <!-- ============================================ -->
                <!-- CARD 3: WANT A CUSTOM PLUGIN (6 cols)        -->
                <!-- ============================================ -->
                <div class="frl-bento-6">
                    <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=dashboard&utm_campaign=custom-plugin"
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

                <!-- ============================================ -->
                <!-- AUTH ACTIVITY CHART (8 cols)                 -->
                <!-- ============================================ -->
                <div class="frl-bento-8">
                    <div class="frl-glass" style="min-height: 340px;">
                        <div class="frl-glass-header">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('Authentication Activity', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Last 7 days &mdash; Successful vs Failed', 'recognition'); ?></p>
                            </div>
                            <div class="frl-legend" style="flex-direction: row; gap: var(--frl-space-4);">
                                <div class="frl-legend-item">
                                    <div class="frl-legend-color frl-legend-color--success"></div>
                                    <span class="frl-legend-label"><?php esc_html_e('Success', 'recognition'); ?></span>
                                    <span class="frl-legend-value"><?php echo (int) array_sum( array_column( $chart_data, 'success' ) ); ?></span>
                                </div>
                                <div class="frl-legend-item">
                                    <div class="frl-legend-color frl-legend-color--failed"></div>
                                    <span class="frl-legend-label"><?php esc_html_e('Failed', 'recognition'); ?></span>
                                    <span class="frl-legend-value"><?php echo (int) array_sum( array_column( $chart_data, 'failed' ) ); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="frl-glass-body">
                            <div class="frl-chart" role="img" aria-label="<?php esc_attr_e('Authentication activity for the last 7 days', 'recognition'); ?>">
                                <div class="frl-chart-yaxis" aria-hidden="true">
                                    <?php for ( $yi = $y_axis_steps; $yi >= 0; $yi-- ) : ?>
                                        <span class="frl-chart-yaxis-step"><?php echo (int) ( $yi * $y_step_value ); ?></span>
                                    <?php endfor; ?>
                                </div>
                                <div class="frl-chart-bars">
                                    <?php foreach ($chart_data as $data) :
                                        $success_h = (int) $data['success'] > 0
                                            ? max( 4, (int) round( ( $data['success'] / $max_chart_value ) * 200 ) )
                                            : 4;
                                        $failed_h  = (int) $data['failed'] > 0
                                            ? max( 4, (int) round( ( $data['failed']  / $max_chart_value ) * 200 ) )
                                            : 4;
                                        ?>
                                        <div class="frl-chart-bar-container">
<div class="frl-chart-bar-pair">
                                                <div class="frl-chart-bar frl-chart-bar--success<?php echo (int) $data['success'] === 0 ? ' is-empty' : ''; ?>"
                                                     style="height: <?php echo (int) $success_h; ?>px;"
                                                     role="img"
                                                     aria-label="<?php echo (int) $data['success']; ?> <?php esc_attr_e('successful authentications', 'recognition'); ?>"
                                                     data-value="<?php echo (int) $data['success']; ?>"
                                                     data-type="success"
                                                     title="<?php echo (int) $data['success']; ?> <?php esc_attr_e('successful', 'recognition'); ?>">
                                                    <span class="frl-chart-tooltip">
                                                        <?php
                                                        /* translators: %s: successful count for tooltip */
                                                        printf( esc_html__( '%s success', 'recognition' ), esc_html( number_format_i18n( (int) $data['success'] ) ) );
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="frl-chart-bar frl-chart-bar--failed<?php echo (int) $data['failed'] === 0 ? ' is-empty' : ''; ?>"
                                                     style="height: <?php echo (int) $failed_h; ?>px;"
                                                     role="img"
                                                     aria-label="<?php echo (int) $data['failed']; ?> <?php esc_attr_e('failed authentications', 'recognition'); ?>"
                                                     data-value="<?php echo (int) $data['failed']; ?>"
                                                     data-type="failed"
                                                     title="<?php echo (int) $data['failed']; ?> <?php esc_attr_e('failed', 'recognition'); ?>">
                                                    <span class="frl-chart-tooltip">
                                                        <?php
                                                        /* translators: %s: failed count for tooltip */
                                                        printf( esc_html__( '%s failed', 'recognition' ), esc_html( number_format_i18n( (int) $data['failed'] ) ) );
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="frl-chart-label"><?php echo esc_html( $data['date'] ); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- CARD 4: SYSTEM HEALTH (4 cols) - Light glass -->
                <!-- ============================================ -->
                <div class="frl-bento-4">
                    <div class="frl-glass">
                        <div class="frl-glass-header" style="padding-bottom: var(--frl-space-3);">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('System Health', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Real-time diagnostics', 'recognition'); ?></p>
                            </div>
                            <?php if ($is_enabled && $is_https && $premium_active) : ?>
                                <span class="frl-status-badge success">
                                    <span class="frl-status-dot"></span>
                                    <?php esc_html_e('All Systems OK', 'recognition'); ?>
                                </span>
                            <?php else : ?>
                                <span class="frl-status-badge warning">
                                    <span class="frl-status-dot"></span>
                                    <?php esc_html_e('Attention', 'recognition'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="frl-glass-body" style="padding-top: 0; display: flex; flex-direction: column; gap: var(--frl-space-1);">
                            <!-- Face Models -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Face Models', 'recognition'); ?></span>
                                <span class="frl-status-badge success"><?php esc_html_e('Loaded', 'recognition'); ?></span>
                            </div>
                            <!-- Database -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Database', 'recognition'); ?></span>
                                <span class="frl-status-badge success"><?php esc_html_e('Connected', 'recognition'); ?></span>
                            </div>
                            <!-- SSL / HTTPS -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('SSL / HTTPS', 'recognition'); ?></span>
                                <?php if ($is_https) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Enabled', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge error"><?php esc_html_e('Disabled', 'recognition'); ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Camera API -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Camera API', 'recognition'); ?></span>
                                <span class="frl-status-badge success"><?php esc_html_e('Ready', 'recognition'); ?></span>
                            </div>
                            <!-- License -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('License', 'recognition'); ?></span>
                                <?php if ($premium_active) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Premium', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge info"><?php esc_html_e('Free', 'recognition'); ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- WooCommerce -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0;">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('WooCommerce', 'recognition'); ?></span>
                                <?php if ($wc_addon_active) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Active', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge info"><?php esc_html_e('Not installed', 'recognition'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- RECENT ACTIVITY (8 cols)                     -->
                <!-- ============================================ -->
                <div class="frl-bento-8">
                    <div class="frl-glass frl-activity-card">
                        <div class="frl-glass-header">
                            <div class="frl-activity-header-text">
                                <h3 class="frl-glass-title"><?php esc_html_e('Recent Activity', 'recognition');?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Latest authentication events', 'recognition'); ?></p>
                            </div>
                            <div class="frl-activity-toolbar">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs' ) ); ?>" class="frl-widget-action"><?php esc_html_e('View all &rarr;', 'recognition'); ?></a>
                            </div>
                        </div>
                        <?php if ( ! empty( $recent_activity ) ) : ?>
                        <div class="frl-activity-filters" role="tablist" aria-label="<?php esc_attr_e('Filter activity', 'recognition'); ?>">
                            <button type="button" class="frl-activity-filter is-active" role="tab" aria-selected="true" data-filter="all">
                                <span class="frl-activity-filter-dot is-all"></span>
                                <span class="frl-activity-filter-label"><?php esc_html_e('All', 'recognition'); ?></span>
                                <span class="frl-activity-filter-count"><?php echo esc_html( number_format_i18n( count( $recent_activity ) ) ); ?></span>
                            </button>
                            <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-filter="success">
                                <span class="frl-activity-filter-dot is-success"></span>
                                <span class="frl-activity-filter-label"><?php esc_html_e('Success', 'recognition'); ?></span>
                            </button>
                            <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-filter="failed">
                                <span class="frl-activity-filter-dot is-error"></span>
                                <span class="frl-activity-filter-label"><?php esc_html_e('Failed', 'recognition'); ?></span>
                            </button>
                            <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-filter="enrolled">
                                <span class="frl-activity-filter-dot is-info"></span>
                                <span class="frl-activity-filter-label"><?php esc_html_e('Enroll', 'recognition'); ?></span>
                            </button>
                        </div>
                        <?php
                            $now_ts         = current_time( 'timestamp' );
                            $today_start    = strtotime( current_time( 'Y-m-d 00:00:00' ) );
                            $yesterday_end  = $today_start - 1;
                            $yesterday_start = $yesterday_end - 86399;
                            $groups = array(
                                'today'     => array(),
                                'yesterday' => array(),
                                'earlier'   => array(),
                            );
                            if ( ! empty( $recent_activity ) ) {
                                foreach ( $recent_activity as $activity ) {
                                    $ts = is_numeric( $activity->created_at ) ? (int) $activity->created_at : strtotime( (string) $activity->created_at );
                                    $ts = $ts ? (int) $ts : 0;
                                    if ( $ts >= $today_start ) {
                                        $groups['today'][] = $activity;
                                    } elseif ( $ts >= $yesterday_start && $ts <= $yesterday_end ) {
                                        $groups['yesterday'][] = $activity;
                                    } else {
                                        $groups['earlier'][] = $activity;
                                    }
                                }
                            }
                            $group_labels = array(
                                'today'     => __( 'Today', 'recognition' ),
                                'yesterday' => __( 'Yesterday', 'recognition' ),
                                'earlier'   => __( 'Earlier', 'recognition' ),
                            );
                        ?>
                        <div class="frl-glass-body frl-activity-body" style="padding-top: 0;">
                            <?php foreach ( $groups as $group_key => $group_items ) :
                                if ( empty( $group_items ) ) { continue; }
                            ?>
                            <div class="frl-activity-group" data-group="<?php echo esc_attr( $group_key ); ?>">
                                <div class="frl-activity-group-label">
                                    <span class="frl-activity-group-line"></span>
                                    <span class="frl-activity-group-text"><?php echo esc_html( $group_labels[ $group_key ] ); ?></span>
                                    <span class="frl-activity-group-line"></span>
                                </div>
                                <ul class="frl-activity">
                                    <?php foreach ( $group_items as $activity ) :
                                        $activity_user   = ! empty( $activity->user_id ) ? get_user_by( 'id', (int) $activity->user_id ) : null;
                                        $activity_result = isset( $activity->result ) ? (string) $activity->result : '';

                                        $event_class = 'info';
                                        $event_icon  = 'info';
                                        $event_text  = __( 'activity recorded', 'recognition' );
                                        $event_key   = 'other';

                                        if ( 'success' === $activity_result ) {
                                            $event_class = 'success';
                                            $event_icon  = 'check';
                                            $event_text  = __( 'logged in successfully', 'recognition' );
                                            $event_key   = 'success';
                                        } elseif ( 'enrolled' === $activity_result ) {
                                            $event_class = 'info';
                                            $event_icon  = 'user-plus';
                                            $event_text  = __( 'enrolled their face', 'recognition' );
                                            $event_key   = 'enrolled';
                                        } elseif ( 'deleted' === $activity_result ) {
                                            $event_class = 'warning';
                                            $event_icon  = 'trash';
                                            $event_text  = __( 'deleted a face profile', 'recognition' );
                                            $event_key   = 'deleted';
                                        } elseif ( 'failed' === $activity_result ) {
                                            $event_class = 'error';
                                            $event_icon  = 'x';
                                            $event_text  = __( 'login failed', 'recognition' );
                                            $event_key   = 'failed';
                                        }

                                        $display_name = $activity_user ? $activity_user->user_login : ( $activity->username ?: __( 'Unknown', 'recognition' ) );
                                        $initial      = '';
                                        if ( function_exists( 'mb_substr' ) ) {
                                            $initial = mb_substr( $display_name, 0, 1 );
                                        } elseif ( '' !== $display_name ) {
                                            $initial = strtoupper( substr( $display_name, 0, 1 ) );
                                        }
                                        if ( '' === $initial ) {
                                            $initial = '?';
                                        }
                                        $initial = strtoupper( $initial );

                                        $timestamp = is_numeric( $activity->created_at ) ? (int) $activity->created_at : strtotime( (string) $activity->created_at );
                                        $timestamp = $timestamp ? (int) $timestamp : 0;

                                        $relative_time = '';
                                        if ( $timestamp ) {
                                            $diff = (int) $timestamp - $now_ts;
                                            if ( abs( $diff ) < 60 ) {
                                                $relative_time = __( 'just now', 'recognition' );
                                            } else {
                                                $human_diff = human_time_diff( $timestamp, $now_ts );
                                                /* translators: %s: human-readable time difference, e.g. "5 mins". */
                                                $relative_time = sprintf( __( '%s ago', 'recognition' ), $human_diff );
                                            }
                                        }
                                        $absolute_time = $timestamp ? wp_date( 'M j, g:i A', $timestamp ) : '';

                                        $confidence_pct  = null;
                                        $confidence_qual = '';
                                        if ( null !== $activity->confidence && '' !== $activity->confidence ) {
                                            $confidence_pct = max( 0, min( 100, (float) $activity->confidence * 100 ) );
                                            if ( $confidence_pct >= 80 ) {
                                                $confidence_qual = 'is-high';
                                            } elseif ( $confidence_pct >= 50 ) {
                                                $confidence_qual = 'is-medium';
                                            } else {
                                                $confidence_qual = 'is-low';
                                            }
                                        }
                                    ?>
                                    <li class="frl-activity-item" data-event="<?php echo esc_attr( $event_key ); ?>">
                                        <div class="frl-activity-avatar <?php echo esc_attr( $event_class ); ?>">
                                            <span class="frl-activity-avatar-initial"><?php echo esc_html( $initial ); ?></span>
                                            <span class="frl-activity-event-icon" aria-hidden="true">
                                                <?php if ( 'check' === $event_icon ) : ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                <?php elseif ( 'x' === $event_icon ) : ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                <?php elseif ( 'trash' === $event_icon ) : ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                                <?php elseif ( 'user-plus' === $event_icon ) : ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                                                <?php else : ?>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="frl-activity-content">
                                            <div class="frl-activity-line">
                                                <span class="frl-activity-user"><?php echo esc_html( $display_name ); ?></span>
                                                <span class="frl-activity-action"><?php echo esc_html( $event_text ); ?></span>
                                            </div>
                                            <div class="frl-activity-meta">
                                                <?php if ( null !== $confidence_pct ) : ?>
                                                <div class="frl-activity-confidence">
                                                    <div class="frl-activity-confidence-bar" aria-hidden="true">
                                                        <div class="frl-activity-confidence-fill <?php echo esc_attr( $confidence_qual ); ?>" style="width: <?php echo esc_attr( number_format_i18n( $confidence_pct, 1 ) ); ?>%;"></div>
                                                    </div>
                                                    <span class="frl-activity-confidence-value"><?php echo esc_html( number_format_i18n( $confidence_pct, 1 ) ); ?>%</span>
                                                </div>
                                                <span class="frl-activity-meta-sep" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <span class="frl-activity-meta-item">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                    <time class="frl-activity-time-relative" datetime="<?php echo esc_attr( $absolute_time ); ?>" title="<?php echo esc_attr( $absolute_time ); ?>"><?php echo esc_html( $relative_time ); ?></time>
                                                </span>
                                            </div>
                                        </div>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs&log_id=' . (int) $activity->id ) ); ?>" class="frl-activity-action-btn" aria-label="<?php esc_attr_e( 'View details', 'recognition' ); ?>">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <script>
                        (function(){
                            var script = document.currentScript;
                            if (!script) return;
                            var root = script.previousElementSibling;
                            if (!root || !root.parentElement) return;
                            var card = root.parentElement;
                            var filters = card.querySelectorAll('.frl-activity-filter');
                            var items   = card.querySelectorAll('.frl-activity-item');
                            var groups  = card.querySelectorAll('.frl-activity-group');
                            filters.forEach(function(btn){
                                btn.addEventListener('click', function(){
                                    filters.forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
                                    btn.classList.add('is-active');
                                    btn.setAttribute('aria-selected', 'true');
                                    var f = btn.getAttribute('data-filter');
                                    items.forEach(function(it){
                                        if (f === 'all' || it.getAttribute('data-event') === f) {
                                            it.style.display = '';
                                        } else {
                                            it.style.display = 'none';
                                        }
                                    });
                                    groups.forEach(function(g){
                                        var visible = 0;
                                        g.querySelectorAll('.frl-activity-item').forEach(function(it){
                                            if (it.style.display !== 'none') visible++;
                                        });
                                        g.style.display = visible > 0 ? '' : 'none';
                                    });
                                });
                            });
                        })();
                        </script>
                        <?php else : ?>
                        <div class="frl-glass-body frl-activity-body" style="padding-top: 0;">
                            <div class="frl-empty-state frl-empty-state--rich">
                                <div class="frl-empty-state-illustration" aria-hidden="true">
                                    <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <circle cx="60" cy="60" r="56" stroke="currentColor" stroke-opacity="0.12" stroke-width="2"/>
                                        <circle cx="60" cy="60" r="40" stroke="currentColor" stroke-opacity="0.20" stroke-width="2" stroke-dasharray="4 6"/>
                                        <circle cx="60" cy="50" r="14" stroke="currentColor" stroke-width="2" fill="none"/>
                                        <path d="M40 80 Q60 96 80 80" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>
                                        <circle cx="92" cy="32" r="8" fill="currentColor" fill-opacity="0.15"/>
                                        <circle cx="92" cy="32" r="4" fill="currentColor"/>
                                    </svg>
                                </div>
                                <p class="frl-empty-state-title"><?php esc_html_e('No activity yet', 'recognition'); ?></p>
                                <p class="frl-empty-state-desc"><?php esc_html_e('Face authentication events will appear here as soon as users sign in.', 'recognition'); ?></p>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs' ) ); ?>" class="frl-btn frl-btn-secondary frl-btn-sm">
                                    <?php esc_html_e('Open activity log', 'recognition'); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- QUICK ACTIONS (4 cols)                      -->
                <!-- ============================================ -->
                <div class="frl-bento-4">
                    <div class="frl-glass" style="min-height: 340px;">
                        <div class="frl-glass-header">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('Quick Actions', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Jump to common tasks', 'recognition'); ?></p>
                            </div>
                        </div>
                        <div class="frl-glass-body" style="padding-top: 0;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-enroll-face' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('Enroll New Face', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('Add a face profile for a user', 'recognition'); ?></div>
                                </div>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-settings' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon" style="background: var(--frl-success-bg); color: var(--frl-success);">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('Configure Settings', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('Adjust thresholds &amp; security', 'recognition'); ?></div>
                                </div>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-users' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('Manage Enrolled', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('View &amp; remove face profiles', 'recognition'); ?></div>
                                </div>
                            </a>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon" style="background: var(--frl-info-bg); color: var(--frl-info);">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('View Auth Logs', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('Inspect history', 'recognition'); ?></div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- CARD 5: NEED SOMETHING CUSTOM? (12 cols)    -->
                <!-- ============================================ -->
                <div class="frl-bento-12">
                    <div class="frl-glass" style="padding: var(--frl-space-8); display: flex; justify-content: space-between; gap: var(--frl-space-6); flex-wrap: wrap; min-height: auto;">
                        <div>
                            <div style="font-size: var(--frl-font-size-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.10em; color: var(--frl-accent); margin-bottom: var(--frl-space-2);">
                                <?php esc_html_e('Built by JSS Web Solutions', 'recognition'); ?>
                            </div>
                            <h3 style="font-size: var(--frl-font-size-2xl); font-weight: 700; color: var(--frl-text-primary); margin: 0 0 var(--frl-space-1); letter-spacing: -0.02em;">
                                <?php esc_html_e('Need something custom?', 'recognition'); ?>
                            </h3>
                            <p style="font-size: var(--frl-font-size-sm); color: var(--frl-text-secondary); margin: 0; max-width: 640px;">
                                <?php esc_html_e('Whether it is hiring a developer or commissioning a bespoke WordPress plugin, JSS Web Solutions is the team behind Recognition &mdash; and we build for the long haul.', 'recognition'); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: var(--frl-space-3); flex-wrap: wrap;">
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=dashboard-strip&utm_campaign=hire-dev" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                                <?php esc_html_e('Hire a Developer', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=dashboard-strip&utm_campaign=custom-plugin" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-secondary">
                                <?php esc_html_e('Custom Plugin &rarr;', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/contact/?utm_source=frl-plugin&utm_medium=dashboard-strip&utm_campaign=contact" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-ghost">
                                <?php esc_html_e('Contact Us', 'recognition'); ?>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
            <!-- /BENTO -->

        </div>
    </main>
</div>

<?php // Theme & sidebar toggles are enqueued via FRL_Admin::enqueue_admin_assets() (frl-admin-shared.js - H-2 - 1.0.0). ?>
