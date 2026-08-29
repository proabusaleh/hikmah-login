<?php
/**
 * Admin Security Settings view.
 *
 * Self-contained settings page for all Phase 09 security
 * options (CAPTCHA, brute force, IP lists, hardening, audit log).
 *
 * @package    Hikmah_Login
 * @subpackage Hikmah_Login/admin/views
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Security\Captcha;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$settings_saved = false;

if ( isset( $_POST['hikmah_security_settings_submit'] ) ) {
    if ( ! check_admin_referer( 'hikmah_security_settings_save', 'hikmah_security_settings_nonce' )
        || ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'hikmah-login' ) );
    }

    // CAPTCHA
    update_option(
        'hikmah_captcha_enabled',
        isset( $_POST['hikmah_captcha_enabled'] ) && '1' === $_POST['hikmah_captcha_enabled'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_captcha_type',
        array_key_exists( wp_unslash( $_POST['hikmah_captcha_type'] ?? '' ), Captcha::get_providers() )
            ? sanitize_text_field( wp_unslash( $_POST['hikmah_captcha_type'] ) )
            : 'recaptcha_v2'
    );
    update_option(
        'hikmah_recaptcha_site_key',
        sanitize_text_field( wp_unslash( $_POST['hikmah_recaptcha_site_key'] ?? '' ) )
    );
    update_option(
        'hikmah_recaptcha_secret_key',
        sanitize_text_field( wp_unslash( $_POST['hikmah_recaptcha_secret_key'] ?? '' ) )
    );
    $min_score = isset( $_POST['hikmah_recaptcha_min_score'] ) ? (float) $_POST['hikmah_recaptcha_min_score'] : 0.5;
    update_option( 'hikmah_recaptcha_min_score', max( 0, min( 1, $min_score ) ) );
    update_option(
        'hikmah_captcha_theme',
        'dark' === sanitize_text_field( wp_unslash( $_POST['hikmah_captcha_theme'] ?? '' ) ) ? 'dark' : 'light'
    );

    // Brute Force
    update_option(
        'hikmah_brute_force_enabled',
        isset( $_POST['hikmah_brute_force_enabled'] ) && '1' === $_POST['hikmah_brute_force_enabled'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_max_login_attempts',
        absint( $_POST['hikmah_max_login_attempts'] ?? 5 ) ?: 5
    );
    update_option(
        'hikmah_lockout_duration',
        absint( $_POST['hikmah_lockout_duration'] ?? 30 ) ?: 30
    );
    update_option(
        'hikmah_progressive_lockout',
        isset( $_POST['hikmah_progressive_lockout'] ) && '1' === $_POST['hikmah_progressive_lockout'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_max_lockout_multiplier',
        absint( $_POST['hikmah_max_lockout_multiplier'] ?? 8 ) ?: 8
    );
    update_option(
        'hikmah_auto_blacklist',
        isset( $_POST['hikmah_auto_blacklist'] ) && '1' === $_POST['hikmah_auto_blacklist'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_blacklist_threshold',
        absint( $_POST['hikmah_blacklist_threshold'] ?? 20 ) ?: 20
    );
    update_option(
        'hikmah_security_alerts',
        isset( $_POST['hikmah_security_alerts'] ) && '1' === $_POST['hikmah_security_alerts'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_security_alert_email',
        sanitize_email( wp_unslash( $_POST['hikmah_security_alert_email'] ?? '' ) )
    );
    update_option(
        'hikmah_alert_threshold',
        absint( $_POST['hikmah_alert_threshold'] ?? 10 ) ?: 10
    );

    // IP lists
    update_option(
        'hikmah_ip_whitelist_enabled',
        isset( $_POST['hikmah_ip_whitelist_enabled'] ) && '1' === $_POST['hikmah_ip_whitelist_enabled'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_ip_whitelist',
        sanitize_textarea_field( wp_unslash( $_POST['hikmah_ip_whitelist'] ?? '' ) )
    );
    update_option(
        'hikmah_ip_blacklist',
        sanitize_textarea_field( wp_unslash( $_POST['hikmah_ip_blacklist'] ?? '' ) )
    );

    // Hardening
    update_option(
        'hikmah_security_headers_enabled',
        isset( $_POST['hikmah_security_headers_enabled'] ) && '1' === $_POST['hikmah_security_headers_enabled'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_permissions_policy',
        sanitize_text_field( wp_unslash( $_POST['hikmah_permissions_policy'] ?? '' ) )
    );
    update_option(
        'hikmah_content_security_policy',
        sanitize_textarea_field( wp_unslash( $_POST['hikmah_content_security_policy'] ?? '' ) )
    );
    update_option(
        'hikmah_disable_xmlrpc',
        isset( $_POST['hikmah_disable_xmlrpc'] ) && '1' === $_POST['hikmah_disable_xmlrpc'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_disable_rest_user_enumeration',
        isset( $_POST['hikmah_disable_rest_user_enumeration'] ) && '1' === $_POST['hikmah_disable_rest_user_enumeration'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_hide_login_errors',
        isset( $_POST['hikmah_hide_login_errors'] ) && '1' === $_POST['hikmah_hide_login_errors'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_restrict_uploads',
        isset( $_POST['hikmah_restrict_uploads'] ) && '1' === $_POST['hikmah_restrict_uploads'] ? 'yes' : 'no'
    );
    update_option(
        'hikmah_allowed_upload_mimes',
        sanitize_text_field( wp_unslash( $_POST['hikmah_allowed_upload_mimes'] ?? '' ) )
    );
    update_option(
        'hikmah_disable_application_passwords',
        isset( $_POST['hikmah_disable_application_passwords'] ) && '1' === $_POST['hikmah_disable_application_passwords'] ? 'yes' : 'no'
    );

    // Audit log
    update_option(
        'hikmah_login_logging_enabled',
        isset( $_POST['hikmah_login_logging_enabled'] ) && '1' === $_POST['hikmah_login_logging_enabled'] ? 'yes' : 'no'
    );

    // Two-Factor Authentication
    update_option(
        'hikmah_2fa_enabled',
        isset( $_POST['hikmah_2fa_enabled'] ) && '1' === $_POST['hikmah_2fa_enabled'] ? 'yes' : 'no'
    );

    $settings_saved = true;
}

$options = [
    'hikmah_captcha_enabled'             => get_option( 'hikmah_captcha_enabled', 'no' ),
    'hikmah_captcha_type'                => get_option( 'hikmah_captcha_type', 'recaptcha_v2' ),
    'hikmah_recaptcha_site_key'          => get_option( 'hikmah_recaptcha_site_key', '' ),
    'hikmah_recaptcha_secret_key'        => get_option( 'hikmah_recaptcha_secret_key', '' ),
    'hikmah_recaptcha_min_score'         => get_option( 'hikmah_recaptcha_min_score', 0.5 ),
    'hikmah_captcha_theme'               => get_option( 'hikmah_captcha_theme', 'light' ),
    'hikmah_brute_force_enabled'         => get_option( 'hikmah_brute_force_enabled', 'yes' ),
    'hikmah_max_login_attempts'          => get_option( 'hikmah_max_login_attempts', 5 ),
    'hikmah_lockout_duration'            => get_option( 'hikmah_lockout_duration', 30 ),
    'hikmah_progressive_lockout'         => get_option( 'hikmah_progressive_lockout', 'yes' ),
    'hikmah_max_lockout_multiplier'      => get_option( 'hikmah_max_lockout_multiplier', 8 ),
    'hikmah_auto_blacklist'              => get_option( 'hikmah_auto_blacklist', 'no' ),
    'hikmah_blacklist_threshold'         => get_option( 'hikmah_blacklist_threshold', 20 ),
    'hikmah_security_alerts'             => get_option( 'hikmah_security_alerts', 'no' ),
    'hikmah_security_alert_email'        => get_option( 'hikmah_security_alert_email', get_option( 'admin_email' ) ),
    'hikmah_alert_threshold'             => get_option( 'hikmah_alert_threshold', 10 ),
    'hikmah_ip_whitelist_enabled'        => get_option( 'hikmah_ip_whitelist_enabled', 'no' ),
    'hikmah_ip_whitelist'                => get_option( 'hikmah_ip_whitelist', '' ),
    'hikmah_ip_blacklist'                => get_option( 'hikmah_ip_blacklist', '' ),
    'hikmah_security_headers_enabled'    => get_option( 'hikmah_security_headers_enabled', 'yes' ),
    'hikmah_permissions_policy'          => get_option( 'hikmah_permissions_policy', 'geolocation=(), microphone=(), camera=()' ),
    'hikmah_content_security_policy'     => get_option( 'hikmah_content_security_policy', '' ),
    'hikmah_disable_xmlrpc'              => get_option( 'hikmah_disable_xmlrpc', 'no' ),
    'hikmah_disable_rest_user_enumeration' => get_option( 'hikmah_disable_rest_user_enumeration', 'no' ),
    'hikmah_hide_login_errors'           => get_option( 'hikmah_hide_login_errors', 'yes' ),
    'hikmah_restrict_uploads'            => get_option( 'hikmah_restrict_uploads', 'no' ),
    'hikmah_allowed_upload_mimes'        => get_option( 'hikmah_allowed_upload_mimes', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,zip' ),
    'hikmah_disable_application_passwords' => get_option( 'hikmah_disable_application_passwords', 'no' ),
    'hikmah_login_logging_enabled'       => get_option( 'hikmah_login_logging_enabled', 'yes' ),
    'hikmah_2fa_enabled'                 => get_option( 'hikmah_2fa_enabled', 'no' ),
];

function __hikmah_checkbox( $name, $checked ) {
    return '<input type="checkbox" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, 'yes', false ) . '>';
}
?>
<div class="wrap" style="padding: 0 12px;">
    <h1 style="font-size: 23px; font-weight: 600;">⚙️ <?php esc_html_e( 'Hikmah Login — Security Settings', 'hikmah-login' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Configure brute force protection, CAPTCHA, IP lists, and hardening. Most features take effect immediately.', 'hikmah-login' ); ?></p>

    <?php if ( $settings_saved ) : ?>
        <div class="notice notice-success is-dismissible" style="margin:16px 0;">
            <p><?php esc_html_e( 'Security settings saved.', 'hikmah-login' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'hikmah_security_settings_save', 'hikmah_security_settings_nonce' ); ?>

        <!-- CAPTCHA -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">🤖 <?php esc_html_e( 'CAPTCHA Protection', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Protect login, registration and password reset forms from automated abuse.', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_captcha_enabled"><?php esc_html_e( 'Enable CAPTCHA', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_captcha_enabled', $options['hikmah_captcha_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Adds CAPTCHA to the login, register and forgot password forms.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_captcha_type"><?php esc_html_e( 'Provider', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select id="hikmah_captcha_type" name="hikmah_captcha_type">
                            <?php foreach ( Captcha::get_providers() as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $options['hikmah_captcha_type'], $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_recaptcha_site_key"><?php esc_html_e( 'Site Key', 'hikmah-login' ); ?></label></th>
                    <td><input type="text" id="hikmah_recaptcha_site_key" name="hikmah_recaptcha_site_key" class="regular-text" value="<?php echo esc_attr( $options['hikmah_recaptcha_site_key'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'hikmah-login' ); ?></label></th>
                    <td><input type="text" id="hikmah_recaptcha_secret_key" name="hikmah_recaptcha_secret_key" class="regular-text" value="<?php echo esc_attr( $options['hikmah_recaptcha_secret_key'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_recaptcha_min_score"><?php esc_html_e( 'Min. Score (v3)', 'hikmah-login' ); ?></label></th>
                    <td>
                        <input type="number" id="hikmah_recaptcha_min_score" name="hikmah_recaptcha_min_score" min="0" max="1" step="0.1" value="<?php echo esc_attr( $options['hikmah_recaptcha_min_score'] ); ?>">
                        <span class="description"><?php esc_html_e( 'Reject reCAPTCHA v3 tokens below this score (0.0 – 1.0).', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_captcha_theme"><?php esc_html_e( 'Widget Theme', 'hikmah-login' ); ?></label></th>
                    <td>
                        <select id="hikmah_captcha_theme" name="hikmah_captcha_theme">
                            <option value="light" <?php selected( $options['hikmah_captcha_theme'], 'light' ); ?>><?php esc_html_e( 'Light', 'hikmah-login' ); ?></option>
                            <option value="dark" <?php selected( $options['hikmah_captcha_theme'], 'dark' ); ?>><?php esc_html_e( 'Dark', 'hikmah-login' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Two-Factor Authentication -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">🔐 <?php esc_html_e( 'Two-Factor Authentication', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Enable email OTP / authenticator-app verification with single-use backup codes. Users manage their own 2FA on their profile.', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_2fa_enabled"><?php esc_html_e( 'Enable 2FA', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_2fa_enabled', $options['hikmah_2fa_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Allows users and administrators to enable two-factor authentication.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Brute Force -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">🛡️ <?php esc_html_e( 'Brute Force Protection', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Lock out attackers after repeated failed login attempts (per-IP, per-username, and combined).', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_brute_force_enabled"><?php esc_html_e( 'Enable Brute Force Protection', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_brute_force_enabled', $options['hikmah_brute_force_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Recommended. Enabled by default.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_max_login_attempts"><?php esc_html_e( 'Max Attempts', 'hikmah-login' ); ?></label></th>
                    <td><input type="number" id="hikmah_max_login_attempts" name="hikmah_max_login_attempts" min="1" max="50" value="<?php echo esc_attr( $options['hikmah_max_login_attempts'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_lockout_duration"><?php esc_html_e( 'Lockout Duration (minutes)', 'hikmah-login' ); ?></label></th>
                    <td><input type="number" id="hikmah_lockout_duration" name="hikmah_lockout_duration" min="1" max="1440" value="<?php echo esc_attr( $options['hikmah_lockout_duration'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_progressive_lockout"><?php esc_html_e( 'Progressive Lockout', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_progressive_lockout', $options['hikmah_progressive_lockout'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Double the lockout duration each time the threshold is re-crossed.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_max_lockout_multiplier"><?php esc_html_e( 'Max Lockout Multiplier', 'hikmah-login' ); ?></label></th>
                    <td><input type="number" id="hikmah_max_lockout_multiplier" name="hikmah_max_lockout_multiplier" min="1" max="100" value="<?php echo esc_attr( $options['hikmah_max_lockout_multiplier'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_auto_blacklist"><?php esc_html_e( 'Auto-Blacklist', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_auto_blacklist', $options['hikmah_auto_blacklist'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Permanently blacklist IPs that exceed the threshold below within 24 hours.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_blacklist_threshold"><?php esc_html_e( 'Blacklist Threshold', 'hikmah-login' ); ?></label></th>
                    <td><input type="number" id="hikmah_blacklist_threshold" name="hikmah_blacklist_threshold" min="1" value="<?php echo esc_attr( $options['hikmah_blacklist_threshold'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_security_alerts"><?php esc_html_e( 'Email Alerts', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_security_alerts', $options['hikmah_security_alerts'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Send an email when suspicious activity is detected.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_alert_threshold"><?php esc_html_e( 'Alert Threshold', 'hikmah-login' ); ?></label></th>
                    <td><input type="number" id="hikmah_alert_threshold" name="hikmah_alert_threshold" min="1" value="<?php echo esc_attr( $options['hikmah_alert_threshold'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_security_alert_email"><?php esc_html_e( 'Alert Email', 'hikmah-login' ); ?></label></th>
                    <td><input type="email" id="hikmah_security_alert_email" name="hikmah_security_alert_email" class="regular-text" value="<?php echo esc_attr( $options['hikmah_security_alert_email'] ); ?>"></td>
                </tr>
            </table>
        </div>

        <!-- IP Lists -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">🌐 <?php esc_html_e( 'IP Blacklist / Whitelist', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'One entry per line. Supports exact IPs, CIDR ranges (192.168.1.0/24) and wildcards (192.168.1.*). Use # for comments.', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_ip_whitelist_enabled"><?php esc_html_e( 'Enable Whitelist', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_ip_whitelist_enabled', $options['hikmah_ip_whitelist_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Whitelisted IPs bypass brute force restrictions.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_ip_whitelist"><?php esc_html_e( 'Whitelist', 'hikmah-login' ); ?></label></th>
                    <td><textarea id="hikmah_ip_whitelist" name="hikmah_ip_whitelist" rows="4" class="large-text code"><?php echo esc_textarea( $options['hikmah_ip_whitelist'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_ip_blacklist"><?php esc_html_e( 'Blacklist', 'hikmah-login' ); ?></label></th>
                    <td>
                        <textarea id="hikmah_ip_blacklist" name="hikmah_ip_blacklist" rows="4" class="large-text code"><?php echo esc_textarea( $options['hikmah_ip_blacklist'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Blocked IPs are rejected before login. They can also be added automatically by Auto-Blacklist.', 'hikmah-login' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Hardening -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">🛡️ <?php esc_html_e( 'Security Hardening', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Reduce the WordPress attack surface (headers, XML-RPC, enumeration, uploads).', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_security_headers_enabled"><?php esc_html_e( 'Security Headers', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_security_headers_enabled', $options['hikmah_security_headers_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_permissions_policy"><?php esc_html_e( 'Permissions-Policy', 'hikmah-login' ); ?></label></th>
                    <td><input type="text" id="hikmah_permissions_policy" name="hikmah_permissions_policy" class="large-text code" value="<?php echo esc_attr( $options['hikmah_permissions_policy'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_content_security_policy"><?php esc_html_e( 'Content-Security-Policy', 'hikmah-login' ); ?></label></th>
                    <td><textarea id="hikmah_content_security_policy" name="hikmah_content_security_policy" rows="2" class="large-text code"><?php echo esc_textarea( $options['hikmah_content_security_policy'] ); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_disable_xmlrpc"><?php esc_html_e( 'Disable XML-RPC', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_disable_xmlrpc', $options['hikmah_disable_xmlrpc'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Prevents DDoS/brute-force amplification via pingbacks.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_disable_rest_user_enumeration"><?php esc_html_e( 'Block REST User Enumeration', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_disable_rest_user_enumeration', $options['hikmah_disable_rest_user_enumeration'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Blocks /wp-json/wp/v2/users and hides emails for non-admins.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_hide_login_errors"><?php esc_html_e( 'Hide Login Errors', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_hide_login_errors', $options['hikmah_hide_login_errors'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Generic message instead of revealing whether a username exists.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_restrict_uploads"><?php esc_html_e( 'Restrict Uploads', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_restrict_uploads', $options['hikmah_restrict_uploads'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Whitelist allowed MIME types and hard-block PHP uploads.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_allowed_upload_mimes"><?php esc_html_e( 'Allowed MIME Extensions', 'hikmah-login' ); ?></label></th>
                    <td><input type="text" id="hikmah_allowed_upload_mimes" name="hikmah_allowed_upload_mimes" class="large-text code" value="<?php echo esc_attr( $options['hikmah_allowed_upload_mimes'] ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hikmah_disable_application_passwords"><?php esc_html_e( 'Disable Application Passwords', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_disable_application_passwords', $options['hikmah_disable_application_passwords'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'Reduces credential abuse risk on sites that do not use the REST API.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Audit Log -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin:20px 0;">
            <h2 style="margin:0 0 4px;">📋 <?php esc_html_e( 'Audit Log', 'hikmah-login' ); ?></h2>
            <p class="description" style="margin:0 0 16px;"><?php esc_html_e( 'Record logins, failures, lockouts and security events for monitoring.', 'hikmah-login' ); ?></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="hikmah_login_logging_enabled"><?php esc_html_e( 'Enable Audit Log', 'hikmah-login' ); ?></label></th>
                    <td>
                        <?php echo __hikmah_checkbox( 'hikmah_login_logging_enabled', $options['hikmah_login_logging_enabled'] ); // phpcs:ignore ?>
                        <span class="description"><?php esc_html_e( 'View events on the Security Dashboard. Enabled by default.', 'hikmah-login' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="hikmah_security_settings_submit" class="button button-primary button-hero">
                <?php esc_html_e( 'Save Security Settings', 'hikmah-login' ); ?>
            </button>
        </p>
    </form>
</div>