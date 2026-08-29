<?php
/**
 * AJAX Login Handler
 *
 * Handles all AJAX login requests from the frontend.
 * Processes form submissions, validates data, and
 * returns JSON responses.
 *
 * @package Hikmah_Login
 * @subpackage Ajax
 * @since   1.0.0
 */

namespace Hikmah_Login\Ajax;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Auth\Auth_Manager;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Validator;
use Hikmah_Login\Helpers\Sanitizer;
use Hikmah_Login\Security\Captcha;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax_Login {

    use Singleton;
    use Hooks;

    /**
     * Auth Manager instance.
     *
     * @var Auth_Manager
     */
    private $auth;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->auth = Auth_Manager::get_instance();
        $this->register_hooks();
    }

    /**
     * Register AJAX hooks.
     *
     * Direct per-action admin-ajax registrations are intentionally
     * omitted — all requests now route through the unified
     * Ajax_Controller (action: hikmah_ajax).
     */
    private function register_hooks() {
        // Handlers are dispatched directly by Ajax_Controller.
    }

    /**
     * =============================================
     * MAIN LOGIN HANDLER
     * =============================================
     */

    /**
     * Handle AJAX login request.
     */
    public function handle_login() {

        // Step 1: Check if already logged in
        if ( is_user_logged_in() ) {
            Helper::send_json(
                true,
                __( 'You are already logged in.', 'hikmah-login' ),
                [ 'redirect' => Helper::get_dashboard_url() ]
            );
        }

        // Step 3: Get and sanitize form data
        $raw_data = [
            'username'   => isset( $_POST['username'] ) ? wp_unslash( $_POST['username'] ) : '',
            'password'   => isset( $_POST['password'] ) ? $_POST['password'] : '',
            'remember'   => isset( $_POST['remember'] ) ? wp_unslash( $_POST['remember'] ) : '',
            'redirect'   => isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : '',
            'website_url' => isset( $_POST['website_url'] ) ? wp_unslash( $_POST['website_url'] ) : '',
        ];

        // Step 4: CAPTCHA verification
        if ( Helper::is_feature_enabled( 'captcha_enabled' ) ) {
            $captcha_response = isset( $_POST['captcha_response'] )
                ? sanitize_text_field( wp_unslash( $_POST['captcha_response'] ) )
                : '';

            $captcha_result = Captcha::get_instance()->verify( $captcha_response );

            if ( is_wp_error( $captcha_result ) || true !== $captcha_result ) {
                Helper::send_json(
                    false,
                    is_wp_error( $captcha_result )
                        ? $captcha_result->get_error_message()
                        : __( 'CAPTCHA verification failed. Please try again.', 'hikmah-login' ),
                    [ 'field' => 'captcha' ],
                    400
                );
            }
        }

        // Step 5: Delegate to Auth Manager
        $result = $this->auth->login( $raw_data );

        // Step 6: Handle 2FA requirement
        if ( ! empty( $result['data']['requires_2fa'] ) ) {
            Helper::send_json(
                true,
                $result['message'],
                [
                    'requires_2fa' => true,
                    'user_id'      => $result['data']['user_id'],
                    'method'       => $result['data']['2fa_method'],
                ]
            );
        }

        // Step 7: Send response
        if ( $result['success'] ) {
            Helper::send_json(
                true,
                $result['message'],
                [ 'redirect' => $result['data']['redirect'] ]
            );
        } else {
            Helper::send_json(
                false,
                $result['message'],
                [ 'code' => $result['code'] ],
                401
            );
        }
    }

    /**
     * =============================================
     * LOGOUT HANDLER
     * =============================================
     */

    /**
     * Handle AJAX logout request.
     */
    public function handle_logout() {

        $redirect = isset( $_POST['redirect'] )
            ? sanitize_text_field( wp_unslash( $_POST['redirect'] ) )
            : '';

        $result = $this->auth->logout( $redirect );

        Helper::send_json(
            $result['success'],
            $result['message'],
            [ 'redirect' => $result['data']['redirect'] ]
        );
    }

    /**
     * =============================================
     * 2FA VERIFICATION HANDLER
     * =============================================
     */

    /**
     * Handle 2FA code verification.
     */
    public function handle_2fa_verification() {

        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
        $remember = isset( $_POST['remember'] ) && '1' === $_POST['remember'];
        $redirect = isset( $_POST['redirect'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect'] ) ) : '';

        if ( ! $user_id || empty( $code ) ) {
            Helper::send_json(
                false,
                __( 'Invalid verification request.', 'hikmah-login' ),
                [],
                400
            );
        }

        // Verify auth state
        $auth_state = $this->auth->get_auth_state( $user_id );

        if ( ! $auth_state || 'pending_2fa' !== $auth_state['state'] ) {
            Helper::send_json(
                false,
                __( 'Two-factor authentication session expired. Please log in again.', 'hikmah-login' ),
                [],
                401
            );
        }

        // Verify the 2FA code
        $is_valid = $this->verify_2fa_code( $user_id, $code );

        if ( ! $is_valid ) {
            Helper::send_json(
                false,
                __( 'Invalid verification code. Please try again.', 'hikmah-login' ),
                [ 'field' => '2fa_code' ],
                401
            );
        }

        // Clear auth state
        $this->auth->clear_auth_state( $user_id );

        // Complete login
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            Helper::send_json( false, __( 'User not found.', 'hikmah-login' ), [], 404 );
        }

        $result = $this->auth->complete_login( $user, $remember, $redirect );

        Helper::send_json(
            $result['success'],
            $result['message'],
            [ 'redirect' => $result['data']['redirect'] ]
        );
    }

    /**
     * =============================================
     * STATUS CHECK HANDLER
     * =============================================
     */

    /**
     * Check current login status.
     */
    public function handle_check_status() {

        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            Helper::send_json( true, 'Logged in', [
                'user_id'      => $user->ID,
                'display_name' => $user->display_name,
                'email'        => $user->user_email,
                'avatar'       => get_avatar_url( $user->ID ),
                'dashboard'    => Helper::get_dashboard_url(),
                'logout'       => Helper::get_logout_url(),
            ]);
        } else {
            Helper::send_json( false, 'Not logged in', [
                'login'    => Helper::get_login_url(),
                'register' => Helper::get_register_url(),
            ]);
        }
    }

    /**
     * =============================================
     * HELPER METHODS
     * =============================================
     */

    /**
     * Verify 2FA code.
     *
     * @param int    $user_id User ID.
     * @param string $code    User-provided code.
     * @return bool
     */
    private function verify_2fa_code( $user_id, $code ) {

        $db = new \Hikmah_Login\Database\DB_Manager();
        $settings = $db->get_2fa_settings( $user_id );

        if ( ! $settings ) {
            return false;
        }

        // Check backup codes
        if ( ! empty( $settings->backup_codes ) ) {
            $backup_codes = json_decode( $settings->backup_codes, true );
            if ( is_array( $backup_codes ) && in_array( $code, $backup_codes, true ) ) {
                // Remove used backup code
                $backup_codes = array_diff( $backup_codes, [ $code ] );
                $db->save_2fa_settings(
                    $user_id,
                    $settings->secret_key,
                    $settings->method,
                    wp_json_encode( array_values( $backup_codes ) )
                );
                return true;
            }
        }

        // Verify based on method
        switch ( $settings->method ) {
            case 'email':
                // Check email token
                return $db->verify_email_token( $user_id, $code, '2fa' );

            case 'authenticator':
                // TOTP verification (simplified — use a library in production)
                return $this->verify_totp( $settings->secret_key, $code );

            default:
                return false;
        }
    }

    /**
     * Verify TOTP code (simplified).
     *
     * @param string $secret Secret key.
     * @param string $code   User code.
     * @return bool
     */
    private function verify_totp( $secret, $code ) {
        // In production, use a proper TOTP library
        // This is a placeholder for the concept
        $time_step = floor( time() / 30 );

        for ( $i = -1; $i <= 1; $i++ ) {
            $expected = $this->generate_totp_code( $secret, $time_step + $i );
            if ( hash_equals( $expected, $code ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code (simplified).
     *
     * @param string $secret    Secret key.
     * @param int    $time_step Time step.
     * @return string 6-digit code.
     */
    private function generate_totp_code( $secret, $time_step ) {
        $binary_time = pack( 'N*', 0, $time_step );
        $hash = hash_hmac( 'sha1', $binary_time, base64_decode( $secret ), true );
        $offset = ord( $hash[19] ) & 0xf;
        $code = (
            ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xff )
        ) % 1000000;

        return str_pad( $code, 6, '0', STR_PAD_LEFT );
    }
}
