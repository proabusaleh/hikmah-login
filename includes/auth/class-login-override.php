<?php
/**
 * Login Override
 *
 * Overrides the default WordPress wp-login.php page.
 * Redirects all login, register, lostpassword, and logout
 * actions to custom Hikmah Login pages.
 *
 * @package Hikmah_Login
 * @subpackage Auth
 * @since   1.0.0
 */

namespace Hikmah_Login\Auth;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Login_Override {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     */
    private function __construct() {

        // Only activate if custom login is enabled
        if ( ! Helper::is_feature_enabled( 'login_enabled' ) ) {
            return;
        }

        $this->register_hooks();
    }

    /**
     * Register all override hooks.
     */
    private function register_hooks() {

        // Redirect wp-login.php to custom page
        $this->add_action( 'init', 'redirect_wp_login', 1 );

        // Override login URL throughout WordPress
        $this->add_filter( 'login_url', 'custom_login_url', 10, 3 );

        // Override registration URL
        $this->add_filter( 'register_url', 'custom_register_url' );

        // Override lost password URL
        $this->add_filter( 'lostpassword_url', 'custom_lostpassword_url', 10, 2 );

        // Override logout URL
        $this->add_filter( 'logout_url', 'custom_logout_url', 10, 2 );

        // Override site URL for login links
        $this->add_filter( 'site_url', 'override_site_url', 10, 4 );

        // Prevent direct access to wp-login.php actions
        $this->add_action( 'login_init', 'handle_login_actions', 1 );

        // Customize wp-login.php header URL (if someone still reaches it)
        $this->add_filter( 'login_headerurl', 'custom_login_header_url' );
        $this->add_filter( 'login_headertext', 'custom_login_header_text' );

        // Hide "Back to site" link on wp-login.php
        $this->add_action( 'login_head', 'custom_login_head_styles' );
    }

    /**
     * =============================================
     * WP-LOGIN.PHP REDIRECT
     * =============================================
     */

    /**
     * Redirect wp-login.php requests to custom pages.
     *
     * This is the primary override mechanism. It catches
     * all direct access to wp-login.php and redirects
     * to the appropriate Hikmah Login page.
     */
    public function redirect_wp_login() {

        // Only on wp-login.php
        if ( ! $this->is_wp_login_page() ) {
            return;
        }

        // Allow specific actions to pass through (e.g., logout with nonce)
        $action = isset( $_REQUEST['action'] )
            ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
            : 'login';

        // Allow logout action (needs nonce verification)
        if ( 'logout' === $action ) {
            return; // Let WordPress handle logout
        }

        // Allow postpass action (password-protected posts)
        if ( 'postpass' === $action ) {
            return;
        }

        // Allow admin-ajax.php (shouldn't hit wp-login but safety)
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        /**
         * Filter whether to allow wp-login.php access.
         *
         * @param bool   $allow  Default false (redirect).
         * @param string $action Current action.
         */
        if ( apply_filters( 'hikmah_login_allow_wp_login', false, $action ) ) {
            return;
        }

        // Determine redirect URL based on action
        $redirect_url = $this->get_redirect_for_action( $action );

        // Preserve query parameters
        $query_params = $_GET;
        unset( $query_params['action'] );

        if ( ! empty( $query_params ) ) {
            $redirect_url = add_query_arg( $query_params, $redirect_url );
        }

        // Redirect
        wp_safe_redirect( $redirect_url, 302 );
        exit;
    }

    /**
     * Handle login actions on wp-login.php (before redirect).
     *
     * This catches form submissions to wp-login.php
     * that happen before our redirect kicks in.
     */
    public function handle_login_actions() {

        $action = isset( $_REQUEST['action'] )
            ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
            : 'login';

        switch ( $action ) {

            case 'register':
                // Redirect registration to custom page
                if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                    wp_safe_redirect( Helper::get_register_url() );
                    exit;
                }
                break;

            case 'lostpassword':
            case 'retrievepassword':
                // Redirect password recovery
                if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                    wp_safe_redirect( Helper::get_forgot_password_url() );
                    exit;
                }
                break;

            case 'rp':
            case 'resetpass':
                // Redirect password reset
                if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
                    $redirect = Helper::get_reset_password_url();
                    if ( isset( $_GET['key'] ) && isset( $_GET['login'] ) ) {
                        $redirect = add_query_arg( [
                            'key'   => sanitize_text_field( wp_unslash( $_GET['key'] ) ),
                            'login' => sanitize_text_field( wp_unslash( $_GET['login'] ) ),
                        ], $redirect );
                    }
                    wp_safe_redirect( $redirect );
                    exit;
                }
                break;

            case 'confirmaction':
                // Allow privacy confirmation actions
                break;

            default:
                // Default login redirect handled by redirect_wp_login()
                break;
        }
    }

    /**
     * =============================================
     * URL FILTERS
     * =============================================
     */

    /**
     * Override the login URL throughout WordPress.
     *
     * This affects wp_login_url(), admin bar, wp_redirect, etc.
     *
     * @param string $login_url    Default login URL.
     * @param string $redirect     Redirect after login.
     * @param bool   $force_reauth Force reauthentication.
     * @return string Custom login URL.
     */
    public function custom_login_url( $login_url, $redirect = '', $force_reauth = false ) {

        $custom_url = Helper::get_login_url();

        if ( ! empty( $redirect ) ) {
            $custom_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom_url );
        }

        if ( $force_reauth ) {
            $custom_url = add_query_arg( 'reauth', '1', $custom_url );
        }

        return $custom_url;
    }

    /**
     * Override the registration URL.
     *
     * @param string $register_url Default registration URL.
     * @return string Custom registration URL.
     */
    public function custom_register_url( $register_url ) {
        return Helper::get_register_url();
    }

    /**
     * Override the lost password URL.
     *
     * @param string $lostpassword_url Default lost password URL.
     * @param string $redirect         Redirect after reset.
     * @return string Custom lost password URL.
     */
    public function custom_lostpassword_url( $lostpassword_url, $redirect = '' ) {

        $custom_url = Helper::get_forgot_password_url();

        if ( ! empty( $redirect ) ) {
            $custom_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom_url );
        }

        return $custom_url;
    }

    /**
     * Override the logout URL.
     *
     * @param string $logout_url Default logout URL.
     * @param string $redirect   Redirect after logout.
     * @return string Custom logout URL.
     */
    public function custom_logout_url( $logout_url, $redirect = '' ) {

        // Keep the WordPress logout URL (it handles nonce and cookie clearing)
        // But change the redirect destination
        if ( empty( $redirect ) ) {
            $redirect = get_option( 'hikmah_login_logout_redirect_url', home_url( '/' ) );
        }

        // Temporarily remove this filter to avoid infinite recursion,
        // since wp_logout_url() re-applies the 'logout_url' filter.
        remove_filter( 'logout_url', [ $this, 'custom_logout_url' ], 10 );

        $custom_url = wp_logout_url( $redirect );

        add_filter( 'logout_url', [ $this, 'custom_logout_url' ], 10, 2 );

        return $custom_url;
    }

    /**
     * Override site_url for login-related paths.
     *
     * @param string      $url     The complete site URL.
     * @param string      $path    Path relative to site URL.
     * @param string|null $scheme  URL scheme.
     * @param int|null    $blog_id Blog ID.
     * @return string
     */
    public function override_site_url( $url, $path, $scheme, $blog_id ) {

        if ( 'wp-login.php' === $path || 'wp-login.php' === ltrim( $path, '/' ) ) {
            return Helper::get_login_url();
        }

        return $url;
    }

    /**
     * =============================================
     * WP-LOGIN.PHP CUSTOMIZATION (Fallback)
     * =============================================
     */

    /**
     * Customize the login header URL (logo link).
     *
     * @return string
     */
    public function custom_login_header_url() {
        return home_url( '/' );
    }

    /**
     * Customize the login header text (logo alt text).
     *
     * @return string
     */
    public function custom_login_header_text() {
        return get_bloginfo( 'name' );
    }

    /**
     * Add custom styles to wp-login.php head.
     */
    public function custom_login_head_styles() {

        $logo_url = get_option( 'hikmah_login_logo', '' );
        $bg_color = get_option( 'hikmah_login_bg_color', '#f1f1f1' );

        ?>
        <style type="text/css">
            body.login {
                background-color: <?php echo esc_attr( $bg_color ); ?>;
            }

            <?php if ( ! empty( $logo_url ) ) : ?>
            body.login div#login h1 a {
                background-image: url('<?php echo esc_url( $logo_url ); ?>');
                background-size: contain;
                width: 100%;
                height: 80px;
            }
            <?php endif; ?>

            /* Hide "Back to site" link if desired */
            body.login .login #backtoblog {
                display: none;
            }

            /* Custom form styling */
            body.login .login form {
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 26px 24px 34px;
            }

            body.login .login .message,
            body.login .login #login_error {
                border-radius: 4px;
                border-left: 4px solid;
            }
        </style>
        <?php
    }

    /**
     * =============================================
     * HELPER METHODS
     * =============================================
     */

    /**
     * Check if the current request is for wp-login.php.
     *
     * @return bool
     */
    private function is_wp_login_page() {

        // Check the global pagenow
        global $pagenow;

        if ( isset( $pagenow ) && 'wp-login.php' === $pagenow ) {
            return true;
        }

        // Fallback: check the request URI
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        return strpos( $request_uri, 'wp-login.php' ) !== false;
    }

    /**
     * Get the redirect URL for a wp-login.php action.
     *
     * @param string $action Login action.
     * @return string Redirect URL.
     */
    private function get_redirect_for_action( $action ) {

        switch ( $action ) {
            case 'register':
                return Helper::get_register_url();

            case 'lostpassword':
            case 'retrievepassword':
                return Helper::get_forgot_password_url();

            case 'rp':
            case 'resetpass':
                return Helper::get_reset_password_url();

            case 'login':
            default:
                return Helper::get_login_url();
        }
    }
}
