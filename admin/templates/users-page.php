<?php
/**
 * Admin Enrolled Users Page Template
 *
 * Aligned with the Swiss + Glassmorphism + Bento + Aurora design
 * system introduced in v2.0.0 (see dashboard-page.php for the
 * canonical layout). Structure:
 *
 *   1. Hero banner             (frl-bento-12)
 *   2. 3 KPI tiles + 1 JSS     (frl-bento-3 Ã— 3  +  frl-bento-3)
 *      promo  ("Hire a Developer")
 *   3. Users glass table       (frl-bento-8)
 *      + Quick actions panel   (frl-bento-4)
 *   4. JSS brand strip         (frl-bento-12)
 *
 * @package Face_Recognition_Login
 * @since   2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = new FRL_Database();
$users = $database->get_users_with_faces();

$total_faces  = 0;
$active_7d    = 0;
$now_ts       = current_time('timestamp');
$week_ago_ts  = $now_ts - (7 * DAY_IN_SECONDS);

foreach ($users as $user) {
    $total_faces += (int) $user->face_count;
    if (!empty($user->last_login)) {
        $login_ts = is_numeric($user->last_login) ? (int) $user->last_login : strtotime((string) $user->last_login);
        if ($login_ts && $login_ts >= $week_ago_ts) {
            $active_7d++;
        }
    }
}

$avg_profiles = count($users) > 0 ? round($total_faces / count($users), 1) : 0;

// Search filter (username or email, case-insensitive)
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin search parameter; no state is changed.
$search_query = isset($_GET['s']) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
if ( '' !== $search_query ) {
    $needle = mb_strtolower( $search_query );
    $users = array_values( array_filter( $users, function( $u ) use ( $needle ) {
        $hay_login = mb_strtolower( (string) $u->user_login );
        $hay_email = mb_strtolower( (string) $u->user_email );
        return ( false !== strpos( $hay_login, $needle ) ) || ( false !== strpos( $hay_email, $needle ) );
    } ) );
    // Recompute totals on the filtered list
    $total_faces_f = 0;
    foreach ( $users as $u ) { $total_faces_f += (int) $u->face_count; }
    $total_faces  = $total_faces_f;
    $avg_profiles = count( $users ) > 0 ? round( $total_faces / count( $users ), 1 ) : 0;
    $active_7d    = 0;
    foreach ( $users as $u ) {
        if ( ! empty( $u->last_login ) ) {
            $login_ts = is_numeric( $u->last_login ) ? (int) $u->last_login : strtotime( (string) $u->last_login );
            if ( $login_ts && $login_ts >= $week_ago_ts ) { $active_7d++; }
        }
    }
}

// Pagination (per_page from query string; default 10, allowed 10/20/50/100)
$allowed_pp   = array( 10, 20, 50, 100 );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; whitelisted against $allowed_per_page.
$per_page_req = isset( $_GET["per_page"] ) ? absint( wp_unslash( $_GET["per_page"] ) ) : 10;
$per_page     = in_array( $per_page_req, $allowed_pp, true ) ? $per_page_req : 10;
$total_pages  = max(1, (int) ceil(count($users) / $per_page));
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
$current_page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paginated_users = array_slice($users, $offset, $per_page);
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
        </div>

        <!-- Header -->
        <header class="frl-header">
            <div class="frl-header-left">
                <h1 class="frl-header-title"><?php esc_html_e('Enrolled Users', 'recognition'); ?></h1>
                <span class="frl-status-badge info">
                    <span class="frl-status-dot"></span>
                    <?php
                    /* translators: %s: number of enrolled users */
                    printf( esc_html__( '%s Users', 'recognition' ), esc_html( number_format_i18n( count( $users ) ) ) );
                    ?>
                </span>
                <?php if ($active_7d > 0) : ?>
                    <span class="frl-status-badge success">
                        <span class="frl-status-dot"></span>
                        <?php
                        /* translators: %s: number of users active in last 7 days */
                        printf( esc_html__( '%s Active 7d', 'recognition' ), esc_html( number_format_i18n( $active_7d ) ) );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="frl-header-right">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-enroll-face' ) ); ?>" class="frl-btn frl-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <line x1="19" x2="19" y1="8" y2="14"/>
                        <line x1="22" x2="16" y1="11" y2="11"/>
                    </svg>
                    <?php esc_html_e('Enroll New', 'recognition'); ?>
                </a>
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
                        <h2 class="frl-hero-title" style="font-size: var(--frl-font-size-3xl);"><?php esc_html_e('Your Enrolled Users', 'recognition'); ?></h2>
                        <p class="frl-hero-subtitle">
                            <?php
                            if (count($users) > 0) {
                                esc_html_e('Manage every face profile, audit recent activity, and keep your biometric directory tidy &mdash; all from one calm, focused surface.', 'recognition');
                            } else {
                                esc_html_e('No users have enrolled their face yet. Onboard a user below and their profile will appear here, ready to manage at a glance.', 'recognition');
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ============================================================ -->
            <!-- BENTO GRID                                                    -->
            <!-- ============================================================ -->
            <div class="frl-bento">

                <!-- ============================================ -->
                <!-- KPI 1: ENROLLED USERS                        -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--info">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Enrolled Users', 'recognition'); ?></span>
                            <div class="frl-kpi-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( count( $users ) ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Total registered', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI 2: TOTAL FACE PROFILES                   -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--aurora">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Face Profiles', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff;">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( (int) $total_faces ) ); ?></div>
                            <div class="frl-kpi-meta">
                                <span class="frl-kpi-trend frl-kpi-trend--up">&#8593;</span>
                                <?php esc_html_e('Stored descriptors', 'recognition'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI 3: AVG PROFILES / USER                   -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--success">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Avg Profiles / User', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-success-bg); color: var(--frl-success);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $avg_profiles, 1 ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Across all enrolments', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- KPI 4: ACTIVE IN LAST 7 DAYS (warning tint)  -->
                <!-- ============================================ -->
                <div class="frl-bento-3">
                    <div class="frl-kpi frl-kpi--warning">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Active 7d', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-warning-bg); color: var(--frl-warning);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="frl-kpi-value"><?php echo esc_html( number_format_i18n( $active_7d ) ); ?></div>
                            <div class="frl-kpi-meta"><?php esc_html_e('Logged in this week', 'recognition'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- USERS GLASS TABLE (8 cols)                    -->
                <!-- ============================================ -->
                <div class="frl-bento-8">
                    <div class="frl-glass">
                        <div class="frl-glass-header">
                            <div>
                                <h3 class="frl-glass-title"><?php esc_html_e('All Enrolled Users', 'recognition'); ?></h3>
                                <p class="frl-glass-subtitle">
                                    <?php
                                    if (count($users) > 0) {
                                        /* translators: %s: total enrolled users */
                                        printf( esc_html__( '%s total &mdash; add a face or remove a profile.', 'recognition' ), esc_html( number_format_i18n( count( $users ) ) ) );
                                    } else {
                                        esc_html_e('No users have enrolled yet.', 'recognition');
                                    }
                                    ?>
                                </p>
                            </div>
                            <?php if (count($users) > 0) : ?>
                            <form class="frl-table-toolbar" method="get" action="">
                                <input type="hidden" name="page" value="frl-users" />
                                <div class="frl-search" style="flex: 1; min-width: 220px;">
                                    <span class="frl-search-icon" aria-hidden="true">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/></svg>
                                    </span>
                                    <input type="search" id="frl-users-search" name="s" class="frl-input" value="<?php echo esc_attr( $search_query ); ?>" placeholder="<?php esc_attr_e('Search by username or email...', 'recognition'); ?>" aria-label="<?php esc_attr_e('Search enrolled users', 'recognition'); ?>" />
                                    <?php if ( '' !== $search_query ) : ?>
                                        <a href="<?php echo esc_url( remove_query_arg( array( 's', 'paged' ) ) ); ?>" class="frl-search-clear" aria-label="<?php esc_attr_e('Clear search', 'recognition'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <label class="frl-per-page">
                                    <span class="frl-per-page-label"><?php esc_html_e('Rows per page', 'recognition'); ?></span>
                                    <select name="per_page" class="frl-input frl-input--select" onchange="this.form.submit()" aria-label="<?php esc_attr_e('Rows per page', 'recognition'); ?>">
                                        <?php foreach ( $allowed_pp as $opt ) : ?>
                                            <option value="<?php echo (int) $opt; ?>" <?php selected( $per_page, $opt ); ?>><?php echo (int) $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php if ( '' !== $search_query ) : ?>
                                    <input type="hidden" name="s" value="<?php echo esc_attr( $search_query ); ?>" />
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($users)) : ?>
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
                                    <p class="frl-empty-state-title"><?php esc_html_e('No enrolled users yet', 'recognition'); ?></p>
                                    <p class="frl-empty-state-desc"><?php esc_html_e('Enroll a face for yourself or a test user to get started. The user will appear here as soon as their face is captured and saved.', 'recognition'); ?></p>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-enroll-face' ) ); ?>" class="frl-btn frl-btn-primary frl-btn-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <line x1="19" x2="19" y1="8" y2="14"/>
                                            <line x1="22" x2="16" y1="11" y2="11"/>
                                        </svg>
                                        <?php esc_html_e('Enroll a User', 'recognition'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php elseif ( count( $users ) === 0 && '' !== $search_query ) : ?>
                            <div class="frl-glass-body" style="padding-top: 0;">
                                <div class="frl-empty-state frl-empty-state--rich">
                                    <p class="frl-empty-state-title"><?php esc_html_e('No matches for that search', 'recognition'); ?></p>
                                    <p class="frl-empty-state-desc"><?php esc_html_e('Try a different username or email, or clear the search to see all enrolled users.', 'recognition'); ?></p>
                                    <a href="<?php echo esc_url( remove_query_arg( array( 's', 'paged' ) ) ); ?>" class="frl-btn frl-btn-primary frl-btn-lg"><?php esc_html_e('Clear search', 'recognition'); ?></a>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="frl-glass-body" style="padding-top: 0;">
                                <div class="frl-table-container">
                                    <table class="frl-table" id="frl-users-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('User', 'recognition'); ?></th>
                                                <th><?php esc_html_e('Email', 'recognition'); ?></th>
                                                <th><?php esc_html_e('Face Profiles', 'recognition'); ?></th>
                                                <th><?php esc_html_e('Last Login', 'recognition'); ?></th>
                                                <th style="text-align: right;"><?php esc_html_e('Actions', 'recognition'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($paginated_users as $user) : ?>
                                                <tr data-search="<?php echo esc_attr( strtolower( $user->user_login . ' ' . $user->user_email . ' ' . $user->ID ) ); ?>" data-user-id="<?php echo (int) $user->ID; ?>">
                                                    <td>
                                                        <div class="frl-table-user">
                                                            <div class="frl-table-avatar">
                                                                <?php echo esc_html( strtoupper( substr( $user->user_login, 0, 1 ) ) ); ?>
                                                            </div>
                                                            <div class="frl-table-user-info">
                                                                <span class="frl-table-user-name"><?php echo esc_html( $user->user_login ); ?></span>
                                                                <span class="frl-table-user-email">
                                                                    <?php
                                                                    /* translators: %d: user ID */
                                                                    printf( esc_html__( 'ID: %d', 'recognition' ), (int) $user->ID );
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?php echo esc_html( $user->user_email ); ?></td>
                                                    <td>
                                                        <span class="frl-status-badge success">
                                                            <?php
                                                            /* translators: %s: number of face profiles */
                                                            printf( esc_html__( '%s profiles', 'recognition' ), esc_html( number_format_i18n( (int) $user->face_count ) ) );
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($user->last_login)) {
                                                            $login_ts = is_numeric($user->last_login) ? (int) $user->last_login : strtotime((string) $user->last_login);
                                                            echo esc_html( $login_ts ? wp_date( 'M j, Y g:i A', $login_ts ) : $user->last_login );
                                                        } else {
                                                            echo '<span style="color: var(--frl-text-tertiary);">' . esc_html__( 'Never', 'recognition' ) . '</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td style="text-align: right;">
                                                        <div class="frl-table-actions" style="justify-content: flex-end;">
                                                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'frl-enroll-face', 'user_id' => (int) $user->ID ] ) ); ?>" class="frl-btn frl-btn-ghost frl-btn-sm" title="<?php esc_attr_e('Add Face', 'recognition'); ?>" aria-label="<?php esc_attr_e('Add face for this user', 'recognition'); ?>">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                                                    <circle cx="9" cy="7" r="4"/>
                                                                    <line x1="19" x2="19" y1="8" y2="14"/>
                                                                    <line x1="22" x2="16" y1="11" y2="11"/>
                                                                </svg>
                                                            </a>
                                                            <button type="button" class="frl-btn frl-btn-ghost frl-btn-sm frl-delete-user-faces" data-user-id="<?php echo esc_attr( $user->ID ); ?>" data-user-name="<?php echo esc_attr( $user->user_login ); ?>" title="<?php esc_attr_e('Delete All Faces', 'recognition'); ?>" aria-label="<?php esc_attr_e('Delete all face profiles for this user', 'recognition'); ?>">
                                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <polyline points="3 6 5 6 21 6"/>
                                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr id="frl-users-no-results" style="display: none;">
                                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--frl-text-tertiary);">
                                                    <?php esc_html_e('No users match your search.', 'recognition'); ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($total_pages > 1) :
                                    // Always derive the current page from the URL parameter
                                    // (with a fallback to the existing $current_page variable).
                                    // This guarantees the current-page indicator and the
                                    // Previous/Next buttons are correct on hosted files
                                    // that may have a stale version of $current_page.
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin pagination parameter; no state is changed.
                                    $paged_req    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 0;
                                    $current_page = $paged_req > 0 ? $paged_req : ( isset( $current_page ) ? max( 1, (int) $current_page ) : 1 );
                                    $current_page = min( $current_page, max( 1, (int) $total_pages ) );

                                    $base_paged_url = remove_query_arg( array( 'paged', 'per_page', 's' ) );
if ( '' !== $search_query ) {
    $base_paged_url = add_query_arg( 's', rawurlencode( $search_query ), $base_paged_url );
}
if ( $per_page !== 10 ) {
    $base_paged_url = add_query_arg( 'per_page', $per_page, $base_paged_url );
}
                                    $window = 1; // pages around current
                                    $start = max( 1, $current_page - $window );
                                    $end   = min( $total_pages, $current_page + $window );
                                ?>
                                <div class="frl-pagination">
                                    <div class="frl-pagination-info" data-page-current="<?php echo (int) $current_page; ?>" data-total-pages="<?php echo (int) $total_pages; ?>">
                                        <?php
                                        /* translators: 1: from, 2: to, 3: total users */
                                        printf( esc_html__( 'Showing %1$s to %2$s of %3$s users', 'recognition' ),
                                            esc_html( number_format_i18n( $offset + 1 ) ),
                                            esc_html( number_format_i18n( min( $offset + $per_page, count( $users ) ) ) ),
                                            esc_html( number_format_i18n( count( $users ) ) )
                                        );
                                        ?>
                                    </div>
                                    <div class="frl-pagination-controls">
                                        <a href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $current_page - 1 ), $base_paged_url ) ); ?>" data-frl-paged="prev" class="frl-pagination-btn" aria-label="<?php esc_attr_e('Previous page', 'recognition'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m15 18-6-6 6-6"/>
                                            </svg>
                                        </a>

                                        <?php if ($start > 1) : ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_paged_url ) ); ?>" class="frl-pagination-btn">1</a>
                                            <?php if ($start > 2) : ?>
                                                <span class="frl-pagination-ellipsis">&hellip;</span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($p = $start; $p <= $end; $p++) : ?>
                                            <?php if ($p === $current_page) : ?>
                                                <span class="frl-pagination-current"><?php echo (int) $p; ?></span>
                                            <?php else : ?>
                                                <a href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_paged_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $p; ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($end < $total_pages) : ?>
                                            <?php if ($end < $total_pages - 1) : ?>
                                                <span class="frl-pagination-ellipsis">&hellip;</span>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'paged', $total_pages, $base_paged_url ) ); ?>" class="frl-pagination-btn"><?php echo (int) $total_pages; ?></a>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $current_page + 1 ), $base_paged_url ) ); ?>" data-frl-paged="next" class="frl-pagination-btn" aria-label="<?php esc_attr_e('Next page', 'recognition'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="m9 18 6-6-6-6"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- QUICK ACTIONS PANEL (4 cols)                 -->
                <!-- ============================================ -->
                <div class="frl-bento-4">
                    <div class="frl-glass" style="min-height: 100%;">
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
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=frl-logs' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon" style="background: var(--frl-info-bg); color: var(--frl-info);">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('View Auth Logs', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('Inspect authentication history', 'recognition'); ?></div>
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
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=recognition' ) ); ?>" class="frl-quick-action">
                                <div class="frl-quick-action-icon" style="background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                </div>
                                <div class="frl-quick-action-text">
                                    <div class="frl-quick-action-title"><?php esc_html_e('Open Dashboard', 'recognition'); ?></div>
                                    <div class="frl-quick-action-desc"><?php esc_html_e('Back to the full overview', 'recognition'); ?></div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- JSS PROMO: HIRE A DEVELOPER (12 cols)        -->
                <!-- (Mirrors the dashboard's brand strip.)        -->
                <!-- ============================================ -->
                <div class="frl-bento-12">
                    <div class="frl-glass" style="padding: var(--frl-space-8); display: flex; justify-content: space-between; gap: var(--frl-space-6); flex-wrap: wrap; min-height: auto;">
                        <div>
                            <div style="font-size: var(--frl-font-size-xs); font-weight: 700; text-transform: uppercase; letter-spacing: 0.10em; color: var(--frl-accent); margin-bottom: var(--frl-space-2);">
                                <?php esc_html_e('Built by JSS Web Solutions', 'recognition'); ?>
                            </div>
                            <h3 style="font-size: var(--frl-font-size-2xl); font-weight: 700; color: var(--frl-text-primary); margin: 0 0 var(--frl-space-1); letter-spacing: -0.02em;">
                                <?php esc_html_e('Need help managing biometric users?', 'recognition'); ?>
                            </h3>
                            <p style="font-size: var(--frl-font-size-sm); color: var(--frl-text-secondary); margin: 0; max-width: 640px;">
                                <?php esc_html_e('From bulk enrolment flows to custom SSO, JSS Web Solutions is the team behind Recognition &mdash; and we build for the long haul.', 'recognition'); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: var(--frl-space-3); flex-wrap: wrap;">
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=users-page-strip&utm_campaign=hire-dev" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                                <?php esc_html_e('Hire a Developer', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/?utm_source=frl-plugin&utm_medium=users-page-strip&utm_campaign=custom-plugin" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-secondary">
                                <?php esc_html_e('Custom Plugin &rarr;', 'recognition'); ?>
                            </a>
                            <a href="https://jsswebsolutions.com/contact/?utm_source=frl-plugin&utm_medium=users-page-strip&utm_campaign=contact" target="_blank" rel="noopener noreferrer" class="frl-btn frl-btn-ghost">
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
<?php // Delete-faces button is bound by FRL_Admin::enqueue_admin_assets() (admin.js â†’ FRLAdmin.deleteUserFaces). ?>
