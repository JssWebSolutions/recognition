<?php
/**
 * User Dashboard Template
 *
 * @package Face_Recognition_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$user = wp_get_current_user();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Recognition Dashboard', 'recognition'); ?></title>
    <?php
    if ( function_exists( 'frl' ) ) {
        frl()->enqueue_dashboard_assets();
    }
    wp_head();
    ?>
</head>
<body class="frl-dashboard">
    <div class="frl-dashboard-container">
        <header class="frl-dashboard-header">
            <h1><?php esc_html_e('Recognition Dashboard', 'recognition'); ?></h1>
            <p><?php
                /* translators: %s: user display name. */
                printf( esc_html__( 'Welcome, %s', 'recognition' ), esc_html( $user->display_name ) );
                ?></p>
        </header>

        <div class="frl-dashboard-content">
            <div class="frl-dashboard-section">
                <h2><?php esc_html_e('Face Profiles', 'recognition'); ?></h2>
                <div id="frl-faces-container">
                    <p><?php esc_html_e('Loading...', 'recognition'); ?></p>
                </div>
                <button type="button" class="button button-primary" id="frl-enroll-btn">
                    <?php esc_html_e('Enroll New Face', 'recognition'); ?>
                </button>
            </div>

            <div class="frl-dashboard-section">
                <h2><?php esc_html_e('Authentication History', 'recognition'); ?></h2>
                <div id="frl-logs-container">
                    <p><?php esc_html_e('Loading...', 'recognition'); ?></p>
                </div>
            </div>

            <div class="frl-dashboard-section">
                <h2><?php esc_html_e('Export Data', 'recognition'); ?></h2>
                <p><?php esc_html_e('Download your biometric data as a JSON file.', 'recognition'); ?></p>
                <button type="button" class="button" id="frl-export-btn">
                    <?php esc_html_e('Export My Data', 'recognition'); ?>
                </button>
            </div>
        </div>

        <footer class="frl-dashboard-footer">
            <a href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Back to Dashboard', 'recognition'); ?></a>
            <a href="<?php echo esc_url(wp_logout_url()); ?>"><?php esc_html_e('Logout', 'recognition'); ?></a>
        </footer>
    </div>

    <!-- Enrollment Modal -->
    <div id="frl-enroll-modal" class="frl-modal" style="display: none;">
        <div class="frl-modal-content">
            <span class="frl-close">&times;</span>
            <h2><?php esc_html_e('Enroll Face', 'recognition'); ?></h2>
            <div id="frl-enroll-video-container">
                <video id="frl-enroll-video" autoplay playsinline></video>
                <canvas id="frl-enroll-canvas"></canvas>
            </div>
            <div id="frl-enroll-status" class="frl-status"></div>
            <div id="frl-enroll-instructions" class="frl-instructions"></div>
            <div class="frl-enroll-progress">
                <div id="frl-enroll-progress-bar"></div>
            </div>
        </div>
    </div>

    <?php
    // Ensure face-api.js + frl-public.js are enqueued properly via WP, not
    // hard-coded <script> tags (fixes C-1 + C-6: a previous version loaded
    // `admin/assets/js/face-api.min.js` which does not exist, and called
    // `FRLDashboard.init()` which is not part of the public bundle).
    if ( function_exists( 'frl' ) ) {
        frl()->enqueue_dashboard_assets();
    }
    wp_footer();
    ?>
</body>
</html>
