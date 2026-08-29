/**
 * Hikmah Login — AJAX Manager
 *
 * Centralized AJAX handler for all frontend requests.
 * Features:
 * - Unified endpoint (single AJAX action)
 * - Automatic nonce management & refresh
 * - Request queue & deduplication
 * - Retry logic with exponential backoff
 * - Global error handling
 * - Request/response interceptors
 *
 * @package Hikmah_Login
 * @version 1.0.0
 */

(function($) {
    'use strict';

    const HikmahAjax = {

        /**
         * Configuration
         */
        config: window.hikmahLogin || {},

        /**
         * Active requests (for deduplication)
         */
        activeRequests: {},

        /**
         * Nonce cache
         */
        nonces: {},

        /**
         * Request queue
         */
        queue: [],

        /**
         * Initialize
         */
        init() {
            this.nonces = {
                login: this.config.loginNonce || this.config.nonce || '',
                register: this.config.registerNonce || this.config.nonce || '',
                forgot: this.config.forgotNonce || this.config.nonce || '',
                reset: this.config.resetNonce || this.config.nonce || '',
                general: this.config.generalNonce || this.config.nonce || '',
                rest: this.config.restNonce || ''
            };

            this.setupHeartbeat();
            this.setupGlobalErrorHandling();
        },

        /**
         * =============================================
         * CORE REQUEST METHOD
         * =============================================
         */

        /**
         * Make an AJAX request through the unified endpoint.
         *
         * @param {string} action   Action name (login, register, etc.)
         * @param {Object} data     Request data
         * @param {Object} options  Additional options
         * @returns {Promise}
         */
        request(action, data = {}, options = {}) {
            const self = this;

            const defaults = {
                method: 'POST',
                timeout: 30000,
                retries: 2,
                retryDelay: 1000,
                deduplicate: true,
                showLoading: false,
                loadingElement: null
            };

            const opts = { ...defaults, ...options };

            // Deduplication: prevent same request while pending
            const requestKey = action + '_' + JSON.stringify(data);
            if (opts.deduplicate && this.activeRequests[requestKey]) {
                return this.activeRequests[requestKey];
            }

            // Build form data
            const formData = new FormData();
            formData.append('action', 'hikmah_ajax');
            formData.append('hikmah_action', action);

            // Attach correct nonce
            const nonceKey = this.getNonceKey(action);
            formData.append('hikmah_' + action + '_nonce', this.nonces[nonceKey] || this.nonces.general);
            formData.append('hikmah_nonce', this.nonces.general);

            // Attach data
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            // Show loading
            if (opts.showLoading && opts.loadingElement) {
                $(opts.loadingElement).addClass('hikmah-loading');
            }

            const promise = new Promise((resolve, reject) => {
                const attempt = (retryCount) => {
                    $.ajax({
                        url: self.config.ajaxUrl,
                        type: opts.method,
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        timeout: opts.timeout,

                        success(response, textStatus, xhr) {
                            // Update nonces from response
                            self.updateNonces(response);

                            if (response.success) {
                                resolve(response);
                            } else {
                                // Check if nonce expired
                                if (response.code === 'nonce_failed' && retryCount < opts.retries) {
                                    self.refreshNonces().then(() => {
                                        // Update nonce in form data
                                        formData.set('hikmah_' + action + '_nonce', self.nonces[nonceKey]);
                                        formData.set('hikmah_nonce', self.nonces.general);
                                        attempt(retryCount + 1);
                                    });
                                    return;
                                }

                                reject(response);
                            }
                        },

                        error(xhr, textStatus, errorThrown) {
                            if (retryCount < opts.retries && textStatus !== 'abort') {
                                const delay = opts.retryDelay * Math.pow(2, retryCount);
                                setTimeout(() => attempt(retryCount + 1), delay);
                                return;
                            }

                            reject({
                                success: false,
                                message: textStatus === 'timeout'
                                    ? 'Request timed out. Please try again.'
                                    : 'Network error. Please check your connection.',
                                code: 'network_error',
                                data: {}
                            });
                        },

                        complete() {
                            delete self.activeRequests[requestKey];
                            if (opts.showLoading && opts.loadingElement) {
                                $(opts.loadingElement).removeClass('hikmah-loading');
                            }
                        }
                    });
                };

                attempt(0);
            });

            if (opts.deduplicate) {
                this.activeRequests[requestKey] = promise;
            }

            return promise;
        },

        /**
         * =============================================
         * CONVENIENCE METHODS
         * =============================================
         */

        login(data, options = {}) {
            return this.request('login', data, options);
        },

        register(data, options = {}) {
            return this.request('register', data, options);
        },

        forgotPassword(data, options = {}) {
            return this.request('forgot_password', data, options);
        },

        resetPassword(data, options = {}) {
            return this.request('reset_password', data, options);
        },

        verify2FA(data, options = {}) {
            return this.request('verify_2fa', data, options);
        },

        setup2FA(data = {}, options = {}) {
            return this.request('2fa_setup', data, options);
        },

        verify2FASetup(data = {}, options = {}) {
            return this.request('2fa_verify_setup', data, options);
        },

        disable2FA(data = {}, options = {}) {
            return this.request('2fa_disable', data, options);
        },

        generateBackupCodes(options = {}) {
            return this.request('2fa_generate_backup', {}, options);
        },

        sendLoginOTP(data = {}, options = {}) {
            return this.request('2fa_send_login_otp', data, options);
        },

        logout(data = {}, options = {}) {
            return this.request('logout', data, options);
        },

        checkStatus(options = {}) {
            return this.request('check_status', {}, { ...options, deduplicate: false });
        },

        resendVerification(data = {}, options = {}) {
            return this.request('resend_verification', data, options);
        },

        /**
         * =============================================
         * NONCE MANAGEMENT
         * =============================================
         */

        getNonceKey(action) {
            const map = {
                login: 'login',
                logout: 'login',
                verify_2fa: 'login',
                register: 'register',
                check_username: 'register',
                check_email: 'register',
                forgot_password: 'forgot',
                reset_password: 'reset',
                '2fa_setup': 'general',
                '2fa_verify_setup': 'general',
                '2fa_disable': 'general',
                '2fa_generate_backup': 'general',
                '2fa_send_login_otp': 'general'
            };
            return map[action] || 'general';
        },

        updateNonces(response) {
            if (response.meta && response.meta.nonces) {
                Object.assign(this.nonces, response.meta.nonces);
            }
        },

        refreshNonces() {
            const self = this;

            return new Promise((resolve, reject) => {
                $.post(self.config.ajaxUrl, {
                    action: 'hikmah_refresh_nonce'
                }, function(response) {
                    if (response.success && response.data.nonces) {
                        self.nonces = { ...self.nonces, ...response.data.nonces };
                        resolve(self.nonces);
                    } else {
                        reject('Failed to refresh nonces');
                    }
                }).fail(() => reject('Network error'));
            });
        },

        /**
         * =============================================
         * HEARTBEAT INTEGRATION
         * =============================================
         */

        setupHeartbeat() {
            const self = this;

            $(document).on('heartbeat-send', function(e, data) {
                data.hikmah_heartbeat = true;
            });

            $(document).on('heartbeat-tick', function(e, data) {
                if (data.hikmah_nonces) {
                    self.nonces = { ...self.nonces, ...data.hikmah_nonces };
                }

                if (data.hikmah_session) {
                    if (!data.hikmah_session.logged_in && self.config.isLoggedIn) {
                        // Session expired — reload page
                        window.location.reload();
                    }
                }
            });
        },

        /**
         * =============================================
         * GLOBAL ERROR HANDLING
         * =============================================
         */

        setupGlobalErrorHandling() {
            const self = this;

            $(document).ajaxError(function(event, xhr, settings, error) {
                // Only handle Hikmah AJAX errors
                if (!settings.url || settings.url.indexOf('admin-ajax.php') === -1) {
                    return;
                }

                if (xhr.status === 429) {
                    const response = xhr.responseJSON;
                    if (response && response.data && response.data.retry_after) {
                        self.showRateLimitNotice(response.data.retry_after);
                    }
                }
            });
        },

        showRateLimitNotice(seconds) {
            const $notice = $(
                '<div class="hikmah-notice hikmah-notice-warning hikmah-fade-in" style="position:fixed;top:20px;right:20px;z-index:99999;max-width:360px;">' +
                    '<p>⏳ Too many requests. Please wait ' + seconds + ' seconds.</p>' +
                    '<button class="hikmah-notice-close">&times;</button>' +
                '</div>'
            );

            $('body').append($notice);

            setTimeout(() => $notice.fadeOut(300, function() { $(this).remove(); }), seconds * 1000);
        }
    };

    // Initialize
    $(document).ready(function() {
        HikmahAjax.init();
    });

    // Expose globally
    window.HikmahAjax = HikmahAjax;

})(jQuery);