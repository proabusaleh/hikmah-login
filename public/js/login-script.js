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

(function($) {
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
            this.bindLogoutLinks();
            this.bindPasswordToggle();
            this.bindNoticeDismiss();
            this.bind2FAForm();
            this.bindFormValidation();
            this.initCaptcha();
        },

        /**
         * =============================================
         * LOGIN FORM
         * =============================================
         */

        bindLoginForm() {
            const self = this;

            $(document).on('submit', '.hikmah-login-form', function(e) {
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
                        grecaptcha.ready(function() {
                            grecaptcha.execute(self.config.recaptchaKey, { action: 'login' })
                                .then(function(token) {
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
            $(document).on('submit', '.hikmah-2fa-form', function(e) {
                e.preventDefault();
                selfBound.handle2FASubmit($(this));
            });
        },

        /**
         * Submit login via AJAX
         */
        submitLogin($form, formData, $submit, $btnText, $btnLoading) {
            const self = this;

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success(response) {
                    if (response.success) {
                        // Check if 2FA is required
                        if (response.data && response.data.requires_2fa) {
                            self.show2FAForm($form, response.data);
                            self.resetButton($submit, $btnText, $btnLoading);
                            return;
                        }

                        // Login successful — redirect
                        self.showNotice($form, 'success', response.message);

                        if (response.data && response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 800);
                        } else {
                            setTimeout(function() {
                                window.location.reload();
                            }, 800);
                        }
                    } else {
                        // Login failed
                        self.showNotice($form, 'error', response.message);
                        self.shakeForm($form);
                        self.resetButton($submit, $btnText, $btnLoading);

                        // Highlight specific field if indicated
                        if (response.data && response.data.field) {
                            self.highlightField($form, response.data.field);
                        }
                    }
                },
                error(xhr) {
                    let message = self.config.i18n.error;

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    } else if (xhr.status === 0) {
                        message = self.config.i18n.networkError;
                    }

                    self.showNotice($form, 'error', message);
                    self.shakeForm($form);
                    self.resetButton($submit, $btnText, $btnLoading);
                }
            });
        },

        /**
         * =============================================
         * LOGOUT
         * =============================================
         */

        bindLogoutLinks() {
            const self = this;

            $(document).on('click', '.hikmah-logout-link, .hikmah-ajax-logout', function(e) {
                e.preventDefault();

                const $link = $(this);
                const redirect = $link.data('redirect') || '';

                if (!confirm(self.config.i18n.confirmLogout)) {
                    return;
                }

                $.post(self.config.ajaxUrl, {
                    action: 'hikmah_logout',
                    hikmah_login_nonce: self.config.nonce,
                    redirect: redirect
                }, function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
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
            $(document).on('click', '.hikmah-toggle-password', function() {
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

            $.post(self.config.ajaxUrl, {
                action: 'hikmah_verify_2fa',
                hikmah_login_nonce: self.config.nonce,
                user_id: userId,
                code: code,
                remember: $container.find('input[name="remember"]').val(),
                redirect: $container.find('input[name="redirect_to"]').val()
            }, function(response) {
                if (response.success) {
                    self.showNotice($form, 'success', response.message);
                    if (response.data && response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 800);
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 800);
                    }
                } else {
                    self.showNotice($form, 'error', response.message);
                    self.shakeForm($form);
                    $container.find('input[name="2fa_code"]').val('').focus();
                }
            }).fail(function() {
                self.showNotice($form, 'error', self.config.i18n.networkError);
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
            setTimeout(function() {
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
            $(document).on('blur', '.hikmah-login-form .hikmah-input', function() {
                HikmahLogin.validateField($(this));
            });

            // Clear error on focus
            $(document).on('focus', '.hikmah-login-form .hikmah-input', function() {
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

            $form.find('.hikmah-input[required]').each(function() {
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
            window.hikmahRecaptchaCallback = function(token) {
                $('.hikmah-login-form input[name="captcha_response"]').val(token);
            };

            window.hikmahRecaptchaExpired = function() {
                $('.hikmah-login-form input[name="captcha_response"]').val('');
            };

            // Turnstile callback
            window.hikmahTurnstileCallback = function(token) {
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
                setTimeout(function() {
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
            setTimeout(function() {
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
            $(document).on('click', '.hikmah-notice-close', function() {
                $(this).closest('.hikmah-notice').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        HikmahLogin.init();
    });

})(jQuery);
