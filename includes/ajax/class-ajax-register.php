<?php
/**
 * AJAX Registration Handler
 *
 * Handles all AJAX registration requests.
 * Creates user accounts, sends verification emails,
 * and optionally auto-logs in the new user.
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
use Hikmah_Login\Database\DB_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Register {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->add_ajax_nopriv( 'hikmah_register', 'handle_registration' );
        $this->add_ajax_nopriv( 'hikmah_check_username', 'handle_check_username' );
        $this->add_ajax_nopriv( 'hikmah_check_email', 'handle_check_email' );
    }

    /**
     * =============================================
     * MAIN REGISTRATION HANDLER
     * =============================================
     */

    /**
     * Handle AJAX registration request.
     */
    public function handle_registration() {

        // Step 1: Verify nonce
        if ( ! check_ajax_referer( 'hikmah_register_action', 'hikmah_register_nonce', false ) ) {
            Helper::send_json( false, __( 'Security verification failed. Please refresh and try again.', 'hikmah-login' ), [], 403 );
        }

        // Step 2: Check if registration is enabled
        if ( 'yes' !== get_option( 'hikmah_registration_enabled', 'yes' ) ) {
            Helper::send_json( false, __( 'Registration is currently disabled.', 'hikmah-login' ), [], 403 );
        }

        // Step 3: Check if already logged in
        if ( is_user_logged_in() ) {
            Helper::send_json( true, __( 'You are already registered.', 'hikmah-login' ), [
                'redirect' => Helper::get_dashboard_url(),
            ]);
        }

        // Step 4: Get and sanitize form data
        $raw_data = [
            'username'         => isset( $_POST['username'] ) ? wp_unslash( $_POST['username'] ) : '',
            'email'            => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
            'password'         => isset( $_POST['password'] ) ? $_POST['password'] : '',
            'confirm_password' => isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '',
            'first_name'       => isset( $_POST['first_name'] ) ? wp_unslash( $_POST['first_name'] ) : '',
            'last_name'        => isset( $_POST['last_name'] ) ? wp_unslash( $_POST['last_name'] ) : '',
            'terms'            => isset( $_POST['terms'] ) ? wp_unslash( $_POST['terms'] ) : '',
            'website_url'      => isset( $_POST['website_url'] ) ? wp_unslash( $_POST['website_url'] ) : '',
            'redirect'         => isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '',
        ];

        $data = Sanitizer::sanitize_registration_data( $raw_data );

        // Step 5: Rate limiting (max 5 registrations per IP per hour)
        $ip = Helper::get_client_ip();
        $rate_key = 'hikmah_reg_rate_' . md5( $ip );
        $reg_count = (int) get_transient( $rate_key );

        if ( $reg_count >= 5 ) {
            Error_Handler::warning( "Registration rate limit hit: IP {$ip}" );
            Helper::send_json( false, __( 'Too many registration attempts. Please try again later.', 'hikmah-login' ), [], 429 );
        }

        // Step 6: Honeypot check
        if ( ! empty( $raw_data['website_url'] ) ) {
            // Bot detected — silently fail
            Helper::send_json( false, __( 'Form submission failed. Please try again.', 'hikmah-login' ), [], 400 );
        }

        // Step 7: CAPTCHA verification
        if ( Helper::is_feature_enabled( 'captcha_enabled' ) ) {
            $captcha_response = isset( $_POST['captcha_response'] )
                ? sanitize_text_field( wp_unslash( $_POST['captcha_response'] ) )
                : '';

            if ( ! $this->verify_captcha( $captcha_response ) ) {
                Helper::send_json( false, __( 'CAPTCHA verification failed.', 'hikmah-login' ), [ 'field' => 'captcha' ], 400 );
            }
        }

        // Step 8: Validate all fields
        $validator = new Validator();
        $validator->validate_registration( $data );

        /**
         * Allow third-party validation.
         *
         * @param Validator $validator Validator instance.
         * @param array     $data      Sanitized form data.
         */
        do_action( 'hikmah_register_validate', $validator, $data );

        if ( ! $validator->is_valid() ) {
            Helper::send_json(
                false,
                $validator->get_first_error(),
                [ 'errors' => $validator->get_errors() ],
                400
            );
        }

        // Step 9: Create the user
        $user_data = [
            'user_login'   => $data['username'],
            'user_email'   => $data['email'],
            'user_pass'    => $data['password'],
            'first_name'   => $data['first_name'],
            'last_name'    => $data['last_name'],
            'display_name' => ! empty( $data['first_name'] )
                ? trim( $data['first_name'] . ' ' . $data['last_name'] )
                : $data['username'],
            'role'         => get_option( 'hikmah_default_user_role', 'subscriber' ),
        ];

        /**
         * Filter user data before creation.
         *
         * @since 1.0.0
         * @param array $user_data User data array.
         * @param array $data      Sanitized form data.
         */
        $user_data = apply_filters( 'hikmah_register_user_data', $user_data, $data );

        $user_id = wp_insert_user( $user_data );

        if ( is_wp_error( $user_id ) ) {
            $error_message = Error_Handler::handle_wp_error( $user_id, 'registration' );
            Helper::send_json( false, $error_message, [], 400 );
        }

        // Step 10: Set user meta
        update_user_meta( $user_id, 'hikmah_registered_at', Helper::current_datetime() );
        update_user_meta( $user_id, 'hikmah_registration_ip', $ip );
        update_user_meta( $user_id, 'hikmah_login_count', 0 );

        // Custom fields meta
        if ( ! empty( $data['custom_fields'] ) && is_array( $data['custom_fields'] ) ) {
            foreach ( $data['custom_fields'] as $key => $value ) {
                update_user_meta( $user_id, 'hikmah_' . sanitize_key( $key ), $value );
            }
        }

        // Step 11: Email verification
        $email_verify = Helper::is_feature_enabled( 'email_verification_required' );

        if ( $email_verify ) {
            update_user_meta( $user_id, 'hikmah_email_verified', 'no' );
            $this->send_verification_email( $user_id, $data['email'] );
        } else {
            Helper::mark_email_verified( $user_id );
        }

        // Step 12: Send welcome email
        $this->send_welcome_email( $user_id, $data );

        // Step 13: Update rate limit
        set_transient( $rate_key, $reg_count + 1, HOUR_IN_SECONDS );

        // Step 14: Auto-login (if enabled and email not required)
        $auto_login = get_option( 'hikmah_auto_login_after_register', 'no' );

        if ( 'yes' === $auto_login && ! $email_verify ) {
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id, false, is_ssl() );
            do_action( 'wp_login', $data['username'], get_userdata( $user_id ) );
        }

        Helper::log( "New user registered: ID {$user_id}, Username: {$data['username']}" );

        /**
         * Fires after successful registration.
         *
         * @since 1.0.0
         * @param int   $user_id New user ID.
         * @param array $data    Sanitized form data.
         */
        do_action( 'hikmah_register_success', $user_id, $data );

        // Step 15: Build response
        $redirect_url = ! empty( $data['redirect'] )
            ? Sanitizer::redirect_url( $data['redirect'] )
            : Helper::get_login_url( [ 'registered' => 'true' ] );

        if ( 'yes' === $auto_login && ! $email_verify ) {
            $redirect_url = get_option( 'hikmah_login_redirect_url', Helper::get_dashboard_url() );
        }

        $message = $email_verify
            ? __( 'Registration successful! Please check your email to verify your account.', 'hikmah-login' )
            : __( 'Registration successful! Welcome aboard.', 'hikmah-login' );

        Helper::send_json( true, $message, [
            'redirect'     => $redirect_url,
            'user_id'      => $user_id,
            'auto_login'   => ( 'yes' === $auto_login && ! $email_verify ),
            'needs_verify' => $email_verify,
        ]);
    }

    /**
     * =============================================
     * REAL-TIME USERNAME CHECK
     * =============================================
     */

    /**
     * Check if username is available (AJAX).
     */
    public function handle_check_username() {

        check_ajax_referer( 'hikmah_register_action', 'hikmah_register_nonce' );

        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );

        if ( empty( $username ) || mb_strlen( $username ) < 3 ) {
            Helper::send_json( false, '', [ 'available' => false ] );
        }

        $exists = username_exists( $username );

        if ( $exists ) {
            Helper::send_json( false, __( 'Username is already taken.', 'hikmah-login' ), [ 'available' => false ] );
        }

        // Check reserved
        $reserved = [ 'admin', 'administrator', 'root', 'webmaster', 'support' ];
        if ( in_array( strtolower( $username ), $reserved, true ) ) {
            Helper::send_json( false, __( 'This username is reserved.', 'hikmah-login' ), [ 'available' => false ] );
        }

        Helper::send_json( true, __( 'Username is available!', 'hikmah-login' ), [ 'available' => true ] );
    }

    /**
     * =============================================
     * REAL-TIME EMAIL CHECK
     * =============================================
     */

    /**
     * Check if email is available (AJAX).
     */
    public function handle_check_email() {

        check_ajax_referer( 'hikmah_register_action', 'hikmah_register_nonce' );

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

        if ( empty( $email ) || ! is_email( $email ) ) {
            Helper::send_json( false, '', [ 'available' => false ] );
        }

        $exists = email_exists( $email );

        if ( $exists ) {
            Helper::send_json( false, __( 'This email is already registered.', 'hikmah-login' ), [ 'available' => false ] );
        }

        Helper::send_json( true, __( 'Email is available!', 'hikmah-login' ), [ 'available' => true ] );
    }

    /**
     * =============================================
     * HELPER METHODS
     * =============================================
     */

    /**
     * Send email verification link.
     *
     * @param int    $user_id User ID.
     * @param string $email   User email.
     */
    private function send_verification_email( $user_id, $email ) {

        $db = new DB_Manager();
        $token = $db->create_email_token( $user_id, 'verification', 1440 ); // 24 hours

        $verify_url = add_query_arg( [
            'hikmah_verify' => $token,
            'uid'           => $user_id,
        ], Helper::get_login_url() );

        $user = get_userdata( $user_id );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: Site name */
            __( '[%s] Verify Your Email Address', 'hikmah-login' ),
            $site_name
        );

        // Try custom template first
        $template = HIKMAH_LOGIN_DIR . 'emails/verification-email.php';

        if ( file_exists( $template ) ) {
            ob_start();
            include $template;
            $message = ob_get_clean();
        } else {
            $message = $this->get_default_verification_email( $user, $verify_url, $site_name );
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'hikmah_email_from_name', $site_name )
                   . ' <' . get_option( 'hikmah_email_from_address', get_option( 'admin_email' ) ) . '>',
        ];

        wp_mail( $email, $subject, $message, $headers );
    }

    /**
     * Get default verification email HTML.
     *
     * @param \WP_User $user       User object.
     * @param string   $verify_url Verification URL.
     * @param string   $site_name  Site name.
     * @return string HTML email.
     */
    private function get_default_verification_email( $user, $verify_url, $site_name ) {

        return '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                <h2 style="color:#1f2937;margin-top:0;">' . esc_html__( 'Verify Your Email', 'hikmah-login' ) . '</h2>
                <p style="color:#6b7280;">' . sprintf(
                    esc_html__( 'Hi %s,', 'hikmah-login' ),
                    esc_html( $user->display_name )
                ) . '</p>
                <p style="color:#6b7280;">' . esc_html__( 'Thank you for registering! Please click the button below to verify your email address:', 'hikmah-login' ) . '</p>
                <div style="text-align:center;margin:24px 0;">
                    <a href="' . esc_url( $verify_url ) . '" style="background:#4f46e5;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;">' . esc_html__( 'Verify Email', 'hikmah-login' ) . '</a>
                </div>
                <p style="color:#9ca3af;font-size:12px;">' . esc_html__( 'This link expires in 24 hours. If you did not create an account, please ignore this email.', 'hikmah-login' ) . '</p>
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">
                <p style="color:#9ca3af;font-size:12px;text-align:center;">' . esc_html( $site_name ) . '</p>
            </div>
        </div>';
    }

    /**
     * Send welcome email.
     *
     * @param int   $user_id User ID.
     * @param array $data    Registration data.
     */
    private function send_welcome_email( $user_id, $data ) {

        $send_welcome = get_option( 'hikmah_send_welcome_email', 'yes' );

        if ( 'yes' !== $send_welcome ) {
            return;
        }

        $user = get_userdata( $user_id );
        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( 'Welcome to %s!', 'hikmah-login' ),
            $site_name
        );

        $message = '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;">
                <h2 style="color:#1f2937;">' . sprintf( esc_html__( 'Welcome, %s! 🎉', 'hikmah-login' ), esc_html( $user->display_name ) ) . '</h2>
                <p style="color:#6b7280;">' . esc_html__( 'Your account has been created successfully.', 'hikmah-login' ) . '</p>
                <p style="color:#6b7280;">' . sprintf( esc_html__( 'Username: %s', 'hikmah-login' ), esc_html( $user->user_login ) ) . '</p>
                <div style="text-align:center;margin:24px 0;">
                    <a href="' . esc_url( Helper::get_login_url() ) . '" style="background:#4f46e5;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Log In Now', 'hikmah-login' ) . '</a>
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

    /**
     * Verify CAPTCHA (reused from Ajax_Login logic).
     *
     * @param string $response CAPTCHA response.
     * @return bool
     */
    private function verify_captcha( $response ) {

        if ( empty( $response ) ) {
            return false;
        }

        $secret_key = get_option( 'hikmah_recaptcha_secret_key', '' );

        if ( empty( $secret_key ) ) {
            return true;
        }

        $captcha_type = get_option( 'hikmah_captcha_type', 'recaptcha_v2' );

        $urls = [
            'recaptcha_v2' => 'https://www.google.com/recaptcha/api/siteverify',
            'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
            'hcaptcha'     => 'https://hcaptcha.com/siteverify',
            'turnstile'    => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ];

        $url = $urls[ $captcha_type ] ?? '';

        if ( empty( $url ) ) {
            return false;
        }

        $api_response = wp_remote_post( $url, [
            'body'    => [
                'secret'   => $secret_key,
                'response' => $response,
                'remoteip' => Helper::get_client_ip(),
            ],
            'timeout' => 10,
        ] );

        if ( is_wp_error( $api_response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $api_response ), true );

        return ! empty( $body['success'] );
    }
}
