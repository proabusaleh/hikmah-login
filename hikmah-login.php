<?php
/**
 * Plugin Name:       Hikmah Login
 * Plugin URI:        https://github.com/proabusaleh/hikmah-login
 * Description:       A complete WordPress login, registration, and authentication system with security, social login, 2FA, and more.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Abu Saleh
 * Author URI:        https://github.com/proabusaleh
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hikmah-login
 * Domain Path:       /languages
 */

// ⛔ Direct access prevention
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =============================================
 * PLUGIN CONSTANTS
 * =============================================
 */

// Plugin Version
define( 'HIKMAH_LOGIN_VERSION', '2.0.0' );

// Plugin File Path (this file)
define( 'HIKMAH_LOGIN_FILE', __FILE__ );

// Plugin Directory Path (with trailing slash)
define( 'HIKMAH_LOGIN_DIR', plugin_dir_path( __FILE__ ) );

// Plugin Directory URL (with trailing slash)
define( 'HIKMAH_LOGIN_URL', plugin_dir_url( __FILE__ ) );

// Plugin Basename (e.g., hikmah-login/hikmah-login.php)
define( 'HIKMAH_LOGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum PHP Version Required
define( 'HIKMAH_LOGIN_MIN_PHP', '7.4' );

// Minimum WordPress Version Required
define( 'HIKMAH_LOGIN_MIN_WP', '5.8' );

// Database Table Prefix for this plugin
define( 'HIKMAH_LOGIN_DB_PREFIX', 'hikmah_login_' );

// Debug Mode (set false in production)
define( 'HIKMAH_LOGIN_DEBUG', false );

/**
 * =============================================
 * PHP VERSION CHECK
 * =============================================
 */
if ( version_compare( PHP_VERSION, HIKMAH_LOGIN_MIN_PHP, '<' ) ) {
    add_action( 'admin_notices', function() {
        $message = sprintf(
            /* translators: 1: Required PHP version 2: Current PHP version */
            esc_html__(
                'Hikmah Login requires PHP version %1$s or higher. You are running PHP %2$s. Please upgrade PHP.',
                'hikmah-login'
            ),
            HIKMAH_LOGIN_MIN_PHP,
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    });
    return;
}

/**
 * =============================================
 * WORDPRESS VERSION CHECK
 * =============================================
 */
if ( version_compare( get_bloginfo( 'version' ), HIKMAH_LOGIN_MIN_WP, '<' ) ) {
    add_action( 'admin_notices', function() {
        $message = sprintf(
            /* translators: 1: Required WP version 2: Current WP version */
            esc_html__(
                'Hikmah Login requires WordPress version %1$s or higher. You are running %2$s. Please update WordPress.',
                'hikmah-login'
            ),
            HIKMAH_LOGIN_MIN_WP,
            get_bloginfo( 'version' )
        );
        echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
    });
    return;
}

/**
 * =============================================
 * BOOTSTRAP THE PLUGIN
 * =============================================
 */

// Load Autoloader
require_once HIKMAH_LOGIN_DIR . 'includes/class-autoloader.php';

// Register Autoloader
$hikmah_autoloader = new Hikmah_Login_Autoloader();
$hikmah_autoloader->register();

/**
 * Register Activation Hook
 */
register_activation_hook( __FILE__, function( $network_wide ) {
    Hikmah_Login\Activator::activate( $network_wide );
});

/**
 * Register Deactivation Hook
 */
register_deactivation_hook( __FILE__, function( $network_wide ) {
    Hikmah_Login\Deactivator::deactivate( $network_wide );
});

/**
 * Handle new blog creation on multisite
 * (Auto-activate when new site is created)
 */
add_action( 'wp_insert_site', function( $new_site ) {
    if ( is_plugin_active_for_network( HIKMAH_LOGIN_BASENAME ) ) {
        switch_to_blog( $new_site->blog_id );
        Hikmah_Login\Activator::activate( false );
        restore_current_blog();
    }
});

/**
 * Initialize the plugin.
 *
 * We use 'plugins_loaded' to ensure all plugins are loaded
 * before we initialize (important for compatibility).
 */
add_action( 'plugins_loaded', function() {
    Hikmah_Login\Hikmah_Login::get_instance();
});

/**
 * Global accessor function.
 *
 * Usage: hikmah_login()->get_module('login')
 *
 * @return Hikmah_Login\Hikmah_Login Plugin instance.
 */
function hikmah_login() {
    return Hikmah_Login\Hikmah_Login::get_instance();
}