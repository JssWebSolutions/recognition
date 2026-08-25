<?php
/**
 * Registration Page Template with Face Enrollment
 * Redesigned to match the Face Login admin dashboard UI/UX.
 *
 * @package Face_Recognition_Login
 */

// Define ABSPATH if not defined (for standalone loading).
//
// IMPORTANT: Do NOT try to require wp-load.php from a hard-coded relative
// path here. The four-`..` walk assumes the plugin lives at
// wp-content/plugins/recognition/recognition/public/templates
// and breaks silently on any non-default install path (Composer installs,
// symlinked plugins, custom WP_PLUGIN_DIR, etc.). It is also flagged by
// Plugin Check as `PCP: Directly loading wp-load.php`.
//
// Instead, this template is rendered exclusively via
// `Face_Recognition_Login::render_enrollment_page()`, which is hooked into
// the WordPress `init` action. By the time this file is included, ABSPATH
// is guaranteed to be defined by core. If a future code path ever reaches
// this file without ABSPATH defined, bail with an explicit error rather
// than guessing at the wp-load.php location.
if (!defined('ABSPATH')) {
    exit;
}

// Get user ID from URL parameter (set during registration redirect)
$user_id  = isset($_GET['enroll_user']) ? intval(wp_unslash($_GET['enroll_user'])) : get_current_user_id();
$nonce    = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
$is_from_registration = isset($_GET['registration']) && sanitize_text_field(wp_unslash($_GET['registration'])) === '1';

// Verify nonce if provided
if ($user_id && !wp_verify_nonce($nonce, 'frl_enroll_' . $user_id)) {
    // Invalid nonce, try to get logged in user
    $user_id = get_current_user_id();
}

$user = $user_id ? get_user_by('id', $user_id) : null;

// If no user, try to get the logged in user
if (!$user) {
    $user_id = get_current_user_id();
    $user    = $user_id ? get_user_by('id', $user_id) : null;
}

// If still no user, redirect to login
if (!$user) {
    wp_safe_redirect(wp_login_url());
    exit;
}

// Get plugin URL
$plugin_url = defined('FRL_PLUGIN_URL') ? FRL_PLUGIN_URL : plugin_dir_url(dirname(__FILE__) . '/../../recognition.php');

// Check if user already has enrolled faces
$database        = new FRL_Database();
$existing_faces  = $database->get_face_descriptors($user_id, false);
$has_enrolled_face = !empty($existing_faces);
$face_count      = is_array($existing_faces) ? count($existing_faces) : 0;

// If user just enrolled (has cookie indicating enrollment just completed), redirect to admin
$just_enrolled = isset($_COOKIE['frl_just_enrolled']) && $_COOKIE['frl_just_enrolled'] === '1';
if ($just_enrolled && $has_enrolled_face) {
    // Clear the cookie
    setcookie('frl_just_enrolled', '', time() - 3600, '/');
    // Redirect to WordPress admin
    wp_safe_redirect(admin_url());
    exit;
}

// User role for badge
$user_role = '';
if (is_object($user) && !empty($user->roles) && is_array($user->roles)) {
    $user_role = ucfirst(str_replace('_', ' ', $user->roles[0]));
}
$is_admin_user = $user && user_can($user, 'manage_options');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Face Enrollment - Recognition', 'recognition'); ?></title>
    <?php wp_head(); ?>
</head>
<body class="frl-registration-page" data-frl-theme="light">
    <div class="frl-app" id="frl-app">
        <!-- Sidebar -->
        <aside class="frl-sidebar" id="frl-sidebar">
            <div class="frl-sidebar-header">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="frl-sidebar-logo">
                    <div class="frl-sidebar-logo-icon">
                        <img src="<?php echo esc_url($plugin_url . 'admin/assets/images/frl-icon.svg'); ?>" alt="<?php esc_attr_e('Recognition', 'recognition'); ?>" class="frl-sidebar-logo-img" />
                    </div>
                    <span class="frl-sidebar-logo-text">Recognition</span>
                </a>
            </div>

            <nav class="frl-sidebar-nav">
                <div class="frl-nav-section">
                    <div class="frl-nav-section-title"><?php esc_html_e('Welcome', 'recognition'); ?></div>
                    <ul class="frl-nav-list">
                        <li class="frl-nav-item">
                            <a href="#" class="frl-nav-link active">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <line x1="19" x2="19" y1="8" y2="14"/>
                                    <line x1="22" x2="16" y1="11" y2="11"/>
                                </svg>
                                <span class="frl-nav-link-text"><?php esc_html_e('Enroll Face', 'recognition'); ?></span>
                            </a>
                        </li>
                        <li class="frl-nav-item">
                            <a href="<?php echo esc_url(admin_url()); ?>" class="frl-nav-link">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="7" height="9" x="3" y="3" rx="1"/>
                                    <rect width="7" height="5" x="14" y="3" rx="1"/>
                                    <rect width="7" height="9" x="14" y="12" rx="1"/>
                                    <rect width="7" height="5" x="3" y="16" rx="1"/>
                                </svg>
                                <span class="frl-nav-link-text"><?php esc_html_e('Dashboard', 'recognition'); ?></span>
                            </a>
                        </li>
                        <li class="frl-nav-item">
                            <a href="<?php echo esc_url(add_query_arg('page', 'frl-license', admin_url('admin.php'))); ?>" class="frl-nav-link">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="9" x2="15" y1="13" y2="13"/>
                                    <line x1="9" x2="15" y1="17" y2="17"/>
                                </svg>
                                <span class="frl-nav-link-text"><?php esc_html_e('License', 'recognition'); ?></span>
                            </a>
                        </li>
                        <li class="frl-nav-item">
                            <a href="<?php echo esc_url(wp_logout_url()); ?>" class="frl-nav-link">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                    <polyline points="16 17 21 12 16 7"/>
                                    <line x1="21" x2="9" y1="12" y2="12"/>
                                </svg>
                                <span class="frl-nav-link-text"><?php esc_html_e('Logout', 'recognition'); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="frl-sidebar-footer">
                <button type="button" class="frl-sidebar-toggle" id="frl-toggle-sidebar" aria-label="<?php esc_attr_e('Toggle sidebar', 'recognition'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m11 17-5-5 5-5"/>
                        <path d="m18 17-5-5 5-5"/>
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
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
                <span class="frl-header-title">Recognition</span>
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
                    <h1 class="frl-header-title"><?php esc_html_e('Face Enrollment', 'recognition'); ?></h1>
                    <?php if ($is_from_registration): ?>
                        <span class="frl-status-badge success">
                            <span class="frl-status-dot"></span>
                            <?php esc_html_e('Account Created', 'recognition'); ?>
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
                <?php if ($is_from_registration): ?>
                    <!-- Welcome Alert (post-registration) -->
                    <div class="frl-alert frl-alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <div class="frl-alert-content">
                            <div class="frl-alert-title"><?php esc_html_e('Welcome aboard!', 'recognition'); ?></div>
                            <p>
                                <?php
                                if ($user) {
                                    printf(
                                        /* translators: %s: user's display name */
                                        esc_html__('Your account %s has been created successfully. A password-setup email has been sent to your inbox. You can now enroll your face to enable passwordless login.', 'recognition'),
                                        '<strong>' . esc_html($user->display_name) . '</strong>'
                                    );
                                } else {
                                    esc_html_e('Your account has been created successfully. You can now enroll your face to enable passwordless login.', 'recognition');
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($has_enrolled_face): ?>
                    <!-- Already enrolled alert -->
                    <div class="frl-alert frl-alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M8 12l3 3 5-6"/>
                        </svg>
                        <div class="frl-alert-content">
                            <div class="frl-alert-title"><?php esc_html_e('Face already enrolled', 'recognition'); ?></div>
                            <p><?php esc_html_e('You can log in with your face any time. Redirecting you to your dashboard in a moment.', 'recognition'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- KPI Cards -->
                <div class="frl-kpi-grid">
                    <div class="frl-kpi-card">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Account Status', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-success-bg); color: var(--frl-success);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                        </div>
                        <div class="frl-kpi-value"><?php esc_html_e('Active', 'recognition'); ?></div>
                        <div class="frl-kpi-change positive">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg>
                            <?php
                            if ($user) {
                                echo esc_html($user->user_login);
                            }
                            ?>
                        </div>
                    </div>

                    <div class="frl-kpi-card">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Face Profiles', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-info-bg); color: var(--frl-info);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                        </div>
                        <div class="frl-kpi-value"><?php echo intval($face_count); ?></div>
<div class="frl-kpi-change">
                            <?php
                            if ($face_count > 0) {
                                esc_html_e('Enrolled & ready', 'recognition');
                            } else {
                                esc_html_e('Not enrolled yet', 'recognition');
                            }
                            ?>
                        </div>
                    </div>

                    <div class="frl-kpi-card">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Login Method', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-accent-subtle); color: var(--frl-accent);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="frl-kpi-value"><?php esc_html_e('Face + Password', 'recognition'); ?></div>
                        <div class="frl-kpi-change">
                            <?php esc_html_e('Both methods available', 'recognition'); ?>
                        </div>
                    </div>

                    <div class="frl-kpi-card">
                        <div class="frl-kpi-header">
                            <span class="frl-kpi-label"><?php esc_html_e('Role', 'recognition'); ?></span>
                            <div class="frl-kpi-icon" style="background: var(--frl-warning-bg); color: var(--frl-warning);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                        </div>
                        <div class="frl-kpi-value"><?php echo esc_html($user_role ? $user_role : __('Member', 'recognition')); ?></div>
                        <div class="frl-kpi-change">
                            <?php
                            if ($is_admin_user) {
                                esc_html_e('Full site access', 'recognition');
                            } else {
                                esc_html_e('Standard access', 'recognition');
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Action Card -->
                <div class="frl-card">
                    <div class="frl-card-header">
                        <div>
                            <h3 class="frl-card-title"><?php esc_html_e('Enroll Your Face', 'recognition'); ?></h3>
                            <p class="frl-card-subtitle"><?php esc_html_e('Enable secure, passwordless login in under a minute', 'recognition'); ?></p>
                        </div>
                    </div>
                    <div class="frl-card-body">
                        <div class="frl-enroll-steps">
                            <div class="frl-enroll-step">
                                <div class="frl-enroll-step-num">1</div>
                                <div class="frl-enroll-step-content">
                                    <div class="frl-enroll-step-title"><?php esc_html_e('Allow camera access', 'recognition'); ?></div>
                                    <div class="frl-enroll-step-desc"><?php esc_html_e('We will ask for permission to use your camera. Images are processed locally in your browser.', 'recognition'); ?></div>
                                </div>
                            </div>
                            <div class="frl-enroll-step">
                                <div class="frl-enroll-step-num">2</div>
                                <div class="frl-enroll-step-content">
                                    <div class="frl-enroll-step-title"><?php esc_html_e('Position your face', 'recognition'); ?></div>
                                    <div class="frl-enroll-step-desc"><?php esc_html_e('Look at the camera, ensure good lighting, and slowly turn your head to capture multiple angles.', 'recognition'); ?></div>
                                </div>
                            </div>
                            <div class="frl-enroll-step">
                                <div class="frl-enroll-step-num">3</div>
                                <div class="frl-enroll-step-content">
                                    <div class="frl-enroll-step-title"><?php esc_html_e('Done!', 'recognition'); ?></div>
                                    <div class="frl-enroll-step-desc"><?php esc_html_e('You can now log in instantly using your face. Your password is still available as a fallback.', 'recognition'); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="frl-enroll-actions">
                            <?php if ($has_enrolled_face): ?>
                                <p class="frl-redirect-countdown" id="frl-redirect-countdown">
                                    <?php esc_html_e('Redirecting to your dashboard in', 'recognition'); ?>
                                    <span id="frl-countdown-seconds">3</span>
                                    <?php esc_html_e('seconds...', 'recognition'); ?>
                                </p>
                                <div class="frl-button-group">
                                    <a href="<?php echo esc_url(admin_url()); ?>" class="frl-btn frl-btn-primary frl-btn-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m9 18 6-6-6-6"/>
                                        </svg>
                                        <?php esc_html_e('Go to Dashboard Now', 'recognition'); ?>
                                    </a>
                                    <button type="button" class="frl-btn frl-btn-secondary frl-btn-lg" id="frl-start-enrollment">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                                            <path d="M21 3v5h-5"/>
                                        </svg>
                                        <?php esc_html_e('Re-enroll Face', 'recognition'); ?>
                                    </button>
                                </div>
                            <?php elseif ($user_id): ?>
                                <div class="frl-button-group">
                                    <button type="button" class="frl-btn frl-btn-primary frl-btn-lg" id="frl-start-enrollment">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <?php esc_html_e('Enroll My Face', 'recognition'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="frl-btn frl-btn-secondary frl-btn-lg">
                                        <?php esc_html_e('Skip for Now', 'recognition'); ?>
                                    </a>
                                </div>
                                <p class="frl-enroll-help">
                                    <?php esc_html_e('You can always enroll your face later from your profile page.', 'recognition'); ?>
                                </p>
                            <?php else: ?>
                                <div class="frl-button-group">
                                    <a href="<?php echo esc_url(wp_login_url()); ?>" class="frl-btn frl-btn-primary frl-btn-lg">
                                        <?php esc_html_e('Go to Login', 'recognition'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Security note card -->
                <div class="frl-card">
                    <div class="frl-card-header">
                        <div>
                            <h3 class="frl-card-title"><?php esc_html_e('Privacy & Security', 'recognition'); ?></h3>
                            <p class="frl-card-subtitle"><?php esc_html_e('How we handle your biometric data', 'recognition'); ?></p>
                        </div>
                    </div>
                    <div class="frl-card-body">
                        <ul class="frl-feature-list">
                            <li class="frl-feature-item">
                                <div class="frl-feature-icon success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </div>
                                <div class="frl-feature-content">
                                    <div class="frl-feature-title"><?php esc_html_e('Encrypted at rest', 'recognition'); ?></div>
                                    <div class="frl-feature-desc"><?php esc_html_e('Your face data is stored as an encrypted mathematical descriptor - never as a photo.', 'recognition'); ?></div>
                                </div>
                            </li>
                            <li class="frl-feature-item">
                                <div class="frl-feature-icon success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </div>
                                <div class="frl-feature-content">
                                    <div class="frl-feature-title"><?php esc_html_e('Never shared', 'recognition'); ?></div>
                                    <div class="frl-feature-desc"><?php esc_html_e('Your data stays on this site. We do not transmit it to any third party.', 'recognition'); ?></div>
                                </div>
                            </li>
                            <li class="frl-feature-item">
                                <div class="frl-feature-icon success">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                        <path d="M3 3v5h5"/>
                                    </svg>
                                </div>
                                <div class="frl-feature-content">
                                    <div class="frl-feature-title"><?php esc_html_e('Removable any time', 'recognition'); ?></div>
                                    <div class="frl-feature-desc"><?php esc_html_e('You can delete your face profile at any time from your account settings.', 'recognition'); ?></div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Face Enrollment Modal -->
    <div id="frl-enroll-modal" class="frl-modal" style="display: none;">
        <div class="frl-modal-content frl-modal-large">
            <div class="frl-modal-header">
                <h2 class="frl-modal-title">
                    <span class="frl-modal-title-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </span>
                    <?php esc_html_e('Enroll Your Face', 'recognition'); ?>
                </h2>
                <button type="button" class="frl-close frl-enroll-close" aria-label="<?php esc_attr_e('Close', 'recognition'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="frl-modal-body">
                <div id="frl-enroll-video-container">
                    <video id="frl-enroll-video" autoplay playsinline></video>
                    <canvas id="frl-enroll-canvas"></canvas>
                    <div id="frl-face-overlay" class="frl-face-guide"></div>
                </div>
                <div id="frl-enroll-status" class="frl-status frl-status-info"></div>
                <div id="frl-enroll-instructions" class="frl-instructions">
                    <?php esc_html_e('Position your face within the circle and ensure good lighting', 'recognition'); ?>
                </div>
                <div class="frl-enroll-progress">
                    <div id="frl-enroll-progress-bar"></div>
                </div>
                <div id="frl-enroll-progress-text">0%</div>
            </div>
        </div>
    </div>

    <!-- Enrollment Complete Modal -->
    <div id="frl-complete-modal" class="frl-modal" style="display: none;">
        <div class="frl-modal-content">
            <div class="frl-modal-header">
                <h2 class="frl-modal-title">
                    <span class="frl-modal-title-icon" style="background: var(--frl-success-bg); color: var(--frl-success);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </span>
                    <?php esc_html_e('Face Enrolled Successfully!', 'recognition'); ?>
                </h2>
                <button type="button" class="frl-close frl-enroll-close" aria-label="<?php esc_attr_e('Close', 'recognition'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="frl-modal-body" style="text-align: center;">
                <p style="color: var(--frl-text-secondary); margin: 0 0 var(--frl-space-5);">
                    <?php esc_html_e('You can now log in using your face recognition. Your password remains available as a fallback.', 'recognition'); ?>
                </p>
                <div class="frl-button-group" style="justify-content: center;">
                    <a href="<?php echo esc_url(admin_url()); ?>" class="frl-btn frl-btn-primary frl-btn-lg">
                        <?php esc_html_e('Go to Dashboard', 'recognition'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
    <script>
        // Initialize frlConfig with proper values
        var frlConfig = window.frlConfig || {};
        frlConfig.isRegistrationPage = true;
        frlConfig.userId = <?php echo intval($user_id); ?>;
        frlConfig.registrationMode = true;
        frlConfig.ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
        frlConfig.nonce = '<?php echo esc_attr( wp_create_nonce( 'frl_nonce' ) ); ?>';
        frlConfig.modelsUrl = '<?php echo esc_url($plugin_url . 'public/models/'); ?>';
        frlConfig.i18n = {
            initializing: '<?php esc_attr_e('Initializing camera...', 'recognition'); ?>',
            cameraReady: '<?php esc_attr_e('Camera ready', 'recognition'); ?>',
            detectingFace: '<?php esc_attr_e('Detecting face...', 'recognition'); ?>',
            faceDetected: '<?php esc_attr_e('Face detected', 'recognition'); ?>',
            noFace: '<?php esc_attr_e('No face detected', 'recognition'); ?>',
            captureSamples: '<?php esc_attr_e('Capturing samples...', 'recognition'); ?>',
            processing: '<?php esc_attr_e('Processing...', 'recognition'); ?>',
            enrollmentComplete: '<?php esc_attr_e('Face enrolled successfully!', 'recognition'); ?>',
            enrollmentFailed: '<?php esc_attr_e('Face enrollment failed', 'recognition'); ?>',
            cameraError: '<?php esc_attr_e('Camera error', 'recognition'); ?>',
            permissionDenied: '<?php esc_attr_e('Camera permission denied', 'recognition'); ?>',
            modelsError: '<?php esc_attr_e('Failed to load face detection models', 'recognition'); ?>',
            // Premium camera overlay strings
            cameraOn: '<?php esc_attr_e('Camera On', 'recognition'); ?>',
            cameraAdjusting: '<?php esc_attr_e('Adjusting…', 'recognition'); ?>',
            switchingCamera: '<?php esc_attr_e('Switching camera…', 'recognition'); ?>',
            switchCamera: '<?php esc_attr_e('Switch camera', 'recognition'); ?>',
            positionFace: '<?php esc_attr_e('Position your face here', 'recognition'); ?>',
            trustHeading: '<?php esc_attr_e('Why this is safe', 'recognition'); ?>',
            trustSecure: '<?php esc_attr_e('Secure', 'recognition'); ?>',
            trustPrivate: '<?php esc_attr_e('Private', 'recognition'); ?>',
            trustFast: '<?php esc_attr_e('Fast', 'recognition'); ?>',
            trustPasswordless: '<?php esc_attr_e('Passwordless', 'recognition'); ?>'
        };
    </script>
    <script>
        jQuery(document).ready(function($) {
            // Handle start enrollment button
            $(document).on('click', '#frl-start-enrollment', function() {
                $('#frl-enroll-modal').show();
                if (typeof FRL !== 'undefined' && FRL.initRegistrationEnrollment) {
                    FRL.initRegistrationEnrollment();
                }
            });

            // Handle close buttons
            $(document).on('click', '.frl-enroll-close', function() {
                $('#frl-enroll-modal').hide();
                $('#frl-complete-modal').hide();
                if (typeof FRL !== 'undefined' && FRL.stopVideo) {
                    FRL.stopVideo();
                }
            });

            // Sidebar toggle (desktop collapse)
            $(document).on('click', '#frl-toggle-sidebar', function() {
                $('#frl-sidebar').toggleClass('collapsed');
            });

            // Mobile menu
            $(document).on('click', '#frl-mobile-menu-btn', function() {
                $('#frl-sidebar').toggleClass('open');
            });

            // Theme toggle (both header buttons)
            $(document).on('click', '#frl-theme-toggle, #frl-theme-toggle-header', function() {
                var body = document.body;
                var current = body.getAttribute('data-frl-theme') || 'light';
                var next = current === 'light' ? 'dark' : 'light';
                body.setAttribute('data-frl-theme', next);
                try { localStorage.setItem('frl-theme', next); } catch (e) {}
                updateThemeIcons(next);
            });

            function updateThemeIcons(theme) {
                $('body').find('.frl-icon-sun, .frl-icon-moon').each(function() {
                    var isSun = $(this).hasClass('frl-icon-sun');
                    if ((theme === 'dark' && isSun) || (theme === 'light' && !isSun)) {
                        // already correct
                    }
                    if (isSun) {
                        $(this).toggle(theme === 'light');
                    } else {
                        $(this).toggle(theme === 'dark');
                    }
                });
            }

            // Restore saved theme
            try {
                var saved = localStorage.getItem('frl-theme');
                if (saved === 'dark' || saved === 'light') {
                    document.body.setAttribute('data-frl-theme', saved);
                    updateThemeIcons(saved);
                }
            } catch (e) {}

            // Already enrolled auto-redirect countdown
            <?php if ($has_enrolled_face && $is_from_registration): ?>
            (function() {
                var seconds = 3;
                var countdownEl = document.getElementById('frl-countdown-seconds');
                var interval = setInterval(function() {
                    seconds--;
                    if (countdownEl) countdownEl.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = '<?php echo esc_url(admin_url()); ?>';
                    }
                }, 1000);
            })();
            <?php endif; ?>
        });
    </script>
    <style>
        /* ==========================================================================
           Registration Page - Admin Dashboard Design System
           ========================================================================== */

        /* ----------------------------------------------------------------------
           GLOBAL SVG SIZING - Defensive defaults for every SVG on the page.
           Some WordPress themes set `svg { width: 100%; height: 100% }` or
           similar, which makes our icons blow up. We re-assert a sane default
           with high specificity so nothing in the page renders oversized.
           ---------------------------------------------------------------------- */
        .frl-registration-page svg {
            max-width: 100%;
            max-height: 100%;
        }
        .frl-registration-page .frl-sidebar-logo-icon svg { width: 20px; height: 20px; color: #fff; flex-shrink: 0; }
        .frl-registration-page .frl-nav-link svg            { width: 18px; height: 18px; flex-shrink: 0; }
        .frl-registration-page .frl-sidebar-toggle svg      { width: 18px; height: 18px; }
        .frl-registration-page .frl-mobile-menu-btn svg    { width: 22px; height: 22px; }
        .frl-registration-page .frl-theme-toggle svg        { width: 18px; height: 18px; }
        .frl-registration-page .frl-kpi-icon svg           { width: 20px; height: 20px; }
        .frl-registration-page .frl-feature-icon svg       { width: 20px; height: 20px; }
        .frl-registration-page .frl-modal-title-icon svg   { width: 18px; height: 18px; }
        .frl-registration-page .frl-close svg              { width: 18px; height: 18px; }
        .frl-registration-page .frl-alert svg              { width: 20px; height: 20px; flex-shrink: 0; }
        .frl-registration-page .frl-btn svg                { width: 18px; height: 18px; flex-shrink: 0; }
        .frl-registration-page .frl-btn-lg svg             { width: 18px; height: 18px; }

        .frl-registration-page {
            background: var(--frl-bg-secondary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            font-family: var(--frl-font-family);
            color: var(--frl-text-primary);
        }

        .frl-registration-page .frl-app {
            min-height: 100vh;
        }

        /* Sidebar top - since this is a standalone page, no WP admin bar */
        .frl-registration-page .frl-sidebar {
            top: 0;
        }

        /* Mobile header shows on small screens */
        @media (max-width: 768px) {
            .frl-registration-page .frl-mobile-header {
                display: flex;
            }
            .frl-registration-page .frl-sidebar {
                position: fixed;
                z-index: 1000;
                height: 100vh;
                transform: translateX(-100%);
            }
            .frl-registration-page .frl-sidebar.open {
                transform: translateX(0);
            }
            .frl-registration-page .frl-sidebar-toggle {
                display: none;
            }
        }

        /* Alert uses full width within content */
        .frl-content .frl-alert {
            display: flex;
            gap: var(--frl-space-3);
            padding: var(--frl-space-4) var(--frl-space-5);
            border: var(--frl-border);
            margin-bottom: var(--frl-space-5);
            align-items: flex-start;
        }
        .frl-content .frl-alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .frl-alert-success {
            background: var(--frl-success-bg);
            color: var(--frl-success);
        }
        .frl-alert-info {
            background: var(--frl-info-bg);
            color: var(--frl-info);
        }
        .frl-alert-warning {
            background: var(--frl-warning-bg);
            color: var(--frl-warning);
        }
        .frl-alert-error {
            background: var(--frl-error-bg);
            color: var(--frl-error);
        }
        .frl-alert-content {
            flex: 1;
        }
        .frl-alert-title {
            font-weight: var(--frl-font-weight-semibold);
            margin-bottom: var(--frl-space-1);
            color: inherit;
        }
        .frl-alert p {
            margin: 0;
            color: var(--frl-text-secondary);
            font-size: var(--frl-font-size-sm);
        }
        .frl-alert p strong {
            color: var(--frl-text-primary);
        }

        /* Enroll steps */
        .frl-enroll-steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--frl-space-4);
            margin-bottom: var(--frl-space-6);
        }
        @media (max-width: 768px) {
            .frl-enroll-steps {
                grid-template-columns: 1fr;
            }
        }
        .frl-enroll-step {
            display: flex;
            gap: var(--frl-space-3);
            padding: var(--frl-space-4);
            background: var(--frl-bg-secondary);
            border: var(--frl-border);
        }
        .frl-enroll-step-num {
            width: 28px;
            height: 28px;
            background: var(--frl-accent);
            color: var(--frl-text-inverted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--frl-font-weight-semibold);
            font-size: var(--frl-font-size-sm);
            flex-shrink: 0;
        }
        .frl-enroll-step-content {
            flex: 1;
            min-width: 0;
        }
        .frl-enroll-step-title {
            font-weight: var(--frl-font-weight-semibold);
            color: var(--frl-text-primary);
            font-size: var(--frl-font-size-base);
            margin-bottom: var(--frl-space-1);
        }
        .frl-enroll-step-desc {
            color: var(--frl-text-tertiary);
            font-size: var(--frl-font-size-sm);
            line-height: var(--frl-line-height-normal);
        }

        /* Actions */
        .frl-enroll-actions {
            padding-top: var(--frl-space-5);
            border-top: var(--frl-border);
        }
        .frl-button-group {
            display: flex;
            gap: var(--frl-space-3);
            flex-wrap: wrap;
        }
        .frl-enroll-help {
            margin: var(--frl-space-3) 0 0;
            color: var(--frl-text-tertiary);
            font-size: var(--frl-font-size-sm);
        }
        .frl-redirect-countdown {
            margin: 0 0 var(--frl-space-4);
            color: var(--frl-text-tertiary);
            font-size: var(--frl-font-size-sm);
        }
        .frl-redirect-countdown span {
            font-weight: var(--frl-font-weight-semibold);
            color: var(--frl-accent);
        }

        /* Feature list (privacy card) */
        .frl-feature-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .frl-feature-item {
            display: flex;
            gap: var(--frl-space-4);
            padding: var(--frl-space-3) 0;
            border-bottom: var(--frl-border);
        }
        .frl-feature-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .frl-feature-item:first-child {
            padding-top: 0;
        }
        .frl-feature-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .frl-feature-icon.success {
            background: var(--frl-success-bg);
            color: var(--frl-success);
        }
        .frl-feature-content {
            flex: 1;
        }
        .frl-feature-title {
            font-weight: var(--frl-font-weight-semibold);
            color: var(--frl-text-primary);
            margin-bottom: var(--frl-space-1);
        }
        .frl-feature-desc {
            color: var(--frl-text-tertiary);
            font-size: var(--frl-font-size-sm);
            line-height: var(--frl-line-height-normal);
        }

        /* Dark mode overrides for new components */
        [data-frl-theme="dark"] .frl-enroll-step {
            background: var(--frl-bg-tertiary);
        }
        [data-frl-theme="dark"] .frl-alert p strong {
            color: var(--frl-text-primary);
        }
    </style>
</body>
</html>
