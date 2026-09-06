<?php
/**
 * Plugin Activator
 *
 * Handles everything that needs to happen
 * when the plugin is activated.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    /**
     * Run activation tasks.
     *
     * Called via register_activation_hook().
     *
     * @param bool $network_wide Whether plugin is activated
     *                           network-wide on multisite.
     */
    public static function activate( $network_wide = false ) {

        // Multisite support
        if ( is_multisite() && $network_wide ) {
            self::activate_for_network();
        } else {
            self::activate_single_site();
        }
    }

    /**
     * Activate for a single site.
     */
    private static function activate_single_site() {
        self::check_requirements();
        self::create_database_tables();
        self::create_default_pages();
        self::set_default_options();
        self::assign_capabilities();
        self::schedule_cron_events();
        self::create_upload_directories();
        self::set_activation_flag();

        // Flush rewrite rules (for custom endpoints)
        flush_rewrite_rules();
    }

    /**
     * Activate for multisite network.
     */
    private static function activate_for_network() {
        global $wpdb;

        $blog_ids = $wpdb->get_col(
            "SELECT blog_id FROM {$wpdb->blogs} WHERE archived = 0 AND deleted = 0"
        );

        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            self::activate_single_site();
            restore_current_blog();
        }
    }

    /**
     * Check system requirements before activation.
     */
    private static function check_requirements() {

        $errors = [];

        // Check PHP version
        if ( version_compare( PHP_VERSION, HIKMAH_LOGIN_MIN_PHP, '<' ) ) {
            $errors[] = sprintf(
                'PHP %s or higher required. Current: %s',
                HIKMAH_LOGIN_MIN_PHP,
                PHP_VERSION
            );
        }

        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), HIKMAH_LOGIN_MIN_WP, '<' ) ) {
            $errors[] = sprintf(
                'WordPress %s or higher required. Current: %s',
                HIKMAH_LOGIN_MIN_WP,
                get_bloginfo( 'version' )
            );
        }

        // Check required PHP extensions
        $required_extensions = [ 'json', 'curl', 'mbstring', 'openssl' ];
        foreach ( $required_extensions as $ext ) {
            if ( ! extension_loaded( $ext ) ) {
                $errors[] = sprintf( 'PHP extension "%s" is required.', $ext );
            }
        }

        // If errors, deactivate and show message
        if ( ! empty( $errors ) ) {
            deactivate_plugins( HIKMAH_LOGIN_BASENAME );
            $error_message = '<strong>Hikmah Login Activation Failed:</strong><br>'
                           . implode( '<br>', $errors );
            wp_die(
                wp_kses_post( $error_message ),
                'Activation Error',
                [ 'back_link' => true ]
            );
        }
    }

    /**
     * Create custom database tables.
     */
    private static function create_database_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . HIKMAH_LOGIN_DB_PREFIX;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ✅ Table 1: Login Logs
        $table_login_logs = $prefix . 'login_logs';
        $sql_login_logs = "CREATE TABLE IF NOT EXISTS {$table_login_logs} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED DEFAULT NULL,
            username        VARCHAR(255) DEFAULT NULL,
            ip_address      VARCHAR(45) NOT NULL,
            user_agent      TEXT DEFAULT NULL,
            status          ENUM('success','failed','blocked','locked') NOT NULL DEFAULT 'failed',
            failure_reason  VARCHAR(255) DEFAULT NULL,
            login_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_ip_address (ip_address),
            KEY idx_status (status),
            KEY idx_login_at (login_at)
        ) {$charset_collate};";

        dbDelta( $sql_login_logs );

        // ✅ Table 2: Login Attempts (Brute Force Tracking)
        $table_attempts = $prefix . 'login_attempts';
        $sql_attempts = "CREATE TABLE IF NOT EXISTS {$table_attempts} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address      VARCHAR(45) NOT NULL,
            username        VARCHAR(255) DEFAULT NULL,
            attempts        INT(11) NOT NULL DEFAULT 0,
            locked_until    DATETIME DEFAULT NULL,
            first_attempt   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_attempt    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_ip_username (ip_address, username),
            KEY idx_locked_until (locked_until)
        ) {$charset_collate};";

        dbDelta( $sql_attempts );

        // ✅ Table 3: Email Verification Tokens
        $table_verification = $prefix . 'email_tokens';
        $sql_verification = "CREATE TABLE IF NOT EXISTS {$table_verification} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED NOT NULL,
            token           VARCHAR(255) NOT NULL,
            token_type      ENUM('verification','password_reset','2fa') NOT NULL DEFAULT 'verification',
            is_used         TINYINT(1) NOT NULL DEFAULT 0,
            expires_at      DATETIME NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_token (token),
            KEY idx_token_type (token_type),
            KEY idx_expires_at (expires_at)
        ) {$charset_collate};";

        dbDelta( $sql_verification );

        // ✅ Table 4: Two-Factor Auth Secrets
        $table_2fa = $prefix . 'two_factor';
        $sql_2fa = "CREATE TABLE IF NOT EXISTS {$table_2fa} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED NOT NULL,
            secret_key      VARCHAR(255) NOT NULL,
            backup_codes    TEXT DEFAULT NULL,
            is_enabled      TINYINT(1) NOT NULL DEFAULT 0,
            method          ENUM('email','authenticator','sms') NOT NULL DEFAULT 'email',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_user_id (user_id)
        ) {$charset_collate};";

        dbDelta( $sql_2fa );

        // ✅ Table 5: Social Login Profiles
        $table_social = $prefix . 'social_profiles';
        $sql_social = "CREATE TABLE IF NOT EXISTS {$table_social} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED NOT NULL,
            provider        VARCHAR(50) NOT NULL,
            provider_uid    VARCHAR(255) NOT NULL,
            profile_data    LONGTEXT DEFAULT NULL,
            linked_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_provider_uid (provider, provider_uid),
            KEY idx_user_id (user_id)
        ) {$charset_collate};";

        dbDelta( $sql_social );

        // ✅ Table 6: User Sessions
        $table_sessions = $prefix . 'sessions';
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$table_sessions} (
            id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT(20) UNSIGNED NOT NULL,
            token           VARCHAR(255) NOT NULL,
            ip_address      VARCHAR(45) DEFAULT NULL,
            user_agent      TEXT DEFAULT NULL,
            device_type     VARCHAR(20) DEFAULT NULL,
            device_name     VARCHAR(100) DEFAULT NULL,
            browser         VARCHAR(50) DEFAULT NULL,
            os              VARCHAR(50) DEFAULT NULL,
            login_time      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_activity   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_token (token),
            KEY idx_last_activity (last_activity)
        ) {$charset_collate};";

        dbDelta( $sql_sessions );

        // Save database version
        update_option( 'hikmah_login_db_version', HIKMAH_LOGIN_VERSION );
    }

    /**
     * Create default pages (Login, Register, etc.)
     */
    private static function create_default_pages() {

        $pages = [
            'login'           => [
                'title'   => __( 'Login', 'hikmah-login' ),
                'content' => '[hikmah_login]',
                'option'  => 'hikmah_login_page_id',
            ],
            'register'        => [
                'title'   => __( 'Register', 'hikmah-login' ),
                'content' => '[hikmah_register]',
                'option'  => 'hikmah_register_page_id',
            ],
            'forgot_password' => [
                'title'   => __( 'Forgot Password', 'hikmah-login' ),
                'content' => '[hikmah_forgot_password]',
                'option'  => 'hikmah_forgot_password_page_id',
            ],
            'reset_password'  => [
                'title'   => __( 'Reset Password', 'hikmah-login' ),
                'content' => '[hikmah_reset_password]',
                'option'  => 'hikmah_reset_password_page_id',
            ],
            'user_dashboard'  => [
                'title'   => __( 'My Account', 'hikmah-login' ),
                'content' => '[hikmah_dashboard]',
                'option'  => 'hikmah_dashboard_page_id',
            ],
        ];

        foreach ( $pages as $slug => $page_data ) {

            // Check if page already exists
            $existing_page_id = get_option( $page_data['option'] );

            if ( $existing_page_id && get_post_status( $existing_page_id ) ) {
                continue; // Page already exists, skip
            }

            // Create the page
            $page_id = wp_insert_post( [
                'post_title'     => $page_data['title'],
                'post_content'   => $page_data['content'],
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'post_name'      => 'hikmah-' . str_replace( '_', '-', $slug ),
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) ) {
                update_option( $page_data['option'], $page_id );
            }
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {

        $default_options = [

            // General Settings
            'hikmah_login_enabled'               => 'yes',
            'hikmah_login_redirect_url'          => home_url( '/my-account/' ),
            'hikmah_login_logout_redirect_url'   => home_url(),

            // Registration Settings
            'hikmah_registration_enabled'        => 'yes',
            'hikmah_default_user_role'           => 'subscriber',
            'hikmah_email_verification_required' => 'yes',

            // Security Settings
            'hikmah_max_login_attempts'          => 5,
            'hikmah_lockout_duration'            => 30, // minutes
            'hikmah_captcha_enabled'             => 'no',
            'hikmah_captcha_type'                => 'recaptcha_v2',
            'hikmah_recaptcha_site_key'          => '',
            'hikmah_recaptcha_secret_key'        => '',

            // 2FA Settings
            'hikmah_2fa_enabled'                 => 'no',
            'hikmah_2fa_method'                  => 'email',

            // Social Login
            'hikmah_google_login_enabled'        => 'no',
            'hikmah_google_client_id'            => '',
            'hikmah_google_client_secret'        => '',
            'hikmah_facebook_login_enabled'      => 'no',
            'hikmah_facebook_app_id'             => '',
            'hikmah_facebook_app_secret'         => '',

            // Appearance
            'hikmah_login_logo'                  => '',
            'hikmah_login_bg_color'              => '#f1f1f1',
            'hikmah_login_form_width'            => '400',
            'hikmah_custom_css'                  => '',

            // Role-based Redirect
            'hikmah_redirect_admin'              => admin_url(),
            'hikmah_redirect_editor'             => admin_url(),
            'hikmah_redirect_author'             => home_url( '/my-account/' ),
            'hikmah_redirect_subscriber'         => home_url( '/my-account/' ),

            // Email Settings
            'hikmah_email_from_name'             => get_bloginfo( 'name' ),
            'hikmah_email_from_address'          => get_option( 'admin_email' ),

            // Login Logs
            'hikmah_login_logging_enabled'       => 'yes',
            'hikmah_log_retention_days'          => 90,

            // Plugin Meta
            'hikmah_login_version'               => HIKMAH_LOGIN_VERSION,
            'hikmah_login_installed_at'          => current_time( 'mysql' ),
        ];

        foreach ( $default_options as $key => $value ) {
            // Only add if not already set (preserves existing settings on reactivation)
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Assign custom capabilities to roles.
     */
    private static function assign_capabilities() {

        $admin_role = get_role( 'administrator' );

        if ( $admin_role ) {
            $admin_role->add_cap( 'hikmah_manage_settings' );
            $admin_role->add_cap( 'hikmah_view_login_logs' );
            $admin_role->add_cap( 'hikmah_manage_users' );
            $admin_role->add_cap( 'hikmah_delete_login_logs' );
            $admin_role->add_cap( 'hikmah_export_login_logs' );
        }
    }

    /**
     * Schedule cron events.
     */
    private static function schedule_cron_events() {

        // Clean expired tokens daily
        if ( ! wp_next_scheduled( 'hikmah_login_cleanup_tokens' ) ) {
            wp_schedule_event( time(), 'daily', 'hikmah_login_cleanup_tokens' );
        }

        // Clean old login logs weekly
        if ( ! wp_next_scheduled( 'hikmah_login_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'weekly', 'hikmah_login_cleanup_logs' );
        }

        // Reset expired lockouts hourly
        if ( ! wp_next_scheduled( 'hikmah_login_reset_lockouts' ) ) {
            wp_schedule_event( time(), 'hourly', 'hikmah_login_reset_lockouts' );
        }
    }

    /**
     * Create required upload directories.
     */
    private static function create_upload_directories() {

        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/hikmah-login/';

        $directories = [
            $plugin_upload_dir,
            $plugin_upload_dir . 'avatars/',
            $plugin_upload_dir . 'logs/',
            $plugin_upload_dir . 'temp/',
        ];

        foreach ( $directories as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );

                // Create .htaccess for security
                $htaccess = $dir . '.htaccess';
                if ( ! file_exists( $htaccess ) ) {
                    file_put_contents(
                        $htaccess,
                        "Options -Indexes\nDeny from all"
                    );
                }

                // Create index.php for security
                $index = $dir . 'index.php';
                if ( ! file_exists( $index ) ) {
                    file_put_contents(
                        $index,
                        '<?php // Silence is golden.'
                    );
                }
            }
        }
    }

    /**
     * Set activation flag for welcome redirect.
     */
    private static function set_activation_flag() {
        set_transient( 'hikmah_login_activation_redirect', true, 30 );
    }
}
