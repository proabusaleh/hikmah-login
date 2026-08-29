/**
 * Hikmah Login — Dashboard Script
 *
 * Binds all dashboard AJAX interactions. Relies on the `hikmahDashboard`
 * global (localized in public/views/user-dashboard.php).
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */
(function ($) {
    'use strict';

    if (typeof window.hikmahDashboard === 'undefined') {
        return;
    }

    var HikmahDashboard = {

        config: window.hikmahDashboard,

        init() {
            this.bindProfileForm();
            this.bindUploadAvatar();
            this.bindRemoveAvatar();
            this.bindChangePassword();
            this.bindDeleteSession();
            this.bindLogoutAll();
            this.bindUnlinkSocial();
            this.bindDeleteAccount();
            this.bindExportData();
            this.bindTabLoader();
        },

        /**
         * Show an inline status message.
         */
        setStatus($target, success, message) {
            if (!$target || !$target.length) {
                return;
            }
            const prefix = success ? '✅ ' : '❌ ';
            $target.text(prefix + message).css('color', success ? '#10b981' : '#dc2626');
        },

        /**
         * Shared AJAX post helper (nonce + action injected).
         */
        post(action, data) {
            data = data || {};
            data.action = action;
            data.nonce = this.config.nonce;
            return $.post(this.config.ajaxUrl, data);
        },

        bindProfileForm() {
            const self = this;

            $(document).on('submit', '#hikmah-profile-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('.hikmah-btn-primary');
                const $status = $form.find('.hikmah-dash-status');

                $btn.prop('disabled', true);
                self.setStatus($status, false, self.config.i18n.saving);

                self.post('hikmah_dashboard_update_profile', $form.serialize())
                    .then(function (response) {
                        self.setStatus($status, response.success, response.message);
                    })
                    .catch(function () {
                        self.setStatus($status, false, self.config.i18n.networkError);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindChangePassword() {
            const self = this;

            $(document).on('submit', '#hikmah-password-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $btn = $form.find('.hikmah-btn-primary');
                const $status = $form.find('.hikmah-dash-status');

                const newPass = $form.find('[name="new_password"]').val();
                const confirm = $form.find('[name="confirm_password"]').val();

                if (newPass !== confirm) {
                    self.setStatus($status, false, 'Passwords do not match.');
                    return;
                }

                $btn.prop('disabled', true);
                self.setStatus($status, false, self.config.i18n.saving);

                self.post('hikmah_dashboard_change_password', $form.serialize())
                    .then(function (response) {
                        self.setStatus($status, response.success, response.message);
                        if (response.success) {
                            $form[0].reset();
                        }
                    })
                    .catch(function () {
                        self.setStatus($status, false, self.config.i18n.networkError);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindUploadAvatar() {
            const self = this;

            $(document).on('change', '#hikmah-avatar-upload', function () {
                const file = this.files[0];
                if (!file) {
                    return;
                }

                if (file.size > 2 * 1024 * 1024) {
                    alert('Image must be less than 2MB.');
                    this.value = '';
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'hikmah_dashboard_upload_avatar');
                formData.append('nonce', self.config.nonce);
                formData.append('avatar', file);

                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success(response) {
                        if (response.success) {
                            $('.hikmah-avatar-img').attr('src', response.data.avatar_url);
                            $('.hikmah-dash-header img').attr('src', response.data.avatar_url);
                            self.setStatus($('.hikmah-dash-status').first(), true, response.message);
                        } else {
                            alert(response.message);
                        }
                    },
                    error() {
                        alert(self.config.i18n.networkError);
                    }
                });
            });
        },

        bindRemoveAvatar() {
            const self = this;

            $(document).on('click', '#hikmah-remove-avatar', function () {
                const $btn = $(this);

                $btn.prop('disabled', true);

                self.post('hikmah_dashboard_remove_avatar', {})
                    .then(function (response) {
                        if (response.success) {
                            $('.hikmah-avatar-img').attr('src', response.data.avatar_url);
                            $('.hikmah-dash-header img').attr('src', response.data.avatar_url);
                            $btn.closest('.hikmah-avatar-section').find('#hikmah-remove-avatar').remove();
                            self.setStatus($('.hikmah-dash-status').first(), true, response.message);
                        } else {
                            alert(response.message);
                        }
                    })
                    .catch(function () {
                        alert(self.config.i18n.networkError);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindDeleteSession() {
            const self = this;

            $(document).on('click', '.hikmah-session-revoke', function () {
                if (!confirm(self.config.i18n.confirmSessionRevoke)) {
                    return;
                }

                const $btn = $(this);
                const token = $btn.data('token');

                $btn.prop('disabled', true);

                self.post('hikmah_dashboard_destroy_session', { token: token })
                    .then(function (response) {
                        if (response.success) {
                            $btn.closest('.hikmah-session-card').fadeOut(300, function () {
                                $(this).remove();
                            });
                        } else {
                            alert(response.message);
                            $btn.prop('disabled', false);
                        }
                    })
                    .catch(function () {
                        alert(self.config.i18n.networkError);
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindLogoutAll() {
            const self = this;

            $(document).on('click', '#hikmah-logout-all', function () {
                if (!confirm(self.config.i18n.confirmLogoutAll)) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true);

                self.post('hikmah_dashboard_destroy_other_sessions', {})
                    .then(function (response) {
                        alert(response.message);
                        if (response.success) {
                            location.reload();
                        }
                    })
                    .catch(function () {
                        alert(self.config.i18n.networkError);
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindUnlinkSocial() {
            const self = this;

            $(document).on('click', '.hikmah-unlink-social', function () {
                if (!confirm(self.config.i18n.confirmUnlinkSocial)) {
                    return;
                }

                const $btn = $(this);
                const provider = $btn.data('provider');

                $btn.prop('disabled', true);

                self.post('hikmah_dashboard_unlink_social', { provider: provider })
                    .then(function (response) {
                        alert(response.message);
                        if (response.success) {
                            location.reload();
                        }
                    })
                    .catch(function () {
                        alert(self.config.i18n.networkError);
                        $btn.prop('disabled', false);
                    });
            });
        },

        bindDeleteAccount() {
            const self = this;

            // Enable the submit button only once the username matches.
            $(document).on('input', '#hikmah-delete-form [name="confirm_username"]', function () {
                $('#hikmah-delete-btn').prop('disabled', $(this).val() !== $(this).data('expected'));
            });

            $(document).on('submit', '#hikmah-delete-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $status = $form.find('.hikmah-dash-status');

                if (!confirm(self.config.i18n.confirmDeleteAccount)) {
                    return;
                }

                self.setStatus($status, false, self.config.i18n.processing);

                self.post('hikmah_dashboard_delete_account', $form.serialize())
                    .then(function (response) {
                        if (response.success) {
                            window.location.href = (response.data && response.data.redirect) || '/';
                        } else {
                            self.setStatus($status, false, response.message);
                        }
                    })
                    .catch(function () {
                        self.setStatus($status, false, self.config.i18n.networkError);
                    });
            });
        },

        bindExportData() {
            const self = this;

            $(document).on('click', '#hikmah-export-data', function () {
                if (!confirm(self.config.i18n.confirmExport)) {
                    return;
                }

                const $btn = $(this);
                const $status = $btn.siblings('.hikmah-dash-status');

                $btn.prop('disabled', true);
                self.setStatus($status, false, self.config.i18n.processing);

                self.post('hikmah_dashboard_export_data', {})
                    .then(function (response) {
                        if (response.success) {
                            self.setStatus($status, true, response.message);
                            window.location.href = self.config.exportUrl +
                                '&file=' + encodeURIComponent(response.data.filename);
                        } else {
                            self.setStatus($status, false, response.message);
                        }
                    })
                    .catch(function () {
                        self.setStatus($status, false, self.config.i18n.networkError);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        },

        /**
         * Server-side tab loading via AJAX.
         *
         * Only active for elements marked with data-load-dashboard-tab; the
         * default sidebar links stay as plain links so every tab (including
         * the 2FA wizard, which binds at DOM-ready) is server-rendered.
         */
        bindTabLoader() {
            const self = this;

            $(document).on('click', '[data-load-dashboard-tab]', function (e) {
                e.preventDefault();

                const $link = $(this);
                const $content = $('#hikmah-dash-content-inner');

                if ($content.data('hikmah-loading')) {
                    return;
                }

                $content.data('hikmah-loading', true).css('opacity', 0.5);

                self.post('hikmah_dashboard_load_tab', { tab: $link.data('load-dashboard-tab') })
                    .then(function (response) {
                        if (response.success) {
                            $content.html(response.data.html);
                        } else {
                            alert(response.message);
                        }
                    })
                    .catch(function () {
                        alert(self.config.i18n.networkError);
                    })
                    .always(function () {
                        $content.data('hikmah-loading', false).css('opacity', 1);
                    });
            });
        }
    };

    $(document).ready(function () {
        HikmahDashboard.init();
    });

})(jQuery);