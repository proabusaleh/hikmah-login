<?php
/**
 * Forgot Password Shortcode
 *
 * [hikmah_forgot_password]
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

class Forgot_Password_Shortcode {

    use Singleton;

    private function __construct() {
        add_shortcode( 'hikmah_forgot_password', [ $this, 'render' ] );
    }

    public function render( $atts = [], $content = '', $tag = '' ) {

        $atts = shortcode_atts( [
            'title'      => __( 'Forgot Password?', 'hikmah-login' ),
            'subtitle'   => '',
            'show_title' => 'true',
            'logo'       => '',
            'theme'      => 'light',
            'custom_class' => '',
            'form_id'    => '',
        ], $atts, $tag );

        if ( is_user_logged_in() ) {
            return '<div class="hikmah-login-wrapper"><div class="hikmah-notice hikmah-notice-info"><p>' .
                esc_html__( 'You are already logged in.', 'hikmah-login' ) .
                '</p></div></div>';
        }

        $vars = $atts;
        $vars['show_title'] = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
        $vars['form_id'] = ! empty( $atts['form_id'] )
            ? sanitize_html_class( $atts['form_id'] )
            : 'hikmah-forgot-form-' . wp_rand( 1000, 9999 );

        $template = locate_template( 'hikmah-login/forgot-password-form.php' );
        if ( ! $template ) {
            $template = HIKMAH_LOGIN_DIR . 'public/views/forgot-password-form.php';
        }

        ob_start();
        extract( $vars ); // phpcs:ignore
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }
}