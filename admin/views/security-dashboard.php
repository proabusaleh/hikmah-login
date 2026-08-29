<?php
/**
 * Admin Security Dashboard view.
 *
 * @package    Hikmah_Login
 * @subpackage Hikmah_Login/admin/views
 */

namespace Hikmah_Login\Admin;

use Hikmah_Login\Security\Brute_Force;
use Hikmah_Login\Security\Security_Hardening;
use Hikmah_Login\Security\Security_Log;
use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$security_status = Security_Hardening::get_security_status();
$brute_stats     = Brute_Force::get_instance()->get_stats();
$log_stats       = Security_Log::get_instance()->get_stats();
$recent_logs     = Security_Log::get_instance()->get_logs( 1, 10 );

$blacklist = array_filter( array_map( 'trim', explode( "\n", get_option( 'hikmah_ip_blacklist', '' ) ) ) );

$level_badge = [
    'strong' => [ 'Secure', '#059669' ],
    'medium' => [ 'Moderate', '#d97706' ],
    'weak'   => [ 'At Risk', '#dc2626' ],
];
$level_badge = $level_badge[ $security_status['overall_level'] ] ?? $level_badge['weak'];
?>
<div class="wrap" style="padding: 0 12px;">
    <h1 style="font-size: 23px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        🔐 <?php esc_html_e( 'Hikmah Login — Security Dashboard', 'hikmah-login' ); ?>
        <span style="display:inline-block;font-size:12px;font-weight:600;color:#fff;background:<?php echo esc_attr( $level_badge[1] ); ?>;padding:3px 10px;border-radius:999px;">
            <?php echo esc_html( $level_badge[0] ); ?>
        </span>
    </h1>
    <p class="description"><?php esc_html_e( 'Monitor login security, brute force attacks, and protected assets in real time.', 'hikmah-login' ); ?></p>

    <hr>

    <h2 style="font-size:18px;margin:18px 0 10px;">📊 <?php esc_html_e( 'Security Overview', 'hikmah-login' ); ?></h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:16px 0;">
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">🛡️ <?php esc_html_e( 'Currently Locked', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#dc2626;margin-top:6px;"><?php echo esc_html( $brute_stats['currently_locked'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Accounts/IPs', 'hikmah-login' ); ?></div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">⚠️ <?php esc_html_e( 'Failed Logins (24h)', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#d97706;margin-top:6px;"><?php echo esc_html( $log_stats['failed_24h'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Failed + blocked attempts', 'hikmah-login' ); ?></div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">🔒 <?php esc_html_e( 'Lockouts (24h)', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#7c3aed;margin-top:6px;"><?php echo esc_html( $log_stats['locked_24h'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Applied lockouts', 'hikmah-login' ); ?></div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">✅ <?php esc_html_e( 'Successful Logins (24h)', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#059669;margin-top:6px;"><?php echo esc_html( $log_stats['success_24h'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Authenticated users', 'hikmah-login' ); ?></div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">🌐 <?php esc_html_e( 'Unique IPs (24h)', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#2563eb;margin-top:6px;"><?php echo esc_html( $brute_stats['unique_ips_24h'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Attempting logins', 'hikmah-login' ); ?></div>
        </div>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;">🚫 <?php esc_html_e( 'Blacklisted IPs', 'hikmah-login' ); ?></div>
            <div style="font-size:32px;font-weight:700;color:#dc2626;margin-top:6px;"><?php echo esc_html( $brute_stats['blacklisted_ips'] ); ?></div>
            <div style="font-size:12px;color:#9ca3af;"><?php esc_html_e( 'Permanently blocked', 'hikmah-login' ); ?></div>
        </div>
    </div>

    <h2 style="font-size:18px;margin:24px 0 10px;">🛡️ <?php esc_html_e( 'Security Checklist', 'hikmah-login' ); ?></h2>

    <table class="widefat striped" style="max-width:100%;">
        <thead>
            <tr>
                <th style="width:60%;"><?php esc_html_e( 'Protection', 'hikmah-login' ); ?></th>
                <th><?php esc_html_e( 'Status', 'hikmah-login' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $security_status['checks'] as $check ) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html( $check['label'] ); ?></strong>
                        <?php if ( ! empty( $check['details'] ) ) : ?>
                            <span class="description" style="display:block;color:#6b7280;"><?php echo esc_html( $check['details'] ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $check['enabled'] ) : ?>
                            <span style="color:#059669;font-weight:600;">✅ <?php esc_html_e( 'Enabled', 'hikmah-login' ); ?></span>
                        <?php else : ?>
                            <span style="color:#dc2626;font-weight:600;">❌ <?php esc_html_e( 'Disabled', 'hikmah-login' ); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 style="font-size:18px;margin:24px 0 10px;">📋 <?php esc_html_e( 'Recent Security Activity', 'hikmah-login' ); ?></h2>

    <p class="description">
        <?php esc_html_e( 'Latest login, lockout and block events.', 'hikmah-login' ); ?>
        <?php echo esc_html( sprintf( __( 'Total events logged: %d', 'hikmah-login' ), $log_stats['total_events'] ) ); ?>
    </p>

    <table class="widefat striped" style="max-width:100%;margin-top:10px;">
        <thead>
            <tr>
                <th style="width:14%;"><?php esc_html_e( 'Time', 'hikmah-login' ); ?></th>
                <th style="width:14%;"><?php esc_html_e( 'Username', 'hikmah-login' ); ?></th>
                <th style="width:14%;"><?php esc_html_e( 'IP Address', 'hikmah-login' ); ?></th>
                <th style="width:12%;"><?php esc_html_e( 'Status', 'hikmah-login' ); ?></th>
                <th><?php esc_html_e( 'Details', 'hikmah-login' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $recent_logs['items'] ) ) : ?>
                <tr><td colspan="5" class="description"><?php esc_html_e( 'No security events recorded yet.', 'hikmah-login' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $recent_logs['items'] as $log ) :
                    $status_labels = [
                        'success' => [ 'Success', '#059669' ],
                        'failed'  => [ 'Failed', '#dc2626' ],
                        'blocked' => [ 'Blocked', '#d97706' ],
                        'locked'  => [ 'Locked', '#7c3aed' ],
                    ];
                    $label = $status_labels[ $log->status ] ?? [ ucfirst( $log->status ), '#6b7280' ];
                    ?>
                    <tr>
                        <td><?php echo esc_html( Helper::format_datetime( $log->login_at ) ); ?></td>
                        <td><?php echo esc_html( $log->username ?? '—' ); ?></td>
                        <td><code><?php echo esc_html( $log->ip_address ?? '—' ); ?></code></td>
                        <td><span style="color:<?php echo esc_attr( $label[1] ); ?>;font-weight:600;">● <?php echo esc_html( $label[0] ); ?></span></td>
                        <td class="description"><?php echo esc_html( $log->failure_reason ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2 style="font-size:18px;margin:24px 0 10px;">🚫 <?php esc_html_e( 'Blacklisted IP Addresses', 'hikmah-login' ); ?></h2>

    <?php if ( empty( $blacklist ) ) : ?>
        <p class="description"><?php esc_html_e( 'No IPs are currently blacklisted.', 'hikmah-login' ); ?></p>
    <?php else : ?>
        <ul style="margin:0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ( $blacklist as $entry ) : ?>
                <li style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:4px 12px;">
                    <code><?php echo esc_html( $entry ); ?></code>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p style="margin-top:28px;">
        <a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=hikmah-login-settings' ) ); ?>">
            ⚙️ <?php esc_html_e( 'Configure Security Settings', 'hikmah-login' ); ?>
        </a>
    </p>
</div>