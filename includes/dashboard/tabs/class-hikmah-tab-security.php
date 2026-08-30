<?php
/**
 * Dashboard Security Tab
 *
 * Handles password changes and 2FA management.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Tab_Security {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('hikmah_dashboard_process_change_password', [$this, 'process_password_change']);
        add_action('hikmah_dashboard_ajax_toggle_2fa', [$this, 'ajax_toggle_2fa']);
        add_action('hikmah_dashboard_ajax_generate_2fa_secret', [$this, 'ajax_generate_2fa_secret']);
        add_action('hikmah_dashboard_ajax_verify_2fa_setup', [$this, 'ajax_verify_2fa_setup']);
        add_action('hikmah_dashboard_ajax_generate_backup_codes', [$this, 'ajax_generate_backup_codes']);
    }

    /**
     * Render security tab
     *
     * @return void
     */
    public function render() {
        $user = wp_get_current_user();
        ?>

        <div class="hikmah-tab-security">

            <!-- Password Change Section -->
            <?php $this->render_password_section($user); ?>

            <!-- Two-Factor Authentication Section -->
            <?php $this->render_2fa_section($user); ?>

            <?php
            /**
             * Action to add custom security sections
             *
             * @param WP_User $user Current user.
             */
            do_action('hikmah_dashboard_security_sections', $user);
            ?>

        </div>

        <?php
    }

    /**
     * Render password change section
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_password_section($user) {
        $last_changed = get_user_meta($user->ID, '_hikmah_password_last_changed', true);
        ?>

        <div class="hikmah-tab-security__section" id="password-section">
            <div class="hikmah-tab-security__section-header">
                <h4 class="hikmah-tab-security__section-title">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e('Change Password', 'hikmah-login'); ?>
                </h4>
                <?php if ($last_changed) : ?>
                    <p class="hikmah-tab-security__section-meta">
                        <?php
                        printf(
                            /* translators: %s: human-readable time difference */
                            esc_html__('Last changed %s ago', 'hikmah-login'),
                            esc_html(human_time_diff($last_changed, current_time('timestamp')))
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </div>

            <form method="post" class="hikmah-tab-security__form" id="hikmah-password-form">

                <?php wp_nonce_field('hikmah_change_password', '_hikmah_password_nonce'); ?>
                <input type="hidden" name="hikmah_dashboard_action" value="change_password" />

                <!-- Current Password -->
                <div class="hikmah-form-group">
                    <label for="hikmah_current_password" class="hikmah-form-label">
                        <?php esc_html_e('Current Password', 'hikmah-login'); ?>
                        <span class="hikmah-form-required">*</span>
                    </label>
                    <div class="hikmah-form-input-wrapper">
                        <input type="password"
                               id="hikmah_current_password"
                               name="current_password"
                               class="hikmah-form-input"
                               required
                               autocomplete="current-password" />
                        <button type="button" class="hikmah-password-toggle" data-target="hikmah_current_password"
                                aria-label="<?php esc_attr_e('Toggle password visibility', 'hikmah-login'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="hikmah-form-group">
                    <label for="hikmah_new_password" class="hikmah-form-label">
                        <?php esc_html_e('New Password', 'hikmah-login'); ?>
                        <span class="hikmah-form-required">*</span>
                    </label>
                    <div class="hikmah-form-input-wrapper">
                        <input type="password"
                               id="hikmah_new_password"
                               name="new_password"
                               class="hikmah-form-input"
                               required
                               minlength="8"
                               autocomplete="new-password" />
                        <button type="button" class="hikmah-password-toggle" data-target="hikmah_new_password"
                                aria-label="<?php esc_attr_e('Toggle password visibility', 'hikmah-login'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>

                    <!-- Password Strength Meter -->
                    <div class="hikmah-password-strength" id="hikmah-password-strength">
                        <div class="hikmah-password-strength__bar">
                            <div class="hikmah-password-strength__fill" id="hikmah-password-strength-fill"></div>
                        </div>
                        <span class="hikmah-password-strength__text" id="hikmah-password-strength-text"></span>
                    </div>

                    <!-- Password Requirements -->
                    <div class="hikmah-password-requirements" id="hikmah-password-requirements">
                        <p class="hikmah-form-description">
                            <?php esc_html_e('Password must meet the following requirements:', 'hikmah-login'); ?>
                        </p>
                        <ul class="hikmah-password-requirements__list">
                            <li class="hikmah-password-requirements__item" data-rule="length">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('At least 8 characters', 'hikmah-login'); ?>
                            </li>
                            <li class="hikmah-password-requirements__item" data-rule="uppercase">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('One uppercase letter', 'hikmah-login'); ?>
                            </li>
                            <li class="hikmah-password-requirements__item" data-rule="lowercase">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('One lowercase letter', 'hikmah-login'); ?>
                            </li>
                            <li class="hikmah-password-requirements__item" data-rule="number">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('One number', 'hikmah-login'); ?>
                            </li>
                            <li class="hikmah-password-requirements__item" data-rule="special">
                                <span class="dashicons dashicons-minus"></span>
                                <?php esc_html_e('One special character', 'hikmah-login'); ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Confirm New Password -->
                <div class="hikmah-form-group">
                    <label for="hikmah_confirm_password" class="hikmah-form-label">
                        <?php esc_html_e('Confirm New Password', 'hikmah-login'); ?>
                        <span class="hikmah-form-required">*</span>
                    </label>
                    <div class="hikmah-form-input-wrapper">
                        <input type="password"
                               id="hikmah_confirm_password"
                               name="confirm_password"
                               class="hikmah-form-input"
                               required
                               autocomplete="new-password" />
                        <button type="button" class="hikmah-password-toggle" data-target="hikmah_confirm_password"
                                aria-label="<?php esc_attr_e('Toggle password visibility', 'hikmah-login'); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <div class="hikmah-password-match" id="hikmah-password-match" style="display:none;">
                        <span class="dashicons"></span>
                        <span class="hikmah-password-match__text"></span>
                    </div>
                </div>

                <!-- Logout other sessions option -->
                <div class="hikmah-form-group">
                    <label class="hikmah-form-checkbox">
                        <input type="checkbox" name="logout_other_sessions" value="1" checked />
                        <span class="hikmah-form-checkbox__label">
                            <?php esc_html_e('Log out of all other sessions after changing password', 'hikmah-login'); ?>
                        </span>
                    </label>
                </div>

                <div class="hikmah-form-actions">
                    <button type="submit" class="hikmah-btn hikmah-btn--primary" id="hikmah-change-password-btn">
                        <?php esc_html_e('Change Password', 'hikmah-login'); ?>
                    </button>
                </div>

            </form>
        </div>

        <?php
    }

    /**
     * Render 2FA management section
     *
     * @param WP_User $user Current user.
     * @return void
     */
    private function render_2fa_section($user) {
        $twofa_enabled = get_user_meta($user->ID, '_hikmah_2fa_enabled', true);
        $twofa_method  = get_user_meta($user->ID, '_hikmah_2fa_method', true) ?: 'totp';
        $backup_codes  = get_user_meta($user->ID, '_hikmah_2fa_backup_codes', true);
        $backup_count  = is_array($backup_codes) ? count(array_filter($backup_codes, function($code) {
            return !empty($code) && !isset($code['used']);
        })) : 0;
        ?>

        <div class="hikmah-tab-security__section" id="twofa-section">
            <div class="hikmah-tab-security__section-header">
                <h4 class="hikmah-tab-security__section-title">
                    <span class="dashicons dashicons-shield-alt"></span>
                    <?php esc_html_e('Two-Factor Authentication', 'hikmah-login'); ?>
                </h4>
                <span class="hikmah-tab-security__status hikmah-tab-security__status--<?php echo $twofa_enabled ? 'enabled' : 'disabled'; ?>">
                    <?php echo $twofa_enabled
                        ? esc_html__('Enabled', 'hikmah-login')
                        : esc_html__('Disabled', 'hikmah-login'); ?>
                </span>
            </div>

            <div class="hikmah-tab-security__section-body">

                <?php if (!$twofa_enabled) : ?>

                    <!-- 2FA Setup -->
                    <div class="hikmah-2fa-setup" id="hikmah-2fa-setup">
                        <p class="hikmah-tab-security__description">
                            <?php esc_html_e(
                                'Add an extra layer of security to your account by enabling two-factor authentication. You will need an authenticator app like Google Authenticator or Authy.',
                                'hikmah-login'
                            ); ?>
                        </p>

                        <button type="button"
                                class="hikmah-btn hikmah-btn--primary"
                                id="hikmah-enable-2fa">
                            <span class="dashicons dashicons-shield-alt"></span>
                            <?php esc_html_e('Enable 2FA', 'hikmah-login'); ?>
                        </button>

                        <!-- Setup Steps (shown after clicking Enable) -->
                        <div class="hikmah-2fa-setup__steps" id="hikmah-2fa-steps" style="display:none;">

                            <div class="hikmah-2fa-setup__step" id="hikmah-2fa-step-1">
                                <h5><?php esc_html_e('Step 1: Scan QR Code', 'hikmah-login'); ?></h5>
                                <p><?php esc_html_e('Scan this QR code with your authenticator app:', 'hikmah-login'); ?></p>
                                <div class="hikmah-2fa-setup__qr" id="hikmah-2fa-qr">
                                    <div class="hikmah-spinner"></div>
                                </div>
                                <div class="hikmah-2fa-setup__manual" id="hikmah-2fa-manual" style="display:none;">
                                    <p><?php esc_html_e('Or enter this key manually:', 'hikmah-login'); ?></p>
                                    <code class="hikmah-2fa-setup__secret" id="hikmah-2fa-secret"></code>
                                    <button type="button" class="hikmah-btn hikmah-btn--sm hikmah-btn--outline"
                                            id="hikmah-2fa-copy-secret">
                                        <?php esc_html_e('Copy', 'hikmah-login'); ?>
                                    </button>
                                </div>
                            </div>

                            <div class="hikmah-2fa-setup__step" id="hikmah-2fa-step-2">
                                <h5><?php esc_html_e('Step 2: Verify Code', 'hikmah-login'); ?></h5>
                                <p><?php esc_html_e('Enter the 6-digit code from your authenticator app:', 'hikmah-login'); ?></p>
                                <div class="hikmah-form-group">
                                    <input type="text"
                                           id="hikmah-2fa-verify-code"
                                           class="hikmah-form-input hikmah-form-input--code"
                                           maxlength="6"
                                           pattern="[0-9]{6}"
                                           placeholder="000000"
                                           autocomplete="one-time-code"
                                           inputmode="numeric" />
                                </div>
                                <button type="button"
                                        class="hikmah-btn hikmah-btn--primary"
                                        id="hikmah-2fa-verify-btn">
                                    <?php esc_html_e('Verify & Enable', 'hikmah-login'); ?>
                                </button>
                            </div>

                        </div>
                    </div>

                <?php else : ?>

                    <!-- 2FA Enabled - Management -->
                    <div class="hikmah-2fa-manage">

                        <p class="hikmah-tab-security__description">
                            <?php esc_html_e('Two-factor authentication is currently enabled on your account.', 'hikmah-login'); ?>
                        </p>

                        <!-- Backup Codes -->
                        <div class="hikmah-2fa-manage__backup">
                            <h5><?php esc_html_e('Backup Codes', 'hikmah-login'); ?></h5>
                            <p>
                                <?php
                                printf(
                                    /* translators: %d: number of remaining backup codes */
                                    esc_html__('You have %d backup codes remaining.', 'hikmah-login'),
                                    $backup_count
                                );
                                ?>
                            </p>
                            <button type="button"
                                    class="hikmah-btn hikmah-btn--secondary hikmah-btn--sm"
                                    id="hikmah-regenerate-backup-codes">
                                <?php esc_html_e('Regenerate Backup Codes', 'hikmah-login'); ?>
                            </button>
                            <div id="hikmah-backup-codes-display" style="display:none;"></div>
                        </div>

                        <!-- Disable 2FA -->
                        <div class="hikmah-2fa-manage__disable">
                            <h5><?php esc_html_e('Disable 2FA', 'hikmah-login'); ?></h5>
                            <p class="hikmah-form-description">
                                <?php esc_html_e('Disabling 2FA will make your account less secure.', 'hikmah-login'); ?>
                            </p>
                            <button type="button"
                                    class="hikmah-btn hikmah-btn--danger hikmah-btn--sm"
                                    id="hikmah-disable-2fa">
                                <?php esc_html_e('Disable 2FA', 'hikmah-login'); ?>
                            </button>
                        </div>

                    </div>

                <?php endif; ?>

            </div>
        </div>

        <?php
    }

    /**
     * Process password change form
     *
     * @return void
     */
    public function process_password_change() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_hikmah_password_nonce'] ?? '', 'hikmah_change_password')) {
            Hikmah_Dashboard::set_notice('error', __('Security check failed.', 'hikmah-login'));
            return;
        }

        $user = wp_get_current_user();

        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate current password
        if (!wp_check_password($current_password, $user->user_pass, $user->ID)) {
            Hikmah_Dashboard::set_notice('error', __('Current password is incorrect.', 'hikmah-login'));
            wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security'));
            exit;
        }

        // Validate new password
        if (strlen($new_password) < 8) {
            Hikmah_Dashboard::set_notice('error', __('New password must be at least 8 characters long.', 'hikmah-login'));
            wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security'));
            exit;
        }

        // Password complexity check
        $errors = [];
        if (!preg_match('/[A-Z]/', $new_password)) {
            $errors[] = __('uppercase letter', 'hikmah-login');
        }
        if (!preg_match('/[a-z]/', $new_password)) {
            $errors[] = __('lowercase letter', 'hikmah-login');
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            $errors[] = __('number', 'hikmah-login');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $errors[] = __('special character', 'hikmah-login');
        }

        if (!empty($errors)) {
            Hikmah_Dashboard::set_notice(
                'error',
                sprintf(
                    /* translators: %s: comma-separated list of missing requirements */
                    __('Password must contain at least one: %s', 'hikmah-login'),
                    implode(', ', $errors)
                )
            );
            wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security'));
            exit;
        }

        // Check passwords match
        if ($new_password !== $confirm_password) {
            Hikmah_Dashboard::set_notice('error', __('New passwords do not match.', 'hikmah-login'));
            wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security'));
            exit;
        }

        // Check new password is different from current
        if (wp_check_password($new_password, $user->user_pass, $user->ID)) {
            Hikmah_Dashboard::set_notice('error', __('New password must be different from current password.', 'hikmah-login'));
            wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security'));
            exit;
        }

        /**
         * Action before password is changed
         *
         * @param WP_User $user Current user.
         */
        do_action('hikmah_before_password_change', $user);

        // Change password
        wp_set_password($new_password, $user->ID);

        // Update last changed timestamp
        update_user_meta($user->ID, '_hikmah_password_last_changed', current_time('timestamp'));

        // Log out other sessions if requested
        if (!empty($_POST['logout_other_sessions'])) {
            $sessions = WP_Session_Tokens::get_instance($user->ID);
            $sessions->destroy_all();
        }

        // Re-authenticate the user with new password
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        /**
         * Action after password is changed
         *
         * @param int $user_id User ID.
         */
        do_action('hikmah_password_changed', $user->ID);

        Hikmah_Dashboard::set_notice('success', __('Password changed successfully.', 'hikmah-login'));

        wp_safe_redirect(Hikmah_Dashboard::get_dashboard_url('security', [
            'notice' => 'success',
            'message' => urlencode(__('Password changed successfully.', 'hikmah-login')),
        ]));
        exit;
    }

    /**
     * AJAX: Generate 2FA secret and QR code
     *
     * @return void
     */
    public function ajax_generate_2fa_secret() {
        $user = wp_get_current_user();

        // Check if Hikmah_Two_Factor class exists (from Phase 11)
        if (!class_exists('Hikmah_Two_Factor')) {
            wp_send_json_error(['message' => __('2FA module not available.', 'hikmah-login')]);
        }

        $twofa = Hikmah_Two_Factor::get_instance();
        $secret = $twofa->generate_secret();

        // Store temporarily
        update_user_meta($user->ID, '_hikmah_2fa_temp_secret', $secret);

        // Generate QR code URL
        $site_name = get_bloginfo('name');
        $qr_url = $twofa->get_qr_code_url($secret, $user->user_email, $site_name);

        wp_send_json_success([
            'qr_url' => $qr_url,
            'secret' => $secret,
        ]);
    }

    /**
     * AJAX: Verify 2FA setup code
     *
     * @return void
     */
    public function ajax_verify_2fa_setup() {
        $user = wp_get_current_user();
        $code = sanitize_text_field($_POST['code'] ?? '');

        if (empty($code) || strlen($code) !== 6) {
            wp_send_json_error(['message' => __('Please enter a valid 6-digit code.', 'hikmah-login')]);
        }

        $temp_secret = get_user_meta($user->ID, '_hikmah_2fa_temp_secret', true);
        if (empty($temp_secret)) {
            wp_send_json_error(['message' => __('Setup session expired. Please start again.', 'hikmah-login')]);
        }

        if (!class_exists('Hikmah_Two_Factor')) {
            wp_send_json_error(['message' => __('2FA module not available.', 'hikmah-login')]);
        }

        $twofa = Hikmah_Two_Factor::get_instance();

        if (!$twofa->verify_code($temp_secret, $code)) {
            wp_send_json_error(['message' => __('Invalid code. Please try again.', 'hikmah-login')]);
        }

        // Enable 2FA
        update_user_meta($user->ID, '_hikmah_2fa_secret', $temp_secret);
        update_user_meta($user->ID, '_hikmah_2fa_enabled', true);
        update_user_meta($user->ID, '_hikmah_2fa_method', 'totp');
        delete_user_meta($user->ID, '_hikmah_2fa_temp_secret');

        // Generate backup codes
        $backup_codes = $twofa->generate_backup_codes($user->ID);

        /**
         * Action after 2FA is enabled
         *
         * @param int $user_id User ID.
         */
        do_action('hikmah_2fa_enabled', $user->ID);

        wp_send_json_success([
            'message'      => __('Two-factor authentication enabled successfully!', 'hikmah-login'),
            'backup_codes' => $backup_codes,
        ]);
    }

    /**
     * AJAX: Toggle 2FA off
     *
     * @return void
     */
    public function ajax_toggle_2fa() {
        $user   = wp_get_current_user();
        $action = sanitize_key($_POST['toggle_action'] ?? '');

        if ($action === 'disable') {
            // Require current password for disabling
            $password = $_POST['password'] ?? '';
            if (!wp_check_password($password, $user->user_pass, $user->ID)) {
                wp_send_json_error(['message' => __('Incorrect password.', 'hikmah-login')]);
            }

            delete_user_meta($user->ID, '_hikmah_2fa_secret');
            delete_user_meta($user->ID, '_hikmah_2fa_enabled');
            delete_user_meta($user->ID, '_hikmah_2fa_method');
            delete_user_meta($user->ID, '_hikmah_2fa_backup_codes');
            delete_user_meta($user->ID, '_hikmah_2fa_temp_secret');

            /**
             * Action after 2FA is disabled
             *
             * @param int $user_id User ID.
             */
            do_action('hikmah_2fa_disabled', $user->ID);

            wp_send_json_success([
                'message' => __('Two-factor authentication has been disabled.', 'hikmah-login'),
            ]);
        }

        wp_send_json_error(['message' => __('Invalid action.', 'hikmah-login')]);
    }

    /**
     * AJAX: Regenerate backup codes
     *
     * @return void
     */
    public function ajax_generate_backup_codes() {
        $user = wp_get_current_user();

        $twofa_enabled = get_user_meta($user->ID, '_hikmah_2fa_enabled', true);
        if (!$twofa_enabled) {
            wp_send_json_error(['message' => __('2FA is not enabled.', 'hikmah-login')]);
        }

        if (!class_exists('Hikmah_Two_Factor')) {
            wp_send_json_error(['message' => __('2FA module not available.', 'hikmah-login')]);
        }

        $twofa = Hikmah_Two_Factor::get_instance();
        $backup_codes = $twofa->generate_backup_codes($user->ID);

        wp_send_json_success([
            'backup_codes' => $backup_codes,
            'message'      => __('New backup codes generated. Save them securely!', 'hikmah-login'),
        ]);
    }
}
