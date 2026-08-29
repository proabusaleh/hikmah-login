<?php
/**
 * Email Verification Handler
 *
 * Manages the complete email verification lifecycle:
 * - Processing verification links from emails
 * - Generating and validating verification tokens
 * - Resending verification emails
 * - Blocking unverified users from login
 * - Cleaning up expired tokens
 *
 * @package Hikmah_Login
 * @subpackage Auth
 * @since   1.0.0
 */

namespace Hikmah_Login\Auth;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Error_Handler;
use Hikmah_Login\Database\DB_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Email_Verification {

    use Singleton;
    use Hooks;

    /**
     * Database Manager instance.
     *
     * @var DB_Manager
     */
    private $db;

    /**
     * Token expiration in minutes (default: 24 hours).
     *
     * @var int
     */
    private $token_expiry = 1440;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->db = new DB_Manager();

        // Only activate if email verification is enabled
        if ( ! Helper::is_feature_enabled( 'email_verification_required' ) ) {
            return;
        }

        $this->token_expiry = (int) get_option( 'hikmah_verification_token_expiry', 1440 );

        $this->register_hooks();
    }

    /**
     * Register all verification hooks.
     */
    private function register_hooks() {

        // Process verification link on page load
        $this->add_action( 'init', 'process_verification_link', 5 );

        // Block unverified users from logging in (wp-login.php)
        $this->add_filter( 'authenticate', 'block_unverified_login', 30, 3 );

        // Block unverified users from accessing restricted content
        $this->add_action( 'template_redirect', 'restrict_unverified_access' );

        // Show verification notice to logged-in unverified users
        $this->add_action( 'wp_footer', 'show_verification_notice' );
        $this->add_action( 'admin_notices', 'show_admin_verification_notice' );

        // Cron: Clean up expired tokens
        $this->add_action( 'hikmah_login_cleanup_tokens', 'cleanup_expired_tokens' );

        // Frontend resend verification is routed through the unified Ajax_Controller
        // (hikmah_ajax -> resend_verification). See class-ajax-controller.php.

        // AJAX: Admin resend verification from user profile
        $this->add_ajax( 'hikmah_admin_resend_verification', 'ajax_admin_resend_verification' );

        // After user registration (generate initial token)
        $this->add_action( 'hikmah_login_user_registered', 'on_user_registered', 10, 2 );

        // After email change in profile
        $this->add_action( 'profile_update', 'on_email_changed', 20, 3 );

        // Admin: Manual verify/unverify
        $this->add_action( 'hikmah_admin_manual_verify', 'manual_verify_user', 10, 2 );
    }

    /**
     * =============================================
     * VERIFICATION LINK PROCESSOR
     * =============================================
     */

    /**
     * Process the verification link when user clicks it.
     *
     * URL format: /login/?hikmah_verify=TOKEN&uid=USER_ID
     */
    public function process_verification_link() {

        // Check if this is a verification request
        if ( ! isset( $_GET['hikmah_verify'] ) || ! isset( $_GET['uid'] ) ) {
            return;
        }

        $token   = sanitize_text_field( wp_unslash( $_GET['hikmah_verify'] ) );
        $user_id = absint( $_GET['uid'] );

        if ( empty( $token ) || ! $user_id ) {
            $this->redirect_with_status( 'invalid' );
            return;
        }

        // Verify the token
        $result = $this->verify_token( $user_id, $token );

        if ( is_wp_error( $result ) ) {
            $error_code = $result->get_error_code();

            Error_Handler::info( "Verification failed: User {$user_id}, Code: {$error_code}" );

            $this->redirect_with_status( $error_code );
            return;
        }

        // Mark email as verified
        Helper::mark_email_verified( $user_id );

        // Log the event
        Helper::log( "Email verified: User ID {$user_id}" );

        /**
         * Fires after a user's email is successfully verified.
         *
         * @since 1.0.0
         * @param int $user_id User ID.
         */
        do_action( 'hikmah_email_verified', $user_id );

        // Send confirmation email
        $this->send_verification_success_email( $user_id );

        // Redirect to success page
        $this->redirect_with_status( 'success' );
    }

    /**
     * Verify an email verification token.
     *
     * @param int    $user_id User ID.
     * @param string $token   Plain token from URL.
     * @return true|\WP_Error True on success, WP_Error on failure.
     */
    public function verify_token( $user_id, $token ) {

        // Check user exists
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return new \WP_Error(
                'invalid_user',
                __( 'Invalid verification link. The user account does not exist.', 'hikmah-login' )
            );
        }

        // Check if already verified
        if ( Helper::is_email_verified( $user_id ) ) {
            return new \WP_Error(
                'already_verified',
                __( 'Your email is already verified. You can log in now.', 'hikmah-login' )
            );
        }

        // Look up the token in the database
        $record = $this->db->get_row( 'email_tokens', [
            'user_id'    => $user_id,
            'token_type' => 'verification',
            'is_used'    => 0,
        ] );

        if ( ! $record ) {
            return new \WP_Error(
                'invalid_token',
                __( 'Invalid or expired verification link. Please request a new one.', 'hikmah-login' )
            );
        }

        // Check expiration
        if ( Helper::is_expired( $record->expires_at ) ) {
            return new \WP_Error(
                'token_expired',
                __( 'This verification link has expired. Please request a new one.', 'hikmah-login' )
            );
        }

        // Verify token hash
        if ( ! Helper::verify_token( $token, $record->token ) ) {
            return new \WP_Error(
                'invalid_token',
                __( 'Invalid verification link. Please request a new one.', 'hikmah-login' )
            );
        }

        // Mark token as used
        $this->db->update( 'email_tokens', [
            'is_used' => 1,
        ], [
            'id' => $record->id,
        ], [ '%d' ], [ '%d' ] );

        return true;
    }

    /**
     * Redirect to login page with verification status.
     *
     * @param string $status Status: success, invalid, token_expired, already_verified.
     */
    private function redirect_with_status( $status ) {

        $login_url = Helper::get_login_url();

        switch ( $status ) {
            case 'success':
                $url = add_query_arg( 'verified', 'true', $login_url );
                break;

            case 'token_expired':
                $url = add_query_arg( [
                    'verify_error' => 'expired',
                    'action'       => 'resend',
                ], $login_url );
                break;

            case 'already_verified':
                $url = add_query_arg( 'verified', 'already', $login_url );
                break;

            case 'invalid_token':
            case 'invalid_user':
            default:
                $url = add_query_arg( 'verify_error', 'invalid', $login_url );
                break;
        }

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * =============================================
     * TOKEN GENERATION
     * =============================================
     */

    /**
     * Generate a new verification token for a user.
     *
     * @param int $user_id User ID.
     * @return string Plain token (for email link).
     */
    public function generate_token( $user_id ) {

        // Delete any existing unused verification tokens
        $this->db->delete( 'email_tokens', [
            'user_id'    => $user_id,
            'token_type' => 'verification',
            'is_used'    => 0,
        ] );

        // Generate new token
        $plain_token  = Helper::generate_token( 64 );
        $hashed_token = Helper::hash_token( $plain_token );

        $this->db->insert( 'email_tokens', [
            'user_id'    => $user_id,
            'token'      => $hashed_token,
            'token_type' => 'verification',
            'is_used'    => 0,
            'expires_at' => Helper::future_datetime( $this->token_expiry, 'minutes' ),
            'created_at' => Helper::current_datetime(),
        ], [ '%d', '%s', '%s', '%d', '%s', '%s' ] );

        return $plain_token;
    }

    /**
     * Build the verification URL for a user.
     *
     * @param int    $user_id User ID.
     * @param string $token   Plain token.
     * @return string Full verification URL.
     */
    public function build_verification_url( $user_id, $token ) {

        return add_query_arg( [
            'hikmah_verify' => $token,
            'uid'           => $user_id,
        ], Helper::get_login_url() );
    }

    /**
     * =============================================
     * EMAIL SENDING
     * =============================================
     */

    /**
     * Send verification email to a user.
     *
     * @param int $user_id User ID.
     * @return bool Whether the email was sent.
     */
    public function send_verification_email( $user_id ) {

        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        // Generate token
        $token = $this->generate_token( $user_id );

        // Build URL
        $verify_url = $this->build_verification_url( $user_id, $token );

        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: Site name */
            __( '[%s] Verify Your Email Address', 'hikmah-login' ),
            $site_name
        );

        // Try custom template
        $template = HIKMAH_LOGIN_DIR . 'emails/verification-email.php';

        if ( file_exists( $template ) ) {
            ob_start();
            include $template;
            $message = ob_get_clean();
        } else {
            $message = $this->get_default_email_html( $user, $verify_url, $site_name );
        }

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option( 'hikmah_email_from_name', $site_name )
                   . ' <' . get_option( 'hikmah_email_from_address', get_option( 'admin_email' ) ) . '>',
        ];

        /**
         * Filter verification email arguments.
         *
         * @since 1.0.0
         * @param array    $args    Email arguments.
         * @param \WP_User $user    User object.
         */
        $args = apply_filters( 'hikmah_verification_email_args', [
            'to'      => $user->user_email,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        ], $user );

        $sent = wp_mail( $args['to'], $args['subject'], $args['message'], $args['headers'] );

        if ( $sent ) {
            Helper::log( "Verification email sent: User ID {$user_id}" );
        } else {
            Error_Handler::error( "Failed to send verification email: User ID {$user_id}" );
        }

        return $sent;
    }

    /**
     * Send verification success confirmation email.
     *
     * @param int $user_id User ID.
     */
    private function send_verification_success_email( $user_id ) {

        $send_confirmation = get_option( 'hikmah_send_verification_confirmation', 'yes' );

        if ( 'yes' !== $send_confirmation ) {
            return;
        }

        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );

        $subject = sprintf(
            __( '[%s] Email Verified Successfully!', 'hikmah-login' ),
            $site_name
        );

        $message = '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;text-align:center;">
                <div style="font-size:48px;margin-bottom:16px;">✅</div>
                <h2 style="color:#10b981;margin:0 0 12px;">' . esc_html__( 'Email Verified!', 'hikmah-login' ) . '</h2>
                <p style="color:#6b7280;">' . sprintf(
                    esc_html__( 'Hi %s, your email has been verified successfully.', 'hikmah-login' ),
                    esc_html( $user->display_name )
                ) . '</p>
                <p style="color:#6b7280;">' . esc_html__( 'You can now log in and access all features.', 'hikmah-login' ) . '</p>
                <div style="margin:24px 0;">
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
     * Default verification email HTML.
     *
     * @param \WP_User $user       User.
     * @param string   $verify_url URL.
     * @param string   $site_name  Site name.
     * @return string HTML.
     */
    private function get_default_email_html( $user, $verify_url, $site_name ) {

        return '
        <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;background:#f9fafb;padding:20px;">
            <div style="background:#fff;border-radius:8px;padding:32px;">
                <h2 style="color:#1f2937;">' . esc_html__( 'Verify Your Email', 'hikmah-login' ) . '</h2>
                <p style="color:#6b7280;">' . sprintf( esc_html__( 'Hi %s,', 'hikmah-login' ), esc_html( $user->display_name ) ) . '</p>
                <p style="color:#6b7280;">' . esc_html__( 'Please click the button below to verify your email address:', 'hikmah-login' ) . '</p>
                <div style="text-align:center;margin:24px 0;">
                    <a href="' . esc_url( $verify_url ) . '" style="background:#4f46e5;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">' . esc_html__( 'Verify Email', 'hikmah-login' ) . '</a>
                </div>
                <p style="color:#9ca3af;font-size:12px;">' . esc_html__( 'This link expires in 24 hours.', 'hikmah-login' ) . '</p>
            </div>
        </div>';
    }

    /**
     * =============================================
     * LOGIN BLOCKING (Unverified Users)
     * =============================================
     */

    /**
     * Block unverified users from logging in via wp-login.php.
     *
     * @param \WP_User|\WP_Error|null $user     Auth result.
     * @param string                  $username Username.
     * @param string                  $password Password.
     * @return \WP_User|\WP_Error|null
     */
    public function block_unverified_login( $user, $username, $password ) {

        // Only check if auth succeeded
        if ( is_wp_error( $user ) || ! $user instanceof \WP_User ) {
            return $user;
        }

        // Skip for admins (always allow)
        if ( $user->has_cap( 'manage_options' ) ) {
            return $user;
        }

        // Check verification status
        if ( ! Helper::is_email_verified( $user->ID ) ) {

            $resend_url = add_query_arg( [
                'action' => 'resend',
                'email'  => rawurlencode( $user->user_email ),
            ], Helper::get_login_url() );

            return new \WP_Error(
                'hikmah_email_not_verified',
                sprintf(
                    /* translators: %s: Resend verification URL */
                    __( '<strong>Email Not Verified:</strong> Please verify your email address before logging in. <a href="%s">Resend verification email</a>', 'hikmah-login' ),
                    esc_url( $resend_url )
                )
            );
        }

        return $user;
    }

    /**
     * =============================================
     * ACCESS RESTRICTION
     * =============================================
     */

    /**
     * Restrict access to certain pages for unverified users.
     */
    public function restrict_unverified_access() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        // Skip for admins
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }

        // Skip if already verified
        if ( Helper::is_email_verified( $user_id ) ) {
            return;
        }

        // Allow access to login/logout/verification pages
        if ( Helper::is_hikmah_page() ) {
            return;
        }

        /**
         * Filter whether to restrict unverified user access.
         *
         * @param bool $restrict Default true.
         * @param int  $user_id  User ID.
         */
        $restrict = apply_filters( 'hikmah_restrict_unverified_access', true, $user_id );

        if ( ! $restrict ) {
            return;
        }

        // Allow AJAX and REST requests
        if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Redirect to dashboard with notice
        $redirect = add_query_arg(
            'verify_required',
            '1',
            Helper::get_dashboard_url()
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * =============================================
     * VERIFICATION NOTICES
     * =============================================
     */

    /**
     * Show verification notice to unverified logged-in users (frontend).
     */
    public function show_verification_notice() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( current_user_can( 'manage_options' ) || Helper::is_email_verified( $user_id ) ) {
            return;
        }

        $user = wp_get_current_user();
        $resend_url = add_query_arg( [
            'action' => 'resend',
            'email'  => rawurlencode( $user->user_email ),
        ], Helper::get_login_url() );

        ?>
        <div class="hikmah-verify-notice" style="
            position:fixed;top:0;left:0;right:0;z-index:99999;
            background:#fef3c7;border-bottom:2px solid #f59e0b;
            padding:12px 20px;text-align:center;font-size:14px;
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        ">
            <span style="color:#92400e;">
                ⚠️ <?php esc_html_e( 'Your email is not verified.', 'hikmah-login' ); ?>
                <a href="<?php echo esc_url( $resend_url ); ?>" style="color:#4f46e5;font-weight:600;">
                    <?php esc_html_e( 'Resend verification email', 'hikmah-login' ); ?>
                </a>
            </span>
            <button onclick="this.parentElement.style.display='none'" style="
                background:none;border:none;cursor:pointer;font-size:18px;
                color:#92400e;margin-left:12px;vertical-align:middle;
            ">&times;</button>
        </div>
        <style>body { padding-top: 50px !important; }</style>
        <?php
    }

    /**
     * Show verification notice in admin area.
     */
    public function show_admin_verification_notice() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        if ( current_user_can( 'manage_options' ) || Helper::is_email_verified( $user_id ) ) {
            return;
        }

        $user = wp_get_current_user();
        $resend_url = add_query_arg( [
            'action' => 'resend',
            'email'  => rawurlencode( $user->user_email ),
        ], Helper::get_login_url() );

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                ⚠️ <strong><?php esc_html_e( 'Hikmah Login:', 'hikmah-login' ); ?></strong>
                <?php esc_html_e( 'Your email address is not verified. Some features may be restricted.', 'hikmah-login' ); ?>
                <a href="<?php echo esc_url( $resend_url ); ?>">
                    <?php esc_html_e( 'Resend verification email', 'hikmah-login' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * =============================================
     * AJAX RESEND VERIFICATION
     * =============================================
     */

    /**
     * Handle AJAX resend verification (not logged in).
     */
    public function ajax_resend_verification() {

        check_ajax_referer( 'hikmah_login_nonce', 'nonce' );

        $email = isset( $_POST['email'] )
            ? sanitize_email( wp_unslash( $_POST['email'] ) )
            : '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            Helper::send_json( false, __( 'Please enter a valid email address.', 'hikmah-login' ), [], 400 );
        }

        $this->process_resend( $email );
    }

    /**
     * Handle AJAX resend verification (logged in).
     */
    public function ajax_resend_verification_logged_in() {

        check_ajax_referer( 'hikmah_login_nonce', 'nonce' );

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            Helper::send_json( false, __( 'You must be logged in.', 'hikmah-login' ), [], 401 );
        }

        if ( Helper::is_email_verified( $user_id ) ) {
            Helper::send_json( true, __( 'Your email is already verified.', 'hikmah-login' ) );
        }

        $user = get_userdata( $user_id );
        $this->process_resend( $user->user_email );
    }

    /**
     * Handle AJAX resend verification from the admin user profile.
     */
    public function ajax_admin_resend_verification() {

        check_ajax_referer( 'hikmah_admin_nonce', 'nonce' );

        $target_id = isset( $_POST['user_id'] )
            ? absint( $_POST['user_id'] )
            : 0;

        if ( ! $target_id || ! current_user_can( 'edit_user', $target_id ) ) {
            Helper::send_json( false, __( 'You do not have permission to do this.', 'hikmah-login' ), [], 403 );
        }

        $user = get_userdata( $target_id );

        if ( ! $user ) {
            Helper::send_json( false, __( 'Invalid user.', 'hikmah-login' ), [], 400 );
        }

        if ( Helper::is_email_verified( $target_id ) ) {
            Helper::send_json( true, __( 'This user\'s email is already verified.', 'hikmah-login' ) );
        }

        $sent = $this->send_verification_email( $target_id );

        if ( $sent ) {
            Helper::send_json( true, __( 'Verification email sent!', 'hikmah-login' ) );
        }

        Helper::send_json( false, __( 'Failed to send verification email. Please try again.', 'hikmah-login' ), [], 500 );
    }

    /**
     * Process the resend request.
     *
     * @param string $email Email address.
     */
    private function process_resend( $email ) {

        // Rate limiting: max 3 resends per email per hour
        $rate_key = 'hikmah_verify_resend_' . md5( $email );
        $count = (int) get_transient( $rate_key );

        if ( $count >= 3 ) {
            Helper::send_json(
                false,
                __( 'Too many requests. Please wait before requesting another verification email.', 'hikmah-login' ),
                [],
                429
            );
        }

        $user = get_user_by( 'email', $email );

        // Generic response (anti-enumeration)
        $generic_msg = __( 'If an account exists with this email, a verification link has been sent.', 'hikmah-login' );

        if ( ! $user ) {
            Helper::send_json( true, $generic_msg );
        }

        if ( Helper::is_email_verified( $user->ID ) ) {
            Helper::send_json( true, __( 'Your email is already verified. Please log in.', 'hikmah-login' ) );
        }

        // Send verification email
        $sent = $this->send_verification_email( $user->ID );

        set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

        if ( $sent ) {
            Helper::send_json( true, $generic_msg );
        } else {
            Helper::send_json(
                false,
                __( 'Failed to send verification email. Please try again later.', 'hikmah-login' ),
                [],
                500
            );
        }
    }

    /**
     * =============================================
     * EVENT HANDLERS
     * =============================================
     */

    /**
     * Handle new user registration.
     *
     * @param int   $user_id  New user ID.
     * @param array $userdata User data.
     */
    public function on_user_registered( $user_id, $userdata = [] ) {

        if ( Helper::is_email_verified( $user_id ) ) {
            return; // Already verified (e.g., social login)
        }

        // Send initial verification email
        $this->send_verification_email( $user_id );
    }

    /**
     * Handle email change in profile.
     *
     * @param int      $user_id       User ID.
     * @param \WP_User $old_user_data Old user data.
     * @param array    $userdata      New user data.
     */
    public function on_email_changed( $user_id, $old_user_data, $userdata = [] ) {

        if ( ! isset( $userdata['user_email'] ) ) {
            return;
        }

        if ( $userdata['user_email'] === $old_user_data->user_email ) {
            return;
        }

        // Reset verification for new email
        update_user_meta( $user_id, 'hikmah_email_verified', 'no' );
        delete_user_meta( $user_id, 'hikmah_email_verified_at' );

        // Send new verification email
        $this->send_verification_email( $user_id );

        Helper::log( "Email changed, verification reset: User ID {$user_id}" );
    }

    /**
     * =============================================
     * ADMIN MANUAL VERIFICATION
     * =============================================
     */

    /**
     * Manually verify or unverify a user (admin action).
     *
     * @param int  $user_id User ID.
     * @param bool $verify  True to verify, false to unverify.
     */
    public function manual_verify_user( $user_id, $verify = true ) {

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( $verify ) {
            Helper::mark_email_verified( $user_id );
            Helper::log( "Manual email verification: User ID {$user_id}" );
        } else {
            update_user_meta( $user_id, 'hikmah_email_verified', 'no' );
            delete_user_meta( $user_id, 'hikmah_email_verified_at' );
            Helper::log( "Manual email unverification: User ID {$user_id}" );
        }
    }

    /**
     * =============================================
     * TOKEN CLEANUP
     * =============================================
     */

    /**
     * Clean up expired and used tokens.
     */
    public function cleanup_expired_tokens() {

        $deleted = $this->db->cleanup_expired_tokens();

        Helper::log( "Token cleanup: {$deleted} expired tokens removed." );
    }

    /**
     * =============================================
     * PUBLIC API METHODS
     * =============================================
     */

    /**
     * Check if a user needs email verification.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public function needs_verification( $user_id ) {

        if ( ! Helper::is_feature_enabled( 'email_verification_required' ) ) {
            return false;
        }

        return ! Helper::is_email_verified( $user_id );
    }

    /**
     * Get verification status for a user.
     *
     * @param int $user_id User ID.
     * @return array Status info.
     */
    public function get_verification_status( $user_id ) {

        $verified = Helper::is_email_verified( $user_id );
        $verified_at = get_user_meta( $user_id, 'hikmah_email_verified_at', true );

        return [
            'is_verified' => $verified,
            'verified_at' => $verified_at,
            'needs_verification' => $this->needs_verification( $user_id ),
        ];
    }
}