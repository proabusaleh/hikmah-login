<?php
/**
 * Core Hooks & Filters
 *
 * Registers all WordPress hooks and filters that integrate
 * Hikmah Login with the WordPress core authentication system.
 * This class acts as the bridge between WordPress and our plugin.
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core_Hooks {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_all_hooks();
    }

    /**
     * Register all core hooks and filters.
     */
    private function register_all_hooks() {

        // =============================================
        // AUTHENTICATION HOOKS
        // =============================================

        // Pre-authentication filter (before WP checks credentials)
        $this->add_filter( 'pre_authenticate', 'pre_authentication_check', 10, 3 );

        // Authentication filter (main auth interception)
        // Note: Auth_Manager also hooks here at priority 20
        $this->add_filter( 'authenticate', 'early_auth_check', 5, 3 );

        // Determine current user from cookie
        $this->add_filter( 'determine_current_user', 'determine_user_from_cookie', 20, 1 );

        // Validate logged-in cookie
        $this->add_filter( 'validate_logged_in_cookie', 'validate_logged_in_cookie', 10, 2 );

        // =============================================
        // USER REGISTRATION HOOKS
        // =============================================

        // Pre-user registration validation
        $this->add_filter( 'pre_user_login', 'sanitize_user_login', 10, 1 );
        $this->add_filter( 'pre_user_email', 'sanitize_user_email', 10, 1 );

        // After user registration
        $this->add_action( 'user_register', 'on_user_registered', 10, 2 );

        // Registration errors
        $this->add_filter( 'registration_errors', 'custom_registration_errors', 10, 3 );

        // =============================================
        // PASSWORD HOOKS
        // =============================================

        // Password strength validation
        $this->add_filter( 'validate_password_reset', 'validate_password_strength', 10, 2 );

        // Minimum password strength
        $this->add_action( 'admin_enqueue_scripts', 'enforce_password_strength' );

        // Password changed notification
        $this->add_action( 'after_password_reset', 'on_password_changed', 20, 2 );

        // =============================================
        // USER PROFILE HOOKS
        // =============================================

        // Profile update validation
        $this->add_action( 'user_profile_update_errors', 'profile_update_validation', 10, 3 );

        // Custom user contact methods
        $this->add_filter( 'user_contactmethods', 'custom_contact_methods', 10, 1 );

        // Custom user profile fields
        $this->add_action( 'show_user_profile', 'show_custom_profile_fields' );
        $this->add_action( 'edit_user_profile', 'show_custom_profile_fields' );

        // Save custom profile fields
        $this->add_action( 'personal_options_update', 'save_custom_profile_fields' );
        $this->add_action( 'edit_user_profile_update', 'save_custom_profile_fields' );

        // =============================================
        // ADMIN BAR & DASHBOARD HOOKS
        // =============================================

        // Modify admin bar for logged-in users
        $this->add_action( 'admin_bar_menu', 'modify_admin_bar', 999 );

        // Dashboard redirect for non-admins
        $this->add_action( 'admin_init', 'dashboard_access_control' );

        // =============================================
        // SECURITY HOOKS
        // =============================================

        // XML-RPC authentication
        $this->add_filter( 'xmlrpc_enabled', 'control_xmlrpc' );

        // REST API authentication
        $this->add_filter( 'rest_authentication_errors', 'rest_api_auth_check', 10, 1 );

        // Application passwords control
        $this->add_filter( 'wp_is_application_passwords_available', 'control_app_passwords' );

        // =============================================
        // CRON HOOKS
        // =============================================

        $this->add_action( 'hikmah_login_cleanup_tokens', 'cron_cleanup_tokens' );
        $this->add_action( 'hikmah_login_cleanup_logs', 'cron_cleanup_logs' );
        $this->add_action( 'hikmah_login_reset_lockouts', 'cron_reset_lockouts' );

        // =============================================
        // SHORTCODE INITIALIZATION
        // =============================================

        $this->add_action( 'init', 'register_shortcodes', 5 );

        // =============================================
        // WIDGET INITIALIZATION
        // =============================================

        $this->add_action( 'widgets_init', 'register_widgets' );
    }

    /**
     * =============================================
     * AUTHENTICATION HANDLERS
     * =============================================
     */

    /**
     * Pre-authentication check.
     *
     * Runs before WordPress even attempts to authenticate.
     * Used for early blocking (IP bans, maintenance mode, etc.)
     *
     * @param null|\WP_User|\WP_Error $user     Current auth result.
     * @param string                  $username Username.
     * @param string                  $password Password.
     * @return null|\WP_User|\WP_Error
     */
    public function pre_authentication_check( $user, $username, $password ) {

        // Check if login is globally disabled
        if ( ! Helper::is_feature_enabled( 'login_enabled' ) ) {
            return new \WP_Error(
                'hikmah_login_disabled',
                __( 'Login is currently disabled. Please try again later.', 'hikmah-login' )
            );
        }

        // Check IP blacklist
        $ip = Helper::get_client_ip();
        if ( $this->is_ip_blacklisted( $ip ) ) {
            Error_Handler::warning( "Blacklisted IP attempted login: {$ip}" );

            return new \WP_Error(
                'hikmah_ip_blocked',
                __( 'Access denied from your location.', 'hikmah-login' )
            );
        }

        // Check maintenance mode
        if ( $this->is_maintenance_mode() && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'hikmah_maintenance',
                __( 'The site is currently under maintenance. Please try again later.', 'hikmah-login' )
            );
        }

        return $user;
    }

    /**
     * Early authentication check (priority 5).
     *
     * Runs before WordPress core auth (priority 20) and
     * before Auth_Manager (priority 20).
     *
     * @param \WP_User|\WP_Error|null $user     Current auth result.
     * @param string                  $username Username.
     * @param string                  $password Password.
     * @return \WP_User|\WP_Error|null
     */
    public function early_auth_check( $user, $username, $password ) {

        // Skip if empty (initial call)
        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        /**
         * Fires at the very start of authentication.
         *
         * @since 1.0.0
         * @param string $username Username being authenticated.
         * @param string $ip       Client IP address.
         */
        do_action( 'hikmah_login_auth_attempt', $username, Helper::get_client_ip() );

        return $user;
    }

    /**
     * Determine current user from custom cookie.
     *
     * @param int|false $user_id Current user ID.
     * @return int|false
     */
    public function determine_user_from_cookie( $user_id ) {

        // If already determined, pass through
        if ( $user_id ) {
            return $user_id;
        }

        // Additional cookie validation can go here
        // (e.g., custom session tokens, JWT, etc.)

        return $user_id;
    }

    /**
     * Validate the logged-in cookie.
     *
     * @param bool $is_valid Current validation status.
     * @param int  $user_id  User ID from cookie.
     * @return bool
     */
    public function validate_logged_in_cookie( $is_valid, $user_id ) {

        if ( ! $is_valid ) {
            return false;
        }

        // Check if user account still exists and is active
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        // Check suspension
        $suspended = get_user_meta( $user_id, 'hikmah_account_suspended', true );
        if ( 'yes' === $suspended ) {
            return false;
        }

        return true;
    }

    /**
     * =============================================
     * REGISTRATION HANDLERS
     * =============================================
     */

    /**
     * Sanitize username before registration.
     *
     * @param string $user_login Username.
     * @return string Sanitized username.
     */
    public function sanitize_user_login( $user_login ) {
        return sanitize_user( $user_login, true );
    }

    /**
     * Sanitize email before registration.
     *
     * @param string $user_email Email.
     * @return string Sanitized email.
     */
    public function sanitize_user_email( $user_email ) {
        return sanitize_email( $user_email );
    }

    /**
     * Handle post-registration actions.
     *
     * @param int   $user_id  New user ID.
     * @param array $userdata User data array (WP 5.9+).
     */
    public function on_user_registered( $user_id, $userdata = [] ) {

        // Set initial email verification status
        if ( Helper::is_feature_enabled( 'email_verification_required' ) ) {
            update_user_meta( $user_id, 'hikmah_email_verified', 'no' );
        } else {
            update_user_meta( $user_id, 'hikmah_email_verified', 'yes' );
            update_user_meta( $user_id, 'hikmah_email_verified_at', Helper::current_datetime() );
        }

        // Set registration metadata
        update_user_meta( $user_id, 'hikmah_registered_at', Helper::current_datetime() );
        update_user_meta( $user_id, 'hikmah_registration_ip', Helper::get_client_ip() );
        update_user_meta( $user_id, 'hikmah_login_count', 0 );

        // Set default role if configured
        $default_role = get_option( 'hikmah_default_user_role', 'subscriber' );
        $user = get_userdata( $user_id );

        if ( $user && ! in_array( $default_role, $user->roles, true ) ) {
            $user->set_role( $default_role );
        }

        Helper::log( "New user registered: ID {$user_id}" );

        /**
         * Fires after a user registers via Hikmah Login.
         *
         * @since 1.0.0
         * @param int   $user_id  New user ID.
         * @param array $userdata User data.
         */
        do_action( 'hikmah_login_user_registered', $user_id, $userdata );
    }

    /**
     * Add custom registration errors.
     *
     * @param \WP_Error $errors   Registration errors.
     * @param string    $sanitized_user_login Sanitized username.
     * @param string    $user_email User email.
     * @return \WP_Error
     */
    public function custom_registration_errors( $errors, $sanitized_user_login, $user_email ) {

        // Check if registration is enabled
        if ( ! Helper::is_feature_enabled( 'registration_enabled' ) ) {
            $errors->add(
                'hikmah_registration_disabled',
                __( 'Registration is currently disabled.', 'hikmah-login' )
            );
        }

        // Check disposable email
        if ( ! empty( $user_email ) ) {
            $domain = strtolower( substr( strrchr( $user_email, '@' ), 1 ) );
            $disposable = [
                'mailinator.com', 'guerrillamail.com', 'tempmail.com',
                'yopmail.com', 'throwaway.email',
            ];

            if ( in_array( $domain, $disposable, true ) ) {
                $errors->add(
                    'hikmah_disposable_email',
                    __( 'Disposable email addresses are not allowed.', 'hikmah-login' )
                );
            }
        }

        // Check username blacklist
        $reserved = [ 'admin', 'administrator', 'root', 'webmaster', 'support' ];
        if ( in_array( strtolower( $sanitized_user_login ), $reserved, true ) ) {
            $errors->add(
                'hikmah_reserved_username',
                __( 'This username is reserved.', 'hikmah-login' )
            );
        }

        return $errors;
    }

    /**
     * =============================================
     * PASSWORD HANDLERS
     * =============================================
     */

    /**
     * Validate password strength during reset.
     *
     * @param \WP_Error $errors WP_Error object.
     * @param \WP_User  $user   User object.
     * @return \WP_Error
     */
    public function validate_password_strength( $errors, $user ) {

        if ( ! isset( $_POST['pass1'] ) || empty( $_POST['pass1'] ) ) {
            return $errors;
        }

        $password = $_POST['pass1'];
        $min_length = 8;

        if ( mb_strlen( $password ) < $min_length ) {
            $errors->add(
                'hikmah_weak_password',
                sprintf(
                    __( 'Password must be at least %d characters long.', 'hikmah-login' ),
                    $min_length
                )
            );
        }

        return $errors;
    }

    /**
     * Enforce password strength meter on admin pages.
     */
    public function enforce_password_strength() {

        $screen = get_current_screen();

        if ( ! $screen || ! in_array( $screen->id, [ 'profile', 'user-edit' ], true ) ) {
            return;
        }

        // WordPress already has a strength meter
        // We can customize the minimum strength requirement
        wp_add_inline_script(
            'user-profile',
            '
            (function() {
                var originalCheck = wp.passwordStrength.meter;
                if (originalCheck) {
                    wp.passwordStrength.meter = function(password, blacklist, username) {
                        var strength = originalCheck(password, blacklist, username);
                        // Require at least "Medium" (3) strength
                        if (strength < 3 && password.length > 0) {
                            jQuery(".pw-weak").show();
                        }
                        return strength;
                    };
                }
            })();
            '
        );
    }

    /**
     * Handle password change event.
     *
     * @param \WP_User $user     User object.
     * @param string   $new_pass New password.
     */
    public function on_password_changed( $user, $new_pass ) {

        update_user_meta( $user->ID, 'hikmah_password_changed_at', Helper::current_datetime() );

        // Invalidate all other sessions
        $sessions = \WP_Session_Tokens::get_instance( $user->ID );
        $current_token = wp_get_session_token();

        foreach ( $sessions->get_all() as $token => $session ) {
            if ( $token !== $current_token ) {
                $sessions->destroy( $token );
            }
        }

        Helper::log( "Password changed: User ID {$user->ID}" );
    }

    /**
     * =============================================
     * PROFILE HANDLERS
     * =============================================
     */

    /**
     * Validate profile updates.
     *
     * @param \WP_Error $errors WP_Error object.
     * @param bool      $update Whether this is an update.
     * @param \stdClass $user   User object.
     */
    public function profile_update_validation( $errors, $update, $user ) {

        // Validate email change
        if ( isset( $user->user_email ) && ! is_email( $user->user_email ) ) {
            $errors->add(
                'hikmah_invalid_email',
                __( 'Please enter a valid email address.', 'hikmah-login' )
            );
        }
    }

    /**
     * Add custom contact methods to user profile.
     *
     * @param array $methods Existing contact methods.
     * @return array Modified contact methods.
     */
    public function custom_contact_methods( $methods ) {

        // Remove outdated defaults
        unset( $methods['aim'] );
        unset( $methods['yim'] );
        unset( $methods['jabber'] );

        // Add modern contact methods
        $methods['phone']      = __( 'Phone Number', 'hikmah-login' );
        $methods['whatsapp']   = __( 'WhatsApp', 'hikmah-login' );
        $methods['telegram']   = __( 'Telegram', 'hikmah-login' );
        $methods['linkedin']   = __( 'LinkedIn URL', 'hikmah-login' );
        $methods['twitter']    = __( 'X (Twitter) URL', 'hikmah-login' );
        $methods['github']     = __( 'GitHub URL', 'hikmah-login' );

        return $methods;
    }

    /**
     * Show custom profile fields on user profile page.
     *
     * @param \WP_User $user User object.
     */
    public function show_custom_profile_fields( $user ) {

        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }

        $email_verified = get_user_meta( $user->ID, 'hikmah_email_verified', true );
        $last_login     = get_user_meta( $user->ID, 'hikmah_last_login_at', true );
        $login_count    = get_user_meta( $user->ID, 'hikmah_login_count', true );
        $registered_at  = get_user_meta( $user->ID, 'hikmah_registered_at', true );
        $is_suspended   = get_user_meta( $user->ID, 'hikmah_account_suspended', true );

        ?>
        <h3><?php esc_html_e( 'Hikmah Login Information', 'hikmah-login' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Email Verified', 'hikmah-login' ); ?></th>
                <td>
                    <?php if ( 'yes' === $email_verified ) : ?>
                        <span style="color: green;">✅ <?php esc_html_e( 'Verified', 'hikmah-login' ); ?></span>
                    <?php else : ?>
                        <span style="color: red;">❌ <?php esc_html_e( 'Not Verified', 'hikmah-login' ); ?></span>
                        <label>
                            <input type="checkbox" name="hikmah_force_verify" value="1">
                            <?php esc_html_e( 'Manually verify', 'hikmah-login' ); ?>
                        </label>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Account Status', 'hikmah-login' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="hikmah_suspend_account" value="1"
                            <?php checked( $is_suspended, 'yes' ); ?>>
                        <?php esc_html_e( 'Suspend this account', 'hikmah-login' ); ?>
                    </label>
                    <?php if ( 'yes' === $is_suspended ) : ?>
                        <p class="description" style="color: red;">
                            <?php esc_html_e( 'This account is currently suspended.', 'hikmah-login' ); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Last Login', 'hikmah-login' ); ?></th>
                <td>
                    <?php
                    echo $last_login
                        ? esc_html( Helper::format_datetime( $last_login ) )
                        : esc_html__( 'Never', 'hikmah-login' );
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Total Logins', 'hikmah-login' ); ?></th>
                <td><?php echo esc_html( $login_count ?: '0' ); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Registered', 'hikmah-login' ); ?></th>
                <td>
                    <?php
                    echo $registered_at
                        ? esc_html( Helper::format_datetime( $registered_at ) )
                        : esc_html__( 'Unknown', 'hikmah-login' );
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save custom profile fields.
     *
     * @param int $user_id User ID.
     */
    public function save_custom_profile_fields( $user_id ) {

        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        // Force email verification
        if ( isset( $_POST['hikmah_force_verify'] ) ) {
            Helper::mark_email_verified( $user_id );
        }

        // Suspend/unsuspend account
        if ( isset( $_POST['hikmah_suspend_account'] ) ) {
            $auth_manager = Auth_Manager::get_instance();
            $auth_manager->suspend_account( $user_id, 'Manually suspended by admin' );
        } else {
            $auth_manager = Auth_Manager::get_instance();
            $auth_manager->reactivate_account( $user_id );
        }
    }

    /**
     * =============================================
     * ADMIN BAR & DASHBOARD
     * =============================================
     */

    /**
     * Modify the WordPress admin bar.
     *
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
     */
    public function modify_admin_bar( $wp_admin_bar ) {

        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();

        // Add "My Account" link
        $wp_admin_bar->add_node( [
            'id'     => 'hikmah-my-account',
            'parent' => 'user-actions',
            'title'  => __( 'My Account', 'hikmah-login' ),
            'href'   => Helper::get_dashboard_url(),
        ] );

        // Add active sessions count for admins
        if ( current_user_can( 'manage_options' ) ) {
            $sessions = \WP_Session_Tokens::get_instance( $user_id );
            $count = count( $sessions->get_all() );

            $wp_admin_bar->add_node( [
                'id'     => 'hikmah-sessions',
                'parent' => 'user-actions',
                'title'  => sprintf(
                    /* translators: %d: Number of active sessions */
                    __( 'Active Sessions (%d)', 'hikmah-login' ),
                    $count
                ),
                'href'   => admin_url( 'profile.php#hikmah-sessions' ),
            ] );
        }
    }

    /**
     * Control dashboard access for non-admin users.
     */
    public function dashboard_access_control() {

        // Only apply if feature is enabled
        if ( ! get_option( 'hikmah_restrict_dashboard', false ) ) {
            return;
        }

        // Allow admins and editors
        if ( current_user_can( 'edit_others_posts' ) ) {
            return;
        }

        // Allow AJAX requests
        if ( wp_doing_ajax() ) {
            return;
        }

        // Allow profile page
        global $pagenow;
        if ( 'profile.php' === $pagenow ) {
            return;
        }

        // Redirect to frontend dashboard
        wp_safe_redirect( Helper::get_dashboard_url() );
        exit;
    }

    /**
     * =============================================
     * SECURITY CONTROLS
     * =============================================
     */

    /**
     * Control XML-RPC access.
     *
     * @param bool $enabled Whether XML-RPC is enabled.
     * @return bool
     */
    public function control_xmlrpc( $enabled ) {

        $disable_xmlrpc = get_option( 'hikmah_disable_xmlrpc', false );

        if ( $disable_xmlrpc ) {
            return false;
        }

        return $enabled;
    }

    /**
     * REST API authentication check.
     *
     * @param \WP_Error|null|bool $result Current auth result.
     * @return \WP_Error|null|bool
     */
    public function rest_api_auth_check( $result ) {

        // If already authenticated or error, pass through
        if ( ! is_null( $result ) ) {
            return $result;
        }

        // Allow public endpoints
        $public_namespaces = [
            'hikmah-login/v1/login',
            'hikmah-login/v1/register',
            'hikmah-login/v1/forgot-password',
            'hikmah-login/v1/verify-email',
        ];

        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        foreach ( $public_namespaces as $namespace ) {
            if ( strpos( $request_uri, $namespace ) !== false ) {
                return $result;
            }
        }

        return $result;
    }

    /**
     * Control application passwords.
     *
     * @param bool $available Whether app passwords are available.
     * @return bool
     */
    public function control_app_passwords( $available ) {

        $disable_app_passwords = get_option( 'hikmah_disable_app_passwords', false );

        if ( $disable_app_passwords ) {
            return false;
        }

        return $available;
    }

    /**
     * =============================================
     * CRON HANDLERS
     * =============================================
     */

    /**
     * Clean up expired email tokens.
     */
    public function cron_cleanup_tokens() {

        $db = new \Hikmah_Login\Database\DB_Manager();
        $deleted = $db->cleanup_expired_tokens();

        Helper::log( "Cron: Cleaned up {$deleted} expired tokens." );
    }

    /**
     * Clean up old login logs.
     */
    public function cron_cleanup_logs() {

        $retention = (int) get_option( 'hikmah_log_retention_days', 90 );
        $db = new \Hikmah_Login\Database\DB_Manager();
        $deleted = $db->cleanup_old_logs( $retention );

        Helper::log( "Cron: Cleaned up {$deleted} old login logs ({$retention} days)." );
    }

    /**
     * Reset expired lockouts.
     */
    public function cron_reset_lockouts() {

        $db = new \Hikmah_Login\Database\DB_Manager();
        $deleted = $db->cleanup_expired_lockouts();

        Helper::log( "Cron: Cleaned up {$deleted} expired lockouts." );
    }

    /**
     * =============================================
     * SHORTCODES & WIDGETS
     * =============================================
     */

    /**
     * Register all shortcodes.
     */
    public function register_shortcodes() {

        // Shortcodes will be fully implemented in Phase 12
        // For now, register placeholders

        add_shortcode( 'hikmah_login', [ $this, 'shortcode_login' ] );
        add_shortcode( 'hikmah_register', [ $this, 'shortcode_register' ] );
        add_shortcode( 'hikmah_forgot_password', [ $this, 'shortcode_forgot_password' ] );
        add_shortcode( 'hikmah_reset_password', [ $this, 'shortcode_reset_password' ] );
        add_shortcode( 'hikmah_dashboard', [ $this, 'shortcode_dashboard' ] );
        add_shortcode( 'hikmah_logout', [ $this, 'shortcode_logout' ] );
    }

    /**
     * Login shortcode placeholder.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_login( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p class="hikmah-logged-in">' .
                sprintf(
                    /* translators: %s: Dashboard URL */
                    __( 'You are already logged in. <a href="%s">Go to Dashboard</a>', 'hikmah-login' ),
                    esc_url( Helper::get_dashboard_url() )
                ) .
                '</p>';
        }

        ob_start();
        $template = HIKMAH_LOGIN_DIR . 'public/views/login-form.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="hikmah-login-form"><p>' .
                esc_html__( 'Login form will appear here.', 'hikmah-login' ) .
                '</p></div>';
        }
        return ob_get_clean();
    }

    /**
     * Register shortcode placeholder.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_register( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p class="hikmah-logged-in">' .
                sprintf(
                    __( 'You are already registered. <a href="%s">Go to Dashboard</a>', 'hikmah-login' ),
                    esc_url( Helper::get_dashboard_url() )
                ) .
                '</p>';
        }

        ob_start();
        $template = HIKMAH_LOGIN_DIR . 'public/views/register-form.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="hikmah-register-form"><p>' .
                esc_html__( 'Registration form will appear here.', 'hikmah-login' ) .
                '</p></div>';
        }
        return ob_get_clean();
    }

    /**
     * Forgot password shortcode placeholder.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_forgot_password( $atts ) {
        ob_start();
        $template = HIKMAH_LOGIN_DIR . 'public/views/forgot-password-form.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="hikmah-forgot-form"><p>' .
                esc_html__( 'Forgot password form will appear here.', 'hikmah-login' ) .
                '</p></div>';
        }
        return ob_get_clean();
    }

    /**
     * Reset password shortcode placeholder.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_reset_password( $atts ) {
        ob_start();
        $template = HIKMAH_LOGIN_DIR . 'public/views/reset-password-form.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            echo '<div class="hikmah-reset-form"><p>' .
                esc_html__( 'Reset password form will appear here.', 'hikmah-login' ) .
                '</p></div>';
        }
        return ob_get_clean();
    }

    /**
     * Dashboard shortcode placeholder.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_dashboard( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="hikmah-not-logged-in">' .
                sprintf(
                    __( 'Please <a href="%s">log in</a> to view your dashboard.', 'hikmah-login' ),
                    esc_url( Helper::get_login_url() )
                ) .
                '</p>';
        }

        ob_start();
        $template = HIKMAH_LOGIN_DIR . 'public/views/user-dashboard.php';
        if ( file_exists( $template ) ) {
            include $template;
        } else {
            $user = wp_get_current_user();
            echo '<div class="hikmah-dashboard">';
            echo '<h2>' . sprintf(
                esc_html__( 'Welcome, %s!', 'hikmah-login' ),
                esc_html( $user->display_name )
            ) . '</h2>';
            echo '<p>' . esc_html__( 'Your dashboard will appear here.', 'hikmah-login' ) . '</p>';
            echo '</div>';
        }
        return ob_get_clean();
    }

    /**
     * Logout shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function shortcode_logout( $atts ) {

        $atts = shortcode_atts( [
            'text'     => __( 'Logout', 'hikmah-login' ),
            'redirect' => '',
        ], $atts, 'hikmah_logout' );

        if ( ! is_user_logged_in() ) {
            return '';
        }

        $url = Helper::get_logout_url( $atts['redirect'] );

        return sprintf(
            '<a href="%s" class="hikmah-logout-link">%s</a>',
            esc_url( $url ),
            esc_html( $atts['text'] )
        );
    }

    /**
     * Register widgets.
     */
    public function register_widgets() {
        // Widgets will be implemented in a future phase
        // register_widget( 'Hikmah_Login_Widget' );
    }

    /**
     * =============================================
     * SECURITY HELPERS
     * =============================================
     */

    /**
     * Check if an IP is blacklisted.
     *
     * @param string $ip IP address.
     * @return bool
     */
    private function is_ip_blacklisted( $ip ) {

        $blacklist = get_option( 'hikmah_ip_blacklist', '' );

        if ( empty( $blacklist ) ) {
            return false;
        }

        $ips = array_map( 'trim', explode( "\n", $blacklist ) );

        foreach ( $ips as $blocked ) {
            if ( empty( $blocked ) ) {
                continue;
            }

            // Check exact match
            if ( $ip === $blocked ) {
                return true;
            }

            // Check CIDR range
            if ( strpos( $blocked, '/' ) !== false
                && Helper::ip_in_range( $ip, $blocked ) ) {
                return true;
            }

            // Check wildcard (e.g., 192.168.1.*)
            if ( strpos( $blocked, '*' ) !== false ) {
                $pattern = str_replace( [ '.', '*' ], [ '\.', '\d+' ], $blocked );
                if ( preg_match( '/^' . $pattern . '$/', $ip ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if maintenance mode is active.
     *
     * @return bool
     */
    private function is_maintenance_mode() {
        return (bool) get_option( 'hikmah_maintenance_mode', false );
    }
}
