<?php
/**
 * Session Manager
 *
 * Advanced session management beyond WordPress's basic
 * session tokens. Handles session tracking, idle timeouts,
 * concurrent session limits, and device/browser/OS detection.
 *
 * @package Hikmah_Login
 * @subpackage Auth
 * @since   1.0.0
 */

namespace Hikmah_Login\Auth;

use Hikmah_Login\Traits\Singleton;
use Hikmah_Login\Traits\Hooks;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Session_Manager {

    use Singleton;
    use Hooks;

    /**
     * Session cleanup interval key.
     */
    const CLEANUP_INTERVAL = 'hikmah_session_cleanup';

    /**
     * Session table name.
     *
     * @var string
     */
    private $table;

    /**
     * Constructor.
     */
    private function __construct() {

        global $wpdb;
        $this->table = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX . 'sessions';

        $this->register_hooks();
    }

    /**
     * Register session-related hooks.
     */
    private function register_hooks() {

        // Register cron interval
        $this->add_filter( 'cron_schedules', 'add_cron_interval' );

        // Schedule cleanup cron
        $this->add_action( 'wp_loaded', 'schedule_cleanup' );
        $this->add_action( 'hikmah_login_cleanup_sessions', 'cleanup_expired_sessions' );

        // Track session on login
        $this->add_action( 'hikmah_login_after_login', 'track_session' );

        // Update session activity
        $this->add_action( 'admin_init', 'update_session_activity' );
        $this->add_action( 'init', 'update_session_activity', 5 );

        // Logout handling
        $this->add_action( 'hikmah_login_after_logout', 'destroy_current_session' );

        // Session validation
        $this->add_filter( 'auth_cookie_valid', 'validate_session', 5, 2 );

        // Admin UI for session management
        $this->add_action( 'admin_menu', 'register_admin_pages' );
    }

    /**
     * =============================================
     * CRON SCHEDULING
     * =============================================
     */

    /**
     * Add custom cron schedule interval.
     *
     * @param array $schedules Cron schedules.
     * @return array
     */
    public function add_cron_interval( $schedules ) {

        $schedules[ self::CLEANUP_INTERVAL ] = [
            'interval' => HOUR_IN_SECONDS,
            'display'  => __( 'Hikmah Login Session Cleanup (hourly)', 'hikmah-login' ),
        ];

        return $schedules;
    }

    /**
     * Schedule the cleanup cron (runs hourly).
     */
    public function schedule_cleanup() {

        if ( ! wp_next_scheduled( 'hikmah_login_cleanup_sessions' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, self::CLEANUP_INTERVAL, 'hikmah_login_cleanup_sessions' );
        }

        // Make sure the cleanup method is available to the cron.
        // The cron fires 'hikmah_login_cleanup_sessions', which we
        // route to cleanup_expired_sessions() in register_hooks().
    }

    /**
     * =============================================
     * SESSION TRACKING
     * =============================================
     */

    /**
     * Track a new session when a user logs in.
     *
     * @param \WP_User $user Authenticated user.
     */
    public function track_session( $user ) {

        // Don't proceed if session tracking is disabled
        if ( ! Helper::is_feature_enabled( 'session_tracking' ) ) {
            return;
        }

        $token = wp_get_session_token();

        if ( empty( $token ) ) {
            return;
        }

        // Get device info
        $device = $this->get_device_info();

        // Check concurrent session limit
        if ( $this->is_concurrent_limit_exceeded( $user->ID ) ) {

            /**
             * Fires when the concurrent session limit is exceeded.
             *
             * @since 1.0.0
             * @param int    $user_id User ID.
             * @param string $token   Session token.
             */
            do_action( 'hikmah_login_session_limit_exceeded', $user->ID, $token );
        }

        $this->insert_session( [
            'user_id'       => $user->ID,
            'token'         => $token,
            'ip_address'    => Helper::get_client_ip(),
            'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] )
                ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
                : '',
            'device_type'   => $device['type'],
            'device_name'   => $device['name'],
            'browser'       => $device['browser'],
            'os'            => $device['os'],
            'login_time'    => Helper::current_datetime(),
            'last_activity' => Helper::current_datetime(),
        ] );
    }

    /**
     * =============================================
     * SESSION ACTIVITY
     * =============================================
     */

    /**
     * Update session activity timestamp on each request.
     */
    public function update_session_activity() {

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            return;
        }

        // Only update if logged in
        $token = wp_get_session_token();

        if ( empty( $token ) ) {
            return;
        }

        // Throttle updates: only update DB every 5 minutes
        $last_update = (int) get_user_meta( $user_id, 'hikmah_last_session_activity_' . $token, true );
        $now = time();

        if ( $last_update && ( $now - $last_update ) < 300 ) {
            return;
        }

        // Update DB
        $this->db_update( [
            'last_activity' => Helper::current_datetime(),
        ], [ 'token' => $token, 'user_id' => $user_id ] );

        // Cache the last update time
        update_user_meta( $user_id, 'hikmah_last_session_activity_' . $token, $now );
    }

    /**
     * =============================================
     * SESSION VALIDATION
     * =============================================
     */

    /**
     * Validate session on auth cookie check.
     *
     * @param bool     $is_valid Whether the session is valid.
     * @param \WP_User $user     User object.
     * @return bool
     */
    public function validate_session( $is_valid, $user ) {

        if ( ! $is_valid || ! $user ) {
            return $is_valid;
        }

        // Check idle timeout
        if ( $this->is_session_idle( $user->ID ) ) {
            $this->destroy_current_session();
            return false;
        }

        return $is_valid;
    }

    /**
     * Check if the current session has exceeded the idle timeout.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    private function is_session_idle( $user_id ) {

        $idle_timeout = (int) get_option( 'hikmah_idle_timeout', 0 );

        // No idle timeout configured (0 = disabled)
        if ( 0 === $idle_timeout ) {
            return false;
        }

        $token = wp_get_session_token();

        if ( empty( $token ) ) {
            return false;
        }

        $session = $this->get_session( $token, $user_id );

        if ( ! $session ) {
            return false;
        }

        $last_activity = strtotime( $session->last_activity );
        $idle_seconds = $idle_timeout * MINUTE_IN_SECONDS;

        return ( time() - $last_activity ) > $idle_seconds;
    }

    /**
     * =============================================
     * CONCURRENT SESSION LIMITS
     * =============================================
     */

    /**
     * Check if the concurrent session limit is exceeded.
     *
     * @param int $user_id User ID.
     * @return bool
     */
    private function is_concurrent_limit_exceeded( $user_id ) {

        $max_sessions = (int) get_option( 'hikmah_max_concurrent_sessions', 0 );

        // No limit configured
        if ( 0 === $max_sessions ) {
            return false;
        }

        $active = $this->get_active_sessions( $user_id );
        $current_token = wp_get_session_token();

        // Count sessions (exclude current token being created)
        $count = 0;
        foreach ( $active as $session ) {
            if ( $session->token !== $current_token ) {
                $count++;
            }
        }

        return $count >= $max_sessions;
    }

    /**
     * Get all active sessions for a user.
     *
     * @param int $user_id User ID.
     * @return array|object|null Session rows.
     */
    public function get_active_sessions( $user_id ) {

        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE user_id = %d
            ORDER BY last_activity DESC",
            $user_id
        );

        return $wpdb->get_results( $sql );
    }

    /**
     * =============================================
     * SESSION DESTRUCTION
     * =============================================
     */

    /**
     * Destroy the current session.
     *
     * @param int $user_id User ID (unused, from hook).
     */
    public function destroy_current_session( $user_id = 0 ) {

        $token = wp_get_session_token();

        if ( ! empty( $token ) ) {
            // Delete from custom table
            $this->db_delete( [ 'token' => $token ] );

            // Also destroy from WordPress session tokens
            if ( $user_id ) {
                $sessions = \WP_Session_Tokens::get_instance( $user_id );
                $sessions->destroy( $token );
            }
        }
    }

    /**
     * Destroy a specific session.
     *
     * @param string $token Session token.
     * @param int    $user_id User ID (for verification).
     * @return bool
     */
    public function destroy_session( $token, $user_id ) {

        if ( empty( $token ) ) {
            return false;
        }

        // Delete from custom table
        $this->db_delete( [ 'token' => $token, 'user_id' => $user_id ] );

        // Destroy in WordPress session tokens
        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $sessions->destroy( $token );

        return true;
    }

    /**
     * Destroy all other sessions except the current one.
     *
     * @param int $user_id User ID.
     * @return int Number of sessions destroyed.
     */
    public function destroy_other_sessions( $user_id ) {

        global $wpdb;

        $current_token = wp_get_session_token();

        // Delete from custom table
        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table}
            WHERE user_id = %d AND token <> %s",
            $user_id,
            $current_token
        ) );

        // Destroy all other WordPress sessions
        $sessions = \WP_Session_Tokens::get_instance( $user_id );
        $sessions->destroy_others( $current_token );

        return (int) $result;
    }

    /**
     * Clean up expired sessions (cron task).
     */
    public function cleanup_expired_sessions() {

        global $wpdb;

        // Delete sessions older than the max session age
        $max_age_days = (int) get_option( 'hikmah_session_max_age', 30 );

        if ( 0 === $max_age_days ) {
            return;
        }

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $max_age_days * DAY_IN_SECONDS ) );

        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table}
            WHERE login_time < %s",
            $cutoff
        ) );

        // Log cleanup
        if ( $deleted > 0 ) {
            Helper::log( "Session cleanup: Removed {$deleted} expired sessions" );
        }

        /**
         * Fires after expired sessions are cleaned up.
         *
         * @since 1.0.0
         * @param int $deleted Number of sessions deleted.
         */
        do_action( 'hikmah_login_sessions_cleaned', (int) $deleted );
    }

    /**
     * =============================================
     * DEVICE DETECTION
     * =============================================
     */

    /**
     * Detect device, browser, and OS information.
     *
     * @return array
     */
    private function get_device_info() {

        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
            : '';

        $result = [
            'type'    => 'undetected',
            'name'    => '',
            'browser' => 'unknown',
            'os'      => 'unknown',
        ];

        if ( empty( $user_agent ) ) {
            return $result;
        }

        // Device type
        if ( preg_match( '/mobile|android|iphone|ipad|ipod|windows phone/i', $user_agent ) ) {
            $result['type'] = 'mobile';
            if ( preg_match( '/iphone/', $user_agent ) ) {
                $result['name'] = 'iPhone';
            } elseif ( preg_match( '/ipad/', $user_agent ) ) {
                $result['name'] = 'iPad';
            } elseif ( preg_match( '/android/', $user_agent ) ) {
                $result['name'] = 'Android Device';
            } elseif ( preg_match( '/windows phone/', $user_agent ) ) {
                $result['name'] = 'Windows Phone';
            }
        } elseif ( preg_match( '/tablet|ipad/i', $user_agent ) ) {
            $result['type'] = 'tablet';
            $result['name'] = 'Tablet';
        } else {
            $result['type'] = 'desktop';
            $result['name'] = 'Desktop';
        }

        // Browser detection
        if ( preg_match( '/edg\//i', $user_agent ) ) {
            $result['browser'] = 'Edge';
        } elseif ( preg_match( '/chrome\//i', $user_agent ) ) {
            $result['browser'] = 'Chrome';
        } elseif ( preg_match( '/firefox\//i', $user_agent ) ) {
            $result['browser'] = 'Firefox';
        } elseif ( preg_match( '/safari\//i', $user_agent ) ) {
            $result['browser'] = 'Safari';
        } elseif ( preg_match( '/opera\//i', $user_agent ) ) {
            $result['browser'] = 'Opera';
        } elseif ( preg_match( '/msie|trident/i', $user_agent ) ) {
            $result['browser'] = 'Internet Explorer';
        }

        // OS detection
        if ( preg_match( '/windows nt (\d+\.\d+)/i', $user_agent, $matches ) ) {
            $versions = [
                '10.0' => 'Windows 10/11',
                '6.3'  => 'Windows 8.1',
                '6.2'  => 'Windows 8',
                '6.1'  => 'Windows 7',
            ];
            $result['os'] = isset( $versions[ $matches[1] ] )
                ? $versions[ $matches[1] ]
                : 'Windows';
        } elseif ( preg_match( '/mac os x (\d+)[._](\d+)/i', $user_agent, $matches ) ) {
            $result['os'] = 'macOS';
        } elseif ( preg_match( '/android (\d+)/i', $user_agent, $matches ) ) {
            $result['os'] = 'Android';
        } elseif ( preg_match( '/iphone os (\d+)/i', $user_agent, $matches ) ) {
            $result['os'] = 'iOS';
        } elseif ( preg_match( '/linux/i', $user_agent ) ) {
            $result['os'] = 'Linux';
        }

        return $result;
    }

    /**
     * =============================================
     * ADMIN UI
     * =============================================
     */

    /**
     * Register admin pages for session management.
     */
    public function register_admin_pages() {

        /**
         * The session management UI is exposed via the
         * main Hikmah Login settings page. Additional
         * per-user session management is handled in the
         * user profile edit screen.
         */
    }

    /**
     * Get a single session row.
     *
     * @param string $token   Session token.
     * @param int    $user_id User ID.
     * @return object|null
     */
    private function get_session( $token, $user_id ) {

        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE token = %s AND user_id = %d
            LIMIT 1",
            $token,
            $user_id
        ) );
    }

    /**
     * Insert a new session row.
     *
     * @param array $data Session data.
     * @return int|false Insert ID or false on error.
     */
    private function insert_session( $data ) {

        global $wpdb;

        return $wpdb->insert( $this->table, $data );
    }

    /**
     * Update a session row.
     *
     * @param array $data  Data to update.
     * @param array $where Where clause.
     * @return int|false
     */
    private function db_update( $data, $where ) {

        global $wpdb;

        return $wpdb->update( $this->table, $data, $where );
    }

    /**
     * Delete session rows.
     *
     * @param array $where Where clause.
     * @return int|false
     */
    private function db_delete( $where ) {

        global $wpdb;

        return $wpdb->delete( $this->table, $where );
    }
}
