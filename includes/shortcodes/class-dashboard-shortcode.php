<?php
/**
 * Dashboard Shortcode
 *
 * [hikmah_dashboard]
 * [hikmah_dashboard tab="security"]
 *
 * @package Hikmah_Login
 * @subpackage Shortcodes
 * @since   1.0.0
 */

namespace Hikmah_Login\Shortcodes;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Helpers\Helper;

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
            return '<div class="hikmah-login-wrapper"><div class="hikmah-notice hikmah-notice-info"><p>' .
                sprintf(
                    wp_kses_post( __( 'Please <a href="%s">log in</a> to view your dashboard.', 'hikmah-login' ) ),
                    esc_url( Helper::get_login_url() )
                ) .
                '</p></div></div>';
        }

        // Override tab if specified.
        if ( ! empty( $atts['tab'] ) ) {
            $_GET['tab'] = sanitize_key( $atts['tab'] );
        }

        $template = locate_template( 'hikmah-login/user-dashboard.php' );
        if ( ! $template ) {
            $template = HIKMAH_LOGIN_DIR . 'public/views/user-dashboard.php';
        }

        ob_start();
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }
}