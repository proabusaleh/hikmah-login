<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    HikmahLogin
 * @subpackage HikmahLogin/public
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hikmah_Login_Public {

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
	 * Register the styles for the public-facing side.
	 */
	public function enqueue_styles() {
		// Placeholder: wp_enqueue_style( $this->plugin_name, HIKMAH_LOGIN_PLUGIN_URL . 'assets/css/public.css', array(), $this->version );
	}

	/**
	 * Register the JavaScript for the public-facing side.
	 */
	public function enqueue_scripts() {
		// Placeholder: wp_enqueue_script( $this->plugin_name, HIKMAH_LOGIN_PLUGIN_URL . 'assets/js/public.js', array( 'jquery' ), $this->version, false );
	}

	/**
	 * Render the login view.
	 */
	public function render_login() {
		include_once plugin_dir_path( __FILE__ ) . 'views/login.php';
	}

	/**
	 * Render the register view.
	 */
	public function render_register() {
		include_once plugin_dir_path( __FILE__ ) . 'views/register.php';
	}

	/**
	 * Render the forgot password view.
	 */
	public function render_forgot_password() {
		include_once plugin_dir_path( __FILE__ ) . 'views/forgot-password.php';
	}

	/**
	 * Render the reset password view.
	 */
	public function render_reset_password() {
		include_once plugin_dir_path( __FILE__ ) . 'views/reset-password.php';
	}
}
