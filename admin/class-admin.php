<?php
/**
 * The admin-facing functionality of the plugin.
 *
 * @package    HikmahLogin
 * @subpackage HikmahLogin/admin
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hikmah_Login_Admin {

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the styles for the admin area.
	 */
	public function enqueue_styles() {
		// Placeholder: wp_enqueue_style( $this->plugin_name . '-admin', ... );
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		// Placeholder: wp_enqueue_script( $this->plugin_name . '-admin', ..., array( 'jquery' ), $this->version, false );
	}

	/**
	 * Register the plugin admin menu.
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			'Hikmah Login',
			'Hikmah Login',
			'manage_options',
			'hikmah-login',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-users',
			80
		);
	}

	/**
	 * Render the dashboard view.
	 */
	public function render_dashboard() {
		include_once plugin_dir_path( __FILE__ ) . 'views/dashboard.php';
	}

	/**
	 * Render the settings view.
	 */
	public function render_settings() {
		include_once plugin_dir_path( __FILE__ ) . 'views/settings.php';
	}

	/**
	 * Render the security view.
	 */
	public function render_security() {
		include_once plugin_dir_path( __FILE__ ) . 'views/security.php';
	}
}
