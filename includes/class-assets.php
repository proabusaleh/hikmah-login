<?php
/**
 * Assets Manager
 *
 * Handles enqueuing of all CSS, JS, and localized data
 * for both frontend and admin.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register all hooks.
     */
    private function register_hooks() {
        // Frontend assets
        $this->add_action( 'wp_enqueue_scripts', 'enqueue_frontend_assets' );

        // Admin assets
        $this->add_action( 'admin_enqueue_scripts', 'enqueue_admin_assets' );

        // Login page assets (wp-login.php override)
        $this->add_action( 'login_enqueue_scripts', 'enqueue_login_page_assets' );

        // Inline custom CSS
        $this->add_action( 'wp_head', 'output_custom_css', 100 );
    }

    /**
     * =============================================
     * FRONTEND ASSETS
     * =============================================
     */

    /**
     * Enqueue frontend CSS and JS.
     */
    public function enqueue_frontend_assets() {

        // Only load on Hikmah pages or when shortcodes are present
        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'hikmah-login-style',
            HIKMAH_LOGIN_URL . 'public/css/login-style.css',
            [],
            HIKMAH_LOGIN_VERSION,
            'all'
        );

        // RTL support
        if ( is_rtl() ) {
            wp_enqueue_style(
                'hikmah-login-rtl',
                HIKMAH_LOGIN_URL . 'public/css/login-style-rtl.css',
                [ 'hikmah-login-style' ],
                HIKMAH_LOGIN_VERSION,
                'all'
            );
        }

        // JS
        wp_enqueue_script(
            'hikmah-login-script',
            HIKMAH_LOGIN_URL . 'public/js/login-script.js',
            [ 'jquery' ],
            HIKMAH_LOGIN_VERSION,
            true // Load in footer
        );

        // Localize script data
        wp_localize_script( 'hikmah-login-script', 'hikmahLogin', $this->get_localized_data() );

        // Conditionally load CAPTCHA
        if ( Helper::is_feature_enabled( 'captcha_enabled' ) ) {
            $this->enqueue_captcha_script();
        }

        /**
         * Fires after frontend assets are enqueued.
         *
         * @since 1.0.0
         */
        do_action( 'hikmah_login_frontend_assets' );
    }

    /**
     * Check if frontend assets should be loaded.
     *
     * @return bool
     */
    private function should_load_frontend_assets() {

        // Always load on Hikmah pages
        if ( Helper::is_hikmah_page() ) {
            return true;
        }

        // Load if shortcodes are detected in post content
        global $post;
        if ( $post && is_a( $post, 'WP_Post' ) ) {
            $shortcodes = [ 'hikmah_login', 'hikmah_register', 'hikmah_forgot_password', 'hikmah_dashboard' ];
            foreach ( $shortcodes as $shortcode ) {
                if ( has_shortcode( $post->post_content, $shortcode ) ) {
                    return true;
                }
            }
        }

        /**
         * Filter whether to force-load frontend assets.
         *
         * @param bool $load Default false.
         */
        return apply_filters( 'hikmah_login_force_load_assets', false );
    }

    /**
     * Get localized data for JavaScript.
     *
     * @return array
     */
    private function get_localized_data() {

        $data = [
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'restUrl'       => rest_url( 'hikmah-login/v1/' ),
            'nonce'         => wp_create_nonce( 'hikmah_login_nonce' ),
            'restNonce'     => wp_create_nonce( 'wp_rest' ),
            'isLoggedIn'    => is_user_logged_in(),
            'userId'        => get_current_user_id(),
            'loginUrl'      => Helper::get_login_url(),
            'registerUrl'   => Helper::get_register_url(),
            'dashboardUrl'  => Helper::get_dashboard_url(),
            'logoutUrl'     => Helper::get_logout_url(),
            'redirectUrl'   => get_option( 'hikmah_login_redirect_url', home_url() ),
            'captchaEnabled' => Helper::is_feature_enabled( 'captcha_enabled' ),
            'captchaType'   => get_option( 'hikmah_captcha_type', 'recaptcha_v2' ),
            'recaptchaKey'  => get_option( 'hikmah_recaptcha_site_key', '' ),
            'i18n'          => [
                'loading'        => __( 'Loading...', 'hikmah-login' ),
                'processing'     => __( 'Processing...', 'hikmah-login' ),
                'loginSuccess'   => __( 'Login successful! Redirecting...', 'hikmah-login' ),
                'loginFailed'    => __( 'Login failed. Please check your credentials.', 'hikmah-login' ),
                'registerSuccess' => __( 'Registration successful!', 'hikmah-login' ),
                'error'          => __( 'An error occurred. Please try again.', 'hikmah-login' ),
                'networkError'   => __( 'Network error. Please check your connection.', 'hikmah-login' ),
                'required'       => __( 'This field is required.', 'hikmah-login' ),
                'invalidEmail'   => __( 'Please enter a valid email.', 'hikmah-login' ),
                'passwordMismatch' => __( 'Passwords do not match.', 'hikmah-login' ),
                'confirmLogout'  => __( 'Are you sure you want to logout?', 'hikmah-login' ),
            ],
        ];

        /**
         * Filter localized JS data.
         *
         * @param array $data Localized data.
         */
        return apply_filters( 'hikmah_login_localized_data', $data );
    }

    /**
     * Enqueue CAPTCHA script based on type.
     */
    private function enqueue_captcha_script() {

        $captcha_type = get_option( 'hikmah_captcha_type', 'recaptcha_v2' );
        $site_key     = get_option( 'hikmah_recaptcha_site_key', '' );

        if ( empty( $site_key ) ) {
            return;
        }

        switch ( $captcha_type ) {
            case 'recaptcha_v2':
            case 'recaptcha_v3':
                wp_enqueue_script(
                    'google-recaptcha',
                    'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $site_key ),
                    [],
                    null,
                    true
                );
                break;

            case 'hcaptcha':
                wp_enqueue_script(
                    'hcaptcha',
                    'https://js.hcaptcha.com/1/api.js',
                    [],
                    null,
                    true
                );
                break;

            case 'turnstile':
                wp_enqueue_script(
                    'cloudflare-turnstile',
                    'https://challenges.cloudflare.com/turnstile/v0/api.js',
                    [],
                    null,
                    true
                );
                break;
        }
    }

    /**
     * =============================================
     * ADMIN ASSETS
     * =============================================
     */

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_admin_assets( $hook_suffix ) {

        // Only load on Hikmah admin pages
        if ( ! $this->is_hikmah_admin_page( $hook_suffix ) ) {
            return;
        }

        // Admin CSS
        wp_enqueue_style(
            'hikmah-admin-style',
            HIKMAH_LOGIN_URL . 'admin/css/admin-style.css',
            [ 'wp-components' ],
            HIKMAH_LOGIN_VERSION,
            'all'
        );

        // Admin JS
        wp_enqueue_script(
            'hikmah-admin-script',
            HIKMAH_LOGIN_URL . 'admin/js/admin-script.js',
            [ 'jquery', 'wp-util' ],
            HIKMAH_LOGIN_VERSION,
            true
        );

        // Localize admin data
        wp_localize_script( 'hikmah-admin-script', 'hikmahAdmin', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'hikmah_admin_nonce' ),
            'pluginUrl' => HIKMAH_LOGIN_URL,
            'i18n'     => [
                'saving'       => __( 'Saving...', 'hikmah-login' ),
                'saved'        => __( 'Settings saved!', 'hikmah-login' ),
                'error'        => __( 'Error saving settings.', 'hikmah-login' ),
                'confirmReset' => __( 'Are you sure you want to reset all settings?', 'hikmah-login' ),
                'confirmDelete' => __( 'Are you sure you want to delete this log?', 'hikmah-login' ),
            ],
        ] );

        // WordPress media uploader (for logo upload)
        wp_enqueue_media();

        // WordPress color picker
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        /**
         * Fires after admin assets are enqueued.
         *
         * @since 1.0.0
         * @param string $hook_suffix Current admin page.
         */
        do_action( 'hikmah_login_admin_assets', $hook_suffix );
    }

    /**
     * Check if current admin page is a Hikmah page.
     *
     * @param string $hook_suffix Page hook suffix.
     * @return bool
     */
    private function is_hikmah_admin_page( $hook_suffix ) {

        $hikmah_pages = [
            'toplevel_page_hikmah-login',
            'hikmah-login_page_hikmah-login-settings',
            'hikmah-login_page_hikmah-login-logs',
            'hikmah-login_page_hikmah-login-security',
            'hikmah-login_page_hikmah-login-social',
        ];

        if ( in_array( $hook_suffix, $hikmah_pages, true ) ) {
            return true;
        }

        // Also check for our pages by GET parameter
        if ( isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'hikmah-login' ) === 0 ) {
            return true;
        }

        return false;
    }

    /**
     * =============================================
     * WP-LOGIN.PHP ASSETS
     * =============================================
     */

    /**
     * Enqueue assets for the default wp-login.php page.
     * (Only if custom login is NOT fully replacing it)
     */
    public function enqueue_login_page_assets() {

        // Custom logo
        $logo_url = get_option( 'hikmah_login_logo', '' );

        if ( ! empty( $logo_url ) ) {
            wp_enqueue_style(
                'hikmah-login-wplogin',
                HIKMAH_LOGIN_URL . 'public/css/wplogin-override.css',
                [ 'login' ],
                HIKMAH_LOGIN_VERSION
            );
        }
    }

    /**
     * =============================================
     * INLINE / CUSTOM CSS
     * =============================================
     */

    /**
     * Output custom CSS from admin settings.
     */
    public function output_custom_css() {

        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }

        $custom_css = get_option( 'hikmah_custom_css', '' );
        $bg_color   = get_option( 'hikmah_login_bg_color', '#f1f1f1' );
        $form_width = get_option( 'hikmah_login_form_width', '400' );

        $css = '';

        // Background color
        if ( $bg_color && $bg_color !== '#f1f1f1' ) {
            $css .= ".hikmah-login-wrapper { background-color: {$bg_color}; }\n";
        }

        // Form width
        if ( $form_width && $form_width !== '400' ) {
            $css .= ".hikmah-login-form { max-width: {$form_width}px; }\n";
        }

        // Custom CSS from settings
        if ( ! empty( $custom_css ) ) {
            $css .= wp_strip_all_tags( $custom_css ) . "\n";
        }

        if ( ! empty( $css ) ) {
            echo "<style id=\"hikmah-login-custom-css\">\n" . $css . "</style>\n";
        }
    }
}
