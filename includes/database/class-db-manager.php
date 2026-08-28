<?php
/**
 * Database Manager
 *
 * Centralized database operations for all Hikmah Login tables.
 * Provides safe CRUD operations with prepared statements.
 *
 * @package Hikmah_Login
 * @subpackage Database
 * @since   1.0.0
 */

namespace Hikmah_Login\Database;

use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DB_Manager {

    /**
     * WordPress database object.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Table prefix for this plugin.
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb   = $wpdb;
        $this->prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;
    }

    /**
     * Get full table name.
     *
     * @param string $table Short table name.
     * @return string Full table name with prefix.
     */
    public function table( $table ) {
        return $this->prefix . $table;
    }

    /**
     * =============================================
     * GENERIC CRUD OPERATIONS
     * =============================================
     */

    /**
     * Insert a row into a table.
     *
     * @param string $table  Short table name.
     * @param array  $data   Column => Value pairs.
     * @param array  $format Data format (%s, %d, %f).
     * @return int|false Insert ID or false on failure.
     */
    public function insert( $table, $data, $format = null ) {

        $result = $this->wpdb->insert(
            $this->table( $table ),
            $data,
            $format
        );

        if ( false === $result ) {
            Helper::log( 'DB Insert Failed', [
                'table' => $table,
                'error' => $this->wpdb->last_error,
            ]);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update rows in a table.
     *
     * @param string $table  Short table name.
     * @param array  $data   Column => Value pairs to update.
     * @param array  $where  WHERE conditions.
     * @param array  $format Data format.
     * @param array  $where_format WHERE format.
     * @return int|false Number of rows updated or false.
     */
    public function update( $table, $data, $where, $format = null, $where_format = null ) {

        $result = $this->wpdb->update(
            $this->table( $table ),
            $data,
            $where,
            $format,
            $where_format
        );

        if ( false === $result ) {
            Helper::log( 'DB Update Failed', [
                'table' => $table,
                'error' => $this->wpdb->last_error,
            ]);
        }

        return $result;
    }

    /**
     * Delete rows from a table.
     *
     * @param string $table  Short table name.
     * @param array  $where  WHERE conditions.
     * @param array  $format WHERE format.
     * @return int|false Number of rows deleted or false.
     */
    public function delete( $table, $where, $format = null ) {

        $result = $this->wpdb->delete(
            $this->table( $table ),
            $where,
            $format
        );

        if ( false === $result ) {
            Helper::log( 'DB Delete Failed', [
                'table' => $table,
                'error' => $this->wpdb->last_error,
            ]);
        }

        return $result;
    }

    /**
     * Get a single row from a table.
     *
     * @param string $table  Short table name.
     * @param array  $where  WHERE conditions.
     * @param string $output Output type (OBJECT, ARRAY_A, ARRAY_N).
     * @return object|array|null
     */
    public function get_row( $table, $where, $output = OBJECT ) {

        $conditions = [];
        $values     = [];

        foreach ( $where as $column => $value ) {
            $conditions[] = "`{$column}` = %s";
            $values[]     = $value;
        }

        $where_clause = implode( ' AND ', $conditions );

        $query = $this->wpdb->prepare(
            "SELECT * FROM `{$this->table($table)}` WHERE {$where_clause} LIMIT 1",
            $values
        );

        return $this->wpdb->get_row( $query, $output );
    }

    /**
     * Get multiple rows from a table.
     *
     * @param string $table   Short table name.
     * @param array  $args    Query arguments.
     * @return array
     */
    public function get_results( $table, $args = [] ) {

        $defaults = [
            'where'   => [],
            'orderby' => 'id',
            'order'   => 'DESC',
            'limit'   => 20,
            'offset'  => 0,
            'output'  => OBJECT,
        ];

        $args = wp_parse_args( $args, $defaults );

        // Build WHERE clause
        $where_clause = '1=1';
        $values       = [];

        if ( ! empty( $args['where'] ) ) {
            $conditions = [];
            foreach ( $args['where'] as $column => $value ) {
                if ( is_array( $value ) ) {
                    // IN clause
                    $placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
                    $conditions[] = "`{$column}` IN ({$placeholders})";
                    $values       = array_merge( $values, $value );
                } else {
                    $conditions[] = "`{$column}` = %s";
                    $values[]     = $value;
                }
            }
            $where_clause = implode( ' AND ', $conditions );
        }

        // Sanitize ORDER BY
        $allowed_orderby = [ 'id', 'created_at', 'login_at', 'last_attempt', 'expires_at' ];
        $orderby = in_array( $args['orderby'], $allowed_orderby, true )
            ? $args['orderby']
            : 'id';

        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        $limit  = absint( $args['limit'] );
        $offset = absint( $args['offset'] );

        // Build query
        $query = "SELECT * FROM `{$this->table($table)}` WHERE {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        $prepared = $this->wpdb->prepare( $query, $values );

        return $this->wpdb->get_results( $prepared, $args['output'] );
    }

    /**
     * Count rows in a table.
     *
     * @param string $table Short table name.
     * @param array  $where WHERE conditions.
     * @return int
     */
    public function count( $table, $where = [] ) {

        if ( empty( $where ) ) {
            $query = "SELECT COUNT(*) FROM `{$this->table($table)}`";
            return (int) $this->wpdb->get_var( $query );
        }

        $conditions = [];
        $values     = [];

        foreach ( $where as $column => $value ) {
            $conditions[] = "`{$column}` = %s";
            $values[]     = $value;
        }

        $where_clause = implode( ' AND ', $conditions );

        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->table($table)}` WHERE {$where_clause}",
            $values
        );

        return (int) $this->wpdb->get_var( $query );
    }

    /**
     * =============================================
     * LOGIN LOGS OPERATIONS
     * =============================================
     */

    /**
     * Log a login attempt.
     *
     * @param array $data Log data.
     * @return int|false Insert ID.
     */
    public function log_login_attempt( $data ) {

        if ( ! Helper::is_feature_enabled( 'login_logging_enabled' ) ) {
            return false;
        }

        return $this->insert( 'login_logs', [
            'user_id'        => $data['user_id'] ?? null,
            'username'       => $data['username'] ?? '',
            'ip_address'     => Helper::get_client_ip(),
            'user_agent'     => Helper::get_user_agent(),
            'status'         => $data['status'] ?? 'failed',
            'failure_reason' => $data['failure_reason'] ?? null,
            'login_at'       => Helper::current_datetime(),
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
    }

    /**
     * Get login logs with pagination.
     *
     * @param int $page     Page number.
     * @param int $per_page Items per page.
     * @param array $filters Optional filters.
     * @return array ['items' => [], 'total' => int, 'pages' => int]
     */
    public function get_login_logs( $page = 1, $per_page = 20, $filters = [] ) {

        $offset = ( $page - 1 ) * $per_page;

        $where = [];

        if ( ! empty( $filters['status'] ) ) {
            $where['status'] = $filters['status'];
        }

        if ( ! empty( $filters['user_id'] ) ) {
            $where['user_id'] = $filters['user_id'];
        }

        if ( ! empty( $filters['ip_address'] ) ) {
            $where['ip_address'] = $filters['ip_address'];
        }

        $items = $this->get_results( 'login_logs', [
            'where'   => $where,
            'orderby' => 'login_at',
            'order'   => 'DESC',
            'limit'   => $per_page,
            'offset'  => $offset,
        ] );

        $total = $this->count( 'login_logs', $where );

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
        ];
    }

    /**
     * Delete old login logs.
     *
     * @param int $days Retention days.
     * @return int|false Number of deleted rows.
     */
    public function cleanup_old_logs( $days = 90 ) {

        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

        $query = $this->wpdb->prepare(
            "DELETE FROM `{$this->table('login_logs')}` WHERE `login_at` < %s",
            $cutoff
        );

        $result = $this->wpdb->query( $query );

        if ( false !== $result ) {
            Helper::log( "Cleaned up {$result} old login logs (older than {$days} days)." );
        }

        return $result;
    }

    /**
     * =============================================
     * LOGIN ATTEMPTS OPERATIONS (Brute Force)
     * =============================================
     */

    /**
     * Get login attempts for an IP/username combo.
     *
     * @param string $ip       IP address.
     * @param string $username Username (optional).
     * @return object|null Attempt record.
     */
    public function get_login_attempts( $ip, $username = '' ) {

        $where = [ 'ip_address' => $ip ];

        if ( ! empty( $username ) ) {
            $where['username'] = $username;
        }

        return $this->get_row( 'login_attempts', $where );
    }

    /**
     * Record a failed login attempt.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return void
     */
    public function record_failed_attempt( $ip, $username ) {

        $existing = $this->get_login_attempts( $ip, $username );

        if ( $existing ) {
            // Increment attempts
            $this->update( 'login_attempts', [
                'attempts'     => $existing->attempts + 1,
                'last_attempt' => Helper::current_datetime(),
            ], [
                'id' => $existing->id,
            ], [ '%d', '%s' ], [ '%d' ] );
        } else {
            // Create new record
            $this->insert( 'login_attempts', [
                'ip_address'    => $ip,
                'username'      => $username,
                'attempts'      => 1,
                'first_attempt' => Helper::current_datetime(),
                'last_attempt'  => Helper::current_datetime(),
            ], [ '%s', '%s', '%d', '%s', '%s' ] );
        }
    }

    /**
     * Lock an IP/username after too many attempts.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @param int    $minutes  Lockout duration in minutes.
     * @return void
     */
    public function lock_account( $ip, $username, $minutes = 30 ) {

        $locked_until = Helper::future_datetime( $minutes, 'minutes' );

        $existing = $this->get_login_attempts( $ip, $username );

        if ( $existing ) {
            $this->update( 'login_attempts', [
                'locked_until' => $locked_until,
            ], [
                'id' => $existing->id,
            ], [ '%s' ], [ '%d' ] );
        }
    }

    /**
     * Check if an IP/username is currently locked.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return bool
     */
    public function is_locked( $ip, $username = '' ) {

        $attempt = $this->get_login_attempts( $ip, $username );

        if ( ! $attempt || empty( $attempt->locked_until ) ) {
            return false;
        }

        return ! Helper::is_expired( $attempt->locked_until );
    }

    /**
     * Get remaining lockout time in seconds.
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return int Seconds remaining (0 if not locked).
     */
    public function get_lockout_remaining( $ip, $username = '' ) {

        $attempt = $this->get_login_attempts( $ip, $username );

        if ( ! $attempt || empty( $attempt->locked_until ) ) {
            return 0;
        }

        $remaining = strtotime( $attempt->locked_until ) - current_time( 'timestamp' );

        return max( 0, $remaining );
    }

    /**
     * Reset login attempts (after successful login or lockout expiry).
     *
     * @param string $ip       IP address.
     * @param string $username Username.
     * @return void
     */
    public function reset_attempts( $ip, $username = '' ) {

        $where = [ 'ip_address' => $ip ];

        if ( ! empty( $username ) ) {
            $where['username'] = $username;
        }

        $this->delete( 'login_attempts', $where );
    }

    /**
     * Clean up expired lockouts.
     *
     * @return int|false Number of deleted rows.
     */
    public function cleanup_expired_lockouts() {

        $now = Helper::current_datetime();

        $query = $this->wpdb->prepare(
            "DELETE FROM `{$this->table('login_attempts')}` WHERE `locked_until` IS NOT NULL AND `locked_until` < %s",
            $now
        );

        return $this->wpdb->query( $query );
    }

    /**
     * =============================================
     * EMAIL TOKEN OPERATIONS
     * =============================================
     */

    /**
     * Create a new email token.
     *
     * @param int    $user_id    User ID.
     * @param string $token_type Token type (verification, password_reset, 2fa).
     * @param int    $expires_in Expiration in minutes.
     * @return string The plain token (for sending via email).
     */
    public function create_email_token( $user_id, $token_type = 'verification', $expires_in = 60 ) {

        // Delete old tokens of same type for this user
        $this->delete( 'email_tokens', [
            'user_id'    => $user_id,
            'token_type' => $token_type,
        ] );

        // Generate new token
        $plain_token  = Helper::generate_token( 64 );
        $hashed_token = Helper::hash_token( $plain_token );

        $this->insert( 'email_tokens', [
            'user_id'    => $user_id,
            'token'      => $hashed_token,
            'token_type' => $token_type,
            'is_used'    => 0,
            'expires_at' => Helper::future_datetime( $expires_in, 'minutes' ),
            'created_at' => Helper::current_datetime(),
        ], [ '%d', '%s', '%s', '%d', '%s', '%s' ] );

        return $plain_token;
    }

    /**
     * Verify an email token.
     *
     * @param int    $user_id    User ID.
     * @param string $token      Plain token from email link.
     * @param string $token_type Expected token type.
     * @return bool
     */
    public function verify_email_token( $user_id, $token, $token_type = 'verification' ) {

        $record = $this->get_row( 'email_tokens', [
            'user_id'    => $user_id,
            'token_type' => $token_type,
            'is_used'    => 0,
        ] );

        if ( ! $record ) {
            return false;
        }

        // Check expiration
        if ( Helper::is_expired( $record->expires_at ) ) {
            return false;
        }

        // Verify token hash
        if ( ! Helper::verify_token( $token, $record->token ) ) {
            return false;
        }

        // Mark as used
        $this->update( 'email_tokens', [
            'is_used' => 1,
        ], [
            'id' => $record->id,
        ], [ '%d' ], [ '%d' ] );

        return true;
    }

    /**
     * Clean up expired tokens.
     *
     * @return int|false Number of deleted rows.
     */
    public function cleanup_expired_tokens() {

        $now = Helper::current_datetime();

        $query = $this->wpdb->prepare(
            "DELETE FROM `{$this->table('email_tokens')}` WHERE `expires_at` < %s OR `is_used` = 1",
            $now
        );

        return $this->wpdb->query( $query );
    }

    /**
     * =============================================
     * TWO-FACTOR OPERATIONS
     * =============================================
     */

    /**
     * Get 2FA settings for a user.
     *
     * @param int $user_id User ID.
     * @return object|null
     */
    public function get_2fa_settings( $user_id ) {
        return $this->get_row( 'two_factor', [ 'user_id' => $user_id ] );
    }

    /**
     * Save or update 2FA settings.
     *
     * @param int    $user_id    User ID.
     * @param string $secret_key Secret key.
     * @param string $method     2FA method.
     * @param string $backup_codes JSON-encoded backup codes.
     * @return bool
     */
    public function save_2fa_settings( $user_id, $secret_key, $method = 'email', $backup_codes = '' ) {

        $existing = $this->get_2fa_settings( $user_id );

        if ( $existing ) {
            $result = $this->update( 'two_factor', [
                'secret_key'   => $secret_key,
                'method'       => $method,
                'backup_codes' => $backup_codes,
                'updated_at'   => Helper::current_datetime(),
            ], [
                'user_id' => $user_id,
            ] );

            return false !== $result;
        }

        $result = $this->insert( 'two_factor', [
            'user_id'      => $user_id,
            'secret_key'   => $secret_key,
            'backup_codes' => $backup_codes,
            'is_enabled'   => 0,
            'method'       => $method,
        ] );

        return false !== $result;
    }

    /**
     * Enable or disable 2FA for a user.
     *
     * @param int  $user_id User ID.
     * @param bool $enable  Enable or disable.
     * @return bool
     */
    public function toggle_2fa( $user_id, $enable = true ) {

        $result = $this->update( 'two_factor', [
            'is_enabled' => $enable ? 1 : 0,
        ], [
            'user_id' => $user_id,
        ], [ '%d' ], [ '%d' ] );

        return false !== $result;
    }

    /**
     * =============================================
     * SOCIAL LOGIN OPERATIONS
     * =============================================
     */

    /**
     * Link a social profile to a WordPress user.
     *
     * @param int    $user_id      User ID.
     * @param string $provider     Social provider (google, facebook).
     * @param string $provider_uid Provider's unique user ID.
     * @param array  $profile_data Additional profile data.
     * @return int|false Insert ID.
     */
    public function link_social_profile( $user_id, $provider, $provider_uid, $profile_data = [] ) {

        return $this->insert( 'social_profiles', [
            'user_id'      => $user_id,
            'provider'     => $provider,
            'provider_uid' => $provider_uid,
            'profile_data' => wp_json_encode( $profile_data ),
            'linked_at'    => Helper::current_datetime(),
        ] );
    }

    /**
     * Find a WordPress user by social profile.
     *
     * @param string $provider     Social provider.
     * @param string $provider_uid Provider's unique user ID.
     * @return int|false User ID or false.
     */
    public function get_user_by_social( $provider, $provider_uid ) {

        $record = $this->get_row( 'social_profiles', [
            'provider'     => $provider,
            'provider_uid' => $provider_uid,
        ] );

        return $record ? (int) $record->user_id : false;
    }

    /**
     * Unlink a social profile.
     *
     * @param int    $user_id  User ID.
     * @param string $provider Social provider.
     * @return int|false
     */
    public function unlink_social_profile( $user_id, $provider ) {
        return $this->delete( 'social_profiles', [
            'user_id'  => $user_id,
            'provider' => $provider,
        ] );
    }

    /**
     * =============================================
     * UTILITY METHODS
     * =============================================
     */

    /**
     * Check if a table exists.
     *
     * @param string $table Short table name.
     * @return bool
     */
    public function table_exists( $table ) {
        $full_name = $this->table( $table );
        $query     = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $full_name );
        return $this->wpdb->get_var( $query ) === $full_name;
    }

    /**
     * Get table row count (quick).
     *
     * @param string $table Short table name.
     * @return int
     */
    public function get_table_size( $table ) {
        return $this->count( $table );
    }

    /**
     * Run a raw prepared query (use with caution).
     *
     * @param string $query  SQL query with placeholders.
     * @param array  $values Values for placeholders.
     * @return array|object|null
     */
    public function raw_query( $query, $values = [] ) {

        if ( ! empty( $values ) ) {
            $query = $this->wpdb->prepare( $query, $values );
        }

        return $this->wpdb->get_results( $query );
    }

    /**
     * Get the last database error.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->wpdb->last_error;
    }
}
