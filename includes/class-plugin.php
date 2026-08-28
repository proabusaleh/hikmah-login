<?php
/**
 * The core plugin class.
 *
 * @package    HikmahLogin
 * @subpackage HikmahLogin/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hikmah_Login_Plugin {

	/**
	 * The loader responsible for maintaining and registering hooks.
	 *
	 * @var Hikmah_Login_Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name = 'hikmah-login';

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version = HIKMAH_LOGIN_VERSION;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		require_once HIKMAH_LOGIN_PLUGIN_DIR . 'includes/class-loader.php';
		require_once HIKMAH_LOGIN_PLUGIN_DIR . 'admin/class-admin.php';
		require_once HIKMAH_LOGIN_PLUGIN_DIR . 'public/class-public.php';

		$this->loader = new Hikmah_Login_Loader();
	}

	/**
	 * Register all hooks related to the admin area.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new Hikmah_Login_Admin( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
	}

	/**
	 * Register all hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new Hikmah_Login_Public( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all hooks.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		require_once HIKMAH_LOGIN_PLUGIN_DIR . 'includes/class-activator.php';
		Hikmah_Login_Activator::activate();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		require_once HIKMAH_LOGIN_PLUGIN_DIR . 'includes/class-deactivator.php';
		Hikmah_Login_Deactivator::deactivate();
	}

	/**
	 * The name of the plugin.
	 *
	 * @return string The plugin name.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the plugin version.
	 *
	 * @return string The version number.
	 */
	public function get_version() {
		return $this->version;
	}
}
