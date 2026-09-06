<?php
/**
 * Migration System
 *
 * Handles database schema migrations across plugin versions.
 * Ensures smooth upgrades without data loss.
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

class Migration {

    /**
     * Database Manager instance.
     *
     * @var DB_Manager
     */
    private $db;

    /**
     * Current database version.
     *
     * @var string
     */
    private $current_db_version;

    /**
     * Target version (plugin version).
     *
     * @var string
     */
    private $target_version;

    /**
     * Migration registry.
     * Version => callback mapping.
     *
     * @var array
     */
    private $migrations = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->db                 = new DB_Manager();
        $this->current_db_version = get_option( 'hikmah_login_db_version', '0.0.0' );
        $this->target_version     = HIKMAH_LOGIN_VERSION;

        $this->register_migrations();
    }

    /**
     * Register all migration callbacks.
     *
     * Each key is the version that INTRODUCES the migration.
     * Migrations run in order from oldest to newest.
     */
    private function register_migrations() {

        $this->migrations = [
            '1.0.0' => [ $this, 'migrate_to_1_0_0' ],
            // Future migrations:
            // '1.1.0' => [ $this, 'migrate_to_1_1_0' ],
            // '1.2.0' => [ $this, 'migrate_to_1_2_0' ],
            // '2.0.0' => [ $this, 'migrate_to_2_0_0' ],
        ];

        /**
         * Allow third-party migrations.
         *
         * @param array $migrations Migration registry.
         */
        $this->migrations = apply_filters(
            'hikmah_login_migrations',
            $this->migrations
        );

        // Sort by version number
        uksort( $this->migrations, 'version_compare' );
    }

    /**
     * Run pending migrations.
     *
     * Called on 'plugins_loaded' or admin_init.
     *
     * @return bool True if migrations ran, false if none needed.
     */
    public function run() {

        // No migrations needed
        if ( version_compare( $this->current_db_version, $this->target_version, '>=' ) ) {
            return false;
        }

        Helper::log( "Starting migration from {$this->current_db_version} to {$this->target_version}" );

        $ran_count = 0;

        foreach ( $this->migrations as $version => $callback ) {

            // Skip already-applied migrations
            if ( version_compare( $this->current_db_version, $version, '>=' ) ) {
                continue;
            }

            // Skip future migrations (shouldn't happen but safety check)
            if ( version_compare( $version, $this->target_version, '>' ) ) {
                continue;
            }

            Helper::log( "Running migration: {$version}" );

            try {
                $result = call_user_func( $callback );

                if ( false === $result ) {
                    Helper::log( "Migration {$version} FAILED!" );
                    // Don't update version — will retry next load
                    return false;
                }

                // Update version after each successful migration
                update_option( 'hikmah_login_db_version', $version );
                $this->current_db_version = $version;
                $ran_count++;

                Helper::log( "Migration {$version} completed successfully." );

            } catch ( \Exception $e ) {
                Helper::log( "Migration {$version} threw exception: " . $e->getMessage() );
                return false;
            }
        }

        // Final version update
        update_option( 'hikmah_login_db_version', $this->target_version );

        Helper::log( "All migrations complete. {$ran_count} migrations ran." );

        return true;
    }

    /**
     * Check if migrations are needed.
     *
     * @return bool
     */
    public function needs_migration() {
        return version_compare( $this->current_db_version, $this->target_version, '<' );
    }

    /**
     * Get current database version.
     *
     * @return string
     */
    public function get_current_version() {
        return $this->current_db_version;
    }

    /**
     * Get pending migration versions.
     *
     * @return array
     */
    public function get_pending_migrations() {

        $pending = [];

        foreach ( $this->migrations as $version => $callback ) {
            if ( version_compare( $this->current_db_version, $version, '<' )
                && version_compare( $version, $this->target_version, '<=' ) ) {
                $pending[] = $version;
            }
        }

        return $pending;
    }

    /**
     * =============================================
     * INDIVIDUAL MIGRATION METHODS
     * =============================================
     */

    /**
     * Migration to version 1.0.0
     *
     * Initial schema creation (tables already created by Activator,
     * but this ensures they exist for upgrade scenarios).
     *
     * @return bool
     */
    private function migrate_to_1_0_0() {

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Ensure all tables exist (idempotent — IF NOT EXISTS)
        $tables = [
            "CREATE TABLE IF NOT EXISTS {$prefix}login_logs (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED DEFAULT NULL,
                username VARCHAR(255) DEFAULT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT DEFAULT NULL,
                status ENUM('success','failed','blocked','locked') NOT NULL DEFAULT 'failed',
                failure_reason VARCHAR(255) DEFAULT NULL,
                login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_ip_address (ip_address),
                KEY idx_status (status),
                KEY idx_login_at (login_at)
            ) {$charset_collate};",

            "CREATE TABLE IF NOT EXISTS {$prefix}login_attempts (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(255) DEFAULT NULL,
                attempts INT(11) NOT NULL DEFAULT 0,
                locked_until DATETIME DEFAULT NULL,
                first_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_ip_username (ip_address, username),
                KEY idx_locked_until (locked_until)
            ) {$charset_collate};",

            "CREATE TABLE IF NOT EXISTS {$prefix}email_tokens (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                token VARCHAR(255) NOT NULL,
                token_type ENUM('verification','password_reset','2fa') NOT NULL DEFAULT 'verification',
                is_used TINYINT(1) NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_token (token),
                KEY idx_token_type (token_type),
                KEY idx_expires_at (expires_at)
            ) {$charset_collate};",

            "CREATE TABLE IF NOT EXISTS {$prefix}two_factor (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                secret_key VARCHAR(255) NOT NULL,
                backup_codes TEXT DEFAULT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                method ENUM('email','authenticator','sms') NOT NULL DEFAULT 'email',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_user_id (user_id)
            ) {$charset_collate};",

            "CREATE TABLE IF NOT EXISTS {$prefix}social_profiles (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                provider VARCHAR(50) NOT NULL,
                provider_uid VARCHAR(255) NOT NULL,
                profile_data LONGTEXT DEFAULT NULL,
                linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_provider_uid (provider, provider_uid),
                KEY idx_user_id (user_id)
            ) {$charset_collate};",

            "CREATE TABLE IF NOT EXISTS {$prefix}sessions (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                token VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                device_type VARCHAR(20) DEFAULT NULL,
                device_name VARCHAR(100) DEFAULT NULL,
                browser VARCHAR(50) DEFAULT NULL,
                os VARCHAR(50) DEFAULT NULL,
                login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_user_id (user_id),
                KEY idx_token (token),
                KEY idx_last_activity (last_activity)
            ) {$charset_collate};",
        ];

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        // Add default options if missing
        $defaults = [
            'hikmah_login_version'      => HIKMAH_LOGIN_VERSION,
            'hikmah_login_db_version'   => '1.0.0',
            'hikmah_login_installed_at' => Helper::current_datetime(),
        ];

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }

        return true;
    }

    /**
     * =============================================
     * FUTURE MIGRATION EXAMPLES (Commented)
     * =============================================
     */

    /*
    private function migrate_to_1_1_0() {
        global $wpdb;
        $prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;

        // Example: Add a new column
        $column_exists = $wpdb->get_results(
            "SHOW COLUMNS FROM `{$prefix}login_logs` LIKE 'country'"
        );

        if ( empty( $column_exists ) ) {
            $wpdb->query(
                "ALTER TABLE `{$prefix}login_logs`
                 ADD COLUMN `country` VARCHAR(2) DEFAULT NULL AFTER `ip_address`"
            );
        }

        return true;
    }

    private function migrate_to_1_2_0() {
        // Example: Create a new table
        // Example: Migrate data from old format to new
        return true;
    }
    */
}
