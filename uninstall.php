<?php
/**
 * Uninstall Hikmah Login
 *
 * This file runs when the plugin is deleted
 * (not just deactivated) from WordPress admin.
 *
 * ⚠️ WARNING: This permanently deletes ALL plugin data!
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

// Safety check — only run if called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean uninstall — remove everything.
 */
function hikmah_login_uninstall() {

    global $wpdb;

    // Check if admin wants to keep data
    $keep_data = get_option( 'hikmah_login_keep_data_on_uninstall', 'no' );

    if ( 'yes' === $keep_data ) {
        return; // User chose to keep data
    }

    // ============================================
    // 1. Drop custom database tables
    // ============================================
    $prefix = $wpdb->prefix . 'hikmah_login_';

    $tables = [
        $prefix . 'login_logs',
        $prefix . 'login_attempts',
        $prefix . 'email_tokens',
        $prefix . 'two_factor',
        $prefix . 'social_profiles',
        $prefix . 'sessions',
    ];

    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore
    }

    // ============================================
    // 2. Delete all plugin options
    // ============================================
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE 'hikmah_login_%'
         OR option_name LIKE 'hikmah_%'"
    );

    // ============================================
    // 3. Delete all transients
    // ============================================
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '%_transient_hikmah_login_%'
         OR option_name LIKE '%_transient_timeout_hikmah_login_%'"
    );

    // ============================================
    // 4. Delete user meta created by this plugin
    // ============================================
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key LIKE 'hikmah_login_%'
         OR meta_key LIKE 'hikmah_%'"
    );

    // ============================================
    // 5. Delete pages created by plugin
    // ============================================
    $page_options = [
        'hikmah_login_page_id',
        'hikmah_register_page_id',
        'hikmah_forgot_password_page_id',
        'hikmah_reset_password_page_id',
        'hikmah_dashboard_page_id',
    ];

    foreach ( $page_options as $option ) {
        $page_id = get_option( $option );
        if ( $page_id ) {
            wp_delete_post( $page_id, true ); // Force delete
        }
    }

    // ============================================
    // 6. Remove custom capabilities
    // ============================================
    $roles = wp_roles();
    $custom_caps = [
        'hikmah_manage_settings',
        'hikmah_view_login_logs',
        'hikmah_manage_users',
        'hikmah_delete_login_logs',
        'hikmah_export_login_logs',
    ];

    foreach ( $roles->roles as $role_name => $role_info ) {
        $role = get_role( $role_name );
        if ( $role ) {
            foreach ( $custom_caps as $cap ) {
                $role->remove_cap( $cap );
            }
        }
    }

    // ============================================
    // 7. Clear cron events
    // ============================================
    $cron_hooks = [
        'hikmah_login_cleanup_tokens',
        'hikmah_login_cleanup_logs',
        'hikmah_login_reset_lockouts',
    ];

    foreach ( $cron_hooks as $hook ) {
        wp_clear_scheduled_hook( $hook );
    }

    // ============================================
    // 8. Delete upload directory
    // ============================================
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/hikmah-login/';

    if ( is_dir( $plugin_upload_dir ) ) {
        hikmah_login_delete_directory( $plugin_upload_dir );
    }

    // ============================================
    // 9. Flush rewrite rules
    // ============================================
    flush_rewrite_rules();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 */
function hikmah_login_delete_directory( $dir ) {
    if ( ! is_dir( $dir ) ) {
        return;
    }

    $files = array_diff( scandir( $dir ), [ '.', '..' ] );

    foreach ( $files as $file ) {
        $path = $dir . '/' . $file;
        is_dir( $path )
            ? hikmah_login_delete_directory( $path )
            : unlink( $path );
    }

    rmdir( $dir );
}

// Run on multisite or single site
if ( is_multisite() ) {
    global $wpdb;
    $blog_ids = $wpdb->get_col(
        "SELECT blog_id FROM {$wpdb->blogs}"
    );
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        hikmah_login_uninstall();
        restore_current_blog();
    }
} else {
    hikmah_login_uninstall();
}
