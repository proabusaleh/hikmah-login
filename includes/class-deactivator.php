<?php
/**
 * Plugin Deactivator
 *
 * Handles everything that needs to happen
 * when the plugin is deactivated.
 *
 * Note: We DO NOT delete data on deactivation.
 * Data cleanup only happens in uninstall.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

namespace Hikmah_Login;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {

    /**
     * Run deactivation tasks.
     *
     * @param bool $network_wide Whether deactivating network-wide.
     */
    public static function deactivate( $network_wide = false ) {

        if ( is_multisite() && $network_wide ) {
            self::deactivate_for_network();
        } else {
            self::deactivate_single_site();
        }
    }

    /**
     * Deactivate for single site.
     */
    private static function deactivate_single_site() {
        self::clear_cron_events();
        self::clear_transients();
        self::remove_rewrite_rules();
        self::log_deactivation();
    }

    /**
     * Deactivate for multisite network.
     */
    private static function deactivate_for_network() {
        global $wpdb;

        $blog_ids = $wpdb->get_col(
            "SELECT blog_id FROM {$wpdb->blogs} WHERE archived = 0 AND deleted = 0"
        );

        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            self::deactivate_single_site();
            restore_current_blog();
        }
    }

    /**
     * Remove all scheduled cron events.
     */
    private static function clear_cron_events() {

        $cron_hooks = [
            'hikmah_login_cleanup_tokens',
            'hikmah_login_cleanup_logs',
            'hikmah_login_reset_lockouts',
        ];

        foreach ( $cron_hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
            // Clear all instances
            wp_clear_scheduled_hook( $hook );
        }
    }

    /**
     * Clear plugin transients.
     */
    private static function clear_transients() {
        delete_transient( 'hikmah_login_activation_redirect' );
        delete_transient( 'hikmah_login_admin_notices' );

        // Clear any cached data
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '%_transient_hikmah_login_%'
             OR option_name LIKE '%_transient_timeout_hikmah_login_%'"
        );
    }

    /**
     * Flush rewrite rules.
     */
    private static function remove_rewrite_rules() {
        flush_rewrite_rules();
    }

    /**
     * Log deactivation event.
     */
    private static function log_deactivation() {
        update_option(
            'hikmah_login_deactivated_at',
            current_time( 'mysql' )
        );
    }
}
