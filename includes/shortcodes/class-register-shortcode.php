<?php
/**
 * Registration Shortcode
 *
 * Provides the [hikmah_register] shortcode.
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

class Register_Shortcode {

    use Singleton;

    private function __construct() {
        add_shortcode( 'hikmah_register', [ $this, 'render' ] );
    }

    /**
     * Render the registration shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content.
     * @param string $tag     Shortcode tag.
     * @return string
     */
    public function render( $atts = [], $content = '', $tag = '' ) {

        $atts = shortcode_atts( [
            'title'          => __( 'Create Account', 'hikmah-login' ),
            'subtitle'       => '',
            'show_title'     => 'true',
            'logo'           => '',
            'theme'          => 'light',
            'redirect_to'    => '',
            'show_social'    => 'auto',
            'show_name'      => 'true',
            'show_terms'     => 'auto',
            'auto_login'     => 'auto',
            'bg_color'       => '',
            'btn_color'      => '',
            'btn_text'       => __( 'Create Account', 'hikmah-login' ),
            'custom_class'   => '',
            'form_id'        => '',
        ], $atts, $tag );

        // Already logged in
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            return '<div class="hikmah-logged-in-notice"><div class="hikmah-notice hikmah-notice-info"><p>' .
                sprintf(
                    esc_html__( 'You are already registered as %s.', 'hikmah-login' ),
                    '<strong>' . esc_html( $user->display_name ) . '</strong>'
                ) .
                '</p></div></div>';
        }

        // Registration disabled
        if ( 'yes' !== get_option( 'hikmah_registration_enabled', 'yes' ) ) {
            return '<div class="hikmah-login-wrapper"><div class="hikmah-notice hikmah-notice-warning"><p>' .
                esc_html__( 'Registration is currently disabled.', 'hikmah-login' ) .
                '</p></div></div>';
        }

        // Build vars
        $vars = $atts;
        $vars['show_title']  = filter_var( $atts['show_title'], FILTER_VALIDATE_BOOLEAN );
        $vars['show_name']   = filter_var( $atts['show_name'], FILTER_VALIDATE_BOOLEAN );
        $vars['form_id']     = ! empty( $atts['form_id'] )
            ? sanitize_html_class( $atts['form_id'] )
            : 'hikmah-register-form-' . wp_rand( 1000, 9999 );

        // Template
        $template = locate_template( 'hikmah-login/register-form.php' );
        if ( ! $template ) {
            $template = HIKMAH_LOGIN_DIR . 'public/views/register-form.php';
        }

        ob_start();
        extract( $vars ); // phpcs:ignore
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }
}