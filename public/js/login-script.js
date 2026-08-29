/**
 * Hikmah Login — Frontend JavaScript
 *
 * Handles all frontend interactions:
 * - AJAX Login / Logout
 * - Form Validation
 * - Password Toggle
 * - 2FA Flow
 * - CAPTCHA Integration
 * - Notice Management
 *
 * @package Hikmah_Login
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Main Hikmah Login Object
     */
    const HikmahLogin = {

        /**
         * Configuration (from wp_localize_script)
         */
        config: window.hikmahLogin || {},

        /**
         * Initialize all features
         */
        init() {
            this.bindLoginForm();
            this.bindRegisterForm();
            this.bindForgotForm();        // 🆕
            this.bindResetForm();         // 🆕
            this.bindLogoutLinks();
            this.bindPasswordToggle();
            this.bindNoticeDismiss();
            this.bind2FAForm();
            this.bindFormValidation();
            this.bindPasswordStrength();
            this.bindAvailabilityCheck();
            this.bindVerificationResend();
            this.initCaptcha();
        },

        /**
         * =============================================
         * LOGIN FORM
         * =============================================
         */

        bindLoginForm() {
            const self = this;

            $(document).on('submit', '.hikmah-login-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $submit = $form.find('.hikmah-btn-primary');
                const $btnText = $submit.find('.hikmah-btn-text');
                const $btnLoading = $submit.find('.hikmah-btn-loading');

                // Client-side validation
                if (!self.validateLoginForm($form)) {
                    return;
                }

                // Show loading state
                $submit.prop('disabled', true);
                $btnText.addClass('hikmah-hidden');
                $btnLoading.removeClass('hikmah-hidden');
                self.hideNotices($form);

                // Prepare form data
                const formData = new FormData($form[0]);

                // Handle reCAPTCHA v3
                if (self.config.captchaEnabled && self.config.captchaType === 'recaptcha_v3') {
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(function () {
                            grecaptcha.execute(self.config.recaptchaKey, { action: 'login' })
                                .then(function (token) {
                                    formData.set('captcha_response', token);
                                    self.submitLogin($form, formData, $submit, $btnText, $btnLoading);
                                });
                        });
                        return;
                    }
                }

                self.submitLogin($form, formData, $submit, $btnText, $btnLoading);
            });

            // Transform the login form into a 2FA form when the container is active,
            // so the same submit handler drives the 2FA flow.
            const selfBound = this;
            $(document).on('submit', '.hikmah-2fa-form', function (e) {
                e.preventDefault();
                selfBound.handle2FASubmit($(this));
            });
        },

        /**
         * Submit login via AJAX
         */
        submitLogin($form, formData, $submit, $btnText, $btnLoading) {
            const self = this;

            HikmahAjax.login(self.formToData($form))
                .then(function (response) {
                    // Check if 2FA is required
                    if (response.data && response.data.requires_2fa) {
                        self.show2FAForm($form, response.data);
                        self.resetButton($submit, $btnText, $btnLoading);
                        return;
                    }

                    // Login successful — redirect
                    self.showNotice($form, 'success', response.message);

                    if (response.data && response.data.redirect) {
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 800);
                    } else {
                        setTimeout(function () {
                            window.location.reload();
                        }, 800);
                    }
                })
                .catch(function (response) {
                    const message = (response && response.message) || self.config.i18n.error;

                    // Login failed
                    self.showNotice($form, 'error', message);
                    self.shakeForm($form);
                    self.resetButton($submit, $btnText, $btnLoading);

                    // Highlight specific field if indicated
                    if (response && response.data && response.data.field) {
                        self.highlightField($form, response.data.field);
                    }
                });
        },

        /**
         * Serialize form data into a plain object, excluding
         * internal nonce/action fields managed by the AJAX client.
         *
         * @param {jQuery} $form Form element.
         * @returns {Object}
         */
        formToData($form) {
            const internal = [
                'action',
                'hikmah_action',
                'hikmah_nonce',
                'hikmah_login_nonce',
                'hikmah_register_nonce',
                'hikmah_forgot_nonce',
                'hikmah_reset_nonce',
                'nonce',
                '_wp_http_referer'
            ];
            const data = {};
            const formData = new FormData($form[0]);

            formData.forEach(function (value, key) {
                if (internal.indexOf(key) === -1) {
                    data[key] = value;
                }
            });

            return data;
        },

        /**
         * =============================================
         * LOGOUT
         * =============================================
         */

        bindLogoutLinks() {
            const self = this;

            $(document).on('click', '.hikmah-logout-link, .hikmah-ajax-logout', function (e) {
                e.preventDefault();

                const $link = $(this);
                const redirect = $link.data('redirect') || '';

                if (!confirm(self.config.i18n.confirmLogout)) {
                    return;
                }

                HikmahAjax.logout({ redirect: redirect })
                    .then(function (response) {
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function () {
                        window.location.reload();
                    });
            });
        },
        /**
         * =============================================
         * FORGOT PASSWORD FORM
         * =============================================
         */

        bindForgotForm() {
            const self = this;

            $(document).on('submit', '.hikmah-forgot-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $submit = $form.find('.hikmah-btn-primary');
                const $btnText = $submit.find('.hikmah-btn-text');
                const $btnLoading = $submit.find('.hikmah-btn-loading');
                const userLogin = $form.find('input[name="user_login"]').val().trim();

                // Validate
                if (!userLogin) {
                    self.showNotice($form, 'error', self.config.i18n.required);
                    $form.find('input[name="user_login"]').addClass('hikmah-input-error').focus();
                    return;
                }

                // Loading
                $submit.prop('disabled', true);
                $btnText.addClass('hikmah-hidden');
                $btnLoading.removeClass('hikmah-hidden');
                self.hideNotices($form);

                HikmahAjax.forgotPassword(self.formToData($form))
                    .then(function (response) {
                        // Show success, hide form
                        $form.fadeOut(300, function () {
                            const $wrapper = $form.closest('.hikmah-login-wrapper');
                            $wrapper.find('.hikmah-form-subtitle').text(
                                'Check your inbox for the password reset link.'
                            );
                            self.showNotice($form, 'success', response.message);
                        });
                    })
                    .catch(function (response) {
                        const message = (response && response.message) || self.config.i18n.error;
                        self.showNotice($form, 'error', message);
                        self.shakeForm($form);
                        self.resetButton($submit, $btnText, $btnLoading);
                    });
            });
        },

        /**
         * =============================================
         * RESET PASSWORD FORM
         * =============================================
         */

        bindResetForm() {
            const self = this;

            $(document).on('submit', '.hikmah-reset-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $submit = $form.find('.hikmah-btn-primary');
                const $btnText = $submit.find('.hikmah-btn-text');
                const $btnLoading = $submit.find('.hikmah-btn-loading');

                const newPass = $form.find('input[name="new_password"]').val();
                const confirmPass = $form.find('input[name="confirm_password"]').val();

                // Validate
                let isValid = true;

                if (!newPass || newPass.length < 8) {
                    isValid = false;
                    $form.find('input[name="new_password"]').addClass('hikmah-input-error');
                    $form.find('.hikmah-field-error[data-field="new_password"]')
                        .text('Password must be at least 8 characters.');
                }

                if (newPass !== confirmPass) {
                    isValid = false;
                    $form.find('input[name="confirm_password"]').addClass('hikmah-input-error');
                    $form.find('.hikmah-field-error[data-field="confirm_password"]')
                        .text(self.config.i18n.passwordMismatch);
                }

                if (!isValid) {
                    self.shakeForm($form);
                    return;
                }

                // Loading
                $submit.prop('disabled', true);
                $btnText.addClass('hikmah-hidden');
                $btnLoading.removeClass('hikmah-hidden');
                self.hideNotices($form);

                HikmahAjax.resetPassword(self.formToData($form))
                    .then(function (response) {
                        // Show success state
                        $form.fadeOut(300, function () {
                            const $wrapper = $form.closest('.hikmah-login-wrapper');
                            $wrapper.find('.hikmah-form-header .hikmah-form-title')
                                .text('Password Reset Complete! 🎉');
                            $wrapper.find('.hikmah-form-header .hikmah-form-subtitle')
                                .text('Your password has been changed successfully.');

                            const $successHtml = $(
                                '<div class="hikmah-reset-success-state hikmah-fade-in">' +
                                '<span class="hikmah-success-icon">🔐</span>' +
                                '<h3>' + response.message + '</h3>' +
                                '<p>You will be redirected to the login page shortly.</p>' +
                                '<a href="' + (response.data.redirect || self.config.loginUrl) + '" class="hikmah-btn hikmah-btn-primary">' +
                                'Go to Login' +
                                '</a>' +
                                '</div>'
                            );
                            $form.after($successHtml);
                        });

                        // Auto-redirect
                        if (response.data && response.data.redirect) {
                            setTimeout(function () {
                                window.location.href = response.data.redirect;
                            }, 3000);
                        }
                    })
                    .catch(function (response) {
                        const message = (response && response.message) || self.config.i18n.error;
                        self.showNotice($form, 'error', message);
                        self.shakeForm($form);
                        self.resetButton($submit, $btnText, $btnLoading);

                        // If link expired, show request new link button
                        if (response && response.data && response.data.expired) {
                            $form.find('.hikmah-submit-field').html(
                                '<a href="' + self.config.loginUrl + '?action=forgot" class="hikmah-btn hikmah-btn-primary hikmah-btn-full">' +
                                'Request New Reset Link' +
                                '</a>'
                            );
                        }
                    });
            });
        },
        /**
         * =============================================
         * PASSWORD TOGGLE
         * =============================================
         */

        bindPasswordToggle() {
            $(document).on('click', '.hikmah-toggle-password', function () {
                const $btn = $(this);
                const $input = $btn.siblings('.hikmah-input');
                const $eyeOpen = $btn.find('.hikmah-eye-open');
                const $eyeClosed = $btn.find('.hikmah-eye-closed');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $eyeOpen.addClass('hikmah-hidden');
                    $eyeClosed.removeClass('hikmah-hidden');
                } else {
                    $input.attr('type', 'password');
                    $eyeOpen.removeClass('hikmah-hidden');
                    $eyeClosed.addClass('hikmah-hidden');
                }

                $input.focus();
            });
        },

        /**
         * =============================================
         * 2FA
         * =============================================
         */

        /**
         * Handle 2FA code submission via AJAX
         */
        handle2FASubmit($form) {
            const self = this;
            const $container = $form.find('.hikmah-2fa-container');
            const code = $container.find('input[name="2fa_code"]').val().trim();
            const userId = $container.find('input[name="2fa_user_id"]').val();

            if (!code || code.length !== 6) {
                self.showNotice($form, 'error', self.config.i18n.invalidCode || 'Please enter a valid 6-digit code.');
                return;
            }

            HikmahAjax.verify2FA({
                user_id: userId,
                code: code,
                remember: $container.find('input[name="remember"]').val(),
                redirect_to: $container.find('input[name="redirect_to"]').val()
            }).then(function (response) {
                self.showNotice($form, 'success', response.message);
                if (response.data && response.data.redirect) {
                    setTimeout(function () {
                        window.location.href = response.data.redirect;
                    }, 800);
                } else {
                    setTimeout(function () {
                        window.location.reload();
                    }, 800);
                }
            }).catch(function (response) {
                self.showNotice($form, 'error', (response && response.message) || self.config.i18n.networkError);
                self.shakeForm($form);
                $container.find('input[name="2fa_code"]').val('').focus();
            });
        },

        /**
         * Show 2FA form inside login form
         */
        show2FAForm($form, data) {
            const $container = $form.find('.hikmah-2fa-container');
            const $userIdInput = $form.find('input[name="2fa_user_id"]');

            // Hide regular login fields
            $form.find('.hikmah-field:not(.hikmah-2fa-container):not(.hikmah-submit-field)')
                .slideUp(200);
            $form.find('.hikmah-field-row').slideUp(200);
            $form.find('.hikmah-social-login, .hikmah-divider').slideUp(200);

            // Show 2FA container
            $container.removeClass('hikmah-hidden').addClass('hikmah-fade-in');
            $userIdInput.val(data.user_id);

            // Update submit button
            const $submit = $form.find('.hikmah-btn-primary .hikmah-btn-text');
            $submit.text('Verify Code');

            // Focus on 2FA input
            setTimeout(function () {
                $form.find('input[name="2fa_code"]').focus();
            }, 300);
        },

        /**
         * =============================================
         * FORM VALIDATION (Client-Side)
         * =============================================
         */

        bindFormValidation() {
            // Real-time validation on blur
            $(document).on('blur', '.hikmah-login-form .hikmah-input', function () {
                HikmahLogin.validateField($(this));
            });

            // Clear error on focus
            $(document).on('focus', '.hikmah-login-form .hikmah-input', function () {
                $(this).removeClass('hikmah-input-error');
                $(this).closest('.hikmah-field')
                    .find('.hikmah-field-error')
                    .text('');
            });
        },

        /**
         * Validate a single field
         */
        validateField($input) {
            const name = $input.attr('name');
            const value = $input.val().trim();
            const $error = $input.closest('.hikmah-field').find('.hikmah-field-error');
            let isValid = true;
            let message = '';

            switch (name) {
                case 'username':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    }
                    break;

                case 'password':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    }
                    break;

                case 'email':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    } else if (!this.isValidEmail(value)) {
                        isValid = false;
                        message = this.config.i18n.invalidEmail;
                    }
                    break;
            }

            if (!isValid) {
                $input.addClass('hikmah-input-error');
                $error.text(message);
            } else {
                $input.removeClass('hikmah-input-error');
                $error.text('');
            }

            return isValid;
        },

        /**
         * Validate entire login form
         */
        validateLoginForm($form) {
            let isValid = true;
            const self = this;

            $form.find('.hikmah-input[required]').each(function () {
                if (!self.validateField($(this))) {
                    isValid = false;
                }
            });

            if (!isValid) {
                // Focus first error field
                $form.find('.hikmah-input-error').first().focus();
                this.shakeForm($form);
            }

            return isValid;
        },

        /**
         * Email validation regex
         */
        isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * =============================================
         * CAPTCHA
         * =============================================
         */

        initCaptcha() {
            if (!this.config.captchaEnabled) return;

            // reCAPTCHA v2 callback
            window.hikmahRecaptchaCallback = function (token) {
                $('.hikmah-login-form input[name="captcha_response"]').val(token);
            };

            window.hikmahRecaptchaExpired = function () {
                $('.hikmah-login-form input[name="captcha_response"]').val('');
            };

            // Turnstile callback
            window.hikmahTurnstileCallback = function (token) {
                $('.hikmah-login-form input[name="captcha_response"]').val(token);
            };
        },

        /**
         * =============================================
         * UI HELPERS
         * =============================================
         */

        /**
         * Show a notice message
         */
        showNotice($form, type, message) {
            const $wrapper = $form.closest('.hikmah-login-wrapper');
            const $existing = $wrapper.find('.hikmah-notice-' + type).first();

            if ($existing.length) {
                $existing.find('p').text(message);
                $existing.removeClass('hikmah-hidden').addClass('hikmah-fade-in');
            } else {
                const icons = {
                    success: '✅',
                    error: '⚠️',
                    warning: '⚡',
                    info: 'ℹ️'
                };

                const $notice = $(
                    '<div class="hikmah-notice hikmah-notice-' + type + ' hikmah-dismissible hikmah-fade-in">' +
                    '<span class="hikmah-notice-icon">' + (icons[type] || '') + '</span>' +
                    '<p>' + message + '</p>' +
                    '<button type="button" class="hikmah-notice-close">&times;</button>' +
                    '</div>'
                );

                $form.before($notice);
            }

            // Auto-hide success notices after 5 seconds
            if (type === 'success') {
                setTimeout(function () {
                    $wrapper.find('.hikmah-notice-success').fadeOut(300);
                }, 5000);
            }
        },

        /**
         * Hide all notices
         */
        hideNotices($form) {
            $form.closest('.hikmah-login-wrapper')
                .find('.hikmah-notice')
                .addClass('hikmah-hidden');
        },

        /**
         * Reset submit button
         */
        resetButton($submit, $btnText, $btnLoading) {
            $submit.prop('disabled', false);
            $btnText.removeClass('hikmah-hidden');
            $btnLoading.addClass('hikmah-hidden');
        },

        /**
         * Shake animation on form error
         */
        shakeForm($form) {
            $form.addClass('hikmah-shake');
            setTimeout(function () {
                $form.removeClass('hikmah-shake');
            }, 500);
        },

        /**
         * Highlight a specific field
         */
        highlightField($form, fieldName) {
            const $field = $form.find('[name="' + fieldName + '"]');
            if ($field.length) {
                $field.addClass('hikmah-input-error').focus();
            }
        },

        /**
         * Bind notice dismiss buttons
         */
        bindNoticeDismiss() {
            $(document).on('click', '.hikmah-notice-close', function () {
                $(this).closest('.hikmah-notice').fadeOut(200, function () {
                    $(this).remove();
                });
            });
        },

        /**
         * =============================================
         * REGISTRATION FORM
         * =============================================
         */

        /**
         * Bind registration form submission
         */
        bindRegisterForm() {
            const self = this;

            $(document).on('submit', '.hikmah-register-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const $submit = $form.find('.hikmah-btn-primary');
                const $btnText = $submit.find('.hikmah-btn-text');
                const $btnLoading = $submit.find('.hikmah-btn-loading');

                // Client-side validation
                if (!self.validateRegisterForm($form)) {
                    return;
                }

                // Show loading state
                $submit.prop('disabled', true);
                $btnText.addClass('hikmah-hidden');
                $btnLoading.removeClass('hikmah-hidden');
                self.hideNotices($form);

                // Prepare form data
                const formData = new FormData($form[0]);

                // Handle reCAPTCHA v3
                if (self.config.captchaEnabled && self.config.captchaType === 'recaptcha_v3') {
                    if (typeof grecaptcha !== 'undefined') {
                        grecaptcha.ready(function () {
                            grecaptcha.execute(self.config.recaptchaKey, { action: 'register' })
                                .then(function (token) {
                                    formData.set('captcha_response', token);
                                    self.submitRegister($form, formData, $submit, $btnText, $btnLoading);
                                });
                        });
                        return;
                    }
                }

                self.submitRegister($form, formData, $submit, $btnText, $btnLoading);
            });
        },

        /**
         * Submit registration via AJAX
         */
        submitRegister($form, formData, $submit, $btnText, $btnLoading) {
            const self = this;

            HikmahAjax.register(self.formToData($form))
                .then(function (response) {
                    // Registration successful
                    self.showNotice($form, 'success', response.message);

                    // Clear form
                    $form.find('input:not([type="hidden"])').val('');

                    if (response.data && response.data.redirect) {
                        setTimeout(function () {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    } else {
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                    }
                })
                .catch(function (response) {
                    const message = (response && response.message) || self.config.i18n.error;

                    // Registration failed
                    self.showNotice($form, 'error', message);
                    self.shakeForm($form);
                    self.resetButton($submit, $btnText, $btnLoading);

                    // Show field-specific errors
                    if (response && response.data && response.data.errors) {
                        self.showFieldErrors($form, response.data.errors);
                    } else if (response && response.data && response.data.field) {
                        self.highlightField($form, response.data.field);
                    }
                });
        },

        /**
         * Validate entire registration form
         */
        validateRegisterForm($form) {
            let isValid = true;
            const self = this;

            // Validate each field
            const fields = ['first_name', 'last_name', 'username', 'email', 'password', 'confirm_password'];

            fields.forEach(function (name) {
                const $input = $form.find('[name="' + name + '"]');
                if ($input.length && !self.validateRegisterField($input)) {
                    isValid = false;
                }
            });

            // Validate terms checkbox
            const $terms = $form.find('input[name="terms"]');
            if ($terms.length && !$terms.prop('checked')) {
                $terms.closest('.hikmah-field')
                    .find('.hikmah-field-error')
                    .text(self.config.i18n.required);
                isValid = false;
            }

            if (!isValid) {
                $form.find('.hikmah-input-error').first().focus();
                this.shakeForm($form);
            }

            return isValid;
        },

        /**
         * Validate a single registration field
         */
        validateRegisterField($input) {
            const name = $input.attr('name');
            const value = $input.val().trim();
            const $error = $input.closest('.hikmah-field').find('.hikmah-field-error');
            let isValid = true;
            let message = '';

            switch (name) {
                case 'first_name':
                case 'last_name':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    }
                    break;

                case 'username':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    } else if (value.length < 3) {
                        isValid = false;
                        message = this.config.i18n.usernameTooShort || 'Username must be at least 3 characters.';
                    }
                    break;

                case 'email':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    } else if (!this.isValidEmail(value)) {
                        isValid = false;
                        message = this.config.i18n.invalidEmail;
                    }
                    break;

                case 'password':
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    } else if (value.length < 8) {
                        isValid = false;
                        message = this.config.i18n.passwordTooShort || 'Password must be at least 8 characters.';
                    }
                    break;

                case 'confirm_password':
                    const $password = $input.closest('.hikmah-login-form').find('input[name="password"]');
                    if (!value) {
                        isValid = false;
                        message = this.config.i18n.required;
                    } else if (value !== $password.val()) {
                        isValid = false;
                        message = this.config.i18n.passwordMismatch;
                    }
                    break;
            }

            if (!isValid) {
                $input.addClass('hikmah-input-error');
                $error.text(message);
            } else {
                $input.removeClass('hikmah-input-error');
                $error.text('');
            }

            return isValid;
        },

        /**
         * Show field-specific errors returned from server
         */
        showFieldErrors($form, errors) {
            const self = this;

            // Clear existing errors
            $form.find('.hikmah-field-error').text('');
            $form.find('.hikmah-input').removeClass('hikmah-input-error');

            // Display errors
            Object.keys(errors).forEach(function (field) {
                const message = errors[field];
                const $error = $form.find('.hikmah-field-error[data-field="' + field + '"]');

                if ($error.length) {
                    $error.text(message);
                    const $input = $form.find('[name="' + field + '"]');
                    if ($input.length) {
                        $input.addClass('hikmah-input-error');
                    }
                }
            });
        },

        /**
         * =============================================
         * PASSWORD STRENGTH METER
         * =============================================
         */

        bindPasswordStrength() {
            const self = this;

            $(document).on('input', '.hikmah-register-form input[name="password"]', function () {
                const $input = $(this);
                const value = $input.val();
                const $container = $input.closest('.hikmah-field');
                const $strength = $container.find('.hikmah-password-strength');
                const $bar = $strength.find('.hikmah-strength-fill');
                const $text = $strength.find('.hikmah-strength-text');

                // Show meter only when typing
                if (value.length > 0) {
                    $strength.addClass('hikmah-visible');
                } else {
                    $strength.removeClass('hikmah-visible');
                }

                // Calculate strength
                const score = self.calculatePasswordStrength(value);
                $bar.attr('data-strength', score);

                const labels = {
                    0: '',
                    1: this.config.i18n.strengthWeak || 'Weak',
                    2: this.config.i18n.strengthFair || 'Fair',
                    3: this.config.i18n.strengthGood || 'Good',
                    4: this.config.i18n.strengthStrong || 'Strong'
                };

                $text.attr('data-strength', score).text(labels[score]);

                // Update requirements checklist
                self.updateRequirements($container, value);
            });
        },

        /**
         * Calculate password strength score (0-4)
         */
        calculatePasswordStrength(password) {
            let score = 0;

            if (!password) return 0;

            // Length
            if (password.length >= 8) score++;

            // Uppercase + lowercase
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;

            // Numbers
            if (/\d/.test(password)) score++;

            // Special characters
            if (/[^A-Za-z0-9]/.test(password)) score++;

            return score;
        },

        /**
         * Update password requirements checklist
         */
        updateRequirements($container, password) {
            const $list = $container.find('.hikmah-password-requirements');
            if (!$list.length) return;

            $list.find('li').each(function () {
                const $item = $(this);
                const rule = $item.data('rule');
                let passed = false;

                switch (rule) {
                    case 'length':
                        passed = password.length >= 8;
                        break;
                    case 'upper':
                        passed = /[A-Z]/.test(password);
                        break;
                    case 'lower':
                        passed = /[a-z]/.test(password);
                        break;
                    case 'number':
                        passed = /\d/.test(password);
                        break;
                    case 'special':
                        passed = /[^A-Za-z0-9]/.test(password);
                        break;
                }

                $item.removeClass('hikmah-req-pending hikmah-req-pass hikmah-req-fail');
                $item.addClass(passed ? 'hikmah-req-pass' : 'hikmah-req-fail');
            });
        },

        /**
         * =============================================
         * AVAILABILITY CHECKS (Username / Email)
         * =============================================
         */

        bindAvailabilityCheck() {
            const self = this;
            const selector = '.hikmah-register-form input[name="username"][data-validate="username"], .hikmah-register-form input[name="email"][data-validate="email"]';

            // Debounced on blur
            $(document).on('blur', selector, function () {
                const $input = $(this);
                const name = $input.attr('name');
                const value = $input.val().trim();

                if (!value) return;
                if (name === 'username' && value.length < 3) return;
                if (name === 'email' && !self.isValidEmail(value)) return;

                self.checkAvailability(name, value, $input);

                // Clear hint when user edits
                $(this).one('input', function () {
                    $input.closest('.hikmah-field')
                        .find('.hikmah-field-hint')
                        .removeAttr('data-availability')
                        .text('');
                });
            });
        },

        /**
         * AJAX check if username/email is available
         */
        checkAvailability(type, value, $input) {
            const self = this;
            const action = type === 'username' ? 'check_username' : 'check_email';
            const $hint = $input.closest('.hikmah-field').find('.hikmah-field-hint');

            // Show checking state
            $hint.attr('data-availability', 'checking')
                .text(self.config.i18n.checkingAvailability || 'Checking availability...');

            const data = {};
            if (type === 'username') {
                data.username = value;
            } else {
                data.email = value;
            }

            HikmahAjax.request(action, data)
                .then(function (response) {
                    if (response.data && response.data.available) {
                        $hint.attr('data-availability', 'available')
                            .text(response.message)
                            .css('color', 'var(--hikmah-success)');
                        $input.removeClass('hikmah-input-error');
                    } else {
                        $hint.attr('data-availability', 'taken')
                            .text(response.message || self.config.i18n.notAvailable || 'Not available')
                            .css('color', 'var(--hikmah-error)');
                        $input.addClass('hikmah-input-error');
                    }
                })
                .catch(function () {
                    $hint.attr('data-availability', 'error')
                        .text('')
                        .css('color', '');
                });
        },

        /**
         * Bind 2FA verification form interactions.
         */
        bind2FAForm() {
            // Stub — 2FA verification flow to be implemented in a later step.
        },

        /**
         * =============================================
         * VERIFICATION RESEND
         * =============================================
         */

        bindVerificationResend() {
            const self = this;

            // Resend button (logged-in state)
            $(document).on('click', '.hikmah-resend-btn', function (e) {
                e.preventDefault();

                const $btn = $(this);
                const originalText = $btn.text();

                $btn.prop('disabled', true).text(self.config.i18n.processing);

                HikmahAjax.resendVerification({})
                    .then(function (response) {
                        $btn.text('✅ Email Sent!').css('background', '#10b981');
                        setTimeout(function () {
                            $btn.text(originalText).css('background', '').prop('disabled', false);
                        }, 3000);
                    })
                    .catch(function (response) {
                        alert((response && response.message) || self.config.i18n.networkError);
                        $btn.text(originalText).prop('disabled', false);
                    });
            });

            // Resend form (expired state)
            $(document).on('submit', '.hikmah-resend-form', function (e) {
                e.preventDefault();

                const $form = $(this);
                const email = $form.find('input[name="email"]').val().trim();
                const $btn = $form.find('button');

                if (!email || !self.isValidEmail(email)) {
                    alert(self.config.i18n.invalidEmail);
                    return;
                }

                $btn.prop('disabled', true).text(self.config.i18n.processing);

                HikmahAjax.resendVerification({ email: email })
                    .then(function (response) {
                        $form.html(
                            '<div class="hikmah-notice hikmah-notice-success hikmah-fade-in">' +
                                '<p>✅ ' + response.message + '</p>' +
                            '</div>'
                        );
                    })
                    .catch(function (response) {
                        alert((response && response.message) || self.config.i18n.networkError);
                        $btn.prop('disabled', false).text('Resend Verification Email');
                    });
            });
        }
    };

    // Initialize on DOM ready
    $(document).ready(function () {
        HikmahLogin.init();
    });

})(jQuery);
