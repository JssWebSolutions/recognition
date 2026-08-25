<?php
/**
 * Feature Gate
 *
 * Centralised helper that other parts of the plugin can use to
 * determine whether a feature is available in the current
 * licensing context. Wraps FRL_License_Manager and provides
 * static helpers for easy access from templates, AJAX handlers,
 * and REST routes.
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Feature_Gate
 *
 * @since 1.0.0
 */
class FRL_Feature_Gate {

    /**
     * Get the License Manager singleton.
     *
     * @since 1.0.0
     * @return FRL_License_Manager
     */
    protected static function manager() {
        return FRL_License_Manager::get_instance();
    }

    /**
     * Check if a feature is available to the current site.
     *
     * @since 1.0.0
     * @param string $feature Feature slug.
     * @return bool
     */
    public static function is_available($feature) {
        return self::manager()->is_feature_available($feature);
    }

    /**
     * Check if the current site has any active license.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function has_active_license() {
        return self::manager()->has_premium();
    }

    /**
     * Get the current license status string.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_status() {
        return self::manager()->get_status();
    }

    /**
     * Get the human-readable plan name.
     *
     * @since 1.0.0
     * @return string
     */
    public static function get_plan_name() {
        $data = self::manager()->get_license_data();
        return $data['plan'] ?: __('Free', 'recognition');
    }

    /**
     * Get the formatted expiry date, or empty string.
     *
     * @since 1.0.0
     * @param string $format PHP date format.
     * @return string
     */
    public static function get_expiry_date($format = '') {
        $data = self::manager()->get_license_data();
        if (empty($data['expires_at'])) {
            return '';
        }
        $ts = strtotime($data['expires_at']);
        if (!$ts) {
            return '';
        }
        if ('' === $format) {
            $format = get_option('date_format', 'F j, Y');
        }
        return wp_date($format, $ts);
    }

    /**
     * Get the formatted activation date, or empty string.
     *
     * @since 1.0.0
     * @param string $format PHP date format.
     * @return string
     */
    public static function get_activation_date($format = '') {
        $data = self::manager()->get_license_data();
        if (empty($data['activated_at'])) {
            return '';
        }
        $ts = strtotime($data['activated_at']);
        if (!$ts) {
            return '';
        }
        if ('' === $format) {
            $format = get_option('date_format', 'F j, Y');
        }
        return wp_date($format, $ts);
    }

    /**
     * Get the list of free features.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_free_features() {
        return FRL_License_Manager::get_free_features();
    }

    /**
     * Get the list of premium features.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_premium_features() {
        return FRL_License_Manager::get_premium_features();
    }

    /**
     * Convenience helper: gate a piece of code behind a feature.
     *
     * Returns true if the feature is available, false otherwise.
     * Designed to be used in conditionals.
     *
     * @since 1.0.0
     * @param string $feature Feature slug.
     * @return bool
     */
    public static function enabled($feature) {
        return self::is_available($feature);
    }
}
