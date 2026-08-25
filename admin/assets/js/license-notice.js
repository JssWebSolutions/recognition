/**
 * Global License Notice Dismiss Handler
 *
 * Tiny dedicated script that intercepts the click on the
 * `.frl-license-notice .notice-dismiss` "X" button so the dismissal
 * is persisted server-side (sets the `frl_license_notice_dismissed`
 * option) rather than just visually faded out.
 *
 * Why a separate file?
 * --------------------
 * The Recognition License notice is now a SINGLE GLOBAL admin
 * notice that can appear on EVERY wp-admin screen (Dashboard,
 * Posts, Plugins, etc.), not just the plugin's own pages. The
 * full `frl-admin` script bundle is only enqueued on the plugin's
 * own admin pages, so its `dismissLicenseNotice()` handler is not
 * available elsewhere. Without this dedicated script, the user
 * could click the "X" on a non-plugin page and WordPress core's
 * default `notice-dismiss` handler would simply fade the notice
 * out - the dismissal would not be persisted and the notice would
 * reappear on the next page load.
 *
 * The script is enqueued by
 * {@see FRL_License_Admin::enqueue_assets()} on every wp-admin
 * screen that an `administrator` can see, and is gated by the
 * presence of a dismissable license notice (a no-op when no
 * notice is rendered, so the cost on non-plugin pages is just
 * a single delegated event binding).
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    // No-op on screens where the localized config was not provided
    // (e.g. front-end pages, network admin outside the licence hook).
    if (typeof window.frlLicenseNoticeConfig === 'undefined') {
        return;
    }

    var config = window.frlLicenseNoticeConfig;

    /**
     * Dismiss the global "Recognition" license notice.
     *
     * WordPress core's `common.js` ships a generic
     * `$(document).on('click', '.notice-dismiss', ...)` handler
     * that calls `e.preventDefault()` and visually fades the
     * notice out. The dismissal is NOT persisted server-side, so
     * on the next page load the notice reappears. This handler
     * runs in the same delegation chain and uses
     * `e.stopImmediatePropagation()` to override the default
     * behaviour for our specific notice: it stops the browser
     * from following the URL, sends an AJAX request that sets
     * the `frl_license_notice_dismissed` option, and only removes
     * the notice from the DOM once the server confirms the option
     * was saved.
     *
     * @since 1.0.0
     * @param {Event} e Click event.
     */
    function dismissLicenseNotice(e) {
        var $btn = $(e.target).closest('.frl-license-notice .notice-dismiss');
        if (!$btn.length) {
            return;
        }

        // Prevent the browser from following the dismiss URL and
        // stop WP core's generic .notice-dismiss handler from
        // running its own (non-persisted) fade-out logic.
        e.preventDefault();
        e.stopImmediatePropagation();

        var $notice = $btn.closest('.frl-license-notice');
        var dismissUrl = $btn.data('frl-dismiss-url') || '';

        // Extract the nonce from the dismiss URL so we can
        // re-send it via AJAX. Falls back to a regular
        // navigation if the URL is malformed or the nonce is
        // missing (e.g. caching stripped the data attribute).
        var nonce = '';
        if (dismissUrl) {
            try {
                var url = new URL(dismissUrl, window.location.origin);
                nonce = url.searchParams.get('_wpnonce') || '';
            } catch (err) {
                var match = /[?&]_wpnonce=([^&#]+)/.exec(dismissUrl);
                if (match) {
                    nonce = decodeURIComponent(match[1]);
                }
            }
        }

        if (!nonce || !config.ajaxUrl) {
            // Cannot AJAX - let the browser follow the link as
            // a fallback. The server will set the option and
            // redirect back to the same page.
            if (dismissUrl) {
                window.location.href = dismissUrl;
            }
            return;
        }

        // Disable the button while the request is in flight so
        // the user cannot double-click.
        $btn.prop('disabled', true);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'frl_dismiss_license_notice',
                nonce: nonce
            },
            success: function (response) {
                if (response && response.success) {
                    $notice.fadeOut(150, function () {
                        $(this).remove();
                    });
                } else {
                    // Server rejected the request - re-enable
                    // the button so the user can retry.
                    $btn.prop('disabled', false);
                }
            },
            error: function () {
                // Network / server error - re-enable the
                // button so the user can retry.
                $btn.prop('disabled', false);
            }
        });
    }

    // Use delegated event handling so dynamically-injected notices
    // (e.g. after an AJAX refresh) are also covered.
    $(document).on('click', '.frl-license-notice .notice-dismiss', dismissLicenseNotice);
})(jQuery);
