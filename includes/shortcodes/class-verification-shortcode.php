<?php
/**
 * Verification Shortcode
 *
 * [hikmah_verify] — Shows verification status page
 * [hikmah_verify status="success"] — Force a specific status
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

class Verification_Shortcode {

    use Singleton;

    private function __construct() {
        add_shortcode( 'hikmah_verify', [ $this, 'render' ] );
    }

    /**
     * Render the verification shortcode.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Enclosed content.
     * @param string $tag     Shortcode tag.
     * @return string
     */
    public function render( $atts = [], $content = '', $tag = '' ) {

        $atts = shortcode_atts( [
            'status' => 'auto', // auto, success, expired, invalid, pending
        ], $atts, $tag );

        // Determine status
        $status = $atts['status'];

        if ( 'auto' === $status ) {
            $status = $this->detect_status();
        }

        // Render template
        $template = locate_template( 'hikmah-login/email-verification.php' );
        if ( ! $template ) {
            $template = HIKMAH_LOGIN_DIR . 'public/views/email-verification.php';
        }

        ob_start();
        if ( file_exists( $template ) ) {
            include $template;
        }
        return ob_get_clean();
    }

    /**
     * Auto-detect verification status from URL parameters.
     *
     * @return string Status.
     */
    private function detect_status() {

        if ( isset( $_GET['verified'] ) ) {
            if ( 'true' === $_GET['verified'] ) {
                return 'success';
            }
            if ( 'already' === $_GET['verified'] ) {
                return 'success';
            }
        }

        if ( isset( $_GET['verify_error'] ) ) {
            $error = sanitize_text_field( wp_unslash( $_GET['verify_error'] ) );

            if ( 'expired' === $error ) {
                return 'expired';
            }

            return 'invalid';
        }

        // Check if logged-in user needs verification
        if ( is_user_logged_in() && ! Helper::is_email_verified( get_current_user_id() ) ) {
            return 'pending';
        }

        return 'pending';
    }
}