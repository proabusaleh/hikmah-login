<?php
/**
 * Dashboard Shortcode
 *
 * [hikmah_dashboard]
 * [hikmah_dashboard tab="security"]
 * [hikmah_dashboard show_header="false"]
 *
 * @package Hikmah_Login
 * @subpackage Shortcodes
 * @since   1.0.0
 */

namespace Hikmah_Login\Shortcodes;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Dashboard\Dashboard_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dashboard_Shortcode {

	use Singleton;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'hikmah_dashboard', [ $this, 'render' ] );
	}

	/**
	 * Render the dashboard shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content.
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts = [], $content = '', $tag = '' ) {

		$atts = shortcode_atts( [
			'tab'          => '',
			'show_header'  => 'true',
			'custom_class' => '',
		], $atts, $tag );

		if ( ! is_user_logged_in() ) {
			return Dashboard_Manager::get_instance()->render_guest_fallback();
		}

		// Allow a default tab override via the shortcode attribute.
		if ( ! empty( $atts['tab'] ) && empty( $_GET['tab'] ) ) {
			$_GET['tab'] = sanitize_key( $atts['tab'] );
		}

		// Enqueue dashboard script + localized config.
		wp_enqueue_script(
			'hikmah-dashboard-script',
			HIKMAH_LOGIN_URL . 'public/js/dashboard-script.js',
			[ 'jquery' ],
			HIKMAH_LOGIN_VERSION,
			true
		);

		return Dashboard_Manager::get_instance()->render();
	}
}