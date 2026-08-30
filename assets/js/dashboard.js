/**
 * Hikmah Dashboard JavaScript
 *
 * Handles all front-end dashboard interactions including
 * avatar upload/crop, AJAX forms, session management,
 * password strength, modals, and account deletion.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * Dashboard module
     */
    const HikmahDashboard = {

        /**
         * Cropper instance for avatar
         */
        cropper: null,

        /**
         * Temporary attachment ID for avatar upload
         */
        tempAttachmentId: 0,

        /**
         * Initialize all dashboard functionality
         */
        init: function () {
            this.bindNotices();
            this.bindPasswordToggle();
            this.bindPasswordStrength();
            this.bindPasswordMatch();
            this.bindAvatarUpload();
            this.bindAvatarRemove();
            this.bindSessionManagement();
            this.bindSocialDisconnect();
            this.bindDeleteAccount();
            this.bindDataExport();
            this.bind2FA();
            this.bindMobileNav();
        },

        // ─────────────────────────────────────────────
        // NOTICES
        // ─────────────────────────────────────────────

        /**
         * Bind notice dismiss buttons
         */
        bindNotices: function () {
            $(document).on('click', '.hikmah-dashboard__notice-dismiss', function () {
                $(this).closest('.hikmah-dashboard__notice').fadeOut(300, function () {
                    $(this).remove();
                });
            });

            // Auto-dismiss success notices after 5 seconds
            setTimeout(function () {
                $('.hikmah-dashboard__notice--success').fadeOut(300, function () {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Render a dynamic notification banner
         * 
         * @param {string} type Notice type ('success', 'error', 'info', 'warning').
         * @param {string} message Text message to display.
         */
        showNotice: function (type, message) {
            $('.hikmah-dashboard__notices-wrapper').find('.hikmah-dashboard__notice').remove();
            
            const noticeHtml = `
                <div class="hikmah-dashboard__notice hikmah-dashboard__notice--${type}">
                    <div class="hikmah-dashboard__notice-content">
                        <span class="dashicons ${type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'}"></span>
                        <p>${message}</p>
                    </div>
                    <button type="button" class="hikmah-dashboard__notice-dismiss">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            
            const $wrapper = $('.hikmah-dashboard__notices-wrapper');
            if ($wrapper.length) {
                $wrapper.prepend(noticeHtml);
            } else {
                $('.hikmah-dashboard').prepend(`<div class="hikmah-dashboard__notices-wrapper">${noticeHtml}</div>`);
            }

            if (type === 'success') {
                setTimeout(function () {
                    $('.hikmah-dashboard__notice--success').fadeOut(300, function () {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        // ─────────────────────────────────────────────
        // PASSWORD TOGGLE
        // ─────────────────────────────────────────────

        /**
         * Toggle password field visibility
         */
        bindPasswordToggle: function () {
            $(document).on('click', '.hikmah-password-toggle', function () {
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

        // ─────────────────────────────────────────────
        // PASSWORD STRENGTH
        // ─────────────────────────────────────────────

        /**
         * Real-time password strength meter
         */
        bindPasswordStrength: function () {
            const self = this;

            $(document).on('input', '#hikmah_new_password', function () {
                const password = $(this).val();
                const result = self.checkPasswordStrength(password);

                // Update strength bar
                const $fill = $('#hikmah-password-strength-fill');
                const $text = $('#hikmah-password-strength-text');

                $fill.css('width', result.percentage + '%');
                $fill.attr('class', 'hikmah-password-strength__fill hikmah-password-strength__fill--' + result.level);
                $text.text(result.label);

                // Update requirement checklist
                self.updatePasswordRequirements(password);
            });
        },

        /**
         * Check password strength
         *
         * @param {string} password Password to check.
         * @returns {Object} Strength result.
         */
        checkPasswordStrength: function (password) {
            let score = 0;
            const i18n = hikmahDashboard.i18n;

            if (password.length === 0) {
                return { level: 'empty', percentage: 0, label: '' };
            }

            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            if (password.length >= 16) score++;

            let level, percentage, label;

            if (score <= 2) {
                level = 'weak';
                percentage = 25;
                label = i18n.passwordWeak;
            } else if (score <= 4) {
                level = 'medium';
                percentage = 50;
                label = i18n.passwordMedium;
            } else {
                level = 'strong';
                percentage = 100;
                label = i18n.passwordStrong;
            }

            return { level, percentage, label };
        },

        /**
         * Update password requirement checklist
         *
         * @param {string} password Current password value.
         */
        updatePasswordRequirements: function (password) {
            const rules = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password),
            };

            $.each(rules, function (rule, passed) {
                const $item = $('[data-rule="' + rule + '"]');
                const $icon = $item.find('.dashicons');

                if (passed) {
                    $item.addClass('hikmah-password-requirements__item--pass')
                         .removeClass('hikmah-password-requirements__item--fail');
                    $icon.removeClass('dashicons-minus dashicons-no')
                         .addClass('dashicons-yes');
                } else if (password.length > 0) {
                    $item.addClass('hikmah-password-requirements__item--fail')
                         .removeClass('hikmah-password-requirements__item--pass');
                    $icon.removeClass('dashicons-minus dashicons-yes')
                         .addClass('dashicons-no');
                } else {
                    $item.removeClass('hikmah-password-requirements__item--pass hikmah-password-requirements__item--fail');
                    $icon.removeClass('dashicons-yes dashicons-no')
                         .addClass('dashicons-minus');
                }
            });
        },

        // ─────────────────────────────────────────────
        // PASSWORD MATCH
        // ─────────────────────────────────────────────

        /**
         * Check if new password and confirm password match
         */
        bindPasswordMatch: function () {
            $(document).on('input', '#hikmah_confirm_password', function () {
                const newPass = $('#hikmah_new_password').val();
                const confirmPass = $(this).val();
                const $match = $('#hikmah-password-match');

                if (confirmPass.length === 0) {
                    $match.hide();
                    return;
                }

                $match.show();
                const $icon = $match.find('.dashicons');
                const $text = $match.find('.hikmah-password-match__text');

                if (newPass === confirmPass) {
                    $match.removeClass('hikmah-password-match--no')
                          .addClass('hikmah-password-match--yes');
                    $icon.removeClass('dashicons-no').addClass('dashicons-yes');
                    $text.text(hikmahDashboard.i18n.passwordsMatch || 'Passwords match');
                } else {
                    $match.removeClass('hikmah-password-match--yes')
                          .addClass('hikmah-password-match--no');
                    $icon.removeClass('dashicons-yes').addClass('dashicons-no');
                    $text.text(hikmahDashboard.i18n.passwordsNoMatch || 'Passwords do not match');
                }
            });
        },

        // ─────────────────────────────────────────────
        // AVATAR UPLOAD & CROP
        // ─────────────────────────────────────────────

        /**
         * Bind avatar upload and crop functionality
         */
        bindAvatarUpload: function () {
            const self = this;

            // File selection
            $(document).on('change', '#hikmah-avatar-upload', function (e) {
                const file = e.target.files[0];
                if (!file) return;

                // Validate client-side
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    self.showNotice('error', 'Invalid file type. Please upload JPG, PNG, GIF, or WebP.');
                    return;
                }

                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    self.showNotice('error', 'File too large. Maximum size is 5MB.');
                    return;
                }

                // Upload via AJAX
                const formData = new FormData();
                formData.append('action', 'hikmah_dashboard_action');
                formData.append('action_type', 'upload_avatar');
                formData.append('nonce', hikmahDashboard.nonce);
                formData.append('avatar', file);

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        if (response.success) {
                            self.tempAttachmentId = response.data.attachment_id;
                            self.openCropper(response.data.url);
                        } else {
                            self.showNotice('error', response.data.message);
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                    },
                });

                // Reset input
                $(this).val('');
            });

            // Crop save
            $(document).on('click', '#hikmah-crop-save', function () {
                self.saveCroppedAvatar();
            });

            // Crop cancel / close
            $(document).on('click', '#hikmah-crop-cancel, #hikmah-crop-close', function () {
                self.closeCropper();
            });
        },

        /**
         * Open the cropper modal
         *
         * @param {string} imageUrl URL of uploaded image.
         */
        openCropper: function (imageUrl) {
            const $modal = $('#hikmah-crop-modal');
            const $image = $('#hikmah-crop-image');

            $image.attr('src', imageUrl);
            $modal.fadeIn(200);

            $image.on('load', () => {
                if (this.cropper) {
                    this.cropper.destroy();
                }

                this.cropper = new Cropper($image[0], {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    cropBoxResizable: true,
                    cropBoxMovable: true,
                    guides: true,
                    center: true,
                    highlight: false,
                    background: true,
                    responsive: true,
                    minCropBoxWidth: 100,
                    minCropBoxHeight: 100,
                });
            });
        },

        /**
         * Save cropped avatar via AJAX
         */
        saveCroppedAvatar: function () {
            const self = this;

            if (!this.cropper) return;

            const cropData = this.cropper.getData(true);
            const $btn = $('#hikmah-crop-save');

            $btn.prop('disabled', true).text(hikmahDashboard.i18n.saving);

            $.ajax({
                url: hikmahDashboard.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'hikmah_dashboard_action',
                    action_type: 'crop_avatar',
                    nonce: hikmahDashboard.nonce,
                    attachment_id: self.tempAttachmentId,
                    crop_x: cropData.x,
                    crop_y: cropData.y,
                    crop_width: cropData.width,
                    crop_height: cropData.height,
                },
                success: function (response) {
                    if (response.success) {
                        $('.hikmah-dashboard__avatar-img, #hikmah-avatar-preview').attr('src', response.data.url);
                        self.closeCropper();
                        self.showNotice('success', response.data.message);
                    } else {
                        self.showNotice('error', response.data.message);
                    }
                },
                error: function () {
                    self.showNotice('error', hikmahDashboard.i18n.error);
                },
                complete: function () {
                    $btn.prop('disabled', false).text(hikmahDashboard.i18n.cropAvatar);
                },
            });
        },

        /**
         * Close cropper modal and cleanup
         */
        closeCropper: function () {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            $('#hikmah-crop-modal').fadeOut(200);
            $('#hikmah-crop-image').attr('src', '');
        },

        /**
         * Bind avatar remove button
         */
        bindAvatarRemove: function () {
            const self = this;

            $(document).on('click', '#hikmah-avatar-remove', function () {
                if (!confirm('Remove your custom avatar?')) return;

                const $btn = $(this);
                $btn.prop('disabled', true);

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_action',
                        action_type: 'remove_avatar',
                        nonce: hikmahDashboard.nonce,
                    },
                    success: function (response) {
                        if (response.success) {
                            $('.hikmah-dashboard__avatar-img, #hikmah-avatar-preview').attr('src', response.data.url);
                            $btn.fadeOut();
                            self.showNotice('success', response.data.message);
                        } else {
                            self.showNotice('error', response.data.message);
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    },
                });
            });
        },

        // ─────────────────────────────────────────────
        // SESSION MANAGEMENT
        // ─────────────────────────────────────────────

        /**
         * Bind session revoke buttons
         */
        bindSessionManagement: function () {
            const self = this;

            $(document).on('click', '.hikmah-destroy-session', function () {
                const $btn = $(this);
                const token = $btn.data('token');
                const $item = $btn.closest('.hikmah-tab-sessions__item');

                if (!confirm(hikmahDashboard.i18n.confirmLogout)) return;

                $btn.prop('disabled', true).text('...');

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_action',
                        action_type: 'destroy_session',
                        nonce: hikmahDashboard.nonce,
                        token: token,
                    },
                    success: function (response) {
                        if (response.success) {
                            $item.slideUp(300, function () {
                                $(this).remove();
                                self.showNotice('success', response.data.message);

                                if (response.data.remaining_count <= 1) {
                                    $('#hikmah-destroy-all-sessions').fadeOut();
                                }
                            });
                        } else {
                            self.showNotice('error', response.data.message);
                            $btn.prop('disabled', false).text('Revoke');
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                        $btn.prop('disabled', false).text('Revoke');
                    },
                });
            });

            $(document).on('click', '#hikmah-destroy-all-sessions', function () {
                const $btn = $(this);

                if (!confirm('Log out of all other sessions?')) return;

                $btn.prop('disabled', true);

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_action',
                        action_type: 'destroy_all_sessions',
                        nonce: hikmahDashboard.nonce,
                    },
                    success: function (response) {
                        if (response.success) {
                            $('.hikmah-tab-sessions__item:not(.hikmah-tab-sessions__item--current)').slideUp(300, function () {
                                $(this).remove();
                            });
                            $btn.fadeOut();
                            self.showNotice('success', response.data.message);
                        } else {
                            self.showNotice('error', response.data.message);
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    },
                });
            });
        },

        // ─────────────────────────────────────────────
        // SOCIAL DISCONNECT
        // ─────────────────────────────────────────────

        /**
         * Bind social account disconnect buttons
         */
        bindSocialDisconnect: function () {
            const self = this;

            $(document).on('click', '.hikmah-disconnect-social', function () {
                const $btn = $(this);
                const provider = $btn.data('provider');
                const $item = $btn.closest('.hikmah-tab-connected__item');

                if (!confirm('Disconnect your ' + provider + ' account?')) return;

                $btn.prop('disabled', true);

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_action',
                        action_type: 'disconnect_social',
                        nonce: hikmahDashboard.nonce,
                        provider: provider,
                    },
                    success: function (response) {
                        if (response.success) {
                            $item.removeClass('hikmah-tab-connected__item--connected')
                                 .addClass('hikmah-tab-connected__item--disconnected');
                            self.showNotice('success', response.data.message);
                        } else {
                            self.showNotice('error', response.data.message);
                            $btn.prop('disabled', false);
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                        $btn.prop('disabled', false);
                    }
                });
            });
        },

        // ─────────────────────────────────────────────
        // ACCOUNT DELETION (GDPR)
        // ─────────────────────────────────────────────

        /**
         * Handles GDPR-compliant absolute self-deletion confirmation workflow
         */
        bindDeleteAccount: function () {
            const self = this;
            const $modal = $('#hikmah-delete-modal');
            const $triggerBtn = $('#hikmah-delete-account-trigger');
            const $closeBtn = $('#hikmah-delete-modal-close');
            const $cancelBtn = $('#hikmah-delete-cancel');
            const $confirmBtn = $('#hikmah-delete-confirm');

            const $passInput = $('#hikmah-delete-password');
            const $confirmText = $('#hikmah-delete-confirm-text');
            const $understandCheck = $('#hikmah-delete-understand');

            // Opens Delete confirmation modal
            $triggerBtn.on('click', function (e) {
                e.preventDefault();
                $modal.fadeIn(200);
            });

            // Closes modal
            function closeModal() {
                $modal.fadeOut(150);
                $passInput.val('');
                $confirmText.val('');
                $understandCheck.prop('checked', false);
                $confirmBtn.prop('disabled', true);
            }

            $closeBtn.on('click', closeModal);
            $cancelBtn.on('click', closeModal);
            $('.hikmah-modal__overlay').on('click', closeModal);

            // Validation logic to enable confirm button
            function validateDeletionFields() {
                const passwordValue = $passInput.val().trim();
                const textValue = $confirmText.val().trim();
                const isChecked = $understandCheck.is(':checked');

                if (passwordValue.length > 0 && textValue === 'DELETE' && isChecked) {
                    $confirmBtn.prop('disabled', false);
                } else {
                    $confirmBtn.prop('disabled', true);
                }
            }

            $passInput.on('input', validateDeletionFields);
            $confirmText.on('input', validateDeletionFields);
            $understandCheck.on('change', validateDeletionFields);

            // Execution of Account Deletion via AJAX
            $confirmBtn.on('click', function () {
                const password = $passInput.val();
                
                $confirmBtn.prop('disabled', true);
                $confirmBtn.find('.hikmah-btn__text').hide();
                $confirmBtn.find('.hikmah-btn__loading').show();

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_confirm_delete_account',
                        nonce: hikmahDashboard.nonce,
                        password: password
                    },
                    success: function (response) {
                        if (response.success) {
                            self.showNotice('success', response.data.message);
                            setTimeout(function () {
                                window.location.href = response.data.redirect_url;
                            }, 1500);
                        } else {
                            self.showNotice('error', response.data.message);
                            $confirmBtn.prop('disabled', false);
                            $confirmBtn.find('.hikmah-btn__text').show();
                            $confirmBtn.find('.hikmah-btn__loading').hide();
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                        $confirmBtn.prop('disabled', false);
                        $confirmBtn.find('.hikmah-btn__text').show();
                        $confirmBtn.find('.hikmah-btn__loading').hide();
                    }
                });
            });
        },

        // ─────────────────────────────────────────────
        // DATA EXPORT (GDPR)
        // ─────────────────────────────────────────────

        /**
         * Requests generated secure personal files download (Portability rules)
         */
        bindDataExport: function () {
            const self = this;
            const $exportBtn = $('#hikmah-request-export');

            $exportBtn.on('click', function (e) {
                e.preventDefault();
                const format = $('#hikmah-export-format').val();

                $exportBtn.prop('disabled', true).addClass('hikmah-btn--loading');

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_request_data_export',
                        nonce: hikmahDashboard.nonce,
                        format: format
                    },
                    success: function (response) {
                        if (response.success) {
                            self.showNotice('success', response.data.message);
                            // Execute the temporary generated download link in browser
                            if (response.data.download_url) {
                                window.location.href = response.data.download_url;
                            }
                        } else {
                            self.showNotice('error', response.data.message);
                            $exportBtn.prop('disabled', false).removeClass('hikmah-btn--loading');
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                        $exportBtn.prop('disabled', false).removeClass('hikmah-btn--loading');
                    }
                });
            });
        },

        // ─────────────────────────────────────────────
        // TWO-FACTOR AUTHENTICATION (2FA)
        // ─────────────────────────────────────────────

        /**
         * Handle Two-Factor configuration bindings
         */
        bind2FA: function () {
            const self = this;

            $(document).on('change', '#hikmah-2fa-toggle', function () {
                const isEnabled = $(this).is(':checked');
                const $setupSection = $('.hikmah-2fa-setup-section');

                if (isEnabled) {
                    $setupSection.slideDown(250);
                } else {
                    if (confirm('Are you sure you want to disable Two-Factor Authentication?')) {
                        $.ajax({
                            url: hikmahDashboard.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'hikmah_dashboard_action',
                                action_type: 'disable_2fa',
                                nonce: hikmahDashboard.nonce
                            },
                            success: function (response) {
                                if (response.success) {
                                    self.showNotice('success', response.data.message);
                                    $setupSection.slideUp(250);
                                } else {
                                    self.showNotice('error', response.data.message);
                                    $('#hikmah-2fa-toggle').prop('checked', true);
                                }
                            }
                        });
                    } else {
                        $(this).prop('checked', true);
                    }
                }
            });

            // Verify Code & Enable 2FA Setup
            $(document).on('click', '#hikmah-verify-2fa', function () {
                const $btn = $(this);
                const code = $('#hikmah-2fa-code').val();

                $btn.prop('disabled', true).text('Verifying...');

                $.ajax({
                    url: hikmahDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'hikmah_dashboard_action',
                        action_type: 'verify_enable_2fa',
                        nonce: hikmahDashboard.nonce,
                        code: code
                    },
                    success: function (response) {
                        if (response.success) {
                            self.showNotice('success', response.data.message);
                            $('.hikmah-2fa-setup-form').slideUp(200);
                            $('.hikmah-2fa-backup-codes-container').html(response.data.backup_codes_html).slideDown(250);
                        } else {
                            self.showNotice('error', response.data.message);
                            $btn.prop('disabled', false).text('Verify Code');
                        }
                    },
                    error: function () {
                        self.showNotice('error', hikmahDashboard.i18n.error);
                        $btn.prop('disabled', false).text('Verify Code');
                    }
                });
            });
        },

        // ─────────────────────────────────────────────
        // MOBILE RESPONSIVE NAV
        // ─────────────────────────────────────────────

        /**
         * Mobile Nav interactions
         */
        bindMobileNav: function () {
            $(document).on('click', '.hikmah-dashboard__mobile-nav-toggle', function () {
                const $sidebar = $('.hikmah-dashboard__sidebar');
                $sidebar.toggleClass('hikmah-dashboard__sidebar--open');
            });
        }
    };

    // Initialize on document load
    $(document).ready(function () {
        HikmahDashboard.init();
    });

})(jQuery); 
