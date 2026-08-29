<?php
/**
 * Main Plugin Class
 *
 * The core class that orchestrates all plugin functionality.
 * Acts as the central hub connecting all modules.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Hikmah_Login {

    use Singleton;
    use Hooks;

    /**
     * Plugin version.
     *
     * @var string
     */
    public $version;

    /**
     * Loaded modules registry.
     *
     * @var array
     */
    private $modules = [];

    /**
     * Constructor.
     * Private — use get_instance() instead.
     */
    private function __construct() {
        $this->version = HIKMAH_LOGIN_VERSION;
        $this->init();
    }

    /**
     * Initialize the plugin.
     */
    private function init() {
        // Step 1: Load dependencies
        $this->load_dependencies();

        // Step 2: Set locale for translations
        $this->set_locale();

        // Step 3: Register hooks
        $this->register_hooks();

        // Step 4: Initialize modules based on context
        $this->init_modules();

        /**
         * Fires after Hikmah Login is fully initialized.
         *
         * @since 1.0.0
         * @param Hikmah_Login $this Plugin instance.
         */
        do_action( 'hikmah_login_loaded', $this );
    }

    /**
     * Load required dependency files.
     *
     * Files that aren't autoloaded (functions files, etc.)
     */
    private function load_dependencies() {
        // Helper functions file (if needed)
        // require_once HIKMAH_LOGIN_DIR . 'includes/functions.php';
    }

    /**
     * Set plugin locale for translations.
     */
    private function set_locale() {
        add_action( 'init', function() {
            load_plugin_textdomain(
                'hikmah-login',
                false,
                dirname( HIKMAH_LOGIN_BASENAME ) . '/languages/'
            );
        });
    }

    /**
     * Register core WordPress hooks.
     */
    private function register_hooks() {

        // Activation redirect (one-time)
        $this->add_action( 'admin_init', 'maybe_redirect_after_activation' );

        // Plugin action links (Settings link on plugins page)
        add_filter(
            'plugin_action_links_' . HIKMAH_LOGIN_BASENAME,
            [ $this, 'add_plugin_action_links' ]
        );

        // Plugin row meta (extra links)
        add_filter(
            'plugin_row_meta',
            [ $this, 'add_plugin_row_meta' ],
            10,
            2
        );

        // Register custom cron schedules if needed
        $this->add_filter( 'cron_schedules', 'add_custom_cron_schedules' );
    }

    /**
     * Initialize modules based on context (admin vs frontend).
     *
     * Updated in Phase 03 to include core auth modules.
     */
    private function init_modules() {

        /**
         * ================================================
         * CORE MODULES (Loaded on EVERY request)
         * ================================================
         */

        // Assets (CSS/JS)
        $this->modules['assets'] = Assets::get_instance();

        // i18n (Translations)
        $this->modules['i18n'] = I18n::get_instance();

        // Core Hooks & Filters
        $this->modules['core_hooks'] = Auth\Core_Hooks::get_instance();

        // Auth Manager (Login/Logout logic)
        $this->modules['auth'] = Auth\Auth_Manager::get_instance();

        // Login Override (wp-login.php redirect)
        $this->modules['login_override'] = Auth\Login_Override::get_instance();

        // Session Manager
        $this->modules['sessions'] = Auth\Session_Manager::get_instance();

        // Error Handler
        Helpers\Error_Handler::init();

        // Run migrations if needed
        $migration = new Database\Migration();
        if ( $migration->needs_migration() ) {
            $migration->run();
        }

        /**
         * ================================================
         * ADMIN-ONLY modules
         * ================================================
         */
        if ( is_admin() ) {
            // Admin Notices
            $this->modules['admin_notices'] = Admin\Admin_Notices::get_instance();

            // Show SSL warning
            $this->modules['admin_notices']->maybe_show_ssl_warning();

            // Admin menu (Dashboard / Settings / Security)
            $this->modules['admin_menu'] = Admin\Admin_Menu::get_instance();

            /**
             * PHASE 12: CUSTOM UI
             *
             * Admin settings screen for branding/layout/theme. The dynamic
             * frontend stylesheet is generated lazily from the Assets class
             * (output_custom_css) so it also works on non-admin requests.
             */
            $this->modules['admin_ui_settings'] = Admin\Admin_UI_Settings::get_instance();

            // Future admin modules:
            // $this->modules['admin_settings'] = Admin\Admin_Settings::get_instance();
            // $this->modules['login_logs']     = Admin\Login_Logs::get_instance();
        }

        /**
         * ================================================
         * PHASE 04: LOGIN
         * ================================================
         */

        // AJAX handlers (always load — they self-register)
        $this->modules['ajax_login'] = Ajax\Ajax_Login::get_instance();

        // Shortcodes (always load — they self-register)
        $this->modules['shortcode_login'] = Shortcodes\Login_Shortcode::get_instance();

        /**
         * ================================================
         * PHASE 05: REGISTRATION
         * ================================================
         */

        // AJAX handler (always load — self-registers)
        $this->modules['ajax_register'] = Ajax\Ajax_Register::get_instance();

        // Shortcode (always load — self-registers)
        $this->modules['shortcode_register'] = Shortcodes\Register_Shortcode::get_instance();

        /**
         * ================================================
         * PHASE 06: PASSWORD RECOVERY
         * ================================================
         */

        // AJAX handler (always load — self-registers)
        $this->modules['ajax_forgot'] = Ajax\Ajax_Forgot_Password::get_instance();

        // Shortcodes (always load — self-register)
        $this->modules['shortcode_forgot'] = Shortcodes\Forgot_Password_Shortcode::get_instance();
        $this->modules['shortcode_reset'] = Shortcodes\Reset_Password_Shortcode::get_instance();

        /**
         * ================================================
         * PHASE 07: EMAIL VERIFICATION
         * ================================================
         */

        // Email verification module (self-registers hooks/AJAX)
        $this->modules['email_verification'] = Auth\Email_Verification::get_instance();

        // Shortcode (always load — self-registers)
        $this->modules['shortcode_verify'] = Shortcodes\Verification_Shortcode::get_instance();

        // 2FA setup shortcode (always load — self-registers)
        $this->modules['shortcode_2fa'] = Shortcodes\Two_FA_Shortcode::get_instance();

        /**
         * ================================================
         * PHASE 10: TWO-FACTOR AUTHENTICATION
         * ================================================
         */

        // Two-Factor Authentication (email OTP + TOTP + backup codes).
        // Instantiated before Ajax_Controller so its AJAX actions are
        // registered when the controller fires hikmah_ajax_register_actions.
        $this->modules['security_two_factor'] = Security\Two_Factor::get_instance();

        /**
         * ================================================
         * PHASE 08: AJAX ARCHITECTURE
         * ================================================
         */

        // Unified AJAX controller + performance optimizer
        $this->modules['ajax_controller'] = Ajax\Ajax_Controller::get_instance();
        $this->modules['ajax_optimizer'] = Ajax\Ajax_Optimizer::get_instance();

        /**
         * ================================================
         * PHASE 09: SECURITY LAYER
         * ================================================
         */

        // Brute Force Protection + IP Manager
        $this->modules['security_brute_force'] = Security\Brute_Force::get_instance();

        // Unified CAPTCHA (reCAPTCHA v2/v3, hCaptcha, Turnstile)
        $this->modules['security_captcha'] = Security\Captcha::get_instance();

        // Security Hardening (headers, XML-RPC, enumeration, uploads)
        $this->modules['security_hardening'] = Security\Security_Hardening::get_instance();

        // Audit Log
        $this->modules['security_log'] = Security\Security_Log::get_instance();

        /**
         * ================================================
         * PHASE 12: CUSTOM UI
         * ================================================
         *
         * Registered inside the admin-only block above.
         */

        /**
         * ================================================
         * PHASE 13: USER DASHBOARD
         * ================================================
         */

// User dashboard (overview/profile/security/sessions/social/activity/privacy + AJAX).
		$this->modules['dashboard'] = Dashboard\Dashboard_Manager::get_instance();

		// [hikmah_dashboard] shortcode.
		$this->modules['shortcode_dashboard'] = Shortcodes\Dashboard_Shortcode::get_instance();

        /**
         * ================================================
         * REST API modules
         * ================================================
         */
        // Future REST modules:
        // $this->modules['rest_auth'] = RestApi\Auth_Controller::get_instance();
    }

    /**
     * Redirect to settings page after activation.
     */
    public function maybe_redirect_after_activation() {
        if ( get_transient( 'hikmah_login_activation_redirect' ) ) {
            delete_transient( 'hikmah_login_activation_redirect' );

            // Don't redirect on multisite bulk activation
            if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
                return;
            }

            // Redirect to plugin settings page
            wp_safe_redirect(
                admin_url( 'admin.php?page=hikmah-login&welcome=1' )
            );
            exit;
        }
    }

    /**
     * Add Settings link on Plugins page.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function add_plugin_action_links( $links ) {

        $plugin_links = [
            '<a href="' . admin_url( 'admin.php?page=hikmah-login' ) . '">'
                . esc_html__( 'Settings', 'hikmah-login' )
                . '</a>',
            '<a href="' . admin_url( 'admin.php?page=hikmah-login-logs' ) . '">'
                . esc_html__( 'Login Logs', 'hikmah-login' )
                . '</a>',
        ];

        return array_merge( $plugin_links, $links );
    }

    /**
     * Add extra meta links on Plugins page.
     *
     * @param array  $links   Existing meta links.
     * @param string $file    Plugin file.
     * @return array Modified meta links.
     */
    public function add_plugin_row_meta( $links, $file ) {

        if ( HIKMAH_LOGIN_BASENAME !== $file ) {
            return $links;
        }

        $extra_links = [
            '<a href="https://example.com/docs/hikmah-login" target="_blank">'
                . esc_html__( 'Documentation', 'hikmah-login' )
                . '</a>',
            '<a href="https://example.com/support" target="_blank">'
                . esc_html__( 'Support', 'hikmah-login' )
                . '</a>',
        ];

        return array_merge( $links, $extra_links );
    }

    /**
     * Add custom cron schedules.
     *
     * @param array $schedules Existing schedules.
     * @return array Modified schedules.
     */
    public function add_custom_cron_schedules( $schedules ) {

        // Every 5 minutes
        $schedules['hikmah_every_5_minutes'] = [
            'interval' => 300,
            'display'  => esc_html__( 'Every 5 Minutes', 'hikmah-login' ),
        ];

        // Twice daily
        $schedules['hikmah_twice_daily'] = [
            'interval' => 43200,
            'display'  => esc_html__( 'Twice Daily', 'hikmah-login' ),
        ];

        return $schedules;
    }

    /**
     * Get a loaded module instance.
     *
     * @param string $module Module key.
     * @return object|null Module instance or null.
     */
    public function get_module( $module ) {
        return $this->modules[ $module ] ?? null;
    }

    /**
     * Check if a module is loaded.
     *
     * @param string $module Module key.
     * @return bool
     */
    public function has_module( $module ) {
        return isset( $this->modules[ $module ] );
    }

    /**
     * Get plugin version.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
