<?php
/**
 * Front-End Authentication Handler
 *
 * Manages user login, registration, password recovery,
 * rate limiting, and 2FA authentication challenges.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hikmah_Auth_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX Endpoints (Logged out users)
        add_action('wp_ajax_nopriv_hikmah_ajax_login', [$this, 'ajax_login']);
        add_action('wp_ajax_nopriv_hikmah_ajax_register', [$this, 'ajax_register']);
        add_action('wp_ajax_nopriv_hikmah_ajax_lostpassword', [$this, 'ajax_lostpassword']);

        // Non-AJAX Fallback Processors
        add_action('init', [$this, 'process_post_login']);
        add_action('init', [$this, 'process_post_register']);
        add_action('init', [$this, 'process_post_lostpassword']);
    }

    /**
     * Check if client IP is currently rate-limited
     *
     * @param string $ip Client IP address.
     * @return bool True if locked out, false otherwise.
     */
    private function is_ip_locked($ip) {
        if (!hikmah_get_option('limit_attempts', '1')) {
            return false;
        }

        $transient_key = 'hikmah_lockout_' . md5($ip);
        return (bool) get_transient($transient_key);
    }

    /**
     * Record a failed login attempt for rate-limiting
     *
     * @param string $ip Client IP address.
     * @return void
     */
    private function record_failed_attempt($ip) {
        if (!hikmah_get_option('limit_attempts', '1')) {
            return;
        }

        $attempts_key = 'hikmah_attempts_' . md5($ip);
        $attempts     = (int) get_transient($attempts_key);
        $attempts++;

        $max_attempts     = (int) hikmah_get_option('max_login_attempts', 5);
        $lockout_duration = (int) hikmah_get_option('lockout_duration', 60) * MINUTE_IN_SECONDS;

        if ($attempts >= $max_attempts) {
            set_transient('hikmah_lockout_' . md5($ip), true, $lockout_duration);
            delete_transient($attempts_key);
        } else {
            set_transient($attempts_key, $attempts, 15 * MINUTE_IN_SECONDS);
        }
    }

    /**
     * Clear failed login attempts after successful authentication
     *
     * @param string $ip Client IP address.
     * @return void
     */
    private function clear_failed_attempts($ip) {
        delete_transient('hikmah_attempts_' . md5($ip));
        delete_transient('hikmah_lockout_' . md5($ip));
    }

    /**
     * AJAX: Login Processing
     *
     * @return void
     */
    public function ajax_login() {
        check_ajax_referer('hikmah_login_action', 'nonce');

        $ip = hikmah_get_client_ip();

        if ($this->is_ip_locked($ip)) {
            wp_send_json_error([
                'message' => __('Too many failed login attempts. Please try again later.', 'hikmah-login'),
            ]);
        }

        $username = sanitize_text_field($_POST['log'] ?? '');
        $password = $_POST['pwd'] ?? '';
        $remember = !empty($_POST['rememberme']);
        $token_2fa = sanitize_text_field($_POST['hikmah_2fa_token'] ?? '');

        if (empty($username) || empty($password)) {
            wp_send_json_error([
                'message' => __('Please provide both username/email and password.', 'hikmah-login'),
            ]);
        }

        // Resolve user account
        $user = is_email($username) ? get_user_by('email', $username) : get_user_by('login', $username);

        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            $this->record_failed_attempt($ip);
            wp_send_json_error([
                'message' => __('Invalid credentials. Please verify and try again.', 'hikmah-login'),
            ]);
        }

        // Check if 2FA is active on this account
        $is_2fa_enabled = (bool) get_user_meta($user->ID, '_hikmah_2fa_enabled', true);

        if ($is_2fa_enabled) {
            // If 2FA code is not submitted yet, tell front-end to prompt for it
            if (empty($token_2fa)) {
                wp_send_json_success([
                    'requires_2fa' => true,
                    'message'      => __('Please enter the two-factor authentication passcode from your app.', 'hikmah-login'),
                ]);
            }

            // Verify submitted 2FA code
            $secret = get_user_meta($user->ID, '_hikmah_2fa_secret', true);
            $valid  = false;

            if (class_exists('Hikmah_2FA_TOTP')) {
                $valid = Hikmah_2FA_TOTP::verify_code($secret, $token_2fa);
            }

            // Check backup codes fallback if standard TOTP fails
            if (!$valid) {
                $backup_codes = get_user_meta($user->ID, '_hikmah_2fa_backup_codes', true);
                if (is_array($backup_codes) && in_array($token_2fa, $backup_codes, true)) {
                    $valid = true;
                    // Remove used backup code
                    $backup_codes = array_diff($backup_codes, [$token_2fa]);
                    update_user_meta($user->ID, '_hikmah_2fa_backup_codes', $backup_codes);
                }
            }

            if (!$valid) {
                $this->record_failed_attempt($ip);
                wp_send_json_error([
                    'message' => __('Invalid two-factor authentication code.', 'hikmah-login'),
                ]);
            }
        }

        // Perform authentication
        $this->clear_failed_attempts($ip);
        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        // Get redirect URL
        $redirect_url = hikmah_get_option('login_redirect', '');
        if (empty($redirect_url)) {
            $redirect_url = home_url();
        }

        wp_send_json_success([
            'message'      => __('Login successful! Redirecting...', 'hikmah-login'),
            'redirect_url' => esc_url_raw($redirect_url),
        ]);
    }

    /**
     * AJAX: Registration Processing
     *
     * @return void
     */
    public function ajax_register() {
        check_ajax_referer('hikmah_register_action', 'nonce');

        if (!get_option('users_can_register')) {
            wp_send_json_error([
                'message' => __('Registration is currently disabled.', 'hikmah-login'),
            ]);
        }

        $username = sanitize_user($_POST['user_login'] ?? '');
        $email    = sanitize_email($_POST['user_email'] ?? '');
        $password = $_POST['user_pass'] ?? '';
        $terms    = !empty($_POST['hikmah_terms']);

        if (!$terms) {
            wp_send_json_error([
                'message' => __('You must agree to the Terms of Service and Privacy Policy.', 'hikmah-login'),
            ]);
        }

        if (empty($username) || empty($email) || empty($password)) {
            wp_send_json_error([
                'message' => __('Please fill in all required fields.', 'hikmah-login'),
            ]);
        }

        if (!validate_username($username)) {
            wp_send_json_error([
                'message' => __('The username contains invalid characters.', 'hikmah-login'),
            ]);
        }

        if (username_exists($username)) {
            wp_send_json_error([
                'message' => __('This username is already taken. Please choose another.', 'hikmah-login'),
            ]);
        }

        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Please provide a valid email address.', 'hikmah-login'),
            ]);
        }

        if (email_exists($email)) {
            wp_send_json_error([
                'message' => __('An account is already registered with this email address.', 'hikmah-login'),
            ]);
        }

        if (strlen($password) < 8) {
            wp_send_json_error([
                'message' => __('Password must be at least 8 characters long.', 'hikmah-login'),
            ]);
        }

        // Create the user
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            wp_send_json_error([
                'message' => $user_id->get_error_message(),
            ]);
        }

        // Set registration metadata
        update_user_meta($user_id, '_hikmah_password_last_changed', current_time('mysql'));

        // Notification email
        wp_new_user_notification($user_id, null, 'user');

        /**
         * Fires after successful front-end user registration
         *
         * @param int   $user_id User ID created.
         * @param array $_POST   Raw submission data.
         */
        do_action('hikmah_user_registered', $user_id, $_POST);

        // Auto-login newly registered user
        wp_set_current_user($user_id, $username);
        wp_set_auth_cookie($user_id, true, is_ssl());

        $redirect_url = hikmah_get_option('login_redirect', home_url());

        wp_send_json_success([
            'message'      => __('Account created successfully! Redirecting...', 'hikmah-login'),
            'redirect_url' => esc_url_raw($redirect_url),
        ]);
    }

    /**
     * AJAX: Lost Password Processing
     *
     * @return void
     */
    public function ajax_lostpassword() {
        check_ajax_referer('hikmah_lostpassword_action', 'nonce');

        $user_input = sanitize_text_field($_POST['user_login'] ?? '');

        if (empty($user_input)) {
            wp_send_json_error([
                'message' => __('Please enter your username or email address.', 'hikmah-login'),
            ]);
        }

        $user = is_email($user_input) ? get_user_by('email', $user_input) : get_user_by('login', $user_input);

        if (!$user) {
            // Return success anyway to prevent user enumeration attacks
            wp_send_json_success([
                'message' => __('If an account exists with those details, a reset link has been dispatched to your email.', 'hikmah-login'),
            ]);
        }

        // Generate reset key and dispatch notification
        $reset_key = get_password_reset_key($user);

        if (is_wp_error($reset_key)) {
            wp_send_json_error([
                'message' => __('Could not generate password reset link. Please contact support.', 'hikmah-login'),
            ]);
        }

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $reset_url = add_query_arg([
            'action' => 'rp',
            'key'    => $reset_key,
            'login'  => rawurlencode($user->user_login),
        ], wp_login_url());

        $subject = sprintf(__('[%s] Password Reset Request', 'hikmah-login'), $site_name);
        $message = sprintf(
            /* translators: 1: user display name, 2: site name, 3: reset url */
            __("Hello %1\$s,\n\nYou recently requested to reset your password on %2\$s.\n\nClick the link below to set a new password:\n%3\$s\n\nIf you did not make this request, you can safely ignore this email.\n\nRegards,\n%2\$s", 'hikmah-login'),
            $user->display_name,
            $site_name,
            $reset_url
        );

        wp_mail($user->user_email, $subject, $message);

        wp_send_json_success([
            'message' => __('Password reset email has been dispatched. Please check your inbox.', 'hikmah-login'),
        ]);
    }

    /**
     * Non-AJAX Fallback: POST Login
     *
     * @return void
     */
    public function process_post_login() {
        if (!isset($_POST['hikmah_login_nonce']) || !wp_verify_nonce($_POST['hikmah_login_nonce'], 'hikmah_login_action')) {
            return;
        }

        // Redirect flow logic if JS is disabled on the client browser
        $creds = [
            'user_login'    => sanitize_text_field($_POST['log'] ?? ''),
            'user_password' => $_POST['pwd'] ?? '',
            'remember'      => !empty($_POST['rememberme']),
        ];

        $user = wp_signon($creds, is_ssl());

        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg(['login_error' => 1], wp_login_url()));
            exit;
        }

        wp_safe_redirect(hikmah_get_option('login_redirect', home_url()));
        exit;
    }

    /**
     * Non-AJAX Fallback: POST Register
     *
     * @return void
     */
    public function process_post_register() {
        if (!isset($_POST['hikmah_register_nonce']) || !wp_verify_nonce($_POST['hikmah_register_nonce'], 'hikmah_register_action')) {
            return;
        }

        if (!get_option('users_can_register')) {
            return;
        }

        $username = sanitize_user($_POST['user_login'] ?? '');
        $email    = sanitize_email($_POST['user_email'] ?? '');
        $password = $_POST['user_pass'] ?? '';

        if (!username_exists($username) && !email_exists($email) && !empty($password)) {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                wp_set_current_user($user_id, $username);
                wp_set_auth_cookie($user_id, true, is_ssl());
                wp_safe_redirect(hikmah_get_option('login_redirect', home_url()));
                exit;
            }
        }
    }

    /**
     * Non-AJAX Fallback: POST Lost Password
     *
     * @return void
     */
    public function process_post_lostpassword() {
        if (!isset($_POST['hikmah_lostpassword_nonce']) || !wp_verify_nonce($_POST['hikmah_lostpassword_nonce'], 'hikmah_lostpassword_action')) {
            return;
        }

        $user_input = sanitize_text_field($_POST['user_login'] ?? '');
        $user       = is_email($user_input) ? get_user_by('email', $user_input) : get_user_by('login', $user_input);

        if ($user) {
            $reset_key = get_password_reset_key($user);
            if (!is_wp_error($reset_key)) {
                $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
                $reset_url = add_query_arg(['action' => 'rp', 'key' => $reset_key, 'login' => rawurlencode($user->user_login)], wp_login_url());
                $subject   = sprintf(__('[%s] Password Reset Request', 'hikmah-login'), $site_name);
                $message   = sprintf(__("Reset your password by following this link:\n%s", 'hikmah-login'), $reset_url);
                wp_mail($user->user_email, $subject, $message);
            }
        }

        wp_safe_redirect(add_query_arg(['password_reset_sent' => 'true'], wp_login_url()));
        exit;
    }
}
