<?php
/**
 * Security Audit Log
 *
 * Records all security-related events (logins, failures,
 * lockouts, blocklists, registrations, password resets)
 * and provides statistics for the admin dashboard.
 *
 * @package Hikmah_Login
 * @subpackage Security
 * @since   1.0.0
 */

namespace Hikmah_Login\Security;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Database\DB_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Security_Log {

    use Singleton;
    use Hooks;

    /**
     * Database Manager instance.
     *
     * @var DB_Manager
     */
    private $db;

    /**
     * Constructor.
     */
    private function __construct() {

        $this->db = new DB_Manager();

        if ( 'yes' !== get_option( 'hikmah_login_logging_enabled', 'yes' ) ) {
            return;
        }

        // Login success / failure / logout
        $this->add_action( 'hikmah_login_after_login', 'log_successful_login', 20, 1 );
        $this->add_action( 'hikmah_login_after_logout', 'log_logout', 20, 1 );
        $this->add_action( 'wp_login_failed', 'log_failed_login', 5, 2 );

        // Account lifecycle events
        $this->add_action( 'hikmah_register_success', 'log_registration', 20, 2 );
        $this->add_action( 'hikmah_email_verified', 'log_email_verified', 20, 1 );
        $this->add_action( 'hikmah_password_reset_complete', 'log_password_reset', 20, 1 );

        // Security events from Brute_Force
        $this->add_action( 'hikmah_security_lockout', 'log_lockout', 10, 4 );
        $this->add_action( 'hikmah_security_ip_blacklisted', 'log_ip_blacklisted', 10, 2 );
    }

    /**
     * =============================================
     * LOGGING HANDLERS
     * =============================================
     */

    /**
     * Log a successful login.
     *
     * @param \WP_User $user User object.
     */
    public function log_successful_login( $user ) {

        $this->db->log_login_attempt( [
            'user_id'    => $user->ID,
            'username'   => $user->user_login,
            'status'     => 'success',
            'failure_reason' => null,
        ] );
    }

    /**
     * Log a logout.
     *
     * @param int $user_id User ID.
     */
    public function log_logout( $user_id ) {

        $user = get_userdata( (int) $user_id );

        $this->db->log_login_attempt( [
            'user_id'        => $user_id,
            'username'       => $user ? $user->user_login : '',
            'status'         => 'success',
            'failure_reason' => 'logout',
        ] );
    }

    /**
     * Log a failed login attempt.
     *
     * @param string    $username Username.
     * @param \WP_Error $error    Authentication error.
     */
    public function log_failed_login( $username, $error = null ) {

        $status = 'failed';
        $reason = null;

        if ( is_wp_error( $error ) ) {
            $code = $error->get_error_code();
            $reason = $code;

            // Attempts blocked by brute force protection
            if ( in_array( $code, [ 'hikmah_ip_blocked', 'hikmah_ip_locked', 'hikmah_account_locked', 'hikmah_locked' ], true ) ) {
                $status = 'blocked';
                $reason = $code;
            }
        }

        $user = get_user_by( 'login', $username );

        $this->db->log_login_attempt( [
            'user_id'        => $user ? $user->ID : null,
            'username'       => $username,
            'status'         => $status,
            'failure_reason' => $reason,
        ] );
    }

    /**
     * Log a user registration.
     *
     * @param int   $user_id User ID.
     * @param array $data    Registration data.
     */
    public function log_registration( $user_id, $data ) {

        $user = get_userdata( (int) $user_id );

        $this->db->log_login_attempt( [
            'user_id'        => $user_id,
            'username'       => $user ? $user->user_login : ( $data['username'] ?? '' ),
            'status'         => 'success',
            'failure_reason' => 'registration',
        ] );
    }

    /**
     * Log email verification.
     *
     * @param int $user_id User ID.
     */
    public function log_email_verified( $user_id ) {

        $user = get_userdata( (int) $user_id );

        $this->db->log_login_attempt( [
            'user_id'        => $user_id,
            'username'       => $user ? $user->user_login : '',
            'status'         => 'success',
            'failure_reason' => 'email_verified',
        ] );
    }

    /**
     * Log a password reset.
     *
     * @param \WP_User $user User object.
     */
    public function log_password_reset( $user ) {

        $this->db->log_login_attempt( [
            'user_id'        => $user->ID,
            'username'       => $user->user_login,
            'status'         => 'success',
            'failure_reason' => 'password_reset',
        ] );
    }

    /**
     * Log a lockout event.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @param int    $attempts Failed attempt count.
     * @param int    $duration Lockout duration in minutes.
     */
    public function log_lockout( $ip, $username, $attempts, $duration ) {

        if ( ! empty( $username ) && '__ip__' !== $username ) {
            $display_name = $username;
        } else {
            $display_name = 'IP: ' . $ip;
        }

        $this->db->log_login_attempt( [
            'user_id'        => null,
            'username'       => $display_name,
            'status'         => 'locked',
            'failure_reason' => "locked_for_{$duration}min",
        ] );
    }

    /**
     * Log an IP blacklist event.
     *
     * @param string $ip     IP address.
     * @param string $reason Blacklist reason.
     */
    public function log_ip_blacklisted( $ip, $reason ) {

        $this->db->log_login_attempt( [
            'user_id'        => null,
            'username'       => 'IP: ' . $ip,
            'status'         => 'blocked',
            'failure_reason' => 'blacklisted' . ( $reason ? ": {$reason}" : '' ),
        ] );
    }

    /**
     * =============================================
     * QUERIES & STATISTICS
     * =============================================
     */

    /**
     * Get audit log entries (paginated).
     *
     * @param int   $page     Page number.
     * @param int   $per_page Items per page.
     * @param array $filters  Optional filters.
     * @return array
     */
    public function get_logs( $page = 1, $per_page = 20, $filters = [] ) {
        return $this->db->get_login_logs( $page, $per_page, $filters );
    }

    /**
     * Get login/security statistics.
     *
     * @return array
     */
    public function get_stats() {

        global $wpdb;
        $prefix  = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;
        $since_24h = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );

        $success_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$prefix}login_logs`
             WHERE status = 'success' AND login_at > %s AND (failure_reason IS NULL OR failure_reason = '')",
            $since_24h
        ) );

        $failed_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$prefix}login_logs`
             WHERE status IN ('failed','blocked') AND login_at > %s",
            $since_24h
        ) );

        $locked_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$prefix}login_logs`
             WHERE status = 'locked' AND login_at > %s",
            $since_24h
        ) );

        $unique_ips = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) FROM `{$prefix}login_logs` WHERE login_at > %s",
            $since_24h
        ) );

        return [
            'success_24h'   => $success_24h,
            'failed_24h'    => $failed_24h,
            'locked_24h'    => $locked_24h,
            'unique_ips_24h' => $unique_ips,
            'total_events'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$prefix}login_logs`" ),
        ];
    }
}