<?php
/**
 * AJAX Response
 *
 * Standardized JSON response builder for all AJAX endpoints.
 * Ensures consistent response format across the entire plugin.
 *
 * Response Format:
 * {
 *     "success": true|false,
 *     "message": "Human-readable message",
 *     "code": "machine_readable_code",
 *     "data": { ... },
 *     "meta": {
 *         "timestamp": 1234567890,
 *         "nonce": "fresh_nonce",
 *         "execution_time": "12.5ms"
 *     }
 * }
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 */

namespace Hikmah_Login\Ajax;

use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Response {

    /**
     * Response data.
     *
     * @var array
     */
    private $data = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->reset();
    }

    /**
     * Reset response to defaults.
     */
    private function reset() {
        $this->data = [
            'success' => false,
            'message' => '',
            'code'    => '',
            'data'    => [],
            'meta'    => [
                'timestamp' => time(),
            ],
        ];
    }

    /**
     * Send a success response.
     *
     * @param string $message Success message.
     * @param array  $data    Additional data.
     * @param string $code    Machine-readable code.
     * @param int    $status  HTTP status code.
     */
    public function success( $message, $data = [], $code = 'success', $status = 200 ) {

        $this->data['success'] = true;
        $this->data['message'] = $message;
        $this->data['code']    = $code;
        $this->data['data']    = $data;

        $this->add_meta();
        $this->send( $status );
    }

    /**
     * Send an error response.
     *
     * @param string $message Error message.
     * @param string $code    Machine-readable error code.
     * @param int    $status  HTTP status code.
     * @param array  $data    Additional data.
     */
    public function error( $message, $code = 'error', $status = 400, $data = [] ) {

        $this->data['success'] = false;
        $this->data['message'] = $message;
        $this->data['code']    = $code;
        $this->data['data']    = $data;

        $this->add_meta();
        $this->send( $status );
    }

    /**
     * Send a validation error response.
     *
     * @param string $message General error message.
     * @param array  $errors  Field => [errors] array.
     */
    public function validation_error( $message, $errors = [] ) {

        $this->error(
            $message,
            'validation_error',
            422,
            [ 'field_errors' => $errors ]
        );
    }

    /**
     * Send a redirect response.
     *
     * @param string $url     Redirect URL.
     * @param string $message Optional message.
     * @param int    $delay   Delay in milliseconds.
     */
    public function redirect( $url, $message = '', $delay = 500 ) {

        $this->success(
            $message ?: __( 'Redirecting...', 'hikmah-login' ),
            [
                'redirect' => $url,
                'delay'    => $delay,
            ],
            'redirect'
        );
    }

    /**
     * Add metadata to response.
     */
    private function add_meta() {

        // Include fresh nonces for the frontend
        if ( is_user_logged_in() ) {
            $this->data['meta']['nonces'] = [
                'general' => wp_create_nonce( 'hikmah_login_nonce' ),
                'rest'    => wp_create_nonce( 'wp_rest' ),
            ];
        }

        /**
         * Filter response meta data.
         *
         * @since 1.0.0
         * @param array $meta Meta data.
         */
        $this->data['meta'] = apply_filters(
            'hikmah_ajax_response_meta',
            $this->data['meta']
        );
    }

    /**
     * Send the response and terminate.
     *
     * @param int $status HTTP status code.
     */
    private function send( $status = 200 ) {

        /**
         * Filter the complete AJAX response.
         *
         * @since 1.0.0
         * @param array $response Response data.
         * @param int   $status   HTTP status.
         */
        $this->data = apply_filters( 'hikmah_ajax_response', $this->data, $status );

        wp_send_json( $this->data, $status );
    }

    /**
     * Build response array without sending (for testing).
     *
     * @return array
     */
    public function build() {
        $this->add_meta();
        return $this->data;
    }
}
