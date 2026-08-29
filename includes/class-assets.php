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
use Hikmah_Login\Admin\Admin_UI_Settings;

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

        // User profile admin assets (verify/unverify resend)
        $this->add_action( 'admin_enqueue_scripts', 'enqueue_user_profile_assets' );

        // Login page assets (wp-login.php override)
        $this->add_action( 'login_enqueue_scripts', 'enqueue_login_page_assets' );

        // Custom UI settings page assets
        $this->add_action( 'admin_enqueue_scripts', 'enqueue_ui_settings_assets' );

        // Frontend body class for page-level styling
        $this->add_filter( 'body_class', 'add_login_page_body_class' );

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

        // AJAX Manager (load before login-script)
        wp_enqueue_script(
            'hikmah-ajax',
            HIKMAH_LOGIN_URL . 'public/js/hikmah-ajax.js',
            [ 'jquery', 'heartbeat' ],
            HIKMAH_LOGIN_VERSION,
            true
        );

        // JS
        wp_enqueue_script(
            'hikmah-login-script',
            HIKMAH_LOGIN_URL . 'public/js/login-script.js',
            [ 'jquery', 'hikmah-ajax' ],
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
            'loginNonce'    => wp_create_nonce( 'hikmah_login_action' ),
            'registerNonce' => wp_create_nonce( 'hikmah_register_action' ),
            'forgotNonce'   => wp_create_nonce( 'hikmah_forgot_password_action' ),
            'resetNonce'    => wp_create_nonce( 'hikmah_reset_password_action' ),
            'generalNonce'  => wp_create_nonce( 'hikmah_login_nonce' ),
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
     * Enqueue assets on the WordPress user profile pages.
     *
     * Wires the "Resend Verification Email" button on
     * the user-edit / profile screens.
     */
    public function enqueue_user_profile_assets() {

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || ! in_array( $screen->id, [ 'user-edit', 'profile' ], true ) ) {
            return;
        }

        // 2FA setup wizard assets (own profile page).
        if ( 'profile' === $screen->id && Helper::is_feature_enabled( '2fa_enabled' ) ) {
            wp_enqueue_script(
                'hikmah-ajax',
                HIKMAH_LOGIN_URL . 'public/js/hikmah-ajax.js',
                [ 'jquery', 'heartbeat' ],
                HIKMAH_LOGIN_VERSION,
                true
            );

            wp_enqueue_script(
                'hikmah-login-script',
                HIKMAH_LOGIN_URL . 'public/js/login-script.js',
                [ 'jquery', 'hikmah-ajax' ],
                HIKMAH_LOGIN_VERSION,
                true
            );

            wp_localize_script( 'hikmah-login-script', 'hikmahLogin', $this->get_localized_data() );
        }

        $admin_nonce = wp_create_nonce( 'hikmah_admin_nonce' );

        $inline = "
            (function (\$) {
                \$(document).on('click', '.hikmah-resend-verify-btn', function (e) {
                    e.preventDefault();
                    var btn = \$(this);
                    var userId = btn.data('user-id');
                    if (!userId) { return; }

                    btn.prop('disabled', true).text('" . esc_js( __( 'Sending...', 'hikmah-login' ) ) . "');

                    \$.post('" . esc_js( admin_url( 'admin-ajax.php' ) ) . "', {
                        action: 'hikmah_admin_resend_verification',
                        nonce: '" . esc_js( $admin_nonce ) . "',
                        user_id: userId
                    }, function (res) {
                        if (res.success) {
                            btn.text('" . esc_js( __( 'Email sent ✓', 'hikmah-login' ) ) . "').css('color', '#10b981');
                        } else {
                            alert(res.message);
                            btn.prop('disabled', false).text('" . esc_js( __( 'Resend Verification Email', 'hikmah-login' ) ) . "');
                        }
                    }).fail(function () {
                        alert('" . esc_js( __( 'Network error. Please try again.', 'hikmah-login' ) ) . "');
                        btn.prop('disabled', false).text('" . esc_js( __( 'Resend Verification Email', 'hikmah-login' ) ) . "');
                    });
                });
            })(jQuery);";

        wp_add_inline_script( 'jquery-core', $inline );
    }

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
            'hikmah-login_page_hikmah-login-ui',
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
     * Output the dynamic UI stylesheet (Phase 12).
     */
    public function output_custom_css() {

        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }

        $ui     = Admin_UI_Settings::get_instance();
        $css    = $ui->generate_dynamic_css();

        if ( ! empty( $css ) ) {
            echo "<style id=\"hikmah-login-dynamic-css\">\n" . $css . "</style>\n";
        }

        $settings = $ui->get_settings();

        // Force the selected theme mode when it is locked (light/dark).
        if ( 'auto' !== $settings['theme_mode'] ) {
            echo "<script id=\"hikmah-login-force-mode\">\n";
            echo "try{document.documentElement.classList.add('hikmah-" . esc_js( $settings['theme_mode'] ) . "-mode');}catch(e){}\n";
            echo "</script>\n";
        }

        // Theme toggle button (rendered by the form shortcode) needs this script.
        echo "<script id=\"hikmah-login-theme-toggle\">\n";
        echo "(function(){var b=document.querySelector('.hikmah-theme-toggle');if(!b)return;function getStored(){try{var v=localStorage.getItem('hikmah-theme');if(v==='dark'||v==='light'){return v;}}catch(e){}return null;}function render(){var d=document.documentElement;var dark=d.classList.contains('hikmah-dark-mode');b.setAttribute('aria-pressed',dark?'true':'false');var i=b.querySelector('.hikmah-toggle-icon-dark');var l=b.querySelector('.hikmah-toggle-icon-light');if(i)i.style.display=dark?'none':'inline';if(l)l.style.display=dark?'inline':'none';}function apply(v){var d=document.documentElement,dark=(v==='dark')?true:false;if(dark){d.classList.add('hikmah-dark-mode');}else{d.classList.remove('hikmah-dark-mode');}render();}var s=getStored();if(s){apply(s);}b.addEventListener('click',function(){var d=document.documentElement,on=!d.classList.contains('hikmah-dark-mode');if(on){d.classList.add('hikmah-dark-mode');}else{d.classList.remove('hikmah-dark-mode');}try{localStorage.setItem('hikmah-theme',on?'dark':'light');}catch(e){}render();});})();\n";
        echo "</script>\n";
    }

    /**
     * Enqueue assets only on the Custom UI settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_ui_settings_assets( $hook_suffix ) {

        if ( 'hikmah-login_page_hikmah-login-ui' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_script(
            'hikmah-ui-customizer',
            HIKMAH_LOGIN_URL . 'admin/js/admin-ui-customizer.js',
            [ 'jquery' ],
            HIKMAH_LOGIN_VERSION,
            true
        );
    }

    /**
     * Add a body class so page-level styling can target Hikmah pages.
     *
     * @param array $classes Body classes.
     * @return array
     */
    public function add_login_page_body_class( $classes ) {

        if ( $this->should_load_frontend_assets() ) {
            $classes[] = 'hikmah-login-page';
        }

        return $classes;
    }
}
