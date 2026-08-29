<?php
/**
 * Brute Force Protection
 *
 * Advanced brute force attack prevention with:
 * - Per-IP + per-username attempt tracking
 * - Progressive lockout (increasing duration)
 * - IP blacklist auto-add after repeated offenses
 * - Real-time attempt monitoring
 * - Admin email alerts on suspicious activity
 *
 * @package Hikmah_Login
 * @subpackage Security
 * @since   1.0.0
 */

namespace Hikmah_Login\Security;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;
use Hikmah_Login\Helpers\Error_Handler;
use Hikmah_Login\Database\DB_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Brute_Force {

    use Singleton;
    use Hooks;

    /**
     * Sentinel value used as the username on "per-IP"
     * attempt records (root-level tracking).
     */
    const IP_SENTINEL = '__ip__';

    /**
     * Sentinel value used as the IP on "per-username"
     * attempt records (account-level tracking).
     */
    const USER_SENTINEL = '__user__';

    /**
     * Database Manager instance.
     *
     * @var DB_Manager
     */
    private $db;

    /**
     * Settings cache.
     *
     * @var array
     */
    private $settings = [];

    /**
     * Constructor.
     */
    private function __construct() {
        $this->db = new DB_Manager();
        $this->load_settings();
        $this->register_hooks();
    }

    /**
     * Load security settings.
     */
    private function load_settings() {
        $this->settings = [
            'enabled'             => Helper::is_feature_enabled( 'brute_force_enabled' ),
            'max_attempts'        => (int) get_option( 'hikmah_max_login_attempts', 5 ),
            'lockout_duration'    => (int) get_option( 'hikmah_lockout_duration', 30 ),
            'progressive'         => 'yes' === get_option( 'hikmah_progressive_lockout', 'yes' ),
            'max_multiplier'      => (int) get_option( 'hikmah_max_lockout_multiplier', 8 ),
            'auto_blacklist'      => 'yes' === get_option( 'hikmah_auto_blacklist', 'no' ),
            'blacklist_threshold' => (int) get_option( 'hikmah_blacklist_threshold', 20 ),
            'alert_email'         => get_option( 'hikmah_security_alert_email', get_option( 'admin_email' ) ),
            'alert_enabled'       => 'yes' === get_option( 'hikmah_security_alerts', 'no' ),
            'alert_threshold'     => (int) get_option( 'hikmah_alert_threshold', 10 ),
            'whitelist_enabled'   => 'yes' === get_option( 'hikmah_ip_whitelist_enabled', 'no' ),
        ];

        // Brute force is enabled by default if the option is not stored yet.
        $this->settings['enabled'] = Helper::is_feature_enabled( 'brute_force_enabled' );
        if ( false === get_option( 'hikmah_brute_force_enabled' ) ) {
            $this->settings['enabled'] = true;
        }
    }

    /**
     * Register hooks.
     */
    private function register_hooks() {

        if ( ! $this->settings['enabled'] ) {
            return;
        }

        // Check before authentication
        $this->add_filter( 'authenticate', 'check_brute_force', 5, 3 );

        // Record failed attempts
        $this->add_action( 'wp_login_failed', 'on_login_failed', 10, 2 );

        // Reset on successful login
        $this->add_action( 'wp_login', 'on_login_success', 5, 2 );

        // Cron: cleanup expired lockouts
        $this->add_action( 'hikmah_login_reset_lockouts', 'cleanup_and_check' );
    }

    /**
     * =============================================
     * CORE BRUTE FORCE CHECK
     * =============================================
     */

    /**
     * Check if the current request is a brute force attack.
     *
     * Runs at priority 5 on 'authenticate' — before WordPress
     * even checks the password.
     *
     * @param \WP_User|\WP_Error|null $user     Auth result.
     * @param string                  $username Username.
     * @param string                  $password Password.
     * @return \WP_User|\WP_Error|null
     */
    public function check_brute_force( $user, $username, $password ) {

        // Skip if empty credentials (initial page load)
        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        $ip = Helper::get_client_ip();

        // Check IP whitelist first
        if ( $this->is_ip_whitelisted( $ip ) ) {
            return $user;
        }

        // Check IP blacklist
        if ( $this->is_ip_blacklisted( $ip ) ) {
            Error_Handler::warning( "Blacklisted IP blocked: {$ip}" );

            return new \WP_Error(
                'hikmah_ip_blocked',
                __( '<strong>Access Denied:</strong> Your IP address has been blocked due to suspicious activity.', 'hikmah-login' )
            );
        }

        // Check per-IP lockout
        if ( $this->is_locked( $ip, '' ) ) {
            $remaining = $this->get_lockout_remaining( $ip, '' );

            return new \WP_Error(
                'hikmah_ip_locked',
                sprintf(
                    __( '<strong>Too Many Attempts:</strong> Your IP has been temporarily blocked. Try again in %s.', 'hikmah-login' ),
                    $this->format_remaining_time( $remaining )
                )
            );
        }

        // Check per-username lockout
        if ( ! empty( $username ) && $this->is_locked( '', $username ) ) {
            $remaining = $this->get_lockout_remaining( '', $username );

            return new \WP_Error(
                'hikmah_account_locked',
                sprintf(
                    __( '<strong>Account Locked:</strong> Too many failed attempts. Try again in %s.', 'hikmah-login' ),
                    $this->format_remaining_time( $remaining )
                )
            );
        }

        // Check combined IP + username lockout
        if ( ! empty( $username ) && $this->is_locked( $ip, $username ) ) {
            $remaining = $this->get_lockout_remaining( $ip, $username );

            return new \WP_Error(
                'hikmah_locked',
                sprintf(
                    __( '<strong>Locked:</strong> Try again in %s.', 'hikmah-login' ),
                    $this->format_remaining_time( $remaining )
                )
            );
        }

        return $user;
    }

    /**
     * =============================================
     * ATTEMPT RECORDING
     * =============================================
     */

    /**
     * Handle failed login attempt.
     *
     * @param string    $username Username.
     * @param \WP_Error $error    Authentication error.
     */
    public function on_login_failed( $username, $error = null ) {

        $ip = Helper::get_client_ip();

        // Skip whitelisted IPs
        if ( $this->is_ip_whitelisted( $ip ) ) {
            return;
        }

        // Record attempts at all three levels: IP, username, combined.
        $this->record_attempt( $ip, '' );
        $this->record_attempt( $ip, $username );
        $this->record_attempt( '', $username );

        // Check lockout thresholds for each level.
        $this->check_and_lock( $ip, '' );
        $this->check_and_lock( $ip, $username );
        $this->check_and_lock( '', $username );

        // Check auto-blacklist
        if ( $this->settings['auto_blacklist'] ) {
            $this->check_auto_blacklist( $ip );
        }

        // Check alert threshold
        if ( $this->settings['alert_enabled'] ) {
            $this->check_alert_threshold( $ip, $username );
        }
    }

    /**
     * Normalize an IP/username pair for storage.
     *
     * Empty values are replaced with sentinels so per-IP and
     * per-username records never collide in the unique index
     * (ip_address, username).
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     */
    private function normalize_pair( &$ip, &$username ) {

        if ( empty( $username ) && ! empty( $ip ) ) {
            $username = self::IP_SENTINEL;
        }

        if ( empty( $ip ) && ! empty( $username ) ) {
            $ip = self::USER_SENTINEL;
        }

        $ip       = (string) $ip;
        $username = (string) $username;
    }

    /**
     * Record a single failed attempt.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     */
    private function record_attempt( $ip, $username ) {

        $this->normalize_pair( $ip, $username );

        $existing = $this->db->get_login_attempts( $ip, $username );

        if ( $existing ) {
            $this->db->update( 'login_attempts', [
                'attempts'     => $existing->attempts + 1,
                'last_attempt' => Helper::current_datetime(),
            ], [
                'id' => $existing->id,
            ], [ '%d', '%s' ], [ '%d' ] );
        } else {
            $this->db->insert( 'login_attempts', [
                'ip_address'    => $ip,
                'username'      => $username,
                'attempts'      => 1,
                'first_attempt' => Helper::current_datetime(),
                'last_attempt'  => Helper::current_datetime(),
            ], [ '%s', '%s', '%d', '%s', '%s' ] );
        }
    }

    /**
     * Check and apply lockout if threshold reached.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     */
    private function check_and_lock( $ip, $username ) {

        $this->normalize_pair( $ip, $username );

        $attempts = $this->db->get_login_attempts( $ip, $username );

        if ( ! $attempts ) {
            return;
        }

        $max = $this->settings['max_attempts'];

        if ( $attempts->attempts < $max ) {
            return;
        }

        // Don't re-lock an already locked account (keeps the original expiry).
        if ( $this->db->is_locked( $ip, $username ) ) {
            return;
        }

        // Calculate lockout duration (progressive)
        $duration = $this->calculate_lockout_duration( $attempts->attempts, $max );

        $this->db->lock_account( $ip, $username, $duration );

        Error_Handler::warning( "Account locked: IP {$ip}, User {$username}, Duration {$duration}min" );

        /**
         * Fires when an account/IP is locked.
         *
         * @since 1.0.0
         * @param string $ip       IP address.
         * @param string $username Username.
         * @param int    $attempts Total failed attempts.
         * @param int    $duration Lockout duration in minutes.
         */
        do_action( 'hikmah_security_lockout', $ip, $username, $attempts->attempts, $duration );
    }

    /**
     * Calculate lockout duration with progressive increase.
     *
     * @param int $attempts Current attempt count.
     * @param int $max      Base max attempts.
     * @return int Duration in minutes.
     */
    private function calculate_lockout_duration( $attempts, $max ) {

        $base = $this->settings['lockout_duration'];

        if ( ! $this->settings['progressive'] ) {
            return $base;
        }

        // Progressive: double the duration for each threshold exceeded
        $over       = floor( ( $attempts - $max ) / $max );
        $multiplier = min( pow( 2, $over ), $this->settings['max_multiplier'] );

        return (int) ( $base * $multiplier );
    }

    /**
     * =============================================
     * SUCCESS HANDLER
     * =============================================
     */

    /**
     * Reset attempts on successful login.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       User object.
     */
    public function on_login_success( $user_login, $user ) {

        $ip = Helper::get_client_ip();

        // Reset combined IP + username
        $this->db->reset_attempts( $ip, $user_login );

        // Reset per-IP record
        $this->db->reset_attempts( $ip, self::IP_SENTINEL );

        // Reset per-username record
        $this->db->reset_attempts( self::USER_SENTINEL, $user_login );
    }

    /**
     * =============================================
     * LOCKOUT STATUS
     * =============================================
     */

    /**
     * Check if an IP/username is locked.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return bool
     */
    public function is_locked( $ip, $username ) {
        $this->normalize_pair( $ip, $username );
        return $this->db->is_locked( $ip, $username );
    }

    /**
     * Get remaining lockout time in seconds.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return int
     */
    public function get_lockout_remaining( $ip, $username ) {
        $this->normalize_pair( $ip, $username );
        return $this->db->get_lockout_remaining( $ip, $username );
    }

    /**
     * Format remaining time for display.
     *
     * @param int $seconds Seconds remaining.
     * @return string Human-readable time.
     */
    private function format_remaining_time( $seconds ) {

        if ( $seconds <= 0 ) {
            return __( 'a few seconds', 'hikmah-login' );
        }

        if ( $seconds < 60 ) {
            return sprintf(
                _n( '%d second', '%d seconds', $seconds, 'hikmah-login' ),
                $seconds
            );
        }

        $minutes = ceil( $seconds / 60 );

        if ( $minutes < 60 ) {
            return sprintf(
                _n( '%d minute', '%d minutes', $minutes, 'hikmah-login' ),
                $minutes
            );
        }

        $hours = ceil( $minutes / 60 );

        return sprintf(
            _n( '%d hour', '%d hours', $hours, 'hikmah-login' ),
            $hours
        );
    }

    /**
     * =============================================
     * IP BLACKLIST / WHITELIST
     * =============================================
     */

    /**
     * Check if an IP is blacklisted.
     *
     * @param string $ip IP address.
     * @return bool
     */
    public function is_ip_blacklisted( $ip ) {

        $blacklist = get_option( 'hikmah_ip_blacklist', '' );

        if ( empty( $blacklist ) ) {
            return false;
        }

        return $this->match_ip_list( $ip, $blacklist );
    }

    /**
     * Check if an IP is whitelisted.
     *
     * @param string $ip IP address.
     * @return bool
     */
    public function is_ip_whitelisted( $ip ) {

        if ( ! $this->settings['whitelist_enabled'] ) {
            return false;
        }

        $whitelist = get_option( 'hikmah_ip_whitelist', '' );

        if ( empty( $whitelist ) ) {
            return false;
        }

        return $this->match_ip_list( $ip, $whitelist );
    }

    /**
     * Match an IP against a newline-separated list.
     *
     * Supports: exact IP, CIDR ranges, wildcards.
     *
     * @param string $ip   IP to check.
     * @param string $list Newline-separated IP list.
     * @return bool
     */
    private function match_ip_list( $ip, $list ) {

        $entries = array_filter( array_map( 'trim', explode( "\n", $list ) ) );

        foreach ( $entries as $entry ) {
            // Skip comments
            if ( strpos( $entry, '#' ) === 0 ) {
                continue;
            }

            // Exact match
            if ( $ip === $entry ) {
                return true;
            }

            // CIDR match
            if ( strpos( $entry, '/' ) !== false && Helper::ip_in_range( $ip, $entry ) ) {
                return true;
            }

            // Wildcard match (e.g., 192.168.1.*)
            if ( strpos( $entry, '*' ) !== false ) {
                $pattern = '/^' . str_replace( [ '.', '*' ], [ '\.', '\d+' ], $entry ) . '$/';
                if ( preg_match( $pattern, $ip ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add an IP to the blacklist.
     *
     * @param string $ip     IP address.
     * @param string $reason Reason for blacklisting.
     */
    public function blacklist_ip( $ip, $reason = '' ) {

        $blacklist = get_option( 'hikmah_ip_blacklist', '' );
        $entries   = array_filter( array_map( 'trim', explode( "\n", $blacklist ) ) );

        // Check if already listed
        if ( in_array( $ip, $entries, true ) ) {
            return;
        }

        $entry = $ip;
        if ( ! empty( $reason ) ) {
            $entry .= ' # ' . $reason;
        }

        $entries[] = $entry;
        update_option( 'hikmah_ip_blacklist', implode( "\n", $entries ) );

        Error_Handler::warning( "IP blacklisted: {$ip} — {$reason}" );

        /**
         * Fires when an IP is blacklisted.
         *
         * @since 1.0.0
         * @param string $ip     IP address.
         * @param string $reason Reason for blacklisting.
         */
        do_action( 'hikmah_security_ip_blacklisted', $ip, $reason );
    }

    /**
     * Remove an IP from the blacklist.
     *
     * @param string $ip IP address.
     */
    public function unblacklist_ip( $ip ) {

        $blacklist = get_option( 'hikmah_ip_blacklist', '' );
        $entries   = array_filter( array_map( 'trim', explode( "\n", $blacklist ) ) );

        $entries = array_filter( $entries, function( $entry ) use ( $ip ) {
            return strpos( $entry, $ip ) !== 0;
        });

        update_option( 'hikmah_ip_blacklist', implode( "\n", $entries ) );
    }

    /**
     * =============================================
     * AUTO-BLACKLIST
     * =============================================
     */

    /**
     * Check if an IP should be auto-blacklisted.
     *
     * @param string $ip IP address.
     */
    private function check_auto_blacklist( $ip ) {

        $threshold = $this->settings['blacklist_threshold'];

        // Count total failed attempts from this IP in last 24 hours
        global $wpdb;
        $prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;
        $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(attempts), 0) FROM `{$prefix}login_attempts`
             WHERE ip_address = %s AND last_attempt > %s",
            $ip, $cutoff
        ));

        if ( $count >= $threshold ) {
            $this->blacklist_ip( $ip, "Auto-blacklisted: {$count} failed attempts in 24h" );
        }
    }

    /**
     * =============================================
     * ALERTS
     * =============================================
     */

    /**
     * Check if alert threshold is reached.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     */
    private function check_alert_threshold( $ip, $username ) {

        $threshold = $this->settings['alert_threshold'];
        $attempts  = $this->db->get_login_attempts( $ip, $username );

        if ( ! $attempts || $attempts->attempts !== $threshold ) {
            return; // Only alert once at exact threshold
        }

        $this->send_security_alert( $ip, $username, $attempts->attempts );
    }

    /**
     * Send security alert email.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @param int    $attempts Attempt count.
     */
    private function send_security_alert( $ip, $username, $attempts ) {

        $site_name = get_bloginfo( 'name' );
        $admin_url = admin_url( 'admin.php?page=hikmah-login-security' );

        $subject = sprintf(
            __( '[%s] Security Alert: Brute Force Attack Detected', 'hikmah-login' ),
            $site_name
        );

        $message = sprintf(
            __(
                "Security Alert for %s\n\n" .
                "A potential brute force attack has been detected:\n\n" .
                "IP Address: %s\n" .
                "Target Username: %s\n" .
                "Failed Attempts: %d\n" .
                "Time: %s\n\n" .
                "Action taken: Account/IP temporarily locked.\n\n" .
                "Review security logs: %s\n",
                'hikmah-login'
            ),
            $site_name,
            $ip,
            $username ?: 'N/A',
            $attempts,
            Helper::format_datetime( Helper::current_datetime() ),
            $admin_url
        );

        wp_mail( $this->settings['alert_email'], $subject, $message );
    }

    /**
     * =============================================
     * CLEANUP
     * =============================================
     */

    /**
     * Cleanup expired lockouts.
     */
    public function cleanup_and_check() {

        $this->db->cleanup_expired_lockouts();

        Helper::log( 'Brute force: Expired lockouts cleaned up.' );
    }

    /**
     * =============================================
     * PUBLIC API
     * =============================================
     */

    /**
     * Get brute force statistics.
     *
     * @return array
     */
    public function get_stats() {

        global $wpdb;
        $prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;
        $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );

        $total_locked = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$prefix}login_attempts` WHERE locked_until IS NOT NULL AND locked_until > %s",
            Helper::current_datetime()
        ));

        $total_attempts_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(attempts), 0) FROM `{$prefix}login_attempts` WHERE last_attempt > %s",
            $cutoff
        ));

        $unique_ips_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) FROM `{$prefix}login_attempts` WHERE last_attempt > %s",
            $cutoff
        ));

        $blacklist_count = count( array_filter( explode( "\n", get_option( 'hikmah_ip_blacklist', '' ) ) ) );

        return [
            'currently_locked' => $total_locked,
            'attempts_24h'     => $total_attempts_24h,
            'unique_ips_24h'   => $unique_ips_24h,
            'blacklisted_ips'  => $blacklist_count,
        ];
    }
}