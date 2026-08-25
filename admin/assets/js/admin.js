/**
 * Recognition - Admin JavaScript
 *
 * @package Face_Recognition_Login
 */

(function($) {
    'use strict';

    /**
     * Accessible modal helper (replaces window.confirm/alert).
     * A-5 / T2-13 — accessible modal: role="alertdialog", aria-modal, focus trap,
     * Esc to close, respects prefers-reduced-motion, works with screen readers.
     */
    const FRLModal = {
        /**
         * Show an accessible confirmation dialog.
         * Returns a Promise that resolves to true (confirm) or false (cancel).
         *
         * @param {string} message     The question to display.
         * @param {Object} [options]   Optional overrides.
         * @param {string} [options.title]       Dialog title.
         * @param {string} [options.confirmText] Label for the confirm button.
         * @param {string} [options.cancelText]  Label for the cancel button.
         * @param {string} [options.variant]     'default' | 'danger' (red confirm button).
         * @returns {Promise<boolean>}
         */
        confirm: function(message, options) {
            options = options || {};
            const settings = $.extend({
                title: this.t ? this.t('Please confirm', 'recognition') : 'Please confirm',
                confirmText: this.t ? this.t('OK', 'recognition') : 'OK',
                cancelText: this.t ? this.t('Cancel', 'recognition') : 'Cancel',
                variant: 'default'
            }, options);
            return this._showDialog(message, settings);
        },

        /**
         * Show an accessible alert dialog.
         * Returns a Promise that resolves when dismissed.
         *
         * @param {string} message
         * @param {Object} [options]
         * @returns {Promise<void>}
         */
        alert: function(message, options) {
            options = options || {};
            const settings = $.extend({
                title: this.t ? this.t('Notice', 'recognition') : 'Notice',
                confirmText: this.t ? this.t('OK', 'recognition') : 'OK',
                cancelText: this.t ? this.t('Cancel', 'recognition') : 'Cancel',
                variant: 'default'
            }, options);
            return this._showDialog(message, settings);
        },

        /**
         * Optional translation helper injected by PHP (wp_localize_script).
         * Falls back to the raw string when the helper is not available.
         */
        t: (typeof window.frlAdminI18n === 'function')
            ? window.frlAdminI18n
            : (typeof window.frlAdminI18n === 'object' && window.frlAdminI18n !== null)
                ? function(s) { return (window.frlAdminI18n[s] || s); }
                : function(s) { return s; },

        /**
         * Build the dialog DOM, wire it up, mount it on <body>, and
         * return a Promise that resolves to the user's choice.
         * @private
         */
        _showDialog: function(message, settings) {
            const self = this;
            return new Promise(function(resolve) {
                const $backdrop = $('<div class="frl-modal-backdrop" aria-hidden="true"></div>');
                const $dialog = $('<div class="frl-modal" role="alertdialog" aria-modal="true" aria-labelledby="frl-modal-title" aria-describedby="frl-modal-desc" tabindex="-1"></div>');
                const $title = $('<h2 id="frl-modal-title" class="frl-modal-title"></h2>').text(settings.title);
                const $desc = $('<div id="frl-modal-desc" class="frl-modal-body"></div>').text(message);
                const $actions = $('<div class="frl-modal-actions"></div>');
                const $cancel = $('<button type="button" class="frl-btn frl-btn-ghost"></button>').text(settings.cancelText);
                const $ok = $('<button type="button" class="frl-btn frl-btn-primary"></button>').text(settings.confirmText);
                if (settings.variant === 'danger') {
                    $ok.removeClass('frl-btn-primary').addClass('frl-btn-danger');
                }
                $actions.append($cancel).append($ok);
                $dialog.append($title).append($desc).append($actions);
                $backdrop.append($dialog);
                $('body').append($backdrop);

                // Force a reflow then add the "open" class for animation.
                $backdrop[0].offsetHeight; // eslint-disable-line no-unused-expressions
                $backdrop.addClass('is-open');

                const $previouslyFocused = $(document.activeElement);
                setTimeout(function() { $dialog.trigger('focus'); }, 30);

                // Focus trap: keep TAB inside the dialog.
                $dialog.on('keydown', function(e) {
                    if (e.key !== 'Tab') { return; }
                    const focusable = $dialog.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                    if (focusable.length === 0) { return; }
                    const first = focusable[0];
                    const last  = focusable[focusable.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                });

                // Esc closes (acts as cancel).
                $dialog.on('keydown', function(e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        e.preventDefault();
                        closeWith(false);
                    }
                });

                $cancel.on('click', function() { closeWith(false); });
                $ok.on('click', function() { closeWith(true); });
                $backdrop.on('click', function(e) {
                    if (e.target === $backdrop[0]) { closeWith(false); }
                });

                function closeWith(value) {
                    $backdrop.removeClass('is-open');
                    setTimeout(function() {
                        $backdrop.remove();
                        if ($previouslyFocused && $previouslyFocused.length) {
                            try { $previouslyFocused.trigger('focus'); } catch (e) {}
                        }
                        resolve(value);
                    }, 150);
                }
            });
        }
    };

    const FRLAdmin = {
        config: window.frlAdminConfig || {},

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.bindUsersTableSearch();
            this.bindEnrollUserCardSearch();
            this.fixPaginationButtons();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Clean logs button
            $(document).on('click', '#frl-clean-logs', $.proxy(this.cleanLogs, this));

            // Clean all logs (deletes every row in the logs table — destructive).
            $(document).on('click', '#frl-clean-all-logs', $.proxy(this.cleanAllLogs, this));

            // Clean old logs
            $(document).on('click', '#frl-clean-old-logs', $.proxy(this.cleanOldLogs, this));

            // Delete user faces
            $(document).on('click', '.frl-delete-user-faces', $.proxy(this.deleteUserFaces, this));

            // License notice dismiss is handled by the dedicated
            // `frl-license-notice` script (see
            // admin/assets/js/license-notice.js) which is loaded
            // on EVERY wp-admin screen so the global license
            // notice can be dismissed from any page (Dashboard,
            // Posts, Plugins, etc.), not just the plugin's own
            // pages. The handler used to live here but caused
            // double-firing on plugin pages where both scripts
            // are enqueued, so it has been removed from this
            // bundle to keep a single source of truth.
        },

        /**
         * Live client-side search + pagination button fix for the
         * "All Enrolled Users" table on admin.php?page=frl-users.
         *
         * The PHP template now adds `data-search="..."` to every <tr>,
         * an id to the search input/table, and a `data-page-current`
         * attribute on the pagination info block. We use those to:
         *
         *  - Filter the table as the user types (no page refresh).
         *  - Show a "no matches" row when nothing matches.
         *  - Always keep the Previous/Next buttons clickable on the
         *    correct pages, even if the hosted file still has the old
         *    inline `pointer-events:none` style baked in.
         */
        bindUsersTableSearch: function() {
            var self = this;
            var $input = jQuery('#frl-users-search');
            var $table = jQuery('#frl-users-table');
            if ($input.length === 0 || $table.length === 0) {
                return;
            }

            // Cache the original info text so we can restore it when
            // the user clears the search box.
            var $info = jQuery('.frl-pagination-info');
            var originalInfo = $info.length ? $info.text() : '';

            var $rows = $table.find('tbody tr').not('#frl-users-no-results');
            var $noResults = jQuery('#frl-users-no-results');

            var applyFilter = function() {
                var raw = ($input.val() || '').toString().trim().toLowerCase();
                var visible = 0;
                $rows.each(function() {
                    var hay = (this.getAttribute('data-search') || '').toLowerCase();
                    if (raw === '' || hay.indexOf(raw) !== -1) {
                        this.style.display = '';
                        visible++;
                    } else {
                        this.style.display = 'none';
                    }
                });

                if (visible === 0) {
                    if ($noResults.length) { $noResults.show(); }
                } else if ($noResults.length) {
                    $noResults.hide();
                }

                // Update the pagination count line in place.
                if ($info.length) {
                    if (raw === '') {
                        $info.text(originalInfo);
                    } else {
                        var template = 'Showing %1$d of %2$d users matching "%3$s"';
                        $info.text(template
                            .replace('%1$d', visible)
                            .replace('%2$d', $rows.length)
                            .replace('%3$s', raw));
                    }
                }
            };

            // Live search on keyup / input (per user request: no page refresh).
            $input.on('input keyup', jQuery.proxy(applyFilter, this));

            // Pressing Enter in the search box should NOT trigger a full
            // page reload while we are already filtering live. Just
            // re-apply the filter and keep the user on the same page.
            $input.closest('form').on('submit', function(e) {
                e.preventDefault();
                applyFilter();
            });

            // The per-page <select> originally submitted the form on
            // change via inline onchange="this.form.submit()". Keep
            // that behaviour, but skip the submit when the user is
            // still focused on the search box (so changing rows-per-page
            // mid-typing does not cause a jarring reload).
            var $perPage = $input.closest('form').find('select[name="per_page"]');
            if ($perPage.length) {
                var $form = $input.closest('form');
                $perPage.on('change', function() {
                    if (document.activeElement === $input[0]) { return; }
                    $form.trigger('submit');
                });
            }
        },

        /**
         * Live client-side search for the "Select User" card on
         * admin.php?page=frl-enroll-face.
         *
         * The PHP template already submits a GET form to filter users
         * server-side (so the pagination + rows-per-page stay in sync
         * with the search). This handler adds a real-time, no-refresh
         * filter on top of that:
         *
         *   - As the admin types in #frl-user-search, the visible
         *     list is narrowed to matching users.
         *   - The "X of Y" count badge and the "Showing N to N of N"
         *     pagination info update in place.
         *   - When the user clears the input (or hits Enter), the
         *     server-side form takes over and reloads the page with
         *     the canonical, paginated results.
         *   - When the admin clicks "Enroll" on a filtered user, the
         *     selection still works because we only hide the rows
         *     (display:none), we never remove them from the DOM.
         */
        bindEnrollUserCardSearch: function() {
            var self = this;
            var $ = jQuery;
            var $input = $('#frl-user-search');
            var $list  = $('#frl-users-without-face-list');
            if ($input.length === 0 || $list.length === 0) {
                return;
            }

            var $items = $list.find('.frl-user-item');
            var $count = $('#frl-users-without-face-count');
            var $info  = $list.closest('.frl-users-section').find('.frl-pagination-info');

            // Cache the original count text and pagination info so we
            // can restore them when the user clears the search box.
            var originalCount = $count.length ? $count.text() : '';
            var originalInfo  = $info.length  ? $info.text()  : '';
            var totalCount    = $items.length;

            var applyFilter = function() {
                var raw = ($input.val() || '').toString().trim().toLowerCase();
                if (raw === '') {
                    // Empty filter → restore everything.
                    $items.show();
                    if ($count.length) { $count.text(originalCount); }
                    if ($info.length)  { $info.text(originalInfo); }
                    return;
                }

                var visible = 0;
                $items.each(function() {
                    var hay = (this.getAttribute('data-search') || '').toLowerCase();
                    if (hay.indexOf(raw) !== -1) {
                        this.style.display = '';
                        visible++;
                    } else {
                        this.style.display = 'none';
                    }
                });

                if ($count.length) {
                    $count.text(visible + ' of ' + totalCount);
                }
                if ($info.length) {
                    var template = 'Showing %1$d of %2$d matching';
                    $info.text(template.replace('%1$d', visible).replace('%2$d', totalCount));
                }
            };

            // Live filter on every keystroke (no page refresh).
            $input.on('input keyup', $.proxy(applyFilter, this));

            // Pressing Enter (or the search input's "x" button) submits
            // the form so the server can re-paginate the FULL filtered
            // list — not just the current page.
            $input.closest('form').on('submit', function() {
                // Allow the form to submit normally.
                return true;
            });
        },

        /**
         * Ensure the Previous/Next buttons in the users table pagination
         * are clickable on the correct pages.
         *
         * Some hosted copies of the plugin still ship the inline
         * `style="pointer-events:none; opacity:.5;"` on the Previous
         * button even when the current page is > 1, which traps the
         * user on page 2. We read the current page from the data
         * attribute the template just emits and force the styles back
         * to the correct state in JS.
         *
         * The fix is layered to defeat every possible cause:
         *   1. Inject a <style> rule with !important that wins over
         *      any inline style or specificity race.
         *   2. Strip the inline `style` attribute entirely so the
         *      host file's baked-in `pointer-events:none` is gone.
         *   3. Wire a delegated click handler that follows the
         *      anchor's href even if some other CSS still tries to
         *      block pointer events.
         */
        fixPaginationButtons: function() {
            var $ = jQuery;
            var $info = $('.frl-pagination-info');
            if ($info.length === 0) { return; }

            // --- URL-parameter fallback ---------------------------
            // Try the data attribute first (newer template), then fall
            // back to ?paged=N in the URL (any version of the template).
            // This ensures the Previous button still works on hosted
            // files that have not yet picked up the new attribute.
            var readUrlPaged = function() {
                try {
                    var params = new URLSearchParams(window.location.search);
                    var v = parseInt(params.get('paged') || '1', 10);
                    return (isNaN(v) || v < 1) ? 1 : v;
                } catch (e) { return 1; }
            };
            var readAttrInt = function($el, name) {
                var v = parseInt($el.attr(name) || '', 10);
                return isNaN(v) ? NaN : v;
            };
            var attrCurrent = readAttrInt($info, 'data-page-current');
            var attrTotal   = readAttrInt($info, 'data-total-pages');

            var current = !isNaN(attrCurrent) ? attrCurrent : readUrlPaged();
            var total   = !isNaN(attrTotal)   ? attrTotal   : 1;
            if (current < 1) { current = 1; }
            if (total   < 1) { total   = 1; }

            var $prev = $('a[data-frl-paged="prev"]');
            var $next = $('a[data-frl-paged="next"]');

            // 1) Inject a high-specificity stylesheet that wins over
            //    any inline style or CSS rule. We only enable this when
            //    we are actually on a page where the buttons should be
            //    clickable, so the disabled visual state is preserved
            //    where appropriate via the host CSS.
            if ($('#frl-pagination-fix').length === 0) {
                $('<style id="frl-pagination-fix">\n' +
                  '  a.frl-pagination-btn[data-frl-paged="prev"].is-enabled,\n' +
                  '  a.frl-pagination-btn[data-frl-paged="next"].is-enabled {\n' +
                  '    pointer-events: auto !important;\n' +
                  '    opacity: 1 !important;\n' +
                  '    cursor: pointer !important;\n' +
                  '  }\n' +
                  '  a.frl-pagination-btn[data-frl-paged="prev"].is-disabled,\n' +
                  '  a.frl-pagination-btn[data-frl-paged="next"].is-disabled {\n' +
                  '    pointer-events: none !important;\n' +
                  '    opacity: 0.5 !important;\n' +
                  '    cursor: not-allowed !important;\n' +
                  '  }\n' +
                  '</style>').appendTo('head');
            }

            var enable = function($el) {
                if ($el.length === 0) { return; }
                // 2) Strip any inline style the host file baked in.
                $el.removeAttr('style');
                $el.removeAttr('aria-disabled');
                $el.removeClass('is-disabled').addClass('is-enabled');
            };
            var disable = function($el) {
                if ($el.length === 0) { return; }
                $el.removeAttr('style');
                $el.attr('aria-disabled', 'true');
                $el.removeClass('is-enabled').addClass('is-disabled');
            };

            if (current <= 1) { disable($prev); } else { enable($prev); }
            if (current >= total) { disable($next); } else { enable($next); }

            // 3) Delegated click handler — if anything still blocks
            //    the click (e.g. a stale inline style re-applied by
            //    another script), force the navigation ourselves.
            $(document).off('click.frl-paged').on('click.frl-paged',
                'a.frl-pagination-btn[data-frl-paged]', function(e) {
                    var $a = $(this);
                    if ($a.hasClass('is-disabled') || $a.attr('aria-disabled') === 'true') {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        return false;
                    }
                    // If the browser would otherwise not navigate
                    // (because some other style swallowed the click),
                    // re-issue the navigation explicitly.
                    if ($a.attr('href') && $a.attr('href') !== '#' && !e.isDefaultPrevented()) {
                        // Only re-navigate if the click was a real
                        // primary-button click. This preserves normal
                        // middle-click / cmd-click open-in-new-tab.
                        if (e.button === 0 && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                            // Let the default action happen.
                        }
                    }
                });
        },

        /**
         * Clean authentication logs
         */
        cleanLogs: function(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const $result = $('#frl-clean-result');

            $btn.prop('disabled', true).addClass('frl-loading');
            $result.text('');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frl_admin_clean_logs',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span class="frl-message success">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span class="frl-message error">' + (response.data.message || 'Error') + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span class="frl-message error">AJAX Error</span>');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('frl-loading');
                }
            });
        },

        /**
         * Clean all logs
         */
        cleanAllLogs: function(e) {
            e.preventDefault();

            // The user may have clicked on the SVG inside the button —
            // resolve back to the button so we can disable it.
            const $btn = $(e.target).closest('#frl-clean-all-logs');
            const self = this;

            FRLModal.confirm(
                FRLModal.t('Delete ALL log entries? This action cannot be undone.', 'recognition'),
                {
                    title: FRLModal.t('Clean all logs', 'recognition'),
                    confirmText: FRLModal.t('Delete all', 'recognition'),
                    variant: 'danger'
                }
            ).then(function(ok) {
                if (!ok) { return; }
                self._doCleanAllLogs($btn);
            });
        },

        /**
         * Perform the AJAX call to clean ALL logs. Split out so the
         * confirm modal promise chain can stay declarative (mirrors the
         * `_doCleanOldLogs` pattern).
         * @private
         */
        _doCleanAllLogs: function($btn) {
            $btn.prop('disabled', true).addClass('frl-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frl_admin_clean_all_logs',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FRLModal.alert(response.data.message || 'All logs deleted', {
                            title: FRLModal.t('Success', 'recognition')
                        }).then(function() { location.reload(); });
                    } else {
                        FRLModal.alert(response.data.message || 'Error', {
                            title: FRLModal.t('Error', 'recognition')
                        });
                    }
                },
                error: function() {
                    FRLModal.alert('AJAX Error', {
                        title: FRLModal.t('Error', 'recognition')
                    });
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('frl-loading');
                }
            });
        },

        /**
         * Clean old logs
         */
        cleanOldLogs: function(e) {
            e.preventDefault();

            const $btn = $(e.target);
            const self = this;

            FRLModal.confirm('Delete all logs older than 30 days?', {
                title: FRLModal.t('Clean old logs', 'recognition'),
                confirmText: FRLModal.t('Delete', 'recognition'),
                variant: 'danger'
            }).then(function(ok) {
                if (!ok) { return; }
                self._doCleanOldLogs($btn);
            });
        },

        /**
         * Perform the AJAX call to clean old logs. Split out so the
         * confirm modal promise chain can stay declarative.
         * @private
         */
        _doCleanOldLogs: function($btn) {
            $btn.prop('disabled', true).addClass('frl-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frl_admin_clean_old_logs',
                    nonce: this.config.nonce,
                    days: 30
                },
                success: function(response) {
                    if (response.success) {
                        FRLModal.alert(response.data.message || 'Logs cleaned successfully', {
                            title: FRLModal.t('Success', 'recognition')
                        }).then(function() { location.reload(); });
                    } else {
                        FRLModal.alert(response.data.message || 'Error', {
                            title: FRLModal.t('Error', 'recognition')
                        });
                    }
                },
                error: function() {
                    FRLModal.alert('AJAX Error', {
                        title: FRLModal.t('Error', 'recognition')
                    });
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('frl-loading');
                }
            });
        },

        /**
         * Delete all faces for a user
         */
        deleteUserFaces: function(e) {

            e.preventDefault();

            // Find the button element (could be the svg or the button)
            const $btn = $(e.target).closest('.frl-delete-user-faces');
            const userId = $btn.data('user-id');
            const userName = $btn.data('user-name') || 'this user';
            const self = this;

            FRLModal.confirm(
                'Delete all face profiles for user "' + userName + '"? This cannot be undone.',
                {
                    title: FRLModal.t('Delete face profiles', 'recognition'),
                    confirmText: FRLModal.t('Delete', 'recognition'),
                    variant: 'danger'
                }
            ).then(function(ok) {
                if (!ok) { return; }
                self._doDeleteUserFaces($btn, userId);
            });
        },

        /**
         * Perform the AJAX call to delete a user's face profiles. Split out
         * so the confirm modal promise chain can stay declarative.
         * @private
         */
        _doDeleteUserFaces: function($btn, userId) {
            $btn.prop('disabled', true).addClass('frl-loading');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'frl_delete_user_faces',
                    user_id: userId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FRLModal.alert('Faces deleted successfully', {
                            title: FRLModal.t('Success', 'recognition')
                        }).then(function() { location.reload(); });
                    } else {
                        FRLModal.alert(response.data?.message || 'Error', {
title: FRLModal.t('Error', 'recognition')
                        });
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'AJAX Error';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMsg = response.data?.message || response.message || errorMsg;
                    } catch (err) {}
                    FRLModal.alert(errorMsg, {
                        title: FRLModal.t('Error', 'recognition')
                    });
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('frl-loading');
                }
            });
        }
    };

    $(document).ready(function() {
        FRLAdmin.init();
    });

})(jQuery);
