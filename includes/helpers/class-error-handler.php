<?php
/**
 * Error Handler
 *
 * Centralized error and exception handling for the plugin.
 * Provides consistent error logging, user-friendly messages,
 * and debugging support.
 *
 * @package Hikmah_Login
 * @subpackage Helpers
 * @since   1.0.0
 */

namespace Hikmah_Login\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Handler {

    /**
     * Error log file path.
     *
     * @var string
     */
    private static $log_file;

    /**
     * Initialize the error handler.
     */
    public static function init() {

        $upload_dir = wp_upload_dir();
        self::$log_file = $upload_dir['basedir'] . '/hikmah-login/logs/error.log';

        // Register shutdown function for fatal errors
        register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );
    }

    /**
     * Log an error to the plugin log file.
     *
     * @param string $level   Log level (ERROR, WARNING, INFO, DEBUG).
     * @param string $message Error message.
     * @param array  $context Additional context.
     */
    public static function log( $level, $message, $context = [] ) {

        if ( ! HIKMAH_LOGIN_DEBUG && 'DEBUG' === $level ) {
            return;
        }

        $timestamp = gmdate( 'Y-m-d H:i:s' );
        $ip        = Helper::get_client_ip();
        $user_id   = get_current_user_id();

        $log_entry = sprintf(
            "[%s] [%s] [IP: %s] [User: %d] %s",
            $timestamp,
            strtoupper( $level ),
            $ip,
            $user_id,
            $message
        );

        if ( ! empty( $context ) ) {
            $log_entry .= ' | ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES );
        }

        $log_entry .= PHP_EOL;

        // Ensure log directory exists
        $log_dir = dirname( self::$log_file );
        if ( ! file_exists( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
        }

        // Rotate log if too large (> 10MB)
        self::maybe_rotate_log();

        // Append to log file
        file_put_contents( self::$log_file, $log_entry, FILE_APPEND | LOCK_EX );
    }

    /**
     * Log an error.
     *
     * @param string $message Error message.
     * @param array  $context Context data.
     */
    public static function error( $message, $context = [] ) {
        self::log( 'ERROR', $message, $context );
    }

    /**
     * Log a warning.
     *
     * @param string $message Warning message.
     * @param array  $context Context data.
     */
    public static function warning( $message, $context = [] ) {
        self::log( 'WARNING', $message, $context );
    }

    /**
     * Log an info message.
     *
     * @param string $message Info message.
     * @param array  $context Context data.
     */
    public static function info( $message, $context = [] ) {
        self::log( 'INFO', $message, $context );
    }

    /**
     * Log a debug message.
     *
     * @param string $message Debug message.
     * @param array  $context Context data.
     */
    public static function debug( $message, $context = [] ) {
        self::log( 'DEBUG', $message, $context );
    }

    /**
     * Handle a WP_Error object.
     *
     * @param \WP_Error $error   WP_Error instance.
     * @param string    $context Context description.
     * @return string User-friendly error message.
     */
    public static function handle_wp_error( $error, $context = '' ) {

        if ( ! is_wp_error( $error ) ) {
            return '';
        }

        $error_code    = $error->get_error_code();
        $error_message = $error->get_error_message();
        $error_data    = $error->get_error_data();

        // Log the full error
        self::error( "WP_Error in {$context}: [{$error_code}] {$error_message}", [
            'error_data' => $error_data,
        ]);

        // Return user-friendly message
        return self::get_user_friendly_message( $error_code, $context );
    }

    /**
     * Handle a PHP exception.
     *
     * @param \Exception $exception Exception instance.
     * @param string     $context   Context description.
     */
    public static function handle_exception( $exception, $context = '' ) {

        self::error( "Exception in {$context}: " . $exception->getMessage(), [
            'code'  => $exception->getCode(),
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => HIKMAH_LOGIN_DEBUG ? $exception->getTraceAsString() : '(hidden)',
        ]);
    }

    /**
     * Handle shutdown (catch fatal errors).
     */
    public static function handle_shutdown() {

        $error = error_get_last();

        if ( ! $error ) {
            return;
        }

        $fatal_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ];

        if ( in_array( $error['type'], $fatal_types, true ) ) {
            self::error( 'FATAL: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
            ]);
        }
    }

    /**
     * Get a user-friendly error message based on error code.
     *
     * @param string $error_code Error code.
     * @param string $context    Context.
     * @return string
     */
    public static function get_user_friendly_message( $error_code, $context = '' ) {

        $messages = [
            // Login errors
            'invalid_username'     => __( 'Invalid username or password.', 'hikmah-login' ),
            'invalid_email'        => __( 'Invalid email or password.', 'hikmah-login' ),
            'incorrect_password'   => __( 'Invalid username or password.', 'hikmah-login' ),
            'empty_username'       => __( 'Please enter your username or email.', 'hikmah-login' ),
            'empty_password'       => __( 'Please enter your password.', 'hikmah-login' ),
            'account_locked'       => __( 'Your account has been temporarily locked due to too many failed attempts.', 'hikmah-login' ),
            'email_not_verified'   => __( 'Please verify your email address before logging in.', 'hikmah-login' ),

            // Registration errors
            'existing_user_login'  => __( 'This username is already registered.', 'hikmah-login' ),
            'existing_user_email'  => __( 'This email is already registered.', 'hikmah-login' ),
            'registration_disabled' => __( 'Registration is currently disabled.', 'hikmah-login' ),

            // Password errors
            'invalid_key'          => __( 'Invalid or expired password reset link.', 'hikmah-login' ),
            'expired_key'          => __( 'This password reset link has expired.', 'hikmah-login' ),
            'password_mismatch'    => __( 'Passwords do not match.', 'hikmah-login' ),

            // Token errors
            'invalid_token'        => __( 'Invalid or expired verification link.', 'hikmah-login' ),
            'token_expired'        => __( 'This verification link has expired. Please request a new one.', 'hikmah-login' ),

            // 2FA errors
            'invalid_2fa_code'     => __( 'Invalid two-factor authentication code.', 'hikmah-login' ),
            '2fa_required'         => __( 'Two-factor authentication is required.', 'hikmah-login' ),

            // General errors
            'nonce_failed'         => __( 'Security verification failed. Please refresh the page and try again.', 'hikmah-login' ),
            'rate_limited'         => __( 'Too many requests. Please wait a moment and try again.', 'hikmah-login' ),
            'captcha_failed'       => __( 'CAPTCHA verification failed. Please try again.', 'hikmah-login' ),
            'permission_denied'    => __( 'You do not have permission to perform this action.', 'hikmah-login' ),
            'server_error'         => __( 'An internal error occurred. Please try again later.', 'hikmah-login' ),
        ];

        /**
         * Filter user-friendly error messages.
         *
         * @param array  $messages   Error messages.
         * @param string $error_code Current error code.
         */
        $messages = apply_filters( 'hikmah_login_error_messages', $messages, $error_code );

        if ( isset( $messages[ $error_code ] ) ) {
            return $messages[ $error_code ];
        }

        // In debug mode, show the actual error code
        if ( HIKMAH_LOGIN_DEBUG ) {
            return sprintf(
                /* translators: %s: Error code */
                __( 'Error: %s', 'hikmah-login' ),
                $error_code
            );
        }

        return __( 'An unexpected error occurred. Please try again.', 'hikmah-login' );
    }

    /**
     * Rotate log file if it exceeds size limit.
     */
    private static function maybe_rotate_log() {

        if ( ! file_exists( self::$log_file ) ) {
            return;
        }

        $max_size = 10 * 1024 * 1024; // 10MB

        if ( filesize( self::$log_file ) > $max_size ) {
            $backup = self::$log_file . '.' . gmdate( 'Y-m-d-His' ) . '.bak';
            rename( self::$log_file, $backup );

            // Keep only last 3 backups
            $backups = glob( self::$log_file . '.*.bak' );
            if ( $backups && count( $backups ) > 3 ) {
                sort( $backups );
                $to_delete = array_slice( $backups, 0, count( $backups ) - 3 );
                foreach ( $to_delete as $old_backup ) {
                    unlink( $old_backup );
                }
            }
        }
    }

    /**
     * Get the log file contents (for admin display).
     *
     * @param int $lines Number of recent lines to return.
     * @return string Log contents.
     */
    public static function get_recent_logs( $lines = 100 ) {

        if ( ! file_exists( self::$log_file ) ) {
            return __( 'No log entries found.', 'hikmah-login' );
        }

        $file = new \SplFileObject( self::$log_file, 'r' );
        $file->seek( PHP_INT_MAX );
        $total_lines = $file->key();

        $start = max( 0, $total_lines - $lines );
        $file->seek( $start );

        $output = '';
        while ( ! $file->eof() ) {
            $output .= $file->current();
            $file->next();
        }

        return $output;
    }

    /**
     * Clear the log file.
     *
     * @return bool
     */
    public static function clear_logs() {
        if ( file_exists( self::$log_file ) ) {
            return file_put_contents( self::$log_file, '' ) !== false;
        }
        return true;
    }
}
