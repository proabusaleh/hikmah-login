<?php
/**
 * Two-Factor Setup Shortcode
 *
 * [hikmah_2fa_setup] — Renders the 2FA setup wizard for the logged-in user.
 *
 * @package Hikmah_Login
 * @subpackage Shortcodes
 * @since   1.0.0
 */

namespace Hikmah_Login\Shortcodes;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Security\Two_Factor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Two_FA_Shortcode {

	use Singleton;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'hikmah_2fa_setup', [ $this, 'render' ] );
	}

	/**
	 * Render the setup wizard shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Enclosed content.
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts = [], $content = '', $tag = '' ) {

		if ( ! is_user_logged_in() ) {
			return '<p class="hikmah-not-logged-in">' .
				esc_html__( 'Please log in to manage two-factor authentication.', 'hikmah-login' ) .
				'</p>';
		}

		ob_start();
		Two_Factor::get_instance()->render_setup_markup();
		return ob_get_clean();
	}
}