<?php
/**
 * Admin Authentication Logs Page Template
 *
 * Aligned with the Swiss + Glassmorphism + Bento + Aurora design
 * system (see dashboard-page.php for the canonical layout).
 *
 *   1. Hero banner                       (frl-bento-12)
 *   2. 4 KPI tiles (enrolled/last 30d)   (frl-bento-3 × 4)
 *   3. Authentication activity chart     (frl-bento-8)
 *      + System Health side card         (frl-bento-4)
 *   4. Recent logs (timeline)            (frl-bento-12)
 *   5. JSS brand strip                   (frl-bento-12)
 *
 * @package Face_Recognition_Login
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------------
// Premium gate
// ------------------------------------------------------------------
// Same rationale as enroll-face-page.php: when the license is invalid
// and the request includes ?frl_preview=1 the user stays on the page
// so they can see what is included in the premium plan; in that case
// we emit the lock overlay at the top of the page. The CSS in
// premium-gate.css handles the blur effect on the underlying content.
$frl_show_premium_lock = (
    class_exists('FRL_Premium_Gate')
    && FRL_Premium_Gate::should_lock_page('frl-logs')
);

$options          = FRL_Options::all();
$logging_enabled  = isset($options['log_authentications']) && $options['log_authentications'];
$is_https         = is_ssl();
$premium_active   = (class_exists('FRL_Premium_Gate') && FRL_Premium_Gate::is_premium_active());

global $wpdb;
$logs_table   = $wpdb->prefix . 'face_login_logs';
// NOTE: WordPress 6.2+ wpdb::prepare() uses a strict regex that strips
// the space in "SHOW TABLES", mangling it to "SHOWTABLES" and breaking
// the query. The table name is a concatenation of $wpdb->prefix and a
// hardcoded literal (not user input), so we bypass prepare() and use
// the raw SHOW TABLES statement via get_col() instead.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- "SHOW TABLES" is a MySQL introspection statement that does not accept placeholders; argument is a hardcoded literal, not user input.
$table_exists = in_array( $logs_table, $wpdb->get_col( "SHOW TABLES" ), true );

$logger = new FRL_Logger();
$stats  = $logger->get_statistics(30);

// Pagination
// ------------------------------------------------------------------
// These variables can be set by FRL_Admin::render_logs_page() (which
// includes this template) or computed locally as a fallback when the
// template is rendered via another path (e.g. a shortcode, an AJAX
// preview, or a stale OPcache on a deployed server).
//
// Using the same names as FRL_Admin ( $page, $logs, $per_page,
// $offset, $total_pages, $stats ) avoids the duplicate database
// query that this template used to do, and the isset() defaults make
// the file self-sufficient if the caller forgot to set them — which
// fixes the "Undefined variable $current_page" warning reported on
// deployed sites where the include path was the only thing keeping
// the old names alive.
// ------------------------------------------------------------------

// $per_page: number of log rows to show per page.
//
// The user can change this from the toolbar dropdown (10 / 20 / 50 /
// 100). We accept the value via the ?per_page= query string and
// clamp it to a small whitelist so a hand-crafted URL cannot ask
// the server for an arbitrary number of rows.
$frl_allowed_per_page = [ 10, 20, 50, 100 ];
if ( ! isset( $per_page ) || (int) $per_page <= 0 ) {
    $per_page = 10;
} elseif ( ! in_array( (int) $per_page, $frl_allowed_per_page, true ) ) {
    $per_page = 10;
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; whitelisted against $frl_allowed_per_page.
if ( isset( $_GET['per_page'] ) ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; whitelisted against $frl_allowed_per_page.
    $requested_per_page = absint( wp_unslash( $_GET['per_page'] ) );
    if ( in_array( $requested_per_page, $frl_allowed_per_page, true ) ) {
        $per_page = $requested_per_page;
    }
}
$per_page = (int) $per_page;

// $page: the 1-based current page index.
// Always derive the current page from the URL parameter first
// (with a fallback to the existing $page variable set by
// FRL_Admin::render_logs_page()). This guarantees the
// current-page indicator and the Previous/Next buttons are
// correct on hosted files that may have a stale version of $page.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
$paged_req = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 0;
if ( $paged_req > 0 ) {
    $page = $paged_req;
} elseif ( ! isset( $page ) || (int) $page < 1 ) {
    $page = 1;
}
// If the user just changed the per_page value, force-reset to the
// first page so we never land on an out-of-range page index.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
if ( isset( $_GET['per_page'] ) ) {
    $page = 1;
}
$page = (int) $page;

// $logs: the already-paginated log rows for the current page. If
// FRL_Admin::render_logs_page() pre-fetched them we use those,
// otherwise we do a single bounded query (limit = $per_page * 10) so
// the in-page filter/search still has enough data to operate on.
if ( ! isset( $logs ) || ! is_array( $logs ) ) {
    $logs_fallback = $logger->get_logs( [ 'limit' => $per_page * 10 ] );
    $logs          = ! empty( $logs_fallback['logs'] ) ? $logs_fallback['logs'] : [];
}

// $total_logs / $total_pages: totals for the pager math. We prefer
// the value provided by the parent (which knows the unfiltered row
// count); otherwise we derive it from $logs (best effort, capped at
// $per_page * 10 so we never over-count when only a window was
// fetched).
if ( ! isset( $total_logs ) ) {
    $total_logs = is_array( $logs ) ? count( $logs ) : 0;
}
if ( ! isset( $total_pages ) ) {
    $total_pages = max( 1, (int) ceil( (int) $total_logs / (int) $per_page ) );
}
// Clamp the current page into the valid range.
if ( $page > $total_pages ) {
    $page = $total_pages;
}

// $offset / $paginated_logs: the slice of $logs that this page
// should display.
if ( ! isset( $offset ) ) {
    $offset = ( $page - 1 ) * (int) $per_page;
}
if ( ! isset( $paginated_logs ) || ! is_array( $paginated_logs ) ) {
    $paginated_logs = is_array( $logs )
        ? array_slice( $logs, (int) $offset, (int) $per_page )
        : [];
}

// Calculate success rate (auth-only denominator so enrolment/deletion
// events do not dilute the percentage).
$total_attempts = isset($stats['total_attempts']) ? (int) $stats['total_attempts'] : 0;
$success_count  = isset($stats['success'])        ? (int) $stats['success']        : 0;
$failed_count   = isset($stats['failed'])         ? (int) $stats['failed']         : 0;
$enrolled_count = isset($stats['enrolled'])       ? (int) $stats['enrolled']       : 0;
$accuracy       = isset($stats['accuracy'])       ? (float) $stats['accuracy']     : 0.0;
$avg_response   = isset($stats['avg_response_time']) ? (float) $stats['avg_response_time'] : 0.0;
$success_rate   = $total_attempts > 0 ? round(($success_count / $total_attempts) * 100, 1) : 0;

// Chart data - last 7 days. Uses the logger helper that was extracted
// from the dashboard (H-3 - 1.0.0) so the same Y-axis scaling and bar
// markup can be reused.
$chart_data = $logger->get_daily_breakdown(7);

$max_chart_value = max(array_column($chart_data, 'success')) ?: 1;
if (max(array_column($chart_data, 'failed')) > $max_chart_value) {
    $max_chart_value = max(array_column($chart_data, 'failed'));
}
$max_chart_value = max(1, (int) ceil($max_chart_value / 5) * 5);
$y_axis_steps    = 4;
$y_step_value    = max(1, (int) ceil($max_chart_value / $y_axis_steps));
?>

<?php if ($frl_show_premium_lock): ?>
<!-- Premium Gate Lock Overlay -->
<?php
if ( class_exists( 'FRL_Premium_Gate' ) ) {
    echo wp_kses_post( FRL_Premium_Gate::render_page_lock_overlay( esc_html__( 'Authentication Logs', 'recognition' ) ) );
}
?>
<?php endif; ?>

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
        </div>

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title"><?php esc_html_e('Authentication Logs', 'recognition'); ?></h1>
                <span class="frl-status-badge info">
                    <span class="frl-status-dot"></span>
                    <?php
                    /* translators: %s: total log entries in last 30 days */
                    printf( esc_html__( '%s Entries', 'recognition' ), esc_html( number_format_i18n( (int) $stats['total'] ) ) );
                    ?>
                </span>
                <?php if ($success_rate >= 80) : ?>
                    <span class="frl-status-badge success">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('Healthy', 'recognition'); ?>
                    </span>
                <?php elseif ($success_rate >= 50) : ?>
                    <span class="frl-status-badge warning">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('Watch', 'recognition'); ?>
                    </span>
                <?php else : ?>
                    <span class="frl-status-badge error">
                        <span class="frl-status-dot"></span>
                        <?php esc_html_e('Attention', 'recognition'); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="frl-header-right">
                <button type="button" class="frl-btn frl-btn-secondary" id="frl-clean-old-logs" title="<?php esc_attr_e('Delete logs older than 30 days', 'recognition'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                    </svg>
                    <?php esc_html_e('Clean Old', 'recognition'); ?>
                </button>
                <button type="button" class="frl-btn frl-btn-primary" id="frl-clean-all-logs" style="background: var(--frl-error); color: #fff; border-color: var(--frl-error);" title="<?php esc_attr_e('Delete all log entries', 'recognition'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        <line x1="10" x2="10" y1="11" y2="17"/>
                        <line x1="14" x2="14" y1="11" y2="17"/>
                    </svg>
                    <?php esc_html_e('Clean All', 'recognition'); ?>
                </button>
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
                        <h2 class="frl-hero-title" style="font-size: var(--frl-font-size-3xl);"><?php esc_html_e('Authentication Log Center', 'recognition'); ?></h2>
                        <p class="frl-hero-subtitle">
                            <?php
                            if ($total_logs > 0) {
                                /* translators: 1: success rate, 2: total entries */
                                printf( esc_html__( 'A %1$s%% success rate across %2$s entries in the last 30 days. Filter, search, and clean your biometric trail below.', 'recognition' ),
                                    esc_html( number_format_i18n( $success_rate, 1 ) ),
                                    esc_html( number_format_i18n( (int) $stats['total'] ) )
                                );
                            } else {
                                esc_html_e('No authentication events recorded yet. As soon as users log in with their face, entries will flow into this timeline.', 'recognition');
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Alerts (contextual to site state) -->
            <?php if (!$logging_enabled) : ?>
            <div class="frl-alert frl-alert-warning" style="margin-bottom: var(--frl-space-4);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" x2="12" y1="9" y2="13"/>
                    <line x1="12" x2="12.01" y1="17" y2="17"/>
                </svg>
                <div class="frl-alert-content">
                    <div class="frl-alert-title"><?php esc_html_e('Logging Disabled', 'recognition'); ?></div>
                    <p><?php esc_html_e('Authentication logging is currently disabled. Enable it in Settings to see logs here.', 'recognition'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$table_exists) : ?>
            <div class="frl-alert frl-alert-error" style="margin-bottom: var(--frl-space-4);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" x2="12" y1="8" y2="12"/>
                    <line x1="12" x2="12.01" y1="16" y2="16"/>
                </svg>
                <div class="frl-alert-content">
                    <div class="frl-alert-title"><?php esc_html_e('Database Error', 'recognition'); ?></div>
                    <p><?php esc_html_e('The authentication logs table does not exist. Please deactivate and reactivate the plugin.', 'recognition'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================ -->
            <!-- BENTO GRID                                                    -->
            <!-- ============================================================ -->
            <div class="frl-bento">

                <!-- ============================================ -->
                <!-- KPI 1: TOTAL ATTEMPTS                       -->
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
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $total_attempts ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Last 30 days', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI 2: SUCCESSFUL                           -->
                <!-- ============================================ -->
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

                <!-- ============================================ -->
                <!-- KPI 3: FAILED                                -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--danger">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Failed', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-error-bg); color: var(--frl-error);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $failed_count ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Across all attempts', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI 4: AVG RESPONSE / ACCURACY               -->
                <!-- ============================================ -->
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
                            <div class="frl-kpi-meta">
                                <?php
                                /* translators: %s: accuracy percentage */
                                printf( esc_html__( '%s%% accuracy', 'recognition' ), esc_html( number_format_i18n( $accuracy, 1 ) ) );
                                ?>
                            </div>
                        </div>
                    </div>
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
                                    <?php foreach ( $chart_data as $data ) :
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
                <!-- SYSTEM HEALTH (4 cols)                       -->
                <!-- ============================================ -->
                <div class="frl-bento-4">
                    <div class="frl-glass" style="min-height: 100%;">
                        <div class="frl-glass-header" style="padding-bottom: var(--frl-space-3);">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('Log Health', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle"><?php esc_html_e('Real-time diagnostics', 'recognition'); ?></p>
                            </div>
                            <?php if ($logging_enabled && $is_https && $table_exists) : ?>
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
                            <!-- Logging -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Auth Logging', 'recognition'); ?></span>
                                <?php if ($logging_enabled) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Enabled', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge error"><?php esc_html_e('Disabled', 'recognition'); ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Database table -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Logs Table', 'recognition'); ?></span>
                                <?php if ($table_exists) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Present', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge error"><?php esc_html_e('Missing', 'recognition'); ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- HTTPS -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('SSL / HTTPS', 'recognition'); ?></span>
                                <?php if ($is_https) : ?>
                                    <span class="frl-status-badge success"><?php esc_html_e('Enabled', 'recognition'); ?></span>
                                <?php else : ?>
                                    <span class="frl-status-badge error"><?php esc_html_e('Disabled', 'recognition'); ?></span>
                                <?php endif; ?>
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
                            <!-- Avg response -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--frl-stroke-soft);">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Avg Response', 'recognition'); ?></span>
                                <span class="frl-status-badge info">
                                    <?php
                                    /* translators: %s: avg response time in ms */
                                    printf( esc_html__( '%sms', 'recognition' ), esc_html( number_format_i18n( $avg_response, 0 ) ) );
                                    ?>
                                </span>
                            </div>
                            <!-- Accuracy -->
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0;">
                                <span style="color: var(--frl-text-secondary); font-size: var(--frl-font-size-sm);"><?php esc_html_e('Accuracy', 'recognition'); ?></span>
                                <span class="frl-status-badge success">
                                    <?php
                                    /* translators: %s: accuracy percentage */
                                    printf( esc_html__( '%s%%', 'recognition' ), esc_html( number_format_i18n( $accuracy, 1 ) ) );
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- RECENT LOGS TIMELINE (12 cols)               -->
                <!-- ============================================ -->
                <div class="frl-bento-12">
                    <div class="frl-glass frl-logs-card frl-activity-card">
                        <div class="frl-glass-header">
                            <div class="frl-activity-header-text">
                                <h3 class="frl-glass-title"><?php esc_html_e('Recent Logs', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle">
                                    <?php
                                    /* translators: %s: number of log entries in last 30 days */
                                    printf( esc_html__( '%s entries in the last 30 days', 'recognition' ), esc_html( number_format_i18n( (int) $stats['total'] ) ) );
                                    ?>
                                </p>
                            </div>
                            <div class="frl-activity-toolbar">
                                <div class="frl-logs-search">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="search" id="frl-logs-search" class="frl-logs-search-input" placeholder="<?php esc_attr_e('Search user or IP', 'recognition'); ?>" aria-label="<?php esc_attr_e('Search logs', 'recognition'); ?>">
                                </div>
                                <div class="frl-logs-per-page" role="group" aria-label="<?php esc_attr_e( 'Rows per page', 'recognition' ); ?>">
                                    <label for="frl-logs-per-page" class="frl-logs-per-page-label"><?php esc_html_e( 'Rows per page', 'recognition' ); ?></label>
                                    <div class="frl-logs-per-page-select-wrap">
                                        <select id="frl-logs-per-page" class="frl-logs-per-page-select" aria-label="<?php esc_attr_e( 'Rows per page', 'recognition' ); ?>">
                                            <?php foreach ( $frl_allowed_per_page as $frl_per_page_opt ) : ?>
                                                <option value="<?php echo esc_attr( $frl_per_page_opt ); ?>"<?php selected( (int) $per_page, (int) $frl_per_page_opt ); ?>><?php echo esc_html( number_format_i18n( $frl_per_page_opt ) ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <svg class="frl-logs-per-page-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ( ! empty( $paginated_logs ) ) : ?>
                            <div class="frl-activity-filters frl-logs-filters" role="tablist" aria-label="<?php esc_attr_e('Filter logs', 'recognition'); ?>">
                                <button type="button" class="frl-activity-filter is-active" role="tab" aria-selected="true" data-log-filter="all">
                                    <span class="frl-activity-filter-dot is-all"></span>
                                    <span class="frl-activity-filter-label"><?php esc_html_e('All', 'recognition'); ?></span>
                                    <span class="frl-activity-filter-count"><?php echo esc_html( number_format_i18n( count( $paginated_logs ) ) ); ?></span>
                                </button>
                                <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-log-filter="success">
                                    <span class="frl-activity-filter-dot is-success"></span>
                                    <span class="frl-activity-filter-label"><?php esc_html_e('Success', 'recognition'); ?></span>
                                </button>
                                <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-log-filter="failed">
                                    <span class="frl-activity-filter-dot is-error"></span>
                                    <span class="frl-activity-filter-label"><?php esc_html_e('Failed', 'recognition'); ?></span>
                                </button>
                                <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-log-filter="enrolled">
                                    <span class="frl-activity-filter-dot is-info"></span>
                                    <span class="frl-activity-filter-label"><?php esc_html_e('Enroll', 'recognition'); ?></span>
                                </button>
                                <button type="button" class="frl-activity-filter" role="tab" aria-selected="false" data-log-filter="deleted">
                                    <span class="frl-activity-filter-dot is-warning"></span>
                                    <span class="frl-activity-filter-label"><?php esc_html_e('Deleted', 'recognition'); ?></span>
                                </button>
                            </div>

                            <?php
                                $logs_now         = current_time( 'timestamp' );
                                $logs_today_start = strtotime( current_time( 'Y-m-d 00:00:00' ) );
                                $logs_yest_end    = $logs_today_start - 1;
                                $logs_yest_start  = $logs_yest_end - 86399;
                                $log_groups = array(
                                    'today'     => array(),
                                    'yesterday' => array(),
                                    'earlier'   => array(),
                                );
                                foreach ( $paginated_logs as $log ) {
                                    $log_ts = is_numeric( $log->created_at ) ? (int) $log->created_at : strtotime( (string) $log->created_at );
                                    $log_ts = $log_ts ? (int) $log_ts : 0;
                                    if ( $log_ts >= $logs_today_start ) {
                                        $log_groups['today'][] = $log;
                                    } elseif ( $log_ts >= $logs_yest_start && $log_ts <= $logs_yest_end ) {
                                        $log_groups['yesterday'][] = $log;
                                    } else {
                                        $log_groups['earlier'][] = $log;
                                    }
                                }
                                $log_group_labels = array(
                                    'today'     => __( 'Today', 'recognition' ),
                                    'yesterday' => __( 'Yesterday', 'recognition' ),
                                    'earlier'   => __( 'Earlier', 'recognition' ),
                                );
                            ?>
                            <div class="frl-glass-body frl-activity-body frl-logs-body" style="padding-top: 0;">
                                <?php foreach ( $log_groups as $gkey => $gitems ) :
                                    if ( empty( $gitems ) ) { continue; }
                                ?>
                                <div class="frl-activity-group" data-group="<?php echo esc_attr( $gkey ); ?>">
                                    <div class="frl-activity-group-label">
                                        <span class="frl-activity-group-line"></span>
                                        <span class="frl-activity-group-text"><?php echo esc_html( $log_group_labels[ $gkey ] ); ?></span>
                                        <span class="frl-activity-group-line"></span>
                                    </div>
                                    <ul class="frl-activity">
                                        <?php foreach ( $gitems as $log ) :
                                            $log_user = ! empty( $log->user_id ) ? get_user_by( 'id', (int) $log->user_id ) : null;

                                            $ev_class = 'info';
                                            $ev_icon  = 'info';
                                            $ev_text  = __( 'activity recorded', 'recognition' );
                                            $ev_key   = 'other';

                                            if ( 'success' === $log->result ) {
                                                $ev_class = 'success';
                                                $ev_icon  = 'check';
                                                $ev_text  = __( 'logged in successfully', 'recognition' );
                                                $ev_key   = 'success';
                                            } elseif ( 'failed' === $log->result ) {
                                                $ev_class = 'error';
                                                $ev_icon  = 'x';
                                                $ev_text  = __( 'login failed', 'recognition' );
                                                $ev_key   = 'failed';
                                            } elseif ( 'enrolled' === $log->result ) {
                                                $ev_class = 'info';
                                                $ev_icon  = 'user-plus';
                                                $ev_text  = __( 'enrolled their face', 'recognition' );
                                                $ev_key   = 'enrolled';
                                            } elseif ( 'deleted' === $log->result ) {
                                                $ev_class = 'warning';
                                                $ev_icon  = 'trash';
                                                $ev_text  = __( 'deleted a face profile', 'recognition' );
                                                $ev_key   = 'deleted';
                                            }

                                            $display_name = $log_user ? $log_user->user_login : ( $log->username ?: __( 'Unknown', 'recognition' ) );
                                            $initial      = '';
                                            if ( function_exists( 'mb_substr' ) ) {
                                                $initial = mb_substr( $display_name, 0, 1 );
                                            } elseif ( '' !== $display_name ) {
                                                $initial = strtoupper( substr( $display_name, 0, 1 ) );
                                            }
                                            if ( '' === $initial ) { $initial = '?'; }
                                            $initial = strtoupper( $initial );

                                            $log_ts = is_numeric( $log->created_at ) ? (int) $log->created_at : strtotime( (string) $log->created_at );
                                            $log_ts = $log_ts ? (int) $log_ts : 0;

                                            $relative_time = '';
                                            if ( $log_ts ) {
                                                $diff = (int) $log_ts - $logs_now;
                                                if ( abs( $diff ) < 60 ) {
                                                    $relative_time = __( 'just now', 'recognition' );
                                                } else {
                                                    $human_diff = human_time_diff( $log_ts, $logs_now );
                                                    /* translators: %s: human-readable time difference. */
                                                    $relative_time = sprintf( __( '%s ago', 'recognition' ), $human_diff );
                                                }
                                            }
                                            $absolute_time = $log_ts ? wp_date( 'M j, g:i A', $log_ts ) : '';

                                            $confidence_pct  = null;
                                            $confidence_qual = '';
                                            if ( null !== $log->confidence && '' !== $log->confidence ) {
                                                $confidence_pct = max( 0, min( 100, (float) $log->confidence * 100 ) );
                                                if ( $confidence_pct >= 80 ) {
                                                    $confidence_qual = 'is-high';
                                                } elseif ( $confidence_pct >= 50 ) {
                                                    $confidence_qual = 'is-medium';
                                                } else {
                                                    $confidence_qual = 'is-low';
                                                }
                                            }

                                            $rt_class = '';
                                            if ( null !== $log->response_time ) {
                                                if ( $log->response_time < 100 ) {
                                                    $rt_class = 'is-fast';
                                                } elseif ( $log->response_time < 500 ) {
                                                    $rt_class = 'is-medium';
                                                } else {
                                                    $rt_class = 'is-slow';
                                                }
                                            }

                                            $ip_address = $log->ip_address ?: '-';
                                        ?>
                                        <li class="frl-activity-item frl-log-item"
                                            data-event="<?php echo esc_attr( $ev_key ); ?>"
                                            data-search="<?php echo esc_attr( strtolower( $display_name . ' ' . $ip_address ) ); ?>">
                                            <div class="frl-activity-avatar <?php echo esc_attr( $ev_class ); ?>">
                                                <span class="frl-activity-avatar-initial"><?php echo esc_html( $initial ); ?></span>
                                                <span class="frl-activity-event-icon" aria-hidden="true">
                                                    <?php if ( 'check' === $ev_icon ) : ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <?php elseif ( 'x' === $ev_icon ) : ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                    <?php elseif ( 'trash' === $ev_icon ) : ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
                                                    <?php elseif ( 'user-plus' === $ev_icon ) : ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                                                    <?php else : ?>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div class="frl-activity-content">
                                                <div class="frl-activity-line">
                                                    <span class="frl-activity-user"><?php echo esc_html( $display_name ); ?></span>
                                                    <span class="frl-activity-action"><?php echo esc_html( $ev_text ); ?></span>
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
                                                    <?php if ( null !== $log->response_time ) : ?>
                                                    <span class="frl-log-rtime <?php echo esc_attr( $rt_class ); ?>" title="<?php esc_attr_e( 'Response time', 'recognition' ); ?>">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                                        <?php echo esc_html( number_format_i18n( (float) $log->response_time, 1 ) ); ?>ms
                                                    </span>
                                                    <span class="frl-activity-meta-sep" aria-hidden="true"></span>
                                                    <?php endif; ?>
                                                    <span class="frl-log-ip" title="<?php esc_attr_e( 'IP address', 'recognition' ); ?>">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                                        <?php echo esc_html( $ip_address ); ?>
                                                    </span>
                                                    <span class="frl-activity-meta-sep" aria-hidden="true"></span>
                                                    <span class="frl-activity-meta-item">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                        <time class="frl-activity-time-relative" datetime="<?php echo esc_attr( $absolute_time ); ?>" title="<?php echo esc_attr( $absolute_time ); ?>"><?php echo esc_html( $relative_time ); ?></time>
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="frl-log-id" title="<?php esc_attr_e( 'Log entry ID', 'recognition' ); ?>">#<?php echo (int) $log->id; ?></span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php
                                $base_paged_url = remove_query_arg( 'paged' );
                                $window         = 1; // pages around current
                                // Always derive the current page from the URL
                                // parameter (with a fallback to the existing
                                // $page variable). This guarantees the
                                // current-page indicator and the Previous /
                                // Next buttons are correct on hosted files
                                // that may have a stale version of $page.
                                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
                                $paged_req   = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 0;
                                $page        = $paged_req > 0 ? $paged_req : ( isset( $page ) ? max( 1, (int) $page ) : 1 );
                                $page        = min( $page, max( 1, (int) $total_pages ) );
                                $start       = max( 1, $page - $window );
                                $end         = min( $total_pages, $page + $window );
                            ?>
                            <?php if ( $total_pages > 1 ) : ?>
                            <div class="frl-pagination">
                                <div class="frl-pagination-info" data-page-current="<?php echo (int) $page; ?>" data-total-pages="<?php echo (int) $total_pages; ?>">
                                    <?php
                                        $showing_from = (int) ( $offset + 1 );
                                        $showing_to   = (int) min( $offset + $per_page, $total_logs );
                                    ?>
                                    <?php
                                    /* translators: 1: from, 2: to, 3: total entries */
                                    printf( esc_html__( 'Showing %1$s to %2$s of %3$s entries', 'recognition' ),
                                        esc_html( number_format_i18n( $showing_from ) ),
                                        esc_html( number_format_i18n( $showing_to ) ),
                                        esc_html( number_format_i18n( (int) $total_logs ) )
                                    );
                                    ?>
                                </div>
                                <div class="frl-pagination-controls">
                                    <a href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $page - 1 ), $base_paged_url ) );?>" data-frl-paged="prev" class="frl-pagination-btn" aria-label="<?php esc_attr_e( 'Previous page', 'recognition' ); ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                                    </a>

                                    <?php if ( $start > 1 ) : ?>
                                        <a href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_paged_url ) ); ?>" class="frl-pagination-btn">1</a>
                                        <?php if ( $start > 2 ) : ?>
                                            <span class="frl-pagination-ellipsis">&hellip;</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ( $p = $start; $p <= $end; $p++ ) : ?>
                                        <?php if ( $p === $page ) : ?>
                                            <span class="frl-pagination-current"><?php echo (int) $p; ?></span>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_paged_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $p; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ( $end < $total_pages ) : ?>
                                        <?php if ( $end < $total_pages - 1 ) : ?>
                                            <span class="frl-pagination-ellipsis">&hellip;</span>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_paged_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $total_pages; ?></a>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $page + 1 ), $base_paged_url ) ); ?>" data-frl-paged="next" class="frl-pagination-btn" aria-label="<?php esc_attr_e( 'Next page', 'recognition' ); ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>

                            <script>
                            (function(){
                                // Locate the logs card reliably. The <script> sits at
                                // the end of the card, so its previousElementSibling
                                // is the pagination row (or the empty-state body),
                                // NOT the card root. Use closest() to walk up to the
                                // card, and fall back to a document-wide query if
                                // closest() is unavailable (very old browsers).
                                var script = document.currentScript;
                                var card   = null;
                                if (script && typeof script.closest === 'function') {
                                    card = script.closest('.frl-logs-card');
                                }
                                if (!card) { card = document.querySelector('.frl-logs-card'); }
                                if (!card) { return; }
                                var filters = card.querySelectorAll('[data-log-filter]');
                                var items   = card.querySelectorAll('.frl-log-item');
                                var groups  = card.querySelectorAll('.frl-activity-group');
                                var search  = card.querySelector('#frl-logs-search');
                                var currentFilter = 'all';
                                var currentSearch = '';
                                function applyFilter() {
                                    items.forEach(function(it){
                                        var matchFilter = (currentFilter === 'all') || (it.getAttribute('data-event') === currentFilter);
                                        var matchSearch = !currentSearch || (it.getAttribute('data-search') || '').indexOf(currentSearch) !== -1;
                                        it.style.display = (matchFilter && matchSearch) ? '' : 'none';
                                    });
                                    groups.forEach(function(g){
                                        var visible = 0;
                                        g.querySelectorAll('.frl-log-item').forEach(function(it){
                                            if (it.style.display !== 'none') visible++;
                                        });
                                        g.style.display = visible > 0 ? '' : 'none';
                                    });
                                }
                                filters.forEach(function(btn){
                                    btn.addEventListener('click', function(){
                                        filters.forEach(function(b){ b.classList.remove('is-active'); b.setAttribute('aria-selected', 'false'); });
                                        btn.classList.add('is-active');
                                        btn.setAttribute('aria-selected', 'true');
                                        currentFilter = btn.getAttribute('data-log-filter') || 'all';
                                        applyFilter();
                                    });
                                });
                                if (search) {
                                    var t;
                                    search.addEventListener('input', function(){
                                        clearTimeout(t);
                                        t = setTimeout(function(){
                                            currentSearch = (search.value || '').toLowerCase().trim();
                                            applyFilter();
                                        }, 120);
                                    });
                                }
                                // Per-page selector: when the user changes the
                                // value we navigate to the same admin page with
                                // the new per_page and reset paged=1 so we
                                // never land on an out-of-range page index.
                                var perPage = card.querySelector('#frl-logs-per-page');
                                if (perPage) {
                                    perPage.addEventListener('change', function(){
                                        var v = parseInt(perPage.value, 10) || 0;
                                        if (!v) { return; }
                                        var url = new URL(window.location.href);
                                        url.searchParams.set('per_page', String(v));
                                        // Reset to the first page; the new
                                        // total_pages will be computed by
                                        // PHP on the next request.
                                        url.searchParams.set('paged', '1');
                                        window.location.href = url.toString();
                                    });
                                }
                            })();
                            </script>
                        <?php else : ?>
                            <div class="frl-glass-body" style="padding-top: 0;">
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
                                    <p class="frl-empty-state-title"><?php esc_html_e( 'No logs yet', 'recognition' ); ?></p>
                                    <p class="frl-empty-state-desc"><?php esc_html_e( 'Authentication attempts will appear here once users start logging in with face recognition.', 'recognition' ); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- JSS BRAND STRIP (12 cols)                    -->
                <!-- ============================================ -->
                <div class="frl-bento-12">
                    <div class="frl-glass" style="padding: var(--frl-space-8); display: flex; align-items: center; justify-content: space-between; gap: var(--frl-space-6); flex-wrap: wrap; min-height: auto;">
                        <div>
                            <div style="font-size: var(--frl-font-size-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.10em; color: var(--frl-accent); margin-bottom: var(--frl-space-2);">
                                <?php esc_html_e('Built by JSS Web Solutions', 'recognition'); ?>
                            </div>
                            <h3 style="font-size: var(--frl-font-size-2xl); font-weight: 700; color: var(--frl-text-primary); margin: 0 0 var(--frl-space-1); letter-spacing: -0.02em;">
                                <?php esc_html_e('Need a custom log view?', 'recognition'); ?>
                            </h3>
                            <p style="font-size: var(--frl-font-size-sm); color: var(--frl-text-secondary); margin: 0; max-width: 640px;">
                                <?php esc_html_e('Whether you need SIEM export, a real-time dashboard, or anomaly detection, JSS Web Solutions is the team behind Recognition &mdash; and we build for the long haul.', 'recognition'); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: var(--frl-space-3); flex-wrap: wrap;">
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=logs-page-strip&utm_campaign=hire-dev" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                                <?php esc_html_e('Hire a Developer', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=logs-page-strip&utm_campaign=custom-plugin" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-secondary">
                                <?php esc_html_e('Custom Plugin &rarr;', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/contact/?utm_source=frl-plugin&utm_medium=logs-page-strip&utm_campaign=contact" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-ghost">
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
<?php // Clean-logs buttons are bound by FRL_Admin::enqueue_admin_assets() (admin.js → FRLAdmin.cleanLogs / cleanOldLogs). ?>
