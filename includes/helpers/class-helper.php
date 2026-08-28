<?php
/**
 * Helper Class
 *
 * General-purpose utility methods used across the entire plugin.
 * Provides common functions for URLs, IP detection, date formatting,
 * token generation, and more.
 *
 * @package Hikmah_Login
 * @subpackage Helpers
 * @since   1.0.0
 */

namespace Hikmah_Login\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Helper {

    /**
     * =============================================
     * URL & PAGE HELPERS
     * =============================================
     */

    /**
     * Get a plugin page URL by option key.
     *
     * @param string $page_key Option key (e.g., 'hikmah_login_page_id').
     * @param array  $query    Optional query parameters.
     * @return string Page URL or home URL as fallback.
     */
    public static function get_page_url( $page_key, $query = [] ) {

        $page_id = absint( get_option( $page_key, 0 ) );

        if ( ! $page_id ) {
            return home_url( '/' );
        }

        $url = get_permalink( $page_id );

        if ( ! $url ) {
            return home_url( '/' );
        }

        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        return $url;
    }

    /**
     * Get the login page URL.
     *
     * @param array $query Optional query args.
     * @return string
     */
    public static function get_login_url( $query = [] ) {
        return self::get_page_url( 'hikmah_login_page_id', $query );
    }

    /**
     * Get the registration page URL.
     *
     * @param array $query Optional query args.
     * @return string
     */
    public static function get_register_url( $query = [] ) {
        return self::get_page_url( 'hikmah_register_page_id', $query );
    }

    /**
     * Get the forgot password page URL.
     *
     * @param array $query Optional query args.
     * @return string
     */
    public static function get_forgot_password_url( $query = [] ) {
        return self::get_page_url( 'hikmah_forgot_password_page_id', $query );
    }

    /**
     * Get the reset password page URL.
     *
     * @param array $query Optional query args.
     * @return string
     */
    public static function get_reset_password_url( $query = [] ) {
        return self::get_page_url( 'hikmah_reset_password_page_id', $query );
    }

    /**
     * Get the user dashboard URL.
     *
     * @param array $query Optional query args.
     * @return string
     */
    public static function get_dashboard_url( $query = [] ) {
        return self::get_page_url( 'hikmah_dashboard_page_id', $query );
    }

    /**
     * Get the logout URL with redirect.
     *
     * @param string $redirect_to URL to redirect after logout.
     * @return string
     */
    public static function get_logout_url( $redirect_to = '' ) {

        if ( empty( $redirect_to ) ) {
            $redirect_to = get_option(
                'hikmah_login_logout_redirect_url',
                home_url( '/' )
            );
        }

        return wp_logout_url( $redirect_to );
    }

    /**
     * Check if current page is a Hikmah Login page.
     *
     * @return bool
     */
    public static function is_hikmah_page() {

        if ( ! is_page() ) {
            return false;
        }

        $current_id = get_queried_object_id();

        $page_options = [
            'hikmah_login_page_id',
            'hikmah_register_page_id',
            'hikmah_forgot_password_page_id',
            'hikmah_reset_password_page_id',
            'hikmah_dashboard_page_id',
        ];

        foreach ( $page_options as $option ) {
            if ( absint( get_option( $option ) ) === $current_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current page is a specific Hikmah page.
     *
     * @param string $page_key Option key.
     * @return bool
     */
    public static function is_specific_hikmah_page( $page_key ) {

        if ( ! is_page() ) {
            return false;
        }

        $page_id = absint( get_option( $page_key, 0 ) );

        return $page_id && get_queried_object_id() === $page_id;
    }

    /**
     * =============================================
     * IP ADDRESS HELPERS
     * =============================================
     */

    /**
     * Get the real client IP address.
     *
     * Handles proxies, Cloudflare, load balancers, etc.
     *
     * @return string IP address.
     */
    public static function get_client_ip() {

        $ip_headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Load balancer / Proxy
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_CLIENT_IP',        // Shared internet
            'REMOTE_ADDR',           // Standard
        ];

        foreach ( $ip_headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

                // X-Forwarded-For may contain multiple IPs
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }

                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Check if an IP address is in a given range (CIDR).
     *
     * @param string $ip   IP to check.
     * @param string $cidr CIDR range (e.g., '192.168.1.0/24').
     * @return bool
     */
    public static function ip_in_range( $ip, $cidr ) {

        if ( strpos( $cidr, '/' ) === false ) {
            return $ip === $cidr;
        }

        list( $subnet, $bits ) = explode( '/', $cidr );

        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask        = -1 << ( 32 - (int) $bits );

        return ( $ip_long & $mask ) === ( $subnet_long & $mask );
    }

    /**
     * =============================================
     * TOKEN & SECURITY HELPERS
     * =============================================
     */

    /**
     * Generate a cryptographically secure random token.
     *
     * @param int $length Token length (default 64).
     * @return string Hex-encoded token.
     */
    public static function generate_token( $length = 64 ) {

        if ( function_exists( 'random_bytes' ) ) {
            return bin2hex( random_bytes( $length / 2 ) );
        }

        if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
            return bin2hex( openssl_random_pseudo_bytes( $length / 2 ) );
        }

        // Fallback (less secure but functional)
        return wp_generate_password( $length, false );
    }

    /**
     * Generate a short verification code (for 2FA / SMS).
     *
     * @param int $digits Number of digits (default 6).
     * @return string Numeric code.
     */
    public static function generate_otp( $digits = 6 ) {

        $min = (int) str_pad( '1', $digits, '0' );
        $max = (int) str_pad( '', $digits, '9' );

        if ( function_exists( 'random_int' ) ) {
            return (string) random_int( $min, $max );
        }

        return (string) wp_rand( $min, $max );
    }

    /**
     * Hash a token for database storage.
     *
     * @param string $token Plain token.
     * @return string Hashed token.
     */
    public static function hash_token( $token ) {
        return wp_hash_password( $token );
    }

    /**
     * Verify a token against its hash.
     *
     * @param string $token       Plain token.
     * @param string $hashed_token Hashed token from DB.
     * @return bool
     */
    public static function verify_token( $token, $hashed_token ) {
        return wp_check_password( $token, $hashed_token );
    }

    /**
     * =============================================
     * DATE & TIME HELPERS
     * =============================================
     */

    /**
     * Get current MySQL datetime in WordPress timezone.
     *
     * @return string MySQL datetime format.
     */
    public static function current_datetime() {
        return current_time( 'mysql' );
    }

    /**
     * Get a future datetime.
     *
     * @param int    $amount Amount of time.
     * @param string $unit   Unit: 'minutes', 'hours', 'days'.
     * @return string MySQL datetime format.
     */
    public static function future_datetime( $amount, $unit = 'minutes' ) {

        $multipliers = [
            'minutes' => MINUTE_IN_SECONDS,
            'hours'   => HOUR_IN_SECONDS,
            'days'    => DAY_IN_SECONDS,
            'weeks'   => WEEK_IN_SECONDS,
        ];

        $seconds = isset( $multipliers[ $unit ] )
            ? $multipliers[ $unit ]
            : MINUTE_IN_SECONDS;

        $timestamp = current_time( 'timestamp' ) + ( $amount * $seconds );

        return gmdate( 'Y-m-d H:i:s', $timestamp );
    }

    /**
     * Check if a datetime has expired.
     *
     * @param string $datetime MySQL datetime string.
     * @return bool True if expired.
     */
    public static function is_expired( $datetime ) {
        return strtotime( $datetime ) < current_time( 'timestamp' );
    }

    /**
     * Format a datetime for display.
     *
     * @param string $datetime MySQL datetime.
     * @param string $format   PHP date format.
     * @return string Formatted date.
     */
    public static function format_datetime( $datetime, $format = '' ) {

        if ( empty( $format ) ) {
            $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        }

        $timestamp = strtotime( $datetime );

        if ( ! $timestamp ) {
            return '—';
        }

        return date_i18n( $format, $timestamp );
    }

    /**
     * Get human-readable time difference.
     *
     * @param string $datetime MySQL datetime.
     * @return string e.g., "5 minutes ago"
     */
    public static function time_ago( $datetime ) {

        $timestamp = strtotime( $datetime );

        if ( ! $timestamp ) {
            return '—';
        }

        return human_time_diff( $timestamp, current_time( 'timestamp' ) )
               . ' ' . __( 'ago', 'hikmah-login' );
    }

    /**
     * =============================================
     * USER HELPERS
     * =============================================
     */

    /**
     * Get user by email, username, or ID.
     *
     * @param string $identifier Email, username, or user ID.
     * @return \WP_User|false
     */
    public static function get_user_by_identifier( $identifier ) {

        // Try by ID first
        if ( is_numeric( $identifier ) ) {
            $user = get_user_by( 'id', absint( $identifier ) );
            if ( $user ) {
                return $user;
            }
        }

        // Try by email
        if ( is_email( $identifier ) ) {
            $user = get_user_by( 'email', $identifier );
            if ( $user ) {
                return $user;
            }
        }

        // Try by username
        $user = get_user_by( 'login', $identifier );
        if ( $user ) {
            return $user;
        }

        return false;
    }

    /**
     * Check if a user's email is verified.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    public static function is_email_verified( $user_id ) {
        $verified = get_user_meta( $user_id, 'hikmah_email_verified', true );
        return 'yes' === $verified;
    }

    /**
     * Mark a user's email as verified.
     *
     * @param int $user_id User ID.
     */
    public static function mark_email_verified( $user_id ) {
        update_user_meta( $user_id, 'hikmah_email_verified', 'yes' );
        update_user_meta( $user_id, 'hikmah_email_verified_at', self::current_datetime() );
    }

    /**
     * Get user display name with fallback.
     *
     * @param int $user_id User ID.
     * @return string
     */
    public static function get_user_display_name( $user_id ) {

        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return __( 'Unknown User', 'hikmah-login' );
        }

        if ( ! empty( $user->display_name ) ) {
            return $user->display_name;
        }

        if ( ! empty( $user->user_login ) ) {
            return $user->user_login;
        }

        return $user->user_email;
    }

    /**
     * =============================================
     * MISCELLANEOUS HELPERS
     * =============================================
     */

    /**
     * Get a plugin option with default fallback.
     *
     * @param string $key     Option key (without 'hikmah_' prefix).
     * @param mixed  $default Default value.
     * @return mixed
     */
    public static function get_option( $key, $default = '' ) {
        return get_option( 'hikmah_' . $key, $default );
    }

    /**
     * Update a plugin option.
     *
     * @param string $key   Option key (without 'hikmah_' prefix).
     * @param mixed  $value Value to save.
     * @return bool
     */
    public static function update_option( $key, $value ) {
        return update_option( 'hikmah_' . $key, $value );
    }

    /**
     * Check if a feature/module is enabled.
     *
     * @param string $feature Feature key (e.g., 'captcha_enabled').
     * @return bool
     */
    public static function is_feature_enabled( $feature ) {
        return 'yes' === self::get_option( $feature, 'no' );
    }

    /**
     * Get the user agent string.
     *
     * @return string
     */
    public static function get_user_agent() {
        if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
        }
        return '';
    }

    /**
     * Get the current page URL.
     *
     * @return string
     */
    public static function get_current_url() {
        global $wp;
        return home_url( add_query_arg( [], $wp->request ) );
    }

    /**
     * Generate a nonce field for forms.
     *
     * @param string $action Nonce action name.
     * @param string $name   Nonce field name.
     * @return string HTML nonce field.
     */
    public static function nonce_field( $action = 'hikmah_login_nonce', $name = 'hikmah_nonce' ) {
        return wp_nonce_field( $action, $name, true, false );
    }

    /**
     * Verify a nonce from request.
     *
     * @param string $action Nonce action.
     * @param string $name   Nonce field name.
     * @return bool
     */
    public static function verify_nonce( $action = 'hikmah_login_nonce', $name = 'hikmah_nonce' ) {

        $nonce = '';

        if ( isset( $_REQUEST[ $name ] ) ) {
            $nonce = sanitize_text_field( wp_unslash( $_REQUEST[ $name ] ) );
        }

        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Send a JSON response and die.
     *
     * @param bool   $success Success status.
     * @param string $message Response message.
     * @param array  $data    Additional data.
     * @param int    $status  HTTP status code.
     */
    public static function send_json( $success, $message, $data = [], $status = 200 ) {

        $response = [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ];

        wp_send_json( $response, $status );
    }

    /**
     * Log a debug message (only in debug mode).
     *
     * @param string $message Log message.
     * @param mixed  $context Additional context data.
     */
    public static function log( $message, $context = [] ) {

        if ( ! HIKMAH_LOGIN_DEBUG && ! defined( 'WP_DEBUG' ) ) {
            return;
        }

        $log_entry = sprintf(
            '[Hikmah Login %s] %s',
            self::current_datetime(),
            $message
        );

        if ( ! empty( $context ) ) {
            $log_entry .= ' | Context: ' . wp_json_encode( $context );
        }

        error_log( $log_entry );
    }
}
