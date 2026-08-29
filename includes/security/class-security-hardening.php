<?php
/**
 * Security Hardening
 *
 * Applies security hardening headers and protections:
 * - Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
 * - XML-RPC disable
 * - REST API / user enumeration protection
 * - Login error message hardening
 * - Upload restrictions
 * - Application passwords disable
 *
 * @package Hikmah_Login
 * @subpackage Security
 * @since   1.0.0
 */

namespace Hikmah_Login\Security;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Security_Hardening {

    use Singleton;
    use Hooks;

    /**
     * Constructor.
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register all hardening hooks.
     */
    private function register_hooks() {

        // Security headers
        $this->add_action( 'send_headers', 'send_security_headers' );

        // XML-RPC protection
        if ( Helper::is_feature_enabled( 'disable_xmlrpc' ) ) {
            $this->add_filter( 'xmlrpc_enabled', 'disable_xmlrpc' );
            $this->add_filter( 'wp_headers', 'remove_xmlrpc_headers' );
        }

        // REST API user enumeration protection
        if ( Helper::is_feature_enabled( 'disable_rest_user_enumeration' ) ) {
            $this->add_filter( 'rest_authentication_errors', 'restrict_rest_api', 10, 1 );
            $this->add_filter( 'rest_prepare_user', 'hide_user_email', 10, 3 );
        }

        // Login error hardening
        if ( Helper::is_feature_enabled( 'hide_login_errors' ) ) {
            $this->add_filter( 'login_errors', 'hardened_login_errors' );
            $this->add_filter( 'wp_login_errors', 'hardened_login_errors_global' );
            $this->add_filter( 'login_message', 'hardened_login_message' );
        }

        // Upload restrictions
        if ( Helper::is_feature_enabled( 'restrict_uploads' ) ) {
            $this->add_filter( 'upload_mimes', 'restrict_upload_mimes' );
            $this->add_filter( 'wp_check_filetype_and_ext', 'disable_php_uploads', 10, 5 );
        }

        // Application passwords
        if ( Helper::is_feature_enabled( 'disable_application_passwords' ) ) {
            $this->add_filter( 'wp_is_application_passwords_available', '__return_false' );
        }
    }

    /**
     * =============================================
     * SECURITY HEADERS
     * =============================================
     */

    /**
     * Send security headers.
     */
    public function send_security_headers() {

        if ( is_admin() ) {
            return;
        }

        // Don't break the WordPress REST API (needed for some hosts)
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            return;
        }

        $enabled = 'yes' === get_option( 'hikmah_security_headers_enabled', 'yes' );

        if ( ! $enabled ) {
            return;
        }

        $headers = [
            'X-Content-Type-Options: nosniff',
            'X-Frame-Options: SAMEORIGIN',
            'X-XSS-Protection: 1; mode=block',
            'Referrer-Policy: strict-origin-when-cross-origin',
        ];

        $permissions_policy = (string) get_option( 'hikmah_permissions_policy', 'geolocation=(), microphone=(), camera=()' );
        if ( $permissions_policy ) {
            $headers[] = 'Permissions-Policy: ' . $permissions_policy;
        }

        $csp = (string) get_option( 'hikmah_content_security_policy', '' );
        if ( $csp ) {
            $headers[] = 'Content-Security-Policy: ' . $csp;
        }

        foreach ( $headers as $header ) {
            header( $header );
        }
    }

    /**
     * =============================================
     * XML-RPC PROTECTION
     * =============================================
     */

    /**
     * Disable XML-RPC.
     *
     * @return bool
     */
    public function disable_xmlrpc() {
        return false;
    }

    /**
     * Remove X-Pingback header.
     *
     * @param array $headers Existing headers.
     * @return array
     */
    public function remove_xmlrpc_headers( $headers ) {

        unset( $headers['X-Pingback'] );

        return $headers;
    }

    /**
     * =============================================
     * USER ENUMERATION PROTECTION
     * =============================================
     */

    /**
     * Restrict REST API user enumeration.
     *
     * @param \WP_Error|mixed $errors Authentication errors.
     * @return \WP_Error|mixed
     */
    public function restrict_rest_api( $errors ) {

        if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
            $request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

            // Block user enumeration endpoints for unauthenticated requests
            if ( is_user_logged_in() ) {
                return $errors;
            }

            if ( preg_match( '#/wp-json/wp/v2/users#', $request_uri ) ) {
                return new \WP_Error(
                    'rest_user_enumeration',
                    __( 'User enumeration is disabled.', 'hikmah-login' ),
                    [ 'status' => 403 ]
                );
            }
        }

        return $errors;
    }

    /**
     * Hide user email in REST API responses.
     *
     * @param \WP_REST_Response $response Response object.
     * @param \WP_User          $user     User object.
     * @param \WP_REST_Request  $request  Request object.
     * @return \WP_REST_Response
     */
    public function hide_user_email( $response, $user, $request ) {

        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            $data = $response->get_data();
            if ( isset( $data['email'] ) ) {
                unset( $data['email'] );
            }
            $response->set_data( $data );
        }

        return $response;
    }

    /**
     * =============================================
     * LOGIN ERROR HARDENING
     * =============================================
     */

    /**
     * Harden login error messages.
     *
     * @param string $errors Login page errors HTML.
     * @return string
     */
    public function hardened_login_errors( $errors ) {

        if ( empty( $errors ) ) {
            return $errors;
        }

        return '<div id="login_error"><strong>' .
            esc_html__( 'Login failed.', 'hikmah-login' ) . '</strong><br />' .
            esc_html__( 'Invalid username or password. Please check your credentials and try again.', 'hikmah-login' ) .
            '</div>';
    }

    /**
     * Harden generic login errors (WP_Error object).
     *
     * @param \WP_Error $errors Login errors.
     * @return \WP_Error
     */
    public function hardened_login_errors_global( $errors ) {

        if ( ! is_a( $errors, 'WP_Error' ) || 0 === count( $errors->get_error_codes() ) ) {
            return $errors;
        }

        $errors->remove( 'incorrect_password' );
        $errors->remove( 'invalid_username' );
        $errors->remove( 'invalid_email' );
        $errors->remove( 'empty_username' );
        $errors->remove( 'empty_password' );

        return $errors;
    }

    /**
     * Harden the default login message.
     *
     * @param string $message Login message.
     * @return string
     */
    public function hardened_login_message( $message ) {
        return $message;
    }

    /**
     * =============================================
     * UPLOAD RESTRICTIONS
     * =============================================
     */

    /**
     * Restrict allowed upload MIME types.
     *
     * @param array $mimes Allowed MIME types.
     * @return array
     */
    public function restrict_upload_mimes( $mimes ) {

        $allowed = get_option( 'hikmah_allowed_upload_mimes', '' );

        if ( empty( $allowed ) ) {
            return $mimes;
        }

        $allowed = array_map( 'trim', explode( ',', $allowed ) );

        $filtered = [];
        foreach ( $mimes as $ext => $type ) {
            if ( in_array( strtolower( (string) $ext ), $allowed, true ) ) {
                $filtered[ $ext ] = $type;
            }
        }

        return $filtered;
    }

    /**
     * Explicitly deny dangerous file types at upload.
     *
     * @param array $file_data File data.
     * @param string $file     Full path to file.
     * @param string $filename File name.
     * @param array  $mimes    Allowed MIME types.
     * @param string $real_mime Actual MIME type.
     * @return array
     */
    public function disable_php_uploads( $file_data, $file, $filename, $mimes, $real_mime ) {

        $dangerous = [ 'php', 'php3', 'php4', 'php5', 'phtml', 'pht' ];
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        if ( in_array( $extension, $dangerous, true ) ) {
            $file_data['ext']  = false;
            $file_data['type'] = false;
            $file_data['proper_filename'] = $filename;
        }

        return $file_data;
    }

    /**
     * =============================================
     * STATUS REPORT (for admin dashboard)
     * =============================================
     */

    /**
     * Get current security status.
     *
     * @return array
     */
    public static function get_security_status() {

        $checks = [
            'brute_force' => [
                'label'    => __( 'Brute Force Protection', 'hikmah-login' ),
                'enabled'  => (bool) Helper::is_feature_enabled( 'brute_force_enabled' ),
                'details'  => sprintf(
                    __( 'Max %d attempts, %d min lockout', 'hikmah-login' ),
                    (int) get_option( 'hikmah_max_login_attempts', 5 ),
                    (int) get_option( 'hikmah_lockout_duration', 30 )
                ),
            ],
            'captcha' => [
                'label'   => __( 'Captcha Protection', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'captcha_enabled' ),
                'details' => ucwords( str_replace( '_', ' ', get_option( 'hikmah_captcha_type', 'recaptcha_v2' ) ) ),
            ],
            'xmlrpc' => [
                'label'   => __( 'XML-RPC Disabled', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'disable_xmlrpc' ),
            ],
            'user_enumeration' => [
                'label'   => __( 'User Enumeration Protection', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'disable_rest_user_enumeration' ),
            ],
            'login_errors' => [
                'label'   => __( 'Login Error Hardening', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'hide_login_errors' ),
            ],
            'uploads' => [
                'label'   => __( 'Upload Restrictions', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'restrict_uploads' ),
            ],
            'app_passwords' => [
                'label'   => __( 'App Passwords Disabled', 'hikmah-login' ),
                'enabled' => (bool) Helper::is_feature_enabled( 'disable_application_passwords' ),
            ],
            'security_headers' => [
                'label'   => __( 'Security Headers', 'hikmah-login' ),
                'enabled' => 'yes' === get_option( 'hikmah_security_headers_enabled', 'yes' ),
            ],
        ];

        $enabled_count = count( array_filter( $checks, function( $check ) {
            return $check['enabled'];
        } ) );

        return [
            'checks'        => $checks,
            'enabled_count' => $enabled_count,
            'total'         => count( $checks ),
            'overall_level' => self::get_overall_level( $enabled_count, count( $checks ) ),
        ];
    }

    /**
     * Determine overall security level.
     *
     * @param int $enabled Enabled security features.
     * @param int $total   Total security features.
     * @return string
     */
    private static function get_overall_level( $enabled, $total ) {

        $ratio = $enabled / max( $total, 1 );

        if ( $ratio >= 0.8 ) {
            return 'strong';
        }
        if ( $ratio >= 0.5 ) {
            return 'medium';
        }

        return 'weak';
    }
}