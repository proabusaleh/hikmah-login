/**
 * Hikmah Authentication Front-End Engine
 *
 * Handles asynchronous authentication, 2FA prompt transitions,
 * registration validations, and lost password recovery.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    const HikmahAuth = {
        /**
         * Initialize all auth form handlers
         */
        init: function () {
            this.bindPasswordToggle();
            this.bindLoginForm();
            this.bindRegisterForm();
            this.bindLostPasswordForm();
        },

        /**
         * Render an inline notification banner inside the auth card
         *
         * @param {jQuery} $form Target form container.
         * @param {string} type 'success' or 'error'.
         * @param {string} message Text message.
         */
        showAlert: function ($form, type, message) {
            $form.find('.hikmah-auth-alert').remove();

            const iconClass = type === 'success' ? 'dashicons-yes' : 'dashicons-warning';
            const alertHtml = `
                <div class="hikmah-auth-alert hikmah-auth-alert--${type}">
                    <span class="dashicons ${iconClass}"></span>
                    <p>${message}</p>
                </div>
            `;

            $form.prepend(alertHtml);
        },

        /**
         * Password show/hide toggle logic
         */
        bindPasswordToggle: function () {
            $(document).on('click', '.hikmah-password-toggle', function (e) {
                e.preventDefault();
                const targetId = $(this).data('target');
                const $input = $('#' + targetId);
                const $icon = $(this).find('.dashicons');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                }
            });
        },

        /**
         * AJAX Login with Two-Factor dynamic step-up
         */
        bindLoginForm: function () {
            const self = this;
            const $form = $('#hikmah-login-form');

            if (!$form.length) return;

            $form.on('submit', function (e) {
                e.preventDefault();

                const $submitBtn = $('#hikmah-login-submit');
                const $btnText   = $submitBtn.find('.hikmah-btn__text');
                const $btnLoader = $submitBtn.find('.hikmah-btn__loading');

                $submitBtn.prop('disabled', true);
                $btnText.hide();
                $btnLoader.show();
                $form.find('.hikmah-auth-alert').remove();

                const formData = {
                    action: 'hikmah_ajax_login',
                    nonce: $('#hikmah_login_nonce').val() || $('input[name="hikmah_login_nonce"]').val(),
                    log: $('#hikmah-user-login').val(),
                    pwd: $('#hikmah-user-pass').val(),
                    rememberme: $('input[name="rememberme"]').is(':checked') ? 1 : 0,
                    hikmah_2fa_token: $('#hikmah-2fa-token').val() || '',
                };

                $.ajax({
                    url: hikmahAuthData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        if (response.success) {
                            // Check if Two-Factor step is requested
                            if (response.data && response.data.requires_2fa) {
                                self.showAlert($form, 'success', response.data.message);
                                const $field2FA = $('.hikmah-form-group--2fa');
                                $field2FA.slideDown(250);
                                $('#hikmah-2fa-token').prop('required', true).focus();
                                $btnText.text(hikmahAuthData.i18n.verify2FA || 'Verify & Sign In').show();
                                $btnLoader.hide();
                                $submitBtn.prop('disabled', false);
                            } else {
                                self.showAlert($form, 'success', response.data.message);
                                setTimeout(function () {
                                    window.location.href = response.data.redirect_url;
                                }, 1000);
                            }
                        } else {
                            self.showAlert($form, 'error', response.data.message);
                            $btnText.show();
                            $btnLoader.hide();
                            $submitBtn.prop('disabled', false);
                        }
                    },
                    error: function () {
                        self.showAlert($form, 'error', hikmahAuthData.i18n.genericError || 'An unexpected error occurred. Please try again.');
                        $btnText.show();
                        $btnLoader.hide();
                        $submitBtn.prop('disabled', false);
                    },
                });
            });
        },

        /**
         * AJAX User Registration
         */
        bindRegisterForm: function () {
            const self = this;
            const $form = $('#hikmah-registration-form');

            if (!$form.length) return;

            $form.on('submit', function (e) {
                e.preventDefault();

                const $submitBtn = $('#hikmah-register-submit');
                const $btnText   = $submitBtn.find('.hikmah-btn__text');
                const $btnLoader = $submitBtn.find('.hikmah-btn__loading');

                $submitBtn.prop('disabled', true);
                $btnText.hide();
                $btnLoader.show();
                $form.find('.hikmah-auth-alert').remove();

                const formData = {
                    action: 'hikmah_ajax_register',
                    nonce: $('input[name="hikmah_register_nonce"]').val(),
                    user_login: $('#hikmah-reg-username').val(),
                    user_email: $('#hikmah-reg-email').val(),
                    user_pass: $('#hikmah_new_password').val(),
                    hikmah_terms: $('#hikmah-terms').is(':checked') ? 1 : 0,
                };

                $.ajax({
                    url: hikmahAuthData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        if (response.success) {
                            self.showAlert($form, 'success', response.data.message);
                            setTimeout(function () {
                                window.location.href = response.data.redirect_url;
                            }, 1200);
                        } else {
                            self.showAlert($form, 'error', response.data.message);
                            $btnText.show();
                            $btnLoader.hide();
                            $submitBtn.prop('disabled', false);
                        }
                    },
                    error: function () {
                        self.showAlert($form, 'error', hikmahAuthData.i18n.genericError || 'An unexpected error occurred. Please try again.');
                        $btnText.show();
                        $btnLoader.hide();
                        $submitBtn.prop('disabled', false);
                    },
                });
            });
        },

        /**
         * AJAX Lost Password Recovery
         */
        bindLostPasswordForm: function () {
            const self = this;
            const $form = $('#hikmah-lostpassword-form');

            if (!$form.length) return;

            $form.on('submit', function (e) {
                e.preventDefault();

                const $submitBtn = $('#hikmah-lostpassword-submit');
                const $btnText   = $submitBtn.find('.hikmah-btn__text');
                const $btnLoader = $submitBtn.find('.hikmah-btn__loading');

                $submitBtn.prop('disabled', true);
                $btnText.hide();
                $btnLoader.show();
                $form.find('.hikmah-auth-alert').remove();

                const formData = {
                    action: 'hikmah_ajax_lostpassword',
                    nonce: $('input[name="hikmah_lostpassword_nonce"]').val(),
                    user_login: $('#hikmah-lost-user-login').val(),
                };

                $.ajax({
                    url: hikmahAuthData.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        if (response.success) {
                            self.showAlert($form, 'success', response.data.message);
                            $('#hikmah-lost-user-login').val('');
                        } else {
                            self.showAlert($form, 'error', response.data.message);
                        }
                        $btnText.show();
                        $btnLoader.hide();
                        $submitBtn.prop('disabled', false);
                    },
                    error: function () {
                        self.showAlert($form, 'error', hikmahAuthData.i18n.genericError || 'An unexpected error occurred. Please try again.');
                        $btnText.show();
                        $btnLoader.hide();
                        $submitBtn.prop('disabled', false);
                    },
                });
            });
        },
    };

    $(document).ready(function () {
        HikmahAuth.init();
    });

})(jQuery);
