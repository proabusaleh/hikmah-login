<?php
/**
 * ============================================================
 * SPEC REFERENCE — MAIN PLUGIN ENTRY POINT (Step 11)
 * ============================================================
 *
 * NOTE: This is the Step 11 spec version of the plugin's main
 * entry point. The ACTIVE, working file remains hikmah-login.php
 * at the plugin root (namespaced bootstrap: Hikmah_Login\ classes,
 * PSR-4 autoloader, hikmah_login()). This file is saved separately
 * as a reference so the live plugin is not disturbed.
 *
 * The Step 11 spec below uses a NON-namespaced auto-loader and a
 * global Hikmah_Login_Core singleton. It is intentionally NOT the
 * active file. To the degree it illustrates the intended wiring of
 * the standalone Hikmah_* spec classes (Hikmah_Admin_Settings,
 * Hikmah_Shortcodes, Hikmah_Auth_Handler, Hikmah_Social_Auth,
 * Hikmah_Tab_Account), treat it as a roadmap, not as live code.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

/*
 * ==== BEGIN STEP 11 SPEC (for reference only) ====
 */

/**
 * Plugin Name: Hikmah Login & Frontend Dashboard
 * Description: Production-ready enterprise front-end membership login portal complete with multi-factor 2FA protection, login logs, Google/Facebook sign-in, and GDPR-compliant self-export and deletion.
 * Version: 1.0.0
 * Author: Hikmah Development Group
 * Text Domain: hikmah-login
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires At Least: 5.8
 *
 * @package Hikmah_Login
 */

if (!defined('ABSPATH')) {
    exit;
}

// Global Path Core Definitions
define('HIKMAH_LOGIN_VERSION', '1.0.0');
define('HIKMAH_LOGIN_FILE', __FILE__);
define('HIKMAH_LOGIN_DIR', plugin_dir_path(__FILE__));
define('HIKMAH_LOGIN_URL', plugin_dir_url(__FILE__));

/**
 * Autoload Classes Helper Loader
 */
spl_autoload_register(function ($class) {
    // Only load classes matching namespace prefix
    if (strpos($class, 'Hikmah_') !== 0) {
        return;
    }

    $class_name = strtolower(str_replace('_', '-', $class));
    $parts      = explode('-', $class_name);
    
    // Determine path grouping contexts
    $subfolder = '';
    if (in_array('tab', $parts, true)) {
        $subfolder = 'dashboard/tabs/';
        $filename  = 'class-' . $class_name . '.php';
    } elseif (in_array('admin', $parts, true)) {
        $subfolder = 'admin/';
        $filename  = 'class-' . $class_name . '.php';
    } elseif (in_array('auth', $parts, true) || in_array('social', $parts, true)) {
        $subfolder = 'auth/';
        $filename  = 'class-' . $class_name . '.php';
    } else {
        $subfolder = '';
        $filename  = 'class-' . $class_name . '.php';
    }

    $filepath = HIKMAH_LOGIN_DIR . 'includes/' . $subfolder . $filename;

    if (file_exists($filepath)) {
        require_once $filepath;
    }
});

/**
 * Master Plugin Initialization Class
 */
class Hikmah_Login_Core {

    /**
     * Instance container variable
     *
     * @var Hikmah_Login_Core|null
     */
    private static $instance = null;

    /**
     * Retrieve Singleton Core Instance
     *
     * @return Hikmah_Login_Core Core controller instance.
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Singleton Constructor
     */
    private function __construct() {
        register_activation_hook(HIKMAH_LOGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(HIKMAH_LOGIN_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'initialize']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    /**
     * Bootstrap Submodules and Core Modules
     *
     * @return void
     */
    public function initialize() {
        load_plugin_textdomain('hikmah-login', false, dirname(plugin_basename(HIKMAH_LOGIN_FILE)) . '/languages');

        // Initialize core features
        new Hikmah_Admin_Settings();
        new Hikmah_Shortcodes();
        new Hikmah_Auth_Handler();
        new Hikmah_Social_Auth();

        // Load dashboard rendering controllers dynamically
        if (is_user_logged_in() && class_exists('Hikmah_Tab_Account')) {
            new Hikmah_Tab_Account();
        }
    }

    /**
     * Register core frontend script and style bundles
     *
     * @return void
     */
    public function register_assets() {
        // Enqueue Core CSS assets
        wp_register_style('hikmah-auth-style', HIKMAH_LOGIN_URL . 'assets/css/auth.css', [], HIKMAH_LOGIN_VERSION);
        wp_register_style('hikmah-dashboard-style', HIKMAH_LOGIN_URL . 'assets/css/dashboard.css', [], HIKMAH_LOGIN_VERSION);

        // Register AJAX login and authentication dynamic handler scripts
        wp_register_script('hikmah-auth-script', HIKMAH_LOGIN_URL . 'assets/js/auth.js', ['jquery'], HIKMAH_LOGIN_VERSION, true);
        wp_localize_script('hikmah-auth-script', 'hikmahAuthData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n'    => [
                'verify2FA'    => esc_html__('Verify & Sign In', 'hikmah-login'),
                'genericError' => esc_html__('An unexpected error occurred. Please try again.', 'hikmah-login'),
            ],
        ]);

        // Register dashboard view scripts
        wp_register_script('hikmah-dashboard-script', HIKMAH_LOGIN_URL . 'assets/js/dashboard.js', ['jquery'], HIKMAH_LOGIN_VERSION, true);
        wp_localize_script('hikmah-dashboard-script', 'hikmahDashboard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('hikmah_dashboard_nonce'),
            'i18n'    => [
                'passwordWeak'   => esc_html__('Weak', 'hikmah-login'),
                'passwordMedium' => esc_html__('Medium', 'hikmah-login'),
                'passwordStrong' => esc_html__('Strong', 'hikmah-login'),
                'saving'         => esc_html__('Saving...', 'hikmah-login'),
                'cropAvatar'     => esc_html__('Crop & Save Avatar', 'hikmah-login'),
                'confirmLogout'  => esc_html__('Are you sure you want to invalidate this session?', 'hikmah-login'),
                'error'          => esc_html__('Operation failed. Please try again.', 'hikmah-login'),
            ],
        ]);
    }

    /**
     * Plugin Activation Method
     *
     * Creates database logs and secures public export files folders.
     *
     * @return void
     */
    public function activate() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'hikmah_login_logs';
        $charset_collate = $wpdb->get_charset_collate();

        // Schema structure definition for brute force attempts tracking
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            login_time datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            status varchar(20) NOT NULL,
            login_method varchar(20) NOT NULL,
            user_agent text NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY ip_address (ip_address)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Secure dynamic data export directories
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/hikmah-exports/';

        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
            file_put_contents($export_dir . '.htaccess', 'deny from all');
            file_put_contents($export_dir . 'index.php', '<?php // Silence is golden');
        }

        // Force reload rule structure on next reload
        flush_rewrite_rules();
    }

    /**
     * Plugin Deactivation Method
     *
     * @return void
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Global Configuration Options Safe Helper Wrapper
 *
 * @param string $key     Settings key to extract.
 * @param mixed  $default Fallback return.
 * @return mixed          Stored string/number configuration details.
 */
function hikmah_get_option($key, $default = '') {
    $options = get_option('hikmah_login_options', []);
    return $options[$key] ?? $default;
}

/**
 * Retrieve User Client IP Address securely
 *
 * @return string Client IP address.
 */
function hikmah_get_client_ip() {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip  = trim(end($ips));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '127.0.0.1';
}

// Bootstrap core runtime environment
Hikmah_Login_Core::get_instance();

/*
 * ==== END STEP 11 SPEC ====
 *
 * To adopt this spec as the live entry point you would replace the
 * active root hikmah-login.php with the contents of the block above,
 * then ensure the standalone Hikmah_* classes it references are wired
 * in (and that legacy global helper functions do not clash with any
 * existing definitions). Proceed with caution — this is an alternative
 * bootstrap model, not the current one.
 */
