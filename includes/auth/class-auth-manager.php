<?php
/**
 * Authentication Manager
 *
 * The central orchestrator for all authentication operations.
 * Coordinates between Login, Logout, Registration, Password Recovery,
 * Email Verification, 2FA, and Security modules.
 *
 * This class does NOT handle form rendering or AJAX directly.
 * It provides the core business logic that other classes call.
 *
 * @package Hikmah_Login
 * @subpackage Auth
 * @since   1.0.0
 */

namespace Hikmah_Login\Auth;

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

class Auth_Manager {

    use Singleton;
    use Hooks;

    /**
     * Database Manager instance.
     *
     * @var DB_Manager
     */
    private $db;

    /**
     * Current authentication state.
     *
     * @var array
     */
    private $auth_state = [];

    /**
     * Constructor.
     */
    private function __construct() {
        $this->db = new DB_Manager();
        $this->register_hooks();
    }

    /**
     * Register core authentication hooks.
     */
    private function register_hooks() {

        // Intercept WordPress authentication
        $this->add_filter( 'authenticate', 'intercept_authentication', 20, 3 );

        // After successful login
        $this->add_action( 'wp_login', 'on_user_login', 10, 2 );

        // Before logout
        $this->add_action( 'wp_logout', 'on_user_logout', 10, 1 );

        // Set auth cookie expiration
        $this->add_filter( 'auth_cookie_expiration', 'modify_cookie_expiration', 10, 3 );

        // Validate auth cookie
        $this->add_filter( 'auth_cookie_valid', 'validate_auth_cookie', 10, 2 );

        // Password change detection
        $this->add_action( 'after_password_reset', 'on_password_reset', 10, 2 );
        $this->add_action( 'profile_update', 'on_profile_update', 10, 3 );

        // User deletion cleanup
        $this->add_action( 'delete_user', 'on_user_delete', 10, 2 );

        // Login redirect
        $this->add_filter( 'login_redirect', 'handle_login_redirect', 10, 3 );

        // Logout redirect
        $this->add_filter( 'logout_redirect', 'handle_logout_redirect', 10, 3 );
    }

    /**
     * =============================================
     * CORE LOGIN LOGIC
     * =============================================
     */

    /**
     * Authenticate a user with credentials.
     *
     * This is the main login method called by AJAX handlers,
     * shortcodes, and the wp-login.php override.
     *
     * @param array $credentials Login credentials.
     * @return array Result array with 'success', 'message', 'data'.
     */
    public function login( $credentials ) {

        // Step 1: Sanitize input
        $data = Sanitizer::sanitize_login_data( $credentials );

        // Step 2: Validate input
        $validator = new Validator();
        $validator->validate_login( $data );

        if ( ! $validator->is_valid() ) {
            return $this->build_response( false, $validator->get_first_error(), [], 'validation_error' );
        }

        $username = $data['username'];
        $password = $data['password'];
        $remember = $data['remember'];
        $ip       = Helper::get_client_ip();

        // Step 3: Check brute-force lockout
        if ( $this->db->is_locked( $ip, $username ) ) {
            $remaining = $this->db->get_lockout_remaining( $ip, $username );
            $minutes   = ceil( $remaining / 60 );

            $this->log_attempt( null, $username, 'locked', 'Account locked' );

            return $this->build_response(
                false,
                sprintf(
                    /* translators: %d: Minutes remaining */
                    __( 'Too many failed attempts. Please try again in %d minutes.', 'hikmah-login' ),
                    $minutes
                ),
                [ 'lockout_remaining' => $remaining ],
                'account_locked'
            );
        }

        // Step 4: Rate limiting check
        if ( $this->is_rate_limited( $ip ) ) {
            return $this->build_response(
                false,
                __( 'Too many requests. Please slow down.', 'hikmah-login' ),
                [],
                'rate_limited'
            );
        }

        // Step 5: Find the user
        $user = Helper::get_user_by_identifier( $username );

        if ( ! $user ) {
            $this->db->record_failed_attempt( $ip, $username );
            $this->log_attempt( null, $username, 'failed', 'User not found' );
            $this->check_and_lock( $ip, $username );

            // Generic message (don't reveal if user exists)
            return $this->build_response(
                false,
                __( 'Invalid username or password.', 'hikmah-login' ),
                [],
                'invalid_credentials'
            );
        }

        // Step 6: Check if user account is active
        $account_status = $this->get_account_status( $user );

        if ( 'suspended' === $account_status ) {
            $this->log_attempt( $user->ID, $username, 'blocked', 'Account suspended' );

            return $this->build_response(
                false,
                __( 'Your account has been suspended. Please contact the administrator.', 'hikmah-login' ),
                [],
                'account_suspended'
            );
        }

        if ( 'pending_verification' === $account_status ) {
            $this->log_attempt( $user->ID, $username, 'blocked', 'Email not verified' );

            return $this->build_response(
                false,
                __( 'Please verify your email address before logging in. Check your inbox for the verification link.', 'hikmah-login' ),
                [
                    'needs_verification' => true,
                    'resend_url'         => add_query_arg(
                        'resend_verification',
                        $user->ID,
                        Helper::get_login_url()
                    ),
                ],
                'email_not_verified'
            );
        }

        // Step 7: Verify password
        $password_check = wp_check_password( $password, $user->user_pass, $user->ID );

        if ( ! $password_check ) {
            $this->db->record_failed_attempt( $ip, $username );
            $this->log_attempt( $user->ID, $username, 'failed', 'Wrong password' );
            $this->check_and_lock( $ip, $username );

            // Count remaining attempts for user feedback
            $attempts = $this->db->get_login_attempts( $ip, $username );
            $max      = (int) get_option( 'hikmah_max_login_attempts', 5 );
            $remaining = $max - ( $attempts ? $attempts->attempts : 0 );

            $message = __( 'Invalid username or password.', 'hikmah-login' );

            if ( $remaining > 0 && $remaining <= 3 ) {
                $message .= ' ' . sprintf(
                    /* translators: %d: Remaining attempts */
                    _n(
                        'You have %d attempt remaining.',
                        'You have %d attempts remaining.',
                        $remaining,
                        'hikmah-login'
                    ),
                    $remaining
                );
            }

            return $this->build_response( false, $message, [], 'invalid_credentials' );
        }

        // Step 8: Check if 2FA is required
        if ( $this->is_2fa_required( $user ) ) {
            $this->set_auth_state( 'pending_2fa', $user->ID );

            return $this->build_response(
                true,
                __( 'Two-factor authentication required.', 'hikmah-login' ),
                [
                    'requires_2fa' => true,
                    'user_id'      => $user->ID,
                    '2fa_method'   => $this->get_2fa_method( $user ),
                ],
                '2fa_required'
            );
        }

        // Step 9: Complete the login
        return $this->complete_login( $user, $remember, $data['redirect'] );
    }

    /**
     * Complete the login process after all checks pass.
     *
     * @param \WP_User $user     Authenticated user.
     * @param bool     $remember Remember me flag.
     * @param string   $redirect Redirect URL.
     * @return array Result array.
     */
    public function complete_login( $user, $remember = false, $redirect = '' ) {

        $ip = Helper::get_client_ip();

        // Clear failed attempts
        $this->db->reset_attempts( $ip, $user->user_login );

        // Sign in the user
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember, is_ssl() );

        // Fire WordPress login action
        do_action( 'wp_login', $user->user_login, $user );

        // Log successful login
        $this->log_attempt( $user->ID, $user->user_login, 'success' );

        // Update user login metadata
        $this->update_login_meta( $user->ID );

        // Determine redirect URL
        $redirect_url = $this->get_redirect_url( $user, $redirect );

        return $this->build_response(
            true,
            __( 'Login successful! Redirecting...', 'hikmah-login' ),
            [
                'redirect' => $redirect_url,
                'user_id'  => $user->ID,
                'username' => $user->user_login,
            ],
            'login_success'
        );
    }

    /**
     * =============================================
     * CORE LOGOUT LOGIC
     * =============================================
     */

    /**
     * Log out the current user.
     *
     * @param string $redirect Redirect URL after logout.
     * @return array Result array.
     */
    public function logout( $redirect = '' ) {

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return $this->build_response(
                false,
                __( 'You are not logged in.', 'hikmah-login' ),
                [],
                'not_logged_in'
            );
        }

        $user = get_userdata( $user_id );

        // Log the logout
        $this->log_attempt( $user_id, $user ? $user->user_login : '', 'success', 'User logged out' );

        // Destroy session
        $this->destroy_user_session( $user_id );

        // Clear auth cookies
        wp_clear_auth_cookie();

        // Fire WordPress logout action
        do_action( 'wp_logout', $user_id );

        // Determine redirect
        $redirect_url = ! empty( $redirect )
            ? Sanitizer::redirect_url( $redirect )
            : get_option( 'hikmah_login_logout_redirect_url', home_url( '/' ) );

        return $this->build_response(
            true,
            __( 'You have been logged out successfully.', 'hikmah-login' ),
            [ 'redirect' => $redirect_url ],
            'logout_success'
        );
    }

    /**
     * =============================================
     * WORDPRESS AUTHENTICATE FILTER
     * =============================================
     */

    /**
     * Intercept WordPress core authentication.
     *
     * This runs on wp-login.php and any other place
     * that calls wp_authenticate().
     *
     * @param \WP_User|\WP_Error|null $user     Authenticated user or error.
     * @param string                  $username Username.
     * @param string                  $password Password.
     * @return \WP_User|\WP_Error
     */
    public function intercept_authentication( $user, $username, $password ) {

        // If already authenticated or empty credentials, pass through
        if ( is_wp_error( $user ) && empty( $username ) && empty( $password ) ) {
            return $user;
        }

        $ip = Helper::get_client_ip();

        // Check lockout before WordPress even checks password
        if ( $this->db->is_locked( $ip, $username ) ) {
            $remaining = $this->db->get_lockout_remaining( $ip, $username );
            $minutes   = ceil( $remaining / 60 );

            return new \WP_Error(
                'hikmah_account_locked',
                sprintf(
                    __( '<strong>Account Locked:</strong> Too many failed attempts. Try again in %d minutes.', 'hikmah-login' ),
                    $minutes
                )
            );
        }

        // If WordPress auth succeeded, do our post-auth checks
        if ( ! is_wp_error( $user ) && $user instanceof \WP_User ) {

            // Check email verification
            if ( Helper::is_feature_enabled( 'email_verification_required' )
                && ! Helper::is_email_verified( $user->ID ) ) {

                return new \WP_Error(
                    'hikmah_email_not_verified',
                    sprintf(
                        /* translators: %s: Resend verification URL */
                        __( '<strong>Email Not Verified:</strong> Please verify your email. <a href="%s">Resend verification email</a>', 'hikmah-login' ),
                        esc_url( add_query_arg( 'resend_verification', $user->ID, Helper::get_login_url() ) )
                    )
                );
            }

            // Check account status
            $status = $this->get_account_status( $user );
            if ( 'suspended' === $status ) {
                return new \WP_Error(
                    'hikmah_account_suspended',
                    __( '<strong>Account Suspended:</strong> Your account has been suspended. Contact the administrator.', 'hikmah-login' )
                );
            }
        }

        // If WordPress auth failed, record the attempt
        if ( is_wp_error( $user ) && ! empty( $username ) ) {
            $this->db->record_failed_attempt( $ip, $username );
            $this->check_and_lock( $ip, $username );
        }

        return $user;
    }

    /**
     * =============================================
     * LOGIN/LOGOUT EVENT HANDLERS
     * =============================================
     */

    /**
     * Handle post-login actions.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       User object.
     */
    public function on_user_login( $user_login, $user ) {

        // Update login metadata
        $this->update_login_meta( $user->ID );

        // Log the event
        Helper::log( "User logged in: {$user_login} (ID: {$user->ID})" );

        /**
         * Fires after a user successfully logs in via Hikmah Login.
         *
         * @since 1.0.0
         * @param \WP_User $user Authenticated user.
         */
        do_action( 'hikmah_login_after_login', $user );
    }

    /**
     * Handle pre-logout actions.
     *
     * @param int $user_id User ID being logged out.
     */
    public function on_user_logout( $user_id ) {

        // Destroy session data
        $this->destroy_user_session( $user_id );

        // Log the event
        Helper::log( "User logged out: ID {$user_id}" );

        /**
         * Fires after a user logs out via Hikmah Login.
         *
         * @since 1.0.0
         * @param int $user_id User ID.
         */
        do_action( 'hikmah_login_after_logout', $user_id );
    }

    /**
     * =============================================
     * COOKIE & SESSION MANAGEMENT
     * =============================================
     */

    /**
     * Modify auth cookie expiration.
     *
     * @param int  $expiration Cookie expiration in seconds.
     * @param int  $user_id    User ID.
     * @param bool $remember   Whether "Remember Me" was checked.
     * @return int Modified expiration.
     */
    public function modify_cookie_expiration( $expiration, $user_id, $remember ) {

        if ( $remember ) {
            // 30 days for "Remember Me"
            return 30 * DAY_IN_SECONDS;
        }

        // Default: 2 days (WordPress default is 2 days)
        // Can be customized via settings
        $custom_expiration = (int) get_option( 'hikmah_cookie_expiration', 0 );

        if ( $custom_expiration > 0 ) {
            return $custom_expiration * HOUR_IN_SECONDS;
        }

        return $expiration;
    }

    /**
     * Validate auth cookie (extra security checks).
     *
     * @param bool     $is_valid Current validation result.
     * @param \WP_User $user     User from cookie.
     * @return bool
     */
    public function validate_auth_cookie( $is_valid, $user ) {

        if ( ! $is_valid || ! $user ) {
            return $is_valid;
        }

        // Check if account was suspended after cookie was issued
        $status = $this->get_account_status( $user );

        if ( 'suspended' === $status ) {
            wp_clear_auth_cookie();
            return false;
        }

        // Check if password was changed after cookie was issued
        $password_changed = get_user_meta( $user->ID, 'hikmah_password_changed_at', true );

        if ( $password_changed ) {
            $cookie_issued = isset( $_COOKIE[ LOGGED_IN_COOKIE ] )
                ? wp_parse_auth_cookie( $_COOKIE[ LOGGED_IN_COOKIE ], 'logged_in' )
                : null;

            if ( $cookie_issued && isset( $cookie_issued['expiration'] ) ) {
                $cookie_age = time() - ( $cookie_issued['expiration'] - 2 * DAY_IN_SECONDS );
                if ( strtotime( $password_changed ) > $cookie_age ) {
                    wp_clear_auth_cookie();
                    return false;
                }
            }
        }

        return $is_valid;
    }

    /**
     * =============================================
     * REDIRECT MANAGEMENT
     * =============================================
     */

    /**
     * Handle login redirect based on user role.
     *
     * @param string           $redirect_to           Default redirect URL.
     * @param string           $requested_redirect_to Requested redirect URL.
     * @param \WP_User|\WP_Error $user                Authenticated user.
     * @return string Final redirect URL.
     */
    public function handle_login_redirect( $redirect_to, $requested_redirect_to, $user ) {

        if ( is_wp_error( $user ) ) {
            return $redirect_to;
        }

        // If a specific redirect was requested and is safe, use it
        if ( ! empty( $requested_redirect_to )
            && wp_validate_redirect( $requested_redirect_to, false ) ) {
            return $requested_redirect_to;
        }

        return $this->get_redirect_url( $user, $redirect_to );
    }

    /**
     * Handle logout redirect.
     *
     * @param string $redirect_to           Default redirect URL.
     * @param string $requested_redirect_to Requested redirect URL.
     * @param int    $user_id               User being logged out.
     * @return string
     */
    public function handle_logout_redirect( $redirect_to, $requested_redirect_to, $user_id ) {

        if ( ! empty( $requested_redirect_to )
            && wp_validate_redirect( $requested_redirect_to, false ) ) {
            return $requested_redirect_to;
        }

        return get_option( 'hikmah_login_logout_redirect_url', home_url( '/' ) );
    }

    /**
     * Get the appropriate redirect URL based on user role.
     *
     * @param \WP_User $user     User object.
     * @param string   $fallback Fallback URL.
     * @return string
     */
    private function get_redirect_url( $user, $fallback = '' ) {

        // Role-based redirect settings
        $role_redirects = [
            'administrator' => get_option( 'hikmah_redirect_admin', admin_url() ),
            'editor'        => get_option( 'hikmah_redirect_editor', admin_url() ),
            'author'        => get_option( 'hikmah_redirect_author', Helper::get_dashboard_url() ),
            'contributor'   => get_option( 'hikmah_redirect_author', Helper::get_dashboard_url() ),
            'subscriber'    => get_option( 'hikmah_redirect_subscriber', Helper::get_dashboard_url() ),
        ];

        // Get user's primary role
        $user_roles = $user->roles;
        $primary_role = ! empty( $user_roles ) ? $user_roles[0] : 'subscriber';

        // Check role-based redirect
        if ( isset( $role_redirects[ $primary_role ] ) && ! empty( $role_redirects[ $primary_role ] ) ) {
            $url = $role_redirects[ $primary_role ];
        } else {
            $url = get_option( 'hikmah_login_redirect_url', Helper::get_dashboard_url() );
        }

        // Validate the URL
        if ( ! wp_validate_redirect( $url, false ) ) {
            $url = home_url( '/' );
        }

        /**
         * Filter the login redirect URL.
         *
         * @since 1.0.0
         * @param string   $url  Redirect URL.
         * @param \WP_User $user Authenticated user.
         * @param string   $primary_role User's primary role.
         */
        return apply_filters( 'hikmah_login_redirect_url', $url, $user, $primary_role );
    }

    /**
     * =============================================
     * ACCOUNT STATUS & META
     * =============================================
     */

    /**
     * Get the account status of a user.
     *
     * @param \WP_User $user User object.
     * @return string Status: active, suspended, pending_verification.
     */
    public function get_account_status( $user ) {

        // Check if manually suspended
        $suspended = get_user_meta( $user->ID, 'hikmah_account_suspended', true );
        if ( 'yes' === $suspended ) {
            return 'suspended';
        }

        // Check email verification
        if ( Helper::is_feature_enabled( 'email_verification_required' )
            && ! Helper::is_email_verified( $user->ID ) ) {
            return 'pending_verification';
        }

        return 'active';
    }

    /**
     * Suspend a user account.
     *
     * @param int    $user_id User ID.
     * @param string $reason  Suspension reason.
     */
    public function suspend_account( $user_id, $reason = '' ) {
        update_user_meta( $user_id, 'hikmah_account_suspended', 'yes' );
        update_user_meta( $user_id, 'hikmah_suspension_reason', $reason );
        update_user_meta( $user_id, 'hikmah_suspended_at', Helper::current_datetime() );

        // Force logout
        $this->force_logout( $user_id );

        Helper::log( "Account suspended: User ID {$user_id}. Reason: {$reason}" );
    }

    /**
     * Reactivate a suspended account.
     *
     * @param int $user_id User ID.
     */
    public function reactivate_account( $user_id ) {
        delete_user_meta( $user_id, 'hikmah_account_suspended' );
        delete_user_meta( $user_id, 'hikmah_suspension_reason' );
        delete_user_meta( $user_id, 'hikmah_suspended_at' );

        Helper::log( "Account reactivated: User ID {$user_id}" );
    }

    /**
     * Update user login metadata.
     *
     * @param int $user_id User ID.
     */
    private function update_login_meta( $user_id ) {

        $previous_login = get_user_meta( $user_id, 'hikmah_last_login_at', true );

        update_user_meta( $user_id, 'hikmah_previous_login_at', $previous_login );
        update_user_meta( $user_id, 'hikmah_last_login_at', Helper::current_datetime() );
        update_user_meta( $user_id, 'hikmah_last_login_ip', Helper::get_client_ip() );
        update_user_meta( $user_id, 'hikmah_login_count',
            (int) get_user_meta( $user_id, 'hikmah_login_count', true ) + 1
        );
    }

    /**
     * =============================================
     * 2FA CHECKS
     * =============================================
     */

    /**
     * Check if 2FA is required for a user.
     *
     * @param \WP_User $user User object.
     * @return bool
     */
    private function is_2fa_required( $user ) {

        // Global 2FA check
        if ( ! Helper::is_feature_enabled( '2fa_enabled' ) ) {
            return false;
        }

        // Check if user has 2FA enabled
        $settings = $this->db->get_2fa_settings( $user->ID );

        return $settings && (int) $settings->is_enabled === 1;
    }

    /**
     * Get the 2FA method for a user.
     *
     * @param \WP_User $user User object.
     * @return string Method: email, authenticator, sms.
     */
    private function get_2fa_method( $user ) {

        $settings = $this->db->get_2fa_settings( $user->ID );

        if ( $settings && ! empty( $settings->method ) ) {
            return $settings->method;
        }

        return get_option( 'hikmah_2fa_method', 'email' );
    }

    /**
     * =============================================
     * SECURITY HELPERS
     * =============================================
     */

    /**
     * Check and lock account if max attempts exceeded.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     */
    private function check_and_lock( $ip, $username ) {

        $max_attempts = (int) get_option( 'hikmah_max_login_attempts', 5 );
        $lockout_mins = (int) get_option( 'hikmah_lockout_duration', 30 );

        $attempts = $this->db->get_login_attempts( $ip, $username );

        if ( $attempts && $attempts->attempts >= $max_attempts ) {
            $this->db->lock_account( $ip, $username, $lockout_mins );
            $this->log_attempt( null, $username, 'locked', "Max attempts ({$max_attempts}) reached" );

            /**
             * Fires when an account is locked due to brute force.
             *
             * @since 1.0.0
             * @param string $ip       IP address.
             * @param string $username Username.
             * @param int    $attempts Number of failed attempts.
             */
            do_action( 'hikmah_login_account_locked', $ip, $username, $attempts->attempts );
        }
    }

    /**
     * Simple rate limiting check.
     *
     * @param string $ip IP address.
     * @return bool True if rate limited.
     */
    private function is_rate_limited( $ip ) {

        $transient_key = 'hikmah_rate_' . md5( $ip );
        $count = (int) get_transient( $transient_key );

        // Max 20 login requests per minute per IP
        $max_requests = 20;

        if ( $count >= $max_requests ) {
            return true;
        }

        set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

        return false;
    }

    /**
     * Force logout a user (destroy all sessions).
     *
     * @param int $user_id User ID.
     */
    public function force_logout( $user_id ) {

        // Destroy all sessions
        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $sessions->destroy_all();

        // Clear auth cookies if current user
        if ( get_current_user_id() === $user_id ) {
            wp_clear_auth_cookie();
        }

        Helper::log( "Force logout: User ID {$user_id}" );
    }

    /**
     * Destroy a specific user session.
     *
     * @param int $user_id User ID.
     */
    private function destroy_user_session( $user_id ) {

        $sessions = \WP_Session_Tokens::get_instance( $user_id );

        // Get current session token from cookie
        $token = wp_get_session_token();

        if ( $token ) {
            $sessions->destroy( $token );
        }
    }

    /**
     * =============================================
     * EVENT HANDLERS
     * =============================================
     */

    /**
     * Handle password reset event.
     *
     * @param \WP_User $user     User object.
     * @param string   $new_pass New password.
     */
    public function on_password_reset( $user, $new_pass ) {

        update_user_meta( $user->ID, 'hikmah_password_changed_at', Helper::current_datetime() );

        // Force logout from all other sessions
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $sessions->destroy_all();

        $this->log_attempt( $user->ID, $user->user_login, 'success', 'Password reset' );

        Helper::log( "Password reset: User ID {$user->ID}" );
    }

    /**
     * Handle profile update event.
     *
     * @param int      $user_id       User ID.
     * @param \WP_User $old_user_data Old user data.
     * @param array    $userdata       New user data.
     */
    public function on_profile_update( $user_id, $old_user_data, $userdata = [] ) {

        // Check if email was changed
        if ( isset( $userdata['user_email'] )
            && $userdata['user_email'] !== $old_user_data->user_email ) {

            // Reset email verification for new email
            if ( Helper::is_feature_enabled( 'email_verification_required' ) ) {
                update_user_meta( $user_id, 'hikmah_email_verified', 'no' );
                delete_user_meta( $user_id, 'hikmah_email_verified_at' );
            }

            Helper::log( "Email changed: User ID {$user_id}" );
        }
    }

    /**
     * Handle user deletion.
     *
     * @param int      $user_id  User ID being deleted.
     * @param int|null $reassign Reassign posts to this user ID.
     */
    public function on_user_delete( $user_id, $reassign = null ) {

        // Clean up plugin data for this user
        $meta_keys = [
            'hikmah_email_verified',
            'hikmah_email_verified_at',
            'hikmah_last_login_at',
            'hikmah_previous_login_at',
            'hikmah_last_login_ip',
            'hikmah_login_count',
            'hikmah_password_changed_at',
            'hikmah_account_suspended',
            'hikmah_suspension_reason',
            'hikmah_suspended_at',
        ];

        foreach ( $meta_keys as $key ) {
            delete_user_meta( $user_id, $key );
        }

        // Clean up DB tables
        $this->db->delete( 'email_tokens', [ 'user_id' => $user_id ] );
        $this->db->delete( 'two_factor', [ 'user_id' => $user_id ] );
        $this->db->delete( 'social_profiles', [ 'user_id' => $user_id ] );

        Helper::log( "User deleted cleanup: User ID {$user_id}" );
    }

    /**
     * =============================================
     * LOGGING
     * =============================================
     */

    /**
     * Log a login attempt to the database.
     *
     * @param int|null $user_id        User ID.
     * @param string   $username       Username.
     * @param string   $status         Status: success, failed, blocked, locked.
     * @param string   $failure_reason Reason for failure.
     */
    private function log_attempt( $user_id, $username, $status, $failure_reason = '' ) {

        $this->db->log_login_attempt( [
            'user_id'        => $user_id,
            'username'       => $username,
            'status'         => $status,
            'failure_reason' => $failure_reason,
        ] );
    }

    /**
     * =============================================
     * AUTH STATE MANAGEMENT
     * =============================================
     */

    /**
     * Set authentication state (for multi-step auth like 2FA).
     *
     * @param string $state   State name.
     * @param int    $user_id User ID.
     */
    private function set_auth_state( $state, $user_id ) {

        $this->auth_state = [
            'state'   => $state,
            'user_id' => $user_id,
            'time'    => time(),
        ];

        // Store in transient for AJAX follow-up
        $key = 'hikmah_auth_state_' . md5( Helper::get_client_ip() . $user_id );
        set_transient( $key, $this->auth_state, 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Get current authentication state.
     *
     * @param int $user_id User ID.
     * @return array|null
     */
    public function get_auth_state( $user_id ) {

        $key = 'hikmah_auth_state_' . md5( Helper::get_client_ip() . $user_id );
        $state = get_transient( $key );

        if ( ! $state || ( time() - $state['time'] ) > 300 ) {
            return null;
        }

        return $state;
    }

    /**
     * Clear authentication state.
     *
     * @param int $user_id User ID.
     */
    public function clear_auth_state( $user_id ) {
        $key = 'hikmah_auth_state_' . md5( Helper::get_client_ip() . $user_id );
        delete_transient( $key );
    }

    /**
     * =============================================
     * RESPONSE BUILDER
     * =============================================
     */

    /**
     * Build a standardized response array.
     *
     * @param bool   $success Success status.
     * @param string $message Human-readable message.
     * @param array  $data    Additional data.
     * @param string $code    Machine-readable code.
     * @return array
     */
    private function build_response( $success, $message, $data = [], $code = '' ) {

        $response = [
            'success' => $success,
            'message' => $message,
            'code'    => $code,
            'data'    => $data,
        ];

        /**
         * Filter auth response before returning.
         *
         * @since 1.0.0
         * @param array $response Response array.
         */
        return apply_filters( 'hikmah_login_auth_response', $response );
    }
}
