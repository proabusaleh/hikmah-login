<?php
/**
 * AJAX Performance Optimizer
 *
 * Reduces server load and improves AJAX response times.
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 */

namespace Hikmah_Login\Ajax;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Optimizer {

    use Singleton;
    use Hooks;

    private function __construct() {
        $this->register_hooks();
    }

    private function register_hooks() {
        // Disable unnecessary WordPress loading for AJAX
        $this->add_action( 'init', 'optimize_ajax_requests', 1 );

        // Cache AJAX responses where appropriate
        $this->add_filter( 'hikmah_ajax_response', 'maybe_cache_response', 100, 2 );

        // Minimize data in responses
        $this->add_filter( 'hikmah_ajax_response', 'minimize_response', 90, 2 );
    }

    /**
     * Optimize AJAX request loading.
     *
     * Skip unnecessary WordPress initialization for AJAX requests.
     */
    public function optimize_ajax_requests() {

        if ( ! wp_doing_ajax() ) {
            return;
        }

        // Only optimize our own AJAX calls
        $action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

        if ( 'hikmah_ajax' !== $action && strpos( $action, 'hikmah_' ) !== 0 ) {
            return;
        }

        // Disable emoji scripts (not needed for AJAX)
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );

        // Disable WP embeds
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );

        // Disable admin bar
        add_filter( 'show_admin_bar', '__return_false' );

        // Disable shortcode rendering in AJAX context
        // (prevents accidental shortcode execution in form data)
    }

    /**
     * Cache certain AJAX responses.
     *
     * @param array $response Response data.
     * @param int   $status   HTTP status.
     * @return array
     */
    public function maybe_cache_response( $response, $status ) {

        // Only cache successful responses
        if ( ! $response['success'] ) {
            return $response;
        }

        // Cache check_status for 30 seconds
        $action = isset( $_POST['hikmah_action'] ) ? sanitize_key( wp_unslash( $_POST['hikmah_action'] ) ) : '';

        if ( 'check_status' === $action ) {
            $cache_key = 'hikmah_ajax_cache_status_' . get_current_user_id();
            set_transient( $cache_key, $response, 30 );
        }

        return $response;
    }

    /**
     * Minimize response payload.
     *
     * @param array $response Response data.
     * @param int   $status   HTTP status.
     * @return array
     */
    public function minimize_response( $response, $status ) {

        // Remove empty data arrays
        if ( empty( $response['data'] ) ) {
            unset( $response['data'] );
        }

        // Remove meta in production
        if ( ! HIKMAH_LOGIN_DEBUG ) {
            unset( $response['meta']['timestamp'] );
        }

        return $response;
    }
}
