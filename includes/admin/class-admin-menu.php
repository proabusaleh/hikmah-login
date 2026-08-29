<?php
/**
 * Admin Menu
 *
 * Registers the Hikmah Login admin menu, sub-menus,
 * and loads the security dashboard view.
 *
 * @package Hikmah_Login
 * @subpackage Admin
 * @since   1.0.0
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Menu {

    use Singleton;
    use Hooks;

    /**
     * Capability required to access plugin admin pages.
     */
    const CAPABILITY = 'manage_options';

    /**
     * Constructor.
     */
    private function __construct() {
        $this->add_action( 'admin_menu', 'register_menus' );
    }

    /**
     * Register admin menus.
     */
    public function register_menus() {

        $icon = 'dashicons-shield';

        // Parent menu + dashboard
        add_menu_page(
            __( 'Hikmah Login', 'hikmah-login' ),
            __( 'Hikmah Login', 'hikmah-login' ),
            self::CAPABILITY,
            'hikmah-login',
            [ $this, 'render_dashboard' ],
            $icon,
            80
        );

        // Settings sub-menu
        add_submenu_page(
            'hikmah-login',
            __( 'Settings', 'hikmah-login' ),
            __( 'Settings', 'hikmah-login' ),
            self::CAPABILITY,
            'hikmah-login-settings',
            [ $this, 'render_settings' ]
        );

        // Security sub-menu
        add_submenu_page(
            'hikmah-login',
            __( 'Security Dashboard', 'hikmah-login' ),
            __( 'Security', 'hikmah-login' ),
            self::CAPABILITY,
            'hikmah-login-security',
            [ $this, 'render_security_dashboard' ]
        );
    }

    /**
     * Render the main dashboard view.
     */
    public function render_dashboard() {
        $this->load_view( 'dashboard' );
    }

    /**
     * Render the settings view.
     */
    public function render_settings() {
        $this->load_view( 'security-settings' );
    }

    /**
     * Render the security dashboard view.
     */
    public function render_security_dashboard() {
        $this->load_view( 'security-dashboard' );
    }

    /**
     * Load an admin view file.
     *
     * @param string $view View name (without .php).
     */
    private function load_view( $view ) {

        $file = HIKMAH_LOGIN_DIR . 'admin/views/' . basename( $view ) . '.php';

        if ( file_exists( $file ) ) {
            include $file;
        }
    }
}