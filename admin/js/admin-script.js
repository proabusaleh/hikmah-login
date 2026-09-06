/**
 * Hikmah Login — Admin Script
 *
 * Handles generic admin page interactions:
 * - AJAX form saving with visual feedback
 * - Security log deletion
 * - Confirmations for destructive actions
 *
 * @package Hikmah_Login
 * @version 2.0.0
 */

(function ($) {
    'use strict';

    var HikmahAdmin = {
        config: window.hikmahAdmin || {},

        init: function () {
            this.bindAjaxForms();
            this.bindLogDeletion();
            this.bindResetConfirmation();
            this.bindRangeOutputs();
            this.bindToastDismiss();
        },

        bindAjaxForms: function () {
            var self = this,
                i18n = self.config.i18n || {};
            $(document).on('click', '.hikmah-save-button', function (e) {
                var $btn = $(this),
                    $form = $btn.closest('form'),
                    $notice = $form.find('.hikmah-save-notice');

                e.preventDefault();

                if (!$form.length || !$form.attr('action')) {
                    return;
                }

                $btn.prop('disabled', true).text(i18n.saving || 'Saving...');

                $.post(
                    $form.attr('action'),
                    $form.serialize() + '&_wpnonce=' + self.config.nonce,
                    function (response) {
                        $btn.prop('disabled', false).text(i18n.saved || 'Settings saved!');
                        if ($notice.length) {
                            $notice.removeClass('notice-error').addClass('notice-success')
                                .text(i18n.saved || 'Settings saved!').show();
                        }
                        setTimeout(function () {
                            $btn.text($btn.data('originalText') || 'Save Settings');
                            if ($notice.length) {
                                $notice.fadeOut();
                            }
                        }, 2500);
                    }
                ).fail(function () {
                    $btn.prop('disabled', false);
                    if ($notice.length) {
                        $notice.removeClass('notice-success').addClass('notice-error')
                            .text(i18n.error || 'Error saving settings.').show();
                    }
                });

                if ($btn.data('originalText') === undefined) {
                    $btn.data('originalText', $btn.text());
                }
            });
        },

        bindLogDeletion: function () {
            var self = this,
                i18n = self.config.i18n || {};
            $(document).on('click', '.hikmah-delete-log', function (e) {
                e.preventDefault();

                var $row = $(this).closest('tr'),
                    id = $(this).data('id'),
                    action = $(this).data('action') || 'delete_login_log';

                if (!id) {
                    return;
                }

                if (!window.confirm(i18n.confirmDelete || 'Are you sure you want to delete this log?')) {
                    return;
                }

                $.post(
                    self.config.ajaxUrl,
                    {
                        action: action,
                        log_id: id,
                        _wpnonce: self.config.nonce
                    },
                    function (response) {
                        if (response && response.success) {
                            $row.fadeOut('fast', function () {
                                $(this).remove();
                            });
                        }
                    }
                );
            });
        },

        bindResetConfirmation: function () {
            var self = this,
                i18n = self.config.i18n || {};
            $(document).on('click', '.hikmah-reset-settings', function (e) {
                if (!window.confirm(i18n.confirmReset || 'Are you sure you want to reset all settings?')) {
                    e.preventDefault();
                }
            });
        },

        bindRangeOutputs: function () {
            $(document).on('input', '.hikmah-admin-wrap input[type="range"]', function () {
                var id = this.id,
                    $out;

                if (id) {
                    $out = $('#' + id + '-val');
                }
                if (!$out || !$out.length) {
                    $out = $(this).next('.hikmah-hint');
                }

                this.setAttribute('title', this.value);

                if ($out && $out.length) {
                    var suffix = $out.data('suffix') || 'px';
                    $out.text(this.value + suffix);
                }
            });
        },

        bindToastDismiss: function () {
            $(document).on('click', '.hikmah-toast', function () {
                $(this).fadeOut();
            });

            setTimeout(function () {
                $('.hikmah-admin-wrap .hikmah-toast').fadeOut(400);
            }, 5000);
        }
    };

    $(function () {
        HikmahAdmin.init();
    });
})(jQuery);
