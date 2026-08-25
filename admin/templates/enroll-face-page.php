<?php
/**
 * Admin Face Enrollment Page Template
 *
 * Designed to match the Dashboard's Swiss + Glassmorphism + Bento +
 * Aurora design system. Adds a hero header, KPI strip, and a
 * polished Bento layout for the user picker and camera capture.
 *
 * @package Face_Recognition_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------------
// Premium gate (kept identical to previous version)
// ------------------------------------------------------------------
$frl_show_premium_lock = (
    class_exists('FRL_Premium_Gate')
    && FRL_Premium_Gate::should_lock_page('frl-enroll-face')
);

// Get current page for active state
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template parameter; no state is changed.
$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'frl-enroll-face';

// Get all users who don't have face enrolled yet
$users_without_faces = [];
$all_users = get_users([
    'role__not_in' => [],
    'orderby' => 'display_name',
    'order' => 'ASC',
]);

$database = new FRL_Database();
foreach ($all_users as $user) {
    if (!$database->user_has_face($user->ID)) {
        $users_without_faces[] = $user;
    }
}

// KPI numbers used in the hero strip
$enrolled_count = count($database->get_users_with_faces());
$total_users    = count($all_users);
$pending_count  = count($users_without_faces);
$completion_pct = $total_users > 0 ? (int) round((($total_users - $pending_count) / $total_users) * 100) : 0;

// ------------------------------------------------------------------
// Search & Pagination for the "Select User" card
// ------------------------------------------------------------------
// - Search by display_name or user_email (case-insensitive).
// - Rows-per-page defaults to 10, allowed values: 10 / 20 / 50 / 100.
// - All parameters use the `enroll_*` namespace so they never collide
//   with other query vars that may land on the same admin page
//   (e.g. `?s=...`, `?paged=...` from the Enrolled Users list).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search parameter; no state is changed.
$enroll_search_query  = isset($_GET['enroll_s']) ? sanitize_text_field(wp_unslash($_GET['enroll_s'])) : '';
$enroll_allowed_pp    = array(10, 20, 50, 100);
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; whitelisted against $enroll_allowed_pp.
$enroll_per_page_req  = isset($_GET['enroll_per_page']) ? absint(wp_unslash($_GET['enroll_per_page'])) : 10;
$enroll_per_page      = in_array($enroll_per_page_req, $enroll_allowed_pp, true) ? $enroll_per_page_req : 10;

// Apply search filter to a separate array so the KPI strip keeps
// reflecting the TRUE pending count (not the search-filtered one).
$enroll_visible_users = $users_without_faces;
if ('' !== $enroll_search_query) {
    $enroll_needle = mb_strtolower($enroll_search_query);
    $enroll_visible_users = array_values(array_filter($enroll_visible_users, function($u) use ($enroll_needle) {
        $hay_name  = mb_strtolower((string) $u->display_name);
        $hay_email = mb_strtolower((string) $u->user_email);
        return (false !== strpos($hay_name, $enroll_needle))
            || (false !== strpos($hay_email, $enroll_needle));
    }));
}

$enroll_total_count  = count($enroll_visible_users);
$enroll_total_pages  = max(1, (int) ceil($enroll_total_count / $enroll_per_page));
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
$enroll_paged_req    = isset($_GET['enroll_paged']) ? absint(wp_unslash($_GET['enroll_paged'])) : 0;
$enroll_current_page = $enroll_paged_req > 0 ? $enroll_paged_req : 1;
$enroll_current_page = min($enroll_current_page, $enroll_total_pages);
$enroll_offset       = ($enroll_current_page - 1) * $enroll_per_page;
$enroll_paginated    = array_slice($enroll_visible_users, $enroll_offset, $enroll_per_page);

// Build a base URL that keeps the search query + per_page in
// pagination links (but always drops enroll_paged so each link
// can set its own target page).
$enroll_base_url = remove_query_arg(array('enroll_paged'));
if ('' !== $enroll_search_query) {
    $enroll_base_url = add_query_arg('enroll_s', rawurlencode($enroll_search_query), $enroll_base_url);
}
if ($enroll_per_page !== 10) {
    $enroll_base_url = add_query_arg('enroll_per_page', $enroll_per_page, $enroll_base_url);
}
?>

<?php if ($frl_show_premium_lock): ?>
<!-- Premium Gate Lock Overlay -->
<?php
if ( class_exists( 'FRL_Premium_Gate' ) ) {
    echo wp_kses_post( FRL_Premium_Gate::render_page_lock_overlay( esc_html__( 'Enroll Face', 'recognition' ) ) );
}
?>
<?php endif; ?>

<!-- App Container -->
<div class="frl-admin-wrap frl-enroll-page">
<div class="frl-app" id="frl-enroll-app">
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
                <h1 class="frl-header-title">Enroll Face</h1>
                <span class="frl-status-badge info">
                    <span class="frl-status-dot"></span>
                    Admin Tool
                </span>
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
            <!-- HERO BANNER (mirrors dashboard for visual consistency)        -->
            <!-- ============================================================ -->
            <div class="frl-hero">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--frl-space-6);flex-wrap:wrap;">
                    <div>
                        <div style="font-size:var(--frl-font-size-xs);font-weight:700;text-transform:uppercase;letter-spacing:0.10em;color:var(--frl-accent);margin-bottom:var(--frl-space-2);">
                            Admin Enrollment
                        </div>
                        <h2 class="frl-hero-title">Enroll a Face for Any User</h2>
                        <p class="frl-hero-subtitle">
                            Pick a user, position their face inside the camera frame, and capture 15 samples to create a secure biometric profile &mdash; no password required from the user.
                        </p>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:var(--frl-space-2);">
                        <span class="frl-status-badge success">
                            <span class="frl-status-dot"></span>
                            Camera Ready
                        </span>
                        <span style="font-size:var(--frl-font-size-xs);color:var(--frl-text-tertiary);">
                            Models: <strong style="color:var(--frl-text-primary);">Loaded</strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- KPI STRIP (Bento)                                             -->
            <!-- ============================================================ -->
            <div class="frl-bento">
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--info">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label">Enrolled</span>
                            <div class="frl-kpi-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $enrolled_count ) ); ?></div>
                            <div class="frl-kpi-meta">Active face profiles</div>
                        </div>
                    </div>
                </div>
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--warning">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label">Pending</span>
                            <div class="frl-kpi-icon" style="background:var(--frl-warning-bg);color:var(--frl-warning);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $pending_count ) ); ?></div>
                            <div class="frl-kpi-meta">Users without a face</div>
                        </div>
                    </div>
                </div>
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--success">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label">Total</span>
                            <div class="frl-kpi-icon" style="background:var(--frl-success-bg);color:var(--frl-success);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></div>
                            <div class="frl-kpi-meta">Site users</div>
                        </div>
                    </div>
                </div>
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--aurora">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label">Completion</span>
                            <div class="frl-kpi-icon" style="background:linear-gradient(135deg,#ec4899,#8b5cf6);color:#fff;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( $completion_pct ); ?><span class="frl-kpi-value-suffix">%</span></div>
                            <div class="frl-kpi-meta">Coverage</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success Notice (Hidden by default, shown via JS) -->
            <div id="frl-admin-success-notice" class="frl-admin-notice frl-notice-success" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                <div class="frl-notice-content">
                    <strong><?php esc_html_e('Face Enrolled Successfully!', 'recognition'); ?></strong>
                    <p><?php esc_html_e('The user can now log in using face recognition.', 'recognition'); ?></p>
                </div>
                <button type="button" class="frl-notice-dismiss" id="frl-dismiss-success" aria-label="Dismiss">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"/>
                        <path d="m6 6 12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Info Alert -->
            <div class="frl-enroll-page-alert">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4"/>
                    <path d="M12 8h.01"/>
                </svg>
                <div class="frl-alert-content">
                    <div class="frl-alert-title">Admin Face Enrollment</div>
                    <p>Enroll face profiles for existing users. This allows users to log in using face recognition without going through the self-enrollment flow themselves.</p>
                </div>
            </div>

            <!-- Main Grid (Bento) -->
            <div class="frl-enroll-grid">
                <!-- User Selection Panel -->
                <div class="frl-card frl-user-card">
                    <div class="frl-card-header">
                        <div>
                            <h3 class="frl-card-title"><?php esc_html_e('Select User', 'recognition'); ?></h3>
                            <p class="frl-card-subtitle"><?php esc_html_e('Choose a user to enroll a face for', 'recognition'); ?></p>
                        </div>
                    </div>
                    <div class="frl-card-body">
                        <!-- Search (server-side: submits the form to filter + repaginate) -->
                        <form class="frl-user-card-search-form" method="get" action="">
                            <input type="hidden" name="page" value="frl-enroll-face" />
                            <div class="frl-search-box">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.3-4.3"/>
                                </svg>
                                <input type="search" id="frl-user-search" name="enroll_s" value="<?php echo esc_attr($enroll_search_query); ?>" placeholder="<?php esc_attr_e('Search users by name or email…', 'recognition'); ?>" aria-label="<?php esc_attr_e('Search users', 'recognition'); ?>">
                                <?php if ($enroll_per_page !== 10) : ?>
                                    <input type="hidden" name="enroll_per_page" value="<?php echo (int) $enroll_per_page; ?>" />
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Users Without Face -->
                        <div class="frl-users-section">
                            <div class="frl-users-header">
<span class="frl-users-label"><?php esc_html_e('Users Without Face Enrolled', 'recognition'); ?></span>
                                <span class="frl-users-count" id="frl-users-without-face-count">
                                    <?php if ('' !== $enroll_search_query) : ?>
                                        <?php
                                        /* translators: 1: filtered count, 2: total pending */
                                        printf( esc_html__( '%1$d of %2$d', 'recognition' ),
                                            (int) $enroll_total_count,
                                            (int) $pending_count
                                        );
                                        ?>
                                    <?php else : ?>
                                        (<?php echo (int) $pending_count; ?>)
                                    <?php endif; ?>
                                </span>
                            </div>

                            <form class="frl-user-card-per-page-form" method="get" action="">
                                <input type="hidden" name="page" value="frl-enroll-face" />
                                <?php if ('' !== $enroll_search_query) : ?>
                                    <input type="hidden" name="enroll_s" value="<?php echo esc_attr($enroll_search_query); ?>" />
                                <?php endif; ?>
                                <label class="frl-per-page">
                                    <span class="frl-per-page-label"><?php esc_html_e('Rows per page', 'recognition'); ?></span>
                                    <select name="enroll_per_page" class="frl-input frl-input--select" onchange="this.form.submit()" aria-label="<?php esc_attr_e('Rows per page', 'recognition'); ?>">
                                        <?php foreach ($enroll_allowed_pp as $opt) : ?>
                                            <option value="<?php echo (int) $opt; ?>" <?php selected($enroll_per_page, $opt); ?>><?php echo (int) $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </form>

                            <div class="frl-users-list" id="frl-users-without-face-list">
                                <?php if (empty($users_without_faces)): ?>
                                    <div class="frl-empty-state">
                                        <div class="frl-empty-state-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                <polyline points="22 4 12 14.01 9 11.01"/>
                                            </svg>
                                        </div>
                                        <p class="frl-empty-state-desc"><?php esc_html_e('All users have face enrolled!', 'recognition'); ?></p>
                                    </div>
                                <?php elseif (empty($enroll_paginated)): ?>
                                    <div class="frl-empty-state">
                                        <div class="frl-empty-state-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="11" cy="11" r="8"/>
                                                <path d="m21 21-4.3-4.3"/>
                                            </svg>
                                        </div>
                                        <p class="frl-empty-state-desc"><?php esc_html_e('No users match your search.', 'recognition'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($enroll_paginated as $user): ?>
                                        <button type="button" class="frl-user-item" data-user-id="<?php echo esc_attr($user->ID); ?>" data-user-name="<?php echo esc_attr($user->display_name); ?>" data-user-email="<?php echo esc_attr($user->user_email); ?>" data-search="<?php echo esc_attr(strtolower($user->display_name . ' ' . $user->user_email . ' ' . $user->ID)); ?>">
                                            <div class="frl-user-avatar">
                                                <?php echo get_avatar($user->ID, 40, '', '', ['class' => 'frl-avatar-img']); ?>
                                            </div>
                                            <div class="frl-user-info">
                                                <span class="frl-user-name"><?php echo esc_html($user->display_name); ?></span>
                                                <span class="frl-user-email"><?php echo esc_html($user->user_email); ?></span>
                                            </div>
                                            <div class="frl-user-action" aria-hidden="true">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="m9 18 6-6-6-6"/>
                                                </svg>
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($enroll_paginated) && $enroll_total_pages > 1) :
                                $enroll_window = 1;
                                $enroll_start  = max(1, $enroll_current_page - $enroll_window);
                                $enroll_end    = min($enroll_total_pages, $enroll_current_page + $enroll_window);
                            ?>
                                <div class="frl-pagination frl-pagination--enroll">
                                    <div class="frl-pagination-info" data-page-current="<?php echo (int) $enroll_current_page; ?>" data-total-pages="<?php echo (int) $enroll_total_pages; ?>">
                                        <?php
                                        /* translators: 1: from, 2: to, 3: total matching */
                                        printf( esc_html__( 'Showing %1$s to %2$s of %3$s', 'recognition' ),
                                            esc_html( number_format_i18n( $enroll_offset + 1 ) ),
                                            esc_html( number_format_i18n( min( $enroll_offset + $enroll_per_page, $enroll_total_count ) ) ),
                                            esc_html( number_format_i18n( $enroll_total_count ) )
                                        );
                                        ?>
                                    </div>
                                    <div class="frl-pagination-controls">
                                        <a href="<?php echo esc_url( add_query_arg( 'enroll_paged', max( 1, $enroll_current_page - 1 ), $enroll_base_url ) ); ?>" data-frl-paged="prev" class="frl-pagination-btn" aria-label="<?php esc_attr_e('Previous page', 'recognition'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m15 18-6-6 6-6"/>
                                            </svg>
                                        </a>

                                        <?php if ($enroll_start > 1) : ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'enroll_paged', 1, $enroll_base_url ) ); ?>" class="frl-pagination-btn">1</a>
                                            <?php if ($enroll_start > 2) : ?>
                                                <span class="frl-pagination-ellipsis">&hellip;</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($p = $enroll_start; $p <= $enroll_end; $p++) : ?>
                                            <?php if ($p === $enroll_current_page) : ?>
                                                <span class="frl-pagination-current"><?php echo (int) $p; ?></span>
                                            <?php else : ?>
                                                <a href="<?php echo esc_url( add_query_arg( 'enroll_paged', $p, $enroll_base_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $p; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($enroll_end < $enroll_total_pages) : ?>
                                            <?php if ($enroll_end < $enroll_total_pages - 1) : ?>
                                                <span class="frl-pagination-ellipsis">&hellip;</span>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'enroll_paged', $enroll_total_pages, $enroll_base_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $enroll_total_pages; ?></a>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( add_query_arg( 'enroll_paged', min( $enroll_total_pages, $enroll_current_page + 1 ), $enroll_base_url ) ); ?>" data-frl-paged="next" class="frl-pagination-btn" aria-label="<?php esc_attr_e('Next page', 'recognition'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m9 18 6-6-6-6"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Enrollment Panel -->
                <div class="frl-card frl-enrollment-card">
                    <div class="frl-card-header">
                        <div>
                            <h3 class="frl-card-title"><?php esc_html_e('Face Capture', 'recognition'); ?></h3>
                            <p class="frl-card-subtitle"><?php esc_html_e('Position the face in the camera frame', 'recognition'); ?></p>
                        </div>
                    </div>
                    <div class="frl-card-body">
                        <!-- No User Selected State -->
                        <div id="frl-no-user-selected" class="frl-no-selection">
                            <div class="frl-no-selection-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                            <p class="frl-no-selection-text"><?php esc_html_e('Select a user from the list to start face enrollment', 'recognition'); ?></p>
                        </div>

                        <!-- User Selected - Ready for Enrollment -->
                        <div id="frl-enrollment-area" style="display: none;">
                            <!-- Selected User Info -->
                            <div class="frl-selected-user-info">
                                <div class="frl-user-avatar-large">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                </div>
                                <div class="frl-selected-user-details">
                                    <span class="frl-selected-user-name" id="frl-selected-display"></span>
                                    <span class="frl-selected-user-email" id="frl-selected-email"></span>
                                </div>
                                <button type="button" class="frl-btn-icon frl-btn-change-user" id="frl-change-user-btn" title="<?php esc_attr_e('Change user', 'recognition'); ?>">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 2l4 4-4 4"/>
                                        <path d="M3 11v-1a4 4 0 0 1 4-4h14"/>
                                        <path d="M7 22l-4-4 4-4"/>
                                        <path d="M21 13v1a4 4 0 0 1-4 4H3"/>
                                    </svg>
                                </button>
                            </div>

                            <!-- Video Container -->
                            <div id="frl-video-container" class="frl-admin-video-container">
                                <video id="frl-admin-video" autoplay playsinline></video>
                                <canvas id="frl-admin-canvas"></canvas>
                                <div class="frl-face-overlay"></div>
                            </div>

                            <!-- Status Messages -->
                            <div id="frl-enrollment-status" class="frl-status frl-status-info"></div>
                            <div id="frl-enrollment-instructions" class="frl-instructions">
                                <?php esc_html_e("Position the user's face within the frame and ensure good lighting.", 'recognition'); ?>
                            </div>

                            <!-- Progress Bar -->
                            <div class="frl-progress-container">
                                <div class="frl-progress-bar">
                                    <div id="frl-enroll-progress-bar" class="frl-progress-fill"></div>
                                </div>
                                <span id="frl-enroll-progress-text" class="frl-progress-text">0%</span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="frl-enrollment-actions">
                                <button type="button" class="frl-btn frl-btn-primary frl-btn-lg" id="frl-start-enrollment-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <line x1="19" x2="19" y1="8" y2="14"/>
                                        <line x1="22" x2="16" y1="11" y2="11"/>
                                    </svg>
                                    <span><?php esc_html_e('Start Camera & Enroll', 'recognition'); ?></span>
                                </button>
                                <button type="button" class="frl-btn frl-btn-secondary" id="frl-cancel-enrollment" style="display: none;">
                                    <?php esc_html_e('Cancel', 'recognition'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Success State (Hidden by default) -->
                        <div id="frl-enrollment-success" class="frl-success-state" style="display: none;">
                            <div class="frl-success-icon-large">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="m9 12 2 2 4-4"/>
                                </svg>
                            </div>
                            <h3 class="frl-success-title"><?php esc_html_e('Face Enrolled Successfully!', 'recognition'); ?></h3>
                            <p class="frl-success-text"><?php esc_html_e('The user can now log in using face recognition.', 'recognition'); ?></p>
                            <div class="frl-success-user-info" id="frl-success-user-info">
                                <strong id="frl-success-user-name"></strong>
                                <span id="frl-success-user-email"></span>
                            </div>
                            <button type="button" class="frl-btn frl-btn-primary frl-btn-lg" id="frl-enroll-another-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"/>
                                    <path d="M12 5v14"/>
                                </svg>
                                <span><?php esc_html_e('Enroll Another User', 'recognition'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</div>

<script>
(function() {
    // Page-specific: success notice dismiss. Theme + sidebar toggles
    // are enqueued via FRL_Admin::enqueue_admin_assets() (frl-admin-shared.js - H-2 - 1.0.0).
    var dismissBtn = document.getElementById('frl-dismiss-success');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            var notice = document.getElementById('frl-admin-success-notice');
            if (notice) {
                notice.style.display = 'none';
            }
        });
    }
})();
</script>
