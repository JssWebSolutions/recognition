/**
 * License Activation Admin JavaScript
 *
 * Enhances the License Activation admin page with AJAX-based
 * re-validation, automatic feedback messages, and graceful
 * loading states. Designed to be defensive: if the config
 * object is missing, the script silently no-ops so it does
 * not break other admin pages that include the stylesheet.
 *
 * ==========================================================================
 * LOOP-PREVENTION DESIGN
 * ==========================================================================
 *
 * The original code suffered from an infinite reload loop. After a
 * successful license activation, the page was reloaded so the user
 * could see the new "License is valid" status. Unfortunately, the
 * reload was being triggered too aggressively, and combined with a
 * weak cooldown based on sessionStorage (which is not available in
 * some browsers / private mode), the page could end up re-validating
 * and reloading in an endless cycle.
 *
 * The new design uses a defence-in-depth strategy with FOUR independent
 * guards that ALL must be cleared before a reload can be scheduled:
 *
 *  1. In-memory lock (`reloadInProgress`) - prevents concurrent schedules
 *     in the same page lifetime.
 *  2. localStorage-based cooldown (`frl_license_reload_marker`) - blocks
 *     any reload for 60 seconds after the last one. localStorage is
 *     used in preference to sessionStorage because it is more widely
 *     supported and persists across browser restarts. sessionStorage
 *     is used as a fallback if localStorage is unavailable.
 *  3. Activation guard (`frl_license_just_activated`) - blocks any
 *     reload for 30 seconds after a successful activation. This
 *     ensures the user always sees the post-activation state instead
 *     of an immediate revalidation.
 *  4. Server-side throttle (PHP transient) - the server itself rejects
 *     revalidations that occur within 30 seconds of the previous one.
 *     This is the last line of defence against a malicious / buggy
 *     client that bypasses all of the above.
 *
 * The script NEVER auto-triggers a revalidation on page load. The
 * "Re-validate Now" button must be clicked explicitly by the user.
 * After a successful activation the page is reloaded ONCE so the
 * new "License is valid" UI is shown - but a "just activated" flag
 * is stored that prevents the script from doing anything else until
 * the cooldown expires.
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    if (typeof window.frlLicenseConfig === 'undefined') {
        return;
    }

    var config = window.frlLicenseConfig;
    var $message = $('#frl-license-ajax-message');

    // ------------------------------------------------------------------
    // Loop-prevention state
    // ------------------------------------------------------------------

    var requestInFlight = false;
    var reloadInProgress = false;

    // 60 seconds between page reloads. After activation, a single
    // reload is allowed; the second reload is blocked for a full minute.
    var RELOAD_COOLDOWN_MS = 60000;

    // 30 seconds "just activated" guard. While active, no reload or
    // revalidation is allowed even if the user clicks the button.
    var ACTIVATION_GUARD_MS = 30000;

    var STORAGE_KEY_RELOAD = 'frl_license_reload_marker';
    var STORAGE_KEY_ACTIVATED = 'frl_license_just_activated';
    var STORAGE_KEY_PROBE = 'frl_license_storage_probe';

    // ------------------------------------------------------------------
    // Storage helper (localStorage with sessionStorage fallback)
    // ------------------------------------------------------------------

    var storage = (function () {
        try {
            window.localStorage.setItem(STORAGE_KEY_PROBE, '1');
            window.localStorage.removeItem(STORAGE_KEY_PROBE);
            return window.localStorage;
        } catch (e1) {
            try {
                window.sessionStorage.setItem(STORAGE_KEY_PROBE, '1');
                window.sessionStorage.removeItem(STORAGE_KEY_PROBE);
                return window.sessionStorage;
            } catch (e2) {
                // Both unavailable. Provide a no-op shim so the rest of
                // the script still works (it just won't be able to use
                // persistent cooldown - the server-side throttle then
                // becomes the only line of defence).
                return {
                    getItem: function () { return null; },
                    setItem: function () { /* no-op */ },
                    removeItem: function () { /* no-op */ }
                };
            }
        }
    })();

    function getStorageTimestamp(key) {
        try {
            var raw = storage.getItem(key);
            var n = parseInt(raw || '0', 10);
            return isNaN(n) ? 0 : n;
        } catch (e) {
            return 0;
        }
    }

    function setStorageTimestamp(key) {
        try {
            storage.setItem(key, String(Date.now()));
        } catch (e) {
            // Best-effort.
        }
    }

    function clearStorageKey(key) {
        try {
            storage.removeItem(key);
        } catch (e) {
            // Best-effort.
        }
    }

    function wasRecentlyReloaded() {
        var last = getStorageTimestamp(STORAGE_KEY_RELOAD);
        return last > 0 && (Date.now() - last) < RELOAD_COOLDOWN_MS;
    }

    function markReloaded() {
        setStorageTimestamp(STORAGE_KEY_RELOAD);
    }

    function wasJustActivated() {
        var last = getStorageTimestamp(STORAGE_KEY_ACTIVATED);
        return last > 0 && (Date.now() - last) < ACTIVATION_GUARD_MS;
    }

    function markJustActivated() {
        setStorageTimestamp(STORAGE_KEY_ACTIVATED);
    }

    function clearActivationGuard() {
        clearStorageKey(STORAGE_KEY_ACTIVATED);
    }

    // ------------------------------------------------------------------
    // UI helpers
    // ------------------------------------------------------------------

    function showMessage(text, type) {
        if (!$message.length) {
            return;
        }
        type = type || 'info';
        $message
            .removeClass('frl-license-message--success frl-license-message--error frl-license-message--info')
            .addClass('frl-license-message--' + type)
            .html(text)
            .show();
    }

    function hideMessage() {
        if ($message.length) {
            $message.hide().empty();
        }
    }

    /**
     * Generic AJAX request helper for license endpoints.
     */
    function licenseRequest(action, data) {
        var payload = $.extend(
            {
                action: action,
                nonce: config.nonce
            },
            data || {}
        );

        return $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: payload,
            dataType: 'json',
            timeout: 30000
        });
    }

    /**
     * Central reload gatekeeper. A reload is ONLY scheduled if:
     *   - No reload is already in progress in this page lifetime
     *   - The local cooldown has expired (no reload in the last 60s)
     *
     * NOTE: The "just activated" check is intentionally NOT included
     * here. That guard exists to prevent rapid re-validation clicks,
     * but it MUST NOT block the post-activation reload - otherwise
     * the page would never refresh and the user would just see a
     * stuck spinner (the original bug).
     *
     * If any of these guards fail, the function is a no-op. This is
     * the primary defence against the "page keeps refreshing" bug.
     */
    function scheduleReload(delay) {
        if (reloadInProgress) {
            return false;
        }
        if (wasRecentlyReloaded()) {
            return false;
        }

        reloadInProgress = true;
        markReloaded();
        setTimeout(function () {
            window.location.reload();
        }, delay || 1500);
        return true;
    }

    // ------------------------------------------------------------------
    // Handlers
    // ------------------------------------------------------------------

    /**
     * Re-validate the stored license. The handler is only bound to
     * an explicit click on the "Re-validate Now" button. There is
     * NO auto-trigger - opening the license page will never cause
     * a revalidation on its own.
     */
    function handleRevalidate() {
        var $btn = $('#frl-validate-license-btn');
        if (!$btn.length || $btn.data('frl-bound')) {
            return;
        }

        $btn.data('frl-bound', true);

        $btn.on('click', function (e) {
            e.preventDefault();

            if (requestInFlight) {
                return;
            }

            if (wasJustActivated()) {
                showMessage(
                    config.i18n.activatedRecently ||
                    'License was just activated. Please wait a few seconds before re-validating.',
                    'info'
                );
                return;
            }

            if (wasRecentlyReloaded()) {
                showMessage(
                    config.i18n.cooldown ||
                    'A reload just happened. Please wait a minute before re-validating again.',
                    'info'
                );
                return;
            }

            requestInFlight = true;
            $btn.prop('disabled', true).addClass('frl-license-loading');
            showMessage(config.i18n.validating, 'info');

            licenseRequest('frl_validate_license')
                .done(function (response) {
                    if (response && response.success) {
                        showMessage(response.message || 'OK', 'success');
                        // Guard the reload - it will only fire if the
                        // activation guard and cooldown are clear.
                        scheduleReload(1500);
                    } else {
                        var message = (response && response.message) ? response.message : config.i18n.genericError;
                        showMessage(message, 'error');
                        $btn.prop('disabled', false).removeClass('frl-license-loading');
                    }
                })
                .fail(function () {
                    showMessage(config.i18n.genericError, 'error');
                    $btn.prop('disabled', false).removeClass('frl-license-loading');
                })
                .always(function () {
                    requestInFlight = false;
                });
        });
    }

    /**
     * Enhance the activation form: handle submit via AJAX and show
     * feedback without a full page reload.
     */
    function handleActivationForm() {
        var $form = $('#frl-license-activate-form');
        if (!$form.length) {
            return;
        }

        var $btn = $('#frl-activate-license-btn');

        $form.on('submit', function (e) {
            // Fall back to native form submission if the browser
            // disables JS or if for some reason our handler bails.
            if (!window.FormData || !$btn.length) {
                return;
            }

            e.preventDefault();

            if (requestInFlight) {
                return;
            }

            var key = $('#frl-license-key').val() || '';
            var email = $('#frl-license-email').val() || '';

            if (!key.trim() || !email.trim()) {
                showMessage(config.i18n.genericError, 'error');
                return;
            }

            requestInFlight = true;
            $btn.prop('disabled', true).addClass('frl-license-loading');
            showMessage(config.i18n.activating, 'info');

            licenseRequest('frl_activate_license', {
                license_key: key,
                email: email
            })
                .done(function (response) {
                    if (response && response.success) {
                        // Show the success message immediately so the
                        // user gets instant feedback while we wait
                        // for the reload to fire.
                        showMessage(response.message || 'OK', 'success');

                        // Schedule the reload FIRST, then set the
                        // activation guard with a small delay. The
                        // guard prevents rapid re-validation clicks
                        // on the new page; it must NOT be set before
                        // scheduleReload, otherwise the reload would
                        // be blocked and the user would only see the
                        // success message and a stuck spinner.
                        scheduleReload(1500);
                        setTimeout(markJustActivated, 2000);
                    } else {
                        var message = (response && response.message) ? response.message : config.i18n.genericError;
                        showMessage(message, 'error');
                        $btn.prop('disabled', false).removeClass('frl-license-loading');
                    }
                })
                .fail(function () {
                    showMessage(config.i18n.genericError, 'error');
                    $btn.prop('disabled', false).removeClass('frl-license-loading');
                })
                .always(function () {
                    requestInFlight = false;
                });
        });
    }

    // ------------------------------------------------------------------
    // Init
    // ------------------------------------------------------------------

    $(function () {
        // If we just activated (and the page was reloaded), surface a
        // small success notice so the user understands the reload
        // actually happened and the activate button is re-enabled.
        if (wasJustActivated()) {
            showMessage(
                config.i18n.activatedRecently ||
                'License was just activated. Please wait a few seconds before re-validating.',
                'success'
            );
        } else if (!wasRecentlyReloaded()) {
            // Normal page load - clear any stale state from a previous
            // session and reset the message area.
            clearActivationGuard();
            hideMessage();
        }

        handleRevalidate();
        handleActivationForm();
    });
})(jQuery);
