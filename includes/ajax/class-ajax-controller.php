<?php
/**
 * Unified AJAX Controller
 *
 * Central hub for ALL AJAX requests in Hikmah Login.
 * Routes requests to appropriate handlers, manages nonces,
 * rate limiting, error handling, and response formatting.
 *
 * Architecture:
 *   Frontend JS → admin-ajax.php → Ajax_Controller::route()
 *     → Verify nonce
 *     → Check rate limit
 *     → Dispatch to handler
 *     → Format response
 *     → Return JSON
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 */

namespace Hikmah_Login\Ajax;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Error_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Controller {

    use Singleton;
    use Hooks;

    /**
     * Registered AJAX actions.
     *
     * Format: action_name => [
     *     'handler'    => callable,
     *     'auth'       => 'public' | 'logged_in' | 'both',
     *     'nonce'      => nonce action string,
     *     'rate_limit' => max requests per window,
     *     'window'     => rate limit window in seconds,
     * ]
     *
     * @var array
     */
    private $actions = [];

    /**
     * AJAX response object.
     *
     * @var Ajax_Response
     */
    private $response;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->response = new Ajax_Response();
        $this->register_core_actions();
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks() {
        // Register the universal AJAX router
        $this->add_ajax_both( 'hikmah_ajax', 'route' );

        // Nonce refresh endpoint
        $this->add_ajax_both( 'hikmah_refresh_nonce', 'refresh_nonce' );

        // Heartbeat for session keep-alive
        $this->add_action( 'heartbeat_received', 'heartbeat_handler', 10, 2 );
        $this->add_action( 'wp_enqueue_scripts', 'enqueue_heartbeat', 20 );
    }

    /**
     * =============================================
     * ACTION REGISTRATION
     * =============================================
     */

    /**
     * Register all core AJAX actions.
     */
    private function register_core_actions() {

        // ── Authentication ──
        $this->register_action( 'login', [
            'handler'    => [ Ajax_Login::get_instance(), 'handle_login' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_login_action',
            'rate_limit' => 10,
            'window'     => 300, // 5 minutes
        ]);

        $this->register_action( 'logout', [
            'handler'    => [ Ajax_Login::get_instance(), 'handle_logout' ],
            'auth'       => 'logged_in',
            'nonce'      => 'hikmah_login_action',
            'rate_limit' => 5,
            'window'     => 60,
        ]);

        $this->register_action( 'verify_2fa', [
            'handler'    => [ Ajax_Login::get_instance(), 'handle_2fa_verification' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_login_action',
            'rate_limit' => 5,
            'window'     => 300,
        ]);

        // ── Registration ──
        $this->register_action( 'register', [
            'handler'    => [ Ajax_Register::get_instance(), 'handle_registration' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_register_action',
            'rate_limit' => 5,
            'window'     => 3600, // 1 hour
        ]);

        $this->register_action( 'check_username', [
            'handler'    => [ Ajax_Register::get_instance(), 'handle_check_username' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_register_action',
            'rate_limit' => 30,
            'window'     => 60,
        ]);

        $this->register_action( 'check_email', [
            'handler'    => [ Ajax_Register::get_instance(), 'handle_check_email' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_register_action',
            'rate_limit' => 20,
            'window'     => 60,
        ]);

        // ── Password Recovery ──
        $this->register_action( 'forgot_password', [
            'handler'    => [ Ajax_Forgot_Password::get_instance(), 'handle_forgot_password' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_forgot_password_action',
            'rate_limit' => 3,
            'window'     => 900, // 15 minutes
        ]);

        $this->register_action( 'reset_password', [
            'handler'    => [ Ajax_Forgot_Password::get_instance(), 'handle_reset_password' ],
            'auth'       => 'public',
            'nonce'      => 'hikmah_reset_password_action',
            'rate_limit' => 5,
            'window'     => 900,
        ]);

        // ── Verification ──
        $this->register_action( 'resend_verification', [
            'handler'    => [ $this, 'handle_resend_verification' ],
            'auth'       => 'both',
            'nonce'      => 'hikmah_login_nonce',
            'rate_limit' => 3,
            'window'     => 3600,
        ]);

        // ── Status ──
        $this->register_action( 'check_status', [
            'handler'    => [ Ajax_Login::get_instance(), 'handle_check_status' ],
            'auth'       => 'both',
            'nonce'      => 'hikmah_login_nonce',
            'rate_limit' => 30,
            'window'     => 60,
        ]);

        /**
         * Allow third-party AJAX action registration.
         *
         * @since 1.0.0
         * @param Ajax_Controller $controller Controller instance.
         *
         * Usage:
         *   add_action('hikmah_ajax_register_actions', function($controller) {
         *       $controller->register_action('my_custom_action', [
         *           'handler'    => [ $my_class, 'my_method' ],
         *           'auth'       => 'logged_in',
         *           'nonce'      => 'my_nonce_action',
         *           'rate_limit' => 10,
         *           'window'     => 60,
         *       ]);
         *   });
         */
        do_action( 'hikmah_ajax_register_actions', $this );
    }

    /**
     * Register a single AJAX action.
     *
     * @param string $action  Action name (without 'hikmah_' prefix).
     * @param array  $config  Action configuration.
     */
    public function register_action( $action, $config ) {

        $defaults = [
            'handler'    => null,
            'auth'       => 'public',     // public, logged_in, both
            'nonce'      => 'hikmah_login_nonce',
            'rate_limit' => 10,
            'window'     => 60,
            'capability' => '',           // Required capability (for logged_in)
        ];

        $this->actions[ $action ] = wp_parse_args( $config, $defaults );
    }

    /**
     * =============================================
     * UNIVERSAL ROUTER
     * =============================================
     */

    /**
     * Route incoming AJAX requests to the correct handler.
     *
     * All frontend AJAX calls go through this single endpoint:
     *   POST admin-ajax.php
     *   action: hikmah_ajax
     *   hikmah_action: login | register | forgot_password | etc.
     */
    public function route() {

        $start_time = microtime( true );

        // Step 1: Get the requested action
        $action = isset( $_POST['hikmah_action'] )
            ? sanitize_key( wp_unslash( $_POST['hikmah_action'] ) )
            : '';

        if ( empty( $action ) ) {
            $this->response->error(
                __( 'No action specified.', 'hikmah-login' ),
                'missing_action',
                400
            );
        }

        // Step 2: Check if action is registered
        if ( ! isset( $this->actions[ $action ] ) ) {
            Error_Handler::warning( "Unknown AJAX action: {$action}" );
            $this->response->error(
                __( 'Invalid action.', 'hikmah-login' ),
                'invalid_action',
                400
            );
        }

        $config = $this->actions[ $action ];

        // Step 3: Authentication check
        $this->check_auth( $config['auth'], $config['capability'] );

        // Step 4: Nonce verification
        $nonce_field = 'hikmah_' . $action . '_nonce';
        $nonce_value = isset( $_POST[ $nonce_field ] )
            ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) )
            : '';

        // Fallback to generic nonce field
        if ( empty( $nonce_value ) && isset( $_POST['hikmah_nonce'] ) ) {
            $nonce_value = sanitize_text_field( wp_unslash( $_POST['hikmah_nonce'] ) );
            $nonce_field = 'hikmah_nonce';
        }

        if ( ! wp_verify_nonce( $nonce_value, $config['nonce'] ) ) {
            $this->response->error(
                __( 'Security verification failed. Please refresh the page and try again.', 'hikmah-login' ),
                'nonce_failed',
                403,
                [ 'refresh_nonce' => true ]
            );
        }

        // Step 5: Rate limiting
        $this->check_rate_limit( $action, $config['rate_limit'], $config['window'] );

        // Step 6: Dispatch to handler
        try {
            /**
             * Fires before an AJAX action is executed.
             *
             * @since 1.0.0
             * @param string $action Action name.
             * @param array  $config Action config.
             */
            do_action( 'hikmah_ajax_before_dispatch', $action, $config );

            // Call the handler
            $result = call_user_func( $config['handler'] );

            /**
             * Fires after an AJAX action is executed.
             *
             * @since 1.0.0
             * @param string $action Action name.
             * @param mixed  $result Handler result.
             */
            do_action( 'hikmah_ajax_after_dispatch', $action, $result );

        } catch ( \Exception $e ) {
            Error_Handler::handle_exception( $e, "AJAX action: {$action}" );
            $this->response->error(
                __( 'An unexpected error occurred. Please try again.', 'hikmah-login' ),
                'server_error',
                500
            );
        }

        // Note: Individual handlers call wp_send_json() directly,
        // so this point is only reached if handler returns without sending.
        $execution_time = round( ( microtime( true ) - $start_time ) * 1000, 2 );

        Helper::log( "AJAX [{$action}] completed in {$execution_time}ms" );
    }

    /**
     * =============================================
     * AUTH CHECK
     * =============================================
     */

    /**
     * Check authentication requirements for an action.
     *
     * @param string $auth_type   Auth type: public, logged_in, both.
     * @param string $capability  Required capability (optional).
     */
    private function check_auth( $auth_type, $capability = '' ) {

        switch ( $auth_type ) {
            case 'logged_in':
                if ( ! is_user_logged_in() ) {
                    $this->response->error(
                        __( 'You must be logged in to perform this action.', 'hikmah-login' ),
                        'auth_required',
                        401,
                        [ 'login_url' => Helper::get_login_url() ]
                    );
                }

                if ( ! empty( $capability ) && ! current_user_can( $capability ) ) {
                    $this->response->error(
                        __( 'You do not have permission to perform this action.', 'hikmah-login' ),
                        'permission_denied',
                        403
                    );
                }
                break;

            case 'public':
                // No auth required, but reject if already logged in for some actions
                break;

            case 'both':
            default:
                // No restrictions
                break;
        }
    }

    /**
     * =============================================
     * RATE LIMITING
     * =============================================
     */

    /**
     * Check rate limit for an action.
     *
     * Uses a sliding window approach per IP + action.
     *
     * @param string $action     Action name.
     * @param int    $max_requests Max requests allowed.
     * @param int    $window     Window in seconds.
     */
    private function check_rate_limit( $action, $max_requests, $window ) {

        $ip = Helper::get_client_ip();
        $key = 'hikmah_ajax_rate_' . md5( $ip . '_' . $action );

        $data = get_transient( $key );

        if ( ! $data ) {
            $data = [ 'count' => 0, 'start' => time() ];
        }

        // Reset window if expired
        if ( ( time() - $data['start'] ) > $window ) {
            $data = [ 'count' => 0, 'start' => time() ];
        }

        $data['count']++;

        if ( $data['count'] > $max_requests ) {
            $remaining = $window - ( time() - $data['start'] );

            Error_Handler::warning( "AJAX rate limit hit: {$action}, IP: {$ip}" );

            $this->response->error(
                sprintf(
                    /* translators: %d: Seconds remaining */
                    __( 'Too many requests. Please wait %d seconds before trying again.', 'hikmah-login' ),
                    $remaining
                ),
                'rate_limited',
                429,
                [
                    'retry_after' => $remaining,
                    'action'      => $action,
                ]
            );
        }

        set_transient( $key, $data, $window );
    }

    /**
     * =============================================
     * NONCE REFRESH
     * =============================================
     */

    /**
     * Refresh expired nonces without page reload.
     *
     * Called by frontend JS when a nonce failure is detected.
     */
    public function refresh_nonce() {

        $nonces = [
            'login'      => wp_create_nonce( 'hikmah_login_action' ),
            'register'   => wp_create_nonce( 'hikmah_register_action' ),
            'forgot'     => wp_create_nonce( 'hikmah_forgot_password_action' ),
            'reset'      => wp_create_nonce( 'hikmah_reset_password_action' ),
            'general'    => wp_create_nonce( 'hikmah_login_nonce' ),
            'rest'       => wp_create_nonce( 'wp_rest' ),
        ];

        wp_send_json_success( [
            'nonces'    => $nonces,
            'timestamp' => time(),
        ]);
    }

    /**
     * =============================================
     * HEARTBEAT HANDLER
     * =============================================
     */

    /**
     * Handle WordPress heartbeat for session keep-alive.
     *
     * @param array $response Heartbeat response.
     * @param array $data     Heartbeat data.
     * @return array Modified response.
     */
    public function heartbeat_handler( $response, $data ) {

        if ( ! isset( $data['hikmah_heartbeat'] ) ) {
            return $response;
        }

        // Refresh nonces via heartbeat
        $response['hikmah_nonces'] = [
            'login'   => wp_create_nonce( 'hikmah_login_action' ),
            'general' => wp_create_nonce( 'hikmah_login_nonce' ),
        ];

        // Check session status
        $response['hikmah_session'] = [
            'logged_in' => is_user_logged_in(),
            'user_id'   => get_current_user_id(),
        ];

        return $response;
    }

    /**
     * Enqueue heartbeat script on Hikmah pages.
     */
    public function enqueue_heartbeat() {

        if ( ! Helper::is_hikmah_page() ) {
            return;
        }

        wp_enqueue_script( 'heartbeat' );

        // Set heartbeat interval to 60 seconds (reduce server load)
        wp_add_inline_script( 'heartbeat', '
            if (typeof wp !== "undefined" && wp.heartbeat) {
                wp.heartbeat.interval(60);
            }
        ' );
    }

    /**
     * =============================================
     * RESEND VERIFICATION (Routed)
     * =============================================
     */

    /**
     * Handle resend verification via unified router.
     */
    public function handle_resend_verification() {

        $email = isset( $_POST['email'] )
            ? sanitize_email( wp_unslash( $_POST['email'] ) )
            : '';

        // If logged in, use user's email
        if ( is_user_logged_in() && empty( $email ) ) {
            $user = wp_get_current_user();
            $email = $user->user_email;
        }

        if ( empty( $email ) || ! is_email( $email ) ) {
            Helper::send_json( false, __( 'Please enter a valid email.', 'hikmah-login' ), [], 400 );
        }

        $verification = \Hikmah_Login\Auth\Email_Verification::get_instance();
        $user = get_user_by( 'email', $email );

        $generic = __( 'If an account exists, a verification email has been sent.', 'hikmah-login' );

        if ( ! $user ) {
            Helper::send_json( true, $generic );
        }

        if ( Helper::is_email_verified( $user->ID ) ) {
            Helper::send_json( true, __( 'Email already verified.', 'hikmah-login' ) );
        }

        $sent = $verification->send_verification_email( $user->ID );

        Helper::send_json(
            $sent,
            $sent ? $generic : __( 'Failed to send email.', 'hikmah-login' )
        );
    }

    /**
     * =============================================
     * PUBLIC API
     * =============================================
     */

    /**
     * Get all registered actions.
     *
     * @return array
     */
    public function get_registered_actions() {
        return array_keys( $this->actions );
    }

    /**
     * Check if an action is registered.
     *
     * @param string $action Action name.
     * @return bool
     */
    public function has_action( $action ) {
        return isset( $this->actions[ $action ] );
    }
}
