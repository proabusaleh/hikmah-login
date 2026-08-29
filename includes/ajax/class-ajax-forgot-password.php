<?php
/**
 * AJAX Forgot & Reset Password Handler
 *
 * Handles the complete password recovery flow:
 * 1. Forgot password request → send reset email
 * 2. Reset password submission → update password
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 */

namespace Hikmah_Login\Ajax;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Validator;
use Hikmah_Login\Helpers\Sanitizer;
use Hikmah_Login\Helpers\Error_Handler;
use Hikmah_Login\Security\Captcha;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Forgot_Password {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     *
     * Direct per-action admin-ajax registrations are intentionally
     * omitted — all requests now route through the unified
     * Ajax_Controller (action: hikmah_ajax).
     */
    private function __construct() {
        // Handlers are dispatched directly by Ajax_Controller.
    }

    /**
     * =============================================
     * FORGOT PASSWORD HANDLER
     * =============================================
     */

    /**
     * Handle forgot password AJAX request.
     *
     * Generates a password reset key and sends
     * a custom reset email to the user.
     */
    public function handle_forgot_password() {

        // Step 1: Get and sanitize input
        $user_login = isset( $_POST['user_login'] )
            ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) )
            : '';

        $honeypot = isset( $_POST['website_url'] )
            ? sanitize_text_field( wp_unslash( $_POST['website_url'] ) )
            : '';

        // Step 3: Honeypot check
        if ( ! empty( $honeypot ) ) {
            // Bot — silently succeed to prevent enumeration
            Helper::send_json( true, __( 'If an account exists with that email, a reset link has been sent.', 'hikmah-login' ) );
        }

        // Step 3.5: CAPTCHA verification
        if ( Helper::is_feature_enabled( 'captcha_enabled' ) ) {
            $captcha_response = isset( $_POST['captcha_response'] )
                ? sanitize_text_field( wp_unslash( $_POST['captcha_response'] ) )
                : '';

            $captcha_result = Captcha::get_instance()->verify( $captcha_response );

            if ( is_wp_error( $captcha_result ) || true !== $captcha_result ) {
                Helper::send_json(
                    false,
                    is_wp_error( $captcha_result )
                        ? $captcha_result->get_error_message()
                        : __( 'CAPTCHA verification failed. Please try again.', 'hikmah-login' ),
                    [ 'field' => 'captcha' ],
                    400
                );
            }
        }

        // Step 4: Validate input
        if ( empty( $user_login ) ) {
            Helper::send_json(
                false,
                __( 'Please enter your email address or username.', 'hikmah-login' ),
                [ 'field' => 'user_login' ],
                400
            );
        }

        // Step 5: Rate limiting (max 3 requests per email per 15 minutes)
        $rate_key = 'hikmah_forgot_rate_' . md5( $user_login );
        $request_count = (int) get_transient( $rate_key );

        if ( $request_count >= 3 ) {
            Helper::send_json(
                false,
                __( 'Too many reset requests. Please wait 15 minutes before trying again.', 'hikmah-login' ),
                [],
                429
            );
        }

        // Step 6: IP-based rate limiting (max 10 per hour)
        $ip = Helper::get_client_ip();
        $ip_rate_key = 'hikmah_forgot_ip_' . md5( $ip );
        $ip_count = (int) get_transient( $ip_rate_key );

        if ( $ip_count >= 10 ) {
            Error_Handler::warning( "Forgot password IP rate limit: {$ip}" );
            Helper::send_json(
                false,
                __( 'Too many requests from your location. Please try again later.', 'hikmah-login' ),
                [],
                429
            );
        }

        // Step 7: Find the user
        $user = Helper::get_user_by_identifier( $user_login );

        // IMPORTANT: Always return the same message regardless of whether
        // the user exists. This prevents username/email enumeration attacks.
        $generic_message = __( 'If an account exists with that email or username, a password reset link has been sent. Please check your inbox.', 'hikmah-login' );

        if ( ! $user ) {
            // Don't reveal that the user doesn't exist
            Helper::send_json( true, $generic_message );
        }

        // Step 8: Check if user account is active
        $suspended = get_user_meta( $user->ID, 'hikmah_account_suspended', true );
        if ( 'yes' === $suspended ) {
            // Still return generic message
            Helper::send_json( true, $generic_message );
        }

        // Step 9: Generate password reset key
        $reset_key = get_password_reset_key( $user );

        if ( is_wp_error( $reset_key ) ) {
            Error_Handler::handle_wp_error( $reset_key, 'password_reset_key' );
            Helper::send_json(
                false,
                __( 'Unable to generate a reset link. Please try again later.', 'hikmah-login' ),
                [],
                500
            );
        }

        // Step 10: Build reset URL
        $reset_url = add_query_arg( [
            'key'   => $reset_key,
            'login' => rawurlencode( $user->user_login ),
        ], Helper::get_reset_password_url() );

        // Step 11: Send custom reset email
        $email_sent = $this->send_reset_email( $user, $reset_url );

        if ( ! $email_sent ) {
            Error_Handler::error( "Failed to send reset email to: {$user->user_email}" );
            Helper::send_json(
                false,
                __( 'Failed to send the reset email. Please try again later.', 'hikmah-login' ),
                [],
                500
            );
        }

        // Step 12: Update rate limits
        set_transient( $rate_key, $request_count + 1, 15 * MINUTE_IN_SECONDS );
        set_transient( $ip_rate_key, $ip_count + 1, HOUR_IN_SECONDS );

        // Step 13: Log the event
        Helper::log( "Password reset requested: User ID {$user->ID}, IP {$ip}" );

        /**
         * Fires after a password reset email is sent.
         *
         * @since 1.0.0
         * @param \WP_User $user      User object.
         * @param string   $reset_url Reset URL.
         */
        do_action( 'hikmah_forgot_password_sent', $user, $reset_url );

        Helper::send_json( true, $generic_message );
    }

    /**
     * =============================================
     * RESET PASSWORD HANDLER
     * =============================================
     */

    /**
     * Handle reset password AJAX request.
     *
     * Validates the reset key and sets the new password.
     */
    public function handle_reset_password() {

        // Step 1: Get and sanitize input
        $reset_key   = isset( $_POST['reset_key'] )
            ? sanitize_text_field( wp_unslash( $_POST['reset_key'] ) )
            : '';
        $reset_login = isset( $_POST['reset_login'] )
            ? sanitize_text_field( wp_unslash( $_POST['reset_login'] ) )
            : '';
        $new_password     = isset( $_POST['new_password'] ) ? $_POST['new_password'] : '';
        $confirm_password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';

        // Step 3: Validate required fields
        if ( empty( $reset_key ) || empty( $reset_login ) ) {
            Helper::send_json(
                false,
                __( 'Invalid reset link. Please request a new one.', 'hikmah-login' ),
                [],
                400
            );
        }

        if ( empty( $new_password ) || empty( $confirm_password ) ) {
            Helper::send_json(
                false,
                __( 'Please fill in all password fields.', 'hikmah-login' ),
                [ 'field' => 'new_password' ],
                400
            );
        }

        // Step 4: Validate password match
        if ( $new_password !== $confirm_password ) {
            Helper::send_json(
                false,
                __( 'Passwords do not match.', 'hikmah-login' ),
                [ 'field' => 'confirm_password' ],
                400
            );
        }

        // Step 5: Validate password strength
        $validator = new Validator();
        $validator->password( 'new_password', $new_password );

        if ( ! $validator->is_valid() ) {
            Helper::send_json(
                false,
                $validator->get_first_error(),
                [ 'field' => 'new_password' ],
                400
            );
        }

        // Step 6: Validate the reset key
        $user = check_password_reset_key( $reset_key, $reset_login );

        if ( is_wp_error( $user ) ) {
            $error_code = $user->get_error_code();

            if ( 'expired_key' === $error_code ) {
                $message = __( 'This reset link has expired. Please request a new one.', 'hikmah-login' );
            } elseif ( 'invalid_key' === $error_code ) {
                $message = __( 'This reset link is invalid. Please request a new one.', 'hikmah-login' );
            } else {
                $message = __( 'An error occurred. Please request a new reset link.', 'hikmah-login' );
            }

            Helper::send_json( false, $message, [ 'expired' => true ], 400 );
        }

        // Step 7: Check that new password is different from current
        if ( wp_check_password( $new_password, $user->user_pass, $user->ID ) ) {
            Helper::send_json(
                false,
                __( 'Your new password must be different from your current password.', 'hikmah-login' ),
                [ 'field' => 'new_password' ],
                400
            );
        }

        // Step 8: Reset the password
        reset_password( $user, $new_password );

        // Step 9: Update metadata
        update_user_meta( $user->ID, 'hikmah_password_changed_at', Helper::current_datetime() );
        update_user_meta( $user->ID, 'hikmah_password_reset_at', Helper::current_datetime() );
        update_user_meta( $user->ID, 'hikmah_password_reset_ip', Helper::get_client_ip() );

        // Step 10: Destroy all other sessions
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $sessions->destroy_all();

        // Step 11: Log the event
        Helper::log( "Password reset completed: User ID {$user->ID}" );

        /**
         * Fires after a password is successfully reset.
         *
         * @since 1.0.0
         * @param \WP_User $user User object.
         */
        do_action( 'hikmah_password_reset_complete', $user );

        // Step 12: Send confirmation email
        $this->send_password_changed_email( $user );

        // Step 13: Auto-login after reset (optional)
        $auto_login = get_option( 'hikmah_auto_login_after_reset', 'yes' );

        $redirect_url = Helper::get_login_url( [ 'password_reset' => 'true' ] );

        if ( 'yes' === $auto_login ) {
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, false, is_ssl() );
            do_action( 'wp_login', $user->user_login, $user );
            $redirect_url = get_option( 'hikmah_login_redirect_url', Helper::get_dashboard_url() );
        }

        Helper::send_json( true, __( 'Password reset successful!', 'hikmah-login' ), [
            'redirect' => $redirect_url,
        ]);
    }

    /**
     * =============================================
     * EMAIL HELPERS
     * =============================================
     */

    /**
     * Send custom password reset email.
     *
     * @param \WP_User $user      User object.
     * @param string   $reset_url Reset URL with key.
     * @return bool
     */
    private function send_reset_email( $user, $reset_url ) {

        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: Site name */
            __( '[%s] Password Reset Request', 'hikmah-login' ),
            $site_name
        );

        // Try custom template
        $template = HIKMAH_LOGIN_DIR . 'emails/reset-password-email.php';

        if ( file_exists( $template ) ) {
            ob_start();
            include $template;
            $message = ob_get_clean();
        } else {
            $message = $this->get_default_reset_email( $user, $reset_url, $site_name );
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'hikmah_email_from_name', $site_name )
                   . ' <' . get_option( 'hikmah_email_from_address', get_option( 'admin_email' ) ) . '>',
        ];

        /**
         * Filter the reset email arguments.
         *
         * @since 1.0.0
         * @param array    $email_args Email arguments.
         * @param \WP_User $user       User object.
         */
        $email_args = apply_filters( 'hikmah_reset_email_args', [
            'to'      => $user->user_email,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        ], $user );

        return wp_mail(
            $email_args['to'],
            $email_args['subject'],
            $email_args['message'],
            $email_args['headers']
        );
    }

    /**
     * Default reset email HTML.
     *
     * @param \WP_User $user      User.
     * @param string   $reset_url Reset URL.
     * @param string   $site_name Site name.
     * @return string HTML.
     */
    private function get_default_reset_email( $user, $reset_url, $site_name ) {

        return '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;">
                <h2 style="color:#1f2937;">' . esc_html__( 'Password Reset Request', 'hikmah-login' ) . '</h2>
                <p style="color:#6b7280;">' . sprintf(
                    esc_html__( 'Hi %s,', 'hikmah-login' ),
                    esc_html( $user->display_name )
                ) . '</p>
                <p style="color:#6b7280;">' . esc_html__( 'We received a request to reset your password. Click the button below:', 'hikmah-login' ) . '</p>
                <div style="text-align:center;margin:24px 0;">
                    <a href="' . esc_url( $reset_url ) . '" style="background:#4f46e5;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Reset Password', 'hikmah-login' ) . '</a>
                </div>
                <p style="color:#9ca3af;font-size:12px;">' . esc_html__( 'This link expires in 24 hours. If you did not request this, ignore this email.', 'hikmah-login' ) . '</p>
            </div>
        </div>';
    }

    /**
     * Send password changed confirmation email.
     *
     * @param \WP_User $user User object.
     */
    private function send_password_changed_email( $user ) {

        $send_confirmation = get_option( 'hikmah_send_password_change_email', 'yes' );

        if ( 'yes' !== $send_confirmation ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( '[%s] Your Password Has Been Changed', 'hikmah-login' ),
            $site_name
        );

        $message = '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;">
                <h2 style="color:#10b981;">✅ ' . esc_html__( 'Password Changed', 'hikmah-login' ) . '</h2>
                <p style="color:#6b7280;">' . sprintf(
                    esc_html__( 'Hi %s,', 'hikmah-login' ),
                    esc_html( $user->display_name )
                ) . '</p>
                <p style="color:#6b7280;">' . esc_html__( 'Your password has been successfully changed.', 'hikmah-login' ) . '</p>
                <p style="color:#6b7280;">' . sprintf(
                    esc_html__( 'Time: %s', 'hikmah-login' ),
                    Helper::format_datetime( Helper::current_datetime() )
                ) . '</p>
                <div style="background:#fef2f2;border-radius:6px;padding:12px;margin-top:16px;">
                    <p style="color:#991b1b;font-size:13px;margin:0;">' . esc_html__( 'If you did not make this change, please contact the site administrator immediately.', 'hikmah-login' ) . '</p>
                </div>
            </div>
        </div>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'hikmah_email_from_name', $site_name )
                   . ' <' . get_option( 'hikmah_email_from_address', get_option( 'admin_email' ) ) . '>',
        ];

        wp_mail( $user->user_email, $subject, $message, $headers );
    }
}