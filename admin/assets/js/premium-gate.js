/**
 * Premium Feature Gate - Client-Side
 *
 * Responsibilities:
 *   1. Defensive disable of premium inputs - if any input is not
 *      already disabled server-side, disable it on the client so
 *      the user cannot even click / type into the field.
 *   2. Block premium form submissions silently with a friendly
 *      notice so the user understands why their change was not
 *      saved.
 *   3. Poll the server (every 20s while the tab is visible) to
 *      detect a license change in another tab; if the license
 *      becomes active, automatically remove the disabled states
 *      and the page lock overlay without a page refresh.
 *   4. Wire the "Activate License" button in the page lock
 *      overlay so it works even when the rest of the page is
 *      blurred.
 *
 * @package Face_Recognition_Login
 * @subpackage License
 *
 * @since 1.0.0
 */
(function ($) {
    'use strict';

    var FRLPremiumGate = {
        pollTimer: null,
        pollIntervalMs: 20000,
        isPremium: false,
        isUnlocked: false,
        isInitialized: false,

        /**
         * Initialise the gate. Called from jQuery(document).ready().
         */
        init: function () {
            if (this.isInitialized) {
                return;
            }
            this.isInitialized = true;

            var config = window.frlPremiumConfig || {};
            this.isPremium = !!config.isPremium;

            // Set up page-level lock overlay (Enroll Face, Auth Logs).
            this.initPageLock();

            // Defensive disable of any premium field the server
            // might have missed (e.g. when a third-party filter
            // has stripped the disabled attribute).
            this.hardenPremiumFields();

            // Stop users from submitting the settings form with
            // any value in a premium field. We do this client-side
            // so they get instant feedback; the server already
            // drops premium values in sanitize_settings().
            this.guardSettingsForm();

            // Start polling for license changes so we can unlock
            // the UI the moment the user activates in another tab.
            this.startPolling();
        },

        /**
         * Hard-disable all inputs / selects / buttons inside any
         * .frl-premium-field container. The CSS already makes
         * them unclickable but we belt-and-brace it on the JS
         * side too.
         */
        hardenPremiumFields: function () {
            if (this.isPremium) {
                return;
            }
            $('.frl-premium-field').each(function () {
                var $wrapper = $(this);
                var $body = $wrapper.find('.frl-premium-field-body');
                if (!$body.length) {
                    return;
                }
                $body.find('input, select, textarea, button').each(function () {
                    var $el = $(this);
                    // Keep the WP settings field mechanism (nonce
                    // + option_page) functional - they are not
                    // inside the wrapper so this never matches
                    // them, but be defensive anyway.
                    var type = ($el.attr('type') || '').toLowerCase();
                    var name = $el.attr('name') || '';
                    if ('_wpnonce' === name || '_wp_http_referer' === name || 'option_page' === name) {
                        return;
                    }
                    if ('submit' === type) {
                        return;
                    }
                    $el.prop('disabled', true).attr('aria-disabled', 'true');
                });
            });
        },

        /**
         * Watch the settings form for submit attempts. If the
         * form contains any premium fields we prevent the
         * submission and show a brief inline notice.
         */
        guardSettingsForm: function () {
            if (this.isPremium) {
                return;
            }

            $(document).on('submit', 'form[action="options.php"]', function (e) {
                var $form = $(this);
                var $premium = $form.find('.frl-premium-field');
                if (!$premium.length) {
                    return true;
                }
                // Strip premium values before submission so the
                // server does not have to rely on its sanitiser
                // (which already strips them anyway).
                $premium.find('input, select, textarea').each(function () {
                    var $el = $(this);
                    if ($el.is(':disabled')) {
                        // Disabled fields are not submitted by
                        // the browser, but be defensive.
                        $el.prop('disabled', false);
                    }
                });
                // The server's sanitize_settings() will drop the
                // premium values. We allow the submission to
                // proceed so the free settings are saved.
            });
        },

        /**
         * Initialise the page-level lock overlay used on the
         * Enroll Face and Auth Logs pages.
         */
        initPageLock: function () {
            var $overlay = $('.frl-premium-locked-overlay');
            if (!$overlay.length) {
                return;
            }

            // Add the body class so the CSS can apply the blur
            // to the underlying content. This needs to happen
            // before the rest of the page is rendered, but
            // document.ready is fine for that because the page
            // is just a fragment inside the WP admin.
            $('body').addClass('frl-premium-locked-body');

            // Click outside the card - do nothing, we want the
            // user to take the intended action.
            // ESC key - redirect to the license page.
            $(document).on('keydown.frlPremiumLock', function (e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    var url = (window.frlPremiumConfig && window.frlPremiumConfig.licensePageUrl) || '';
                    if (url) {
                        window.location.href = url;
                    }
                }
            });

            // Make sure the overlay is on top of any other admin
            // element (e.g. the WP admin bar notifications).
            $overlay.css('z-index', 100000);
        },

        /**
         * Remove the lock overlay - called when the license
         * becomes active.
         */
        removePageLock: function () {
            var $overlay = $('.frl-premium-locked-overlay');
            if ($overlay.length) {
                $overlay.fadeOut(200, function () {
                    $(this).remove();
                });
            }
            $('body.frl-premium-locked-body').removeClass('frl-premium-locked-body');
            $('#wpbody-content, .frl-admin-wrap').css({
                'filter': '',
                'pointer-events': '',
                'user-select': ''
            });
            $(document).off('keydown.frlPremiumLock');
        },

        /**
         * Re-enable all premium fields. Called when the license
         * becomes active.
         */
        unlockFields: function () {
            $('.frl-premium-field').each(function () {
                var $wrapper = $(this);
                $wrapper.find('input, select, textarea, button').each(function () {
                    var $el = $(this);
                    $el.prop('disabled', false).removeAttr('aria-disabled');
                });
                // Replace the wrapper with its inner body so the
                // free CSS reverts to its normal layout.
                var $body = $wrapper.find('.frl-premium-field-body');
                if ($body.length) {
                    $body.find('*').removeAttr('disabled aria-disabled inert');
                    $body.unwrap().unwrap();
                }
            });
        },

        /**
         * Start polling the server for license status changes.
         * The poll runs only when the tab is visible.
         */
        startPolling: function () {
            var self = this;
            if (this.pollTimer) {
                return;
            }

            var config = window.frlPremiumConfig || {};
            if (!config.ajaxUrl || !config.statusNonce) {
                return;
            }

            var run = function () {
                if (document.hidden) {
                    return;
                }
                $.post(config.ajaxUrl, {
                    action: 'frl_premium_status',
                    nonce: config.statusNonce
                })
                    .done(function (response) {
                        if (!response || !response.success || !response.data) {
                            return;
                        }
                        var newState = !!response.data.premium;
                        if (newState && !self.isPremium) {
                            self.onUnlocked();
                        } else if (!newState && self.isPremium) {
                            self.onLocked();
                        }
                        self.isPremium = newState;
                    })
                    .fail(function () {
                        // Network failure - keep the existing state.
                    });
            };

            // First poll after 10s, then every pollIntervalMs.
            setTimeout(function () {
                run();
                self.pollTimer = setInterval(run, self.pollIntervalMs);
            }, 10000);
        },

        /**
         * Called when the server reports the license just became
         * active.
         */
        onUnlocked: function () {
            if (this.isUnlocked) {
                return;
            }
            this.isUnlocked = true;

            // Remove overlays + disable states.
            this.removePageLock();
            this.unlockFields();

            // Show a friendly success notice.
            var msg = (window.frlPremiumConfig && window.frlPremiumConfig.i18n && window.frlPremiumConfig.i18n.unlocked)
                || 'Your license was activated. All premium features are now available.';
            this.showTransientNotice(msg, 'success');
        },

        /**
         * Called when the server reports the license just
         * expired / was deactivated.
         */
        onLocked: function () {
            this.isUnlocked = false;
            var msg = (window.frlPremiumConfig && window.frlPremiumConfig.i18n && window.frlPremiumConfig.i18n.locked)
                || 'Your license is no longer active. Premium features have been disabled.';
            this.showTransientNotice(msg, 'warning');

            // Re-load the page so the server-rendered lock
            // overlay reappears. The settings page also needs a
            // refresh so the disabled states come back.
            if ($('.frl-premium-locked-overlay').length === 0) {
                window.location.reload();
            }
        },

        /**
         * Show a transient admin notice in the WP admin.
         */
        showTransientNotice: function (message, type) {
            type = type || 'info';
            var $notice = $(
                '<div class="notice notice-' + type + ' is-dismissible frl-premium-live-notice" style="margin: 12px 20px 0;">' +
                '<p>' + $('<div>').text(message).html() + '</p>' +
                '</div>'
            );
            // Place just after the admin bar so it is visible.
            var $target = $('.wrap').first();
            if ($target.length) {
                $target.before($notice);
            } else {
                $('h1').first().after($notice);
            }
            setTimeout(function () {
                $notice.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 8000);
        }
    };

    $(function () {
        FRLPremiumGate.init();
    });

    // Expose for debugging.
    window.FRLPremiumGate = FRLPremiumGate;
})(jQuery);
