<?php
/**
 * Plugin options accessor with a per-request cache.
 *
 * Centralises all reads of the frl_settings option so the rest of
 * the plugin does not need to call get_option() directly. The
 * class maintains a static cache so repeated look-ups (e.g. inside
 * loops or shortcode rendering) do not hit the database more than
 * once per request.
 *
 * T2-15: Replaces dozens of repeated `get_option('frl_settings', [])`
 * calls scattered throughout the codebase with a single,
 * testable, filterable accessor.
 *
 * @package Face_Recognition_Login
 * @subpackage Options
 *
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FRL_Options
 *
 * @since 1.0.0
 */
class FRL_Options {

    /**
     * The option name used to store all plugin settings.
     *
     * @since 1.0.0
     * @var string
     */
    const OPTION_NAME = 'frl_settings';

    /**
     * Per-request cache of the decoded option.
     *
     * @since 1.0.0
     * @var array|null
     */
    private static $cache = null;

    /**
     * Track whether the cache has been populated for the current request.
     *
     * @since 1.0.0
     * @var bool
     */
    private static $loaded = false;

    /**
     * Get a single setting value.
     *
     * @since 1.0.0
     * @param string $key     Setting key.
     * @param mixed  $default Value to return when the key is missing.
     * @return mixed
     */
    public static function get($key, $default = null) {
        $settings = self::all();
        if (null === $key || '' === $key) {
            return $settings;
        }
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Get the entire settings array.
     *
     * The first call in a request loads the option from the database
     * and caches it; subsequent calls return the same array.
     *
     * @since 1.0.0
     * @return array
     */
    public static function all() {
        if (!self::$loaded) {
            $raw = get_option(self::OPTION_NAME, []);
            if (!is_array($raw)) {
                $raw = [];
            }

            /**
             * Filters the plugin settings array after loading it from the DB.
             *
             * @since 1.0.0
             * @param array $raw Decoded settings array.
             */
            $raw = apply_filters('frl_settings_all', $raw);

            self::$cache  = $raw;
            self::$loaded = true;
        }
        return self::$cache;
    }

    /**
     * Persist the entire settings array.
     *
     * The cache is refreshed in-process so subsequent `get()` calls
     * within the same request see the new values without an extra
     * DB round-trip.
     *
     * @since 1.0.0
     * @param array $settings Settings to save.
     * @return bool True on success, false on failure.
     */
    public static function save(array $settings) {
        /**
         * Filters the settings array immediately before it is persisted.
         *
         * @since 1.0.0
         * @param array $settings Settings being saved.
         */
        $settings = apply_filters('frl_settings_pre_save', $settings);

        $result = update_option(self::OPTION_NAME, $settings, false);
        if ($result) {
            self::$cache  = $settings;
            self::$loaded = true;
        }
        return (bool) $result;
    }

    /**
     * Update a single setting key, preserving the rest of the array.
     *
     * @since 1.0.0
     * @param string $key   Setting key.
     * @param mixed  $value New value.
     * @return bool
     */
    public static function set($key, $value) {
        $settings = self::all();
        $settings[(string) $key] = $value;
        return self::save($settings);
    }

    /**
     * Delete a single setting key.
     *
     * @since 1.0.0
     * @param string $key Setting key.
     * @return bool
     */
    public static function delete($key) {
        $settings = self::all();
        if (!array_key_exists((string) $key, $settings)) {
            return true;
        }
        unset($settings[(string) $key]);
        return self::save($settings);
    }

    /**
     * Drop the in-process cache.
     *
     * Useful in tests, after programmatic option changes, and in any
     * long-running process (WP-CLI) that needs a fresh read.
     *
     * @since 1.0.0
     * @return void
     */
    public static function flush() {
        self::$cache  = null;
        self::$loaded = false;
    }
}
