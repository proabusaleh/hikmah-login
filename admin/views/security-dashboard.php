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
$meter_percent = 'strong' === $security_status['overall_level'] ? 100 : ( 'medium' === $security_status['overall_level'] ? 60 : 30 );

$status_labels = [
    'success' => [ 'Success', 'green' ],
    'failed'  => [ 'Failed', 'red' ],
    'blocked' => [ 'Blocked', 'amber' ],
    'locked'  => [ 'Locked', 'violet' ],
];
?>
<div class="wrap hikmah-admin-wrap">

    <div class="hikmah-hero">
        <div class="hikmah-hero__inner">
            <h1 class="hikmah-hero__title">
                <span>🔐</span>
                <?php esc_html_e( 'Security Dashboard', 'hikmah-login' ); ?>
                <span class="hikmah-badge hikmah-badge--glass"><?php echo esc_html( $level_badge[0] ); ?></span>
            </h1>
            <p class="hikmah-hero__subtitle"><?php esc_html_e( 'Monitor login security, brute force attacks, and protected assets in real time.', 'hikmah-login' ); ?></p>

            <div class="hikmah-hero-meter">
                <span class="hikmah-hero-meter__label"><?php esc_html_e( 'Security Level', 'hikmah-login' ); ?></span>
                <div class="hikmah-hero-meter__bar">
                    <span class="hikmah-hero-meter__fill" style="width:<?php echo esc_attr( $meter_percent ); ?>%;"></span>
                </div>
                <span class="hikmah-hero-meter__label"><?php echo esc_attr( $meter_percent ); ?>%</span>
            </div>

            <div class="hikmah-hero__actions">
                <a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( admin_url( 'admin.php?page=hikmah-login-settings' ) ); ?>">⚙️ <?php esc_html_e( 'Configure Security Settings', 'hikmah-login' ); ?></a>
                <a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( admin_url( 'admin.php?page=hikmah-login' ) ); ?>">🏠 <?php esc_html_e( 'Dashboard', 'hikmah-login' ); ?></a>
            </div>
        </div>
    </div>

    <?php if ( 'weak' === $security_status['overall_level'] ) : ?>
        <div class="hikmah-toast hikmah-toast--error"><span>⚠️</span> <?php esc_html_e( 'Security level at risk — review the checklist below and enable the recommended protections.', 'hikmah-login' ); ?></div>
    <?php endif; ?>

    <h2 class="hikmah-card__title" style="margin:26px 0 14px;"><span class="hikmah-icon-chip">📊</span> <?php esc_html_e( 'Security Overview', 'hikmah-login' ); ?></h2>

    <div class="hikmah-stat-grid">
        <div class="hikmah-stat-card hikmah-stat-card--red">
            <span class="hikmah-stat-label">🛡️ <?php esc_html_e( 'Currently Locked', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['currently_locked'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Accounts / IPs', 'hikmah-login' ); ?></span>
        </div>
        <div class="hikmah-stat-card hikmah-stat-card--amber">
            <span class="hikmah-stat-label">⚠️ <?php esc_html_e( 'Failed Logins (24h)', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['failed_24h'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Failed + blocked attempts', 'hikmah-login' ); ?></span>
        </div>
        <div class="hikmah-stat-card hikmah-stat-card--violet">
            <span class="hikmah-stat-label">🔒 <?php esc_html_e( 'Lockouts (24h)', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['locked_24h'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Applied lockouts', 'hikmah-login' ); ?></span>
        </div>
        <div class="hikmah-stat-card hikmah-stat-card--green">
            <span class="hikmah-stat-label">✅ <?php esc_html_e( 'Successful (24h)', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['success_24h'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Authenticated users', 'hikmah-login' ); ?></span>
        </div>
        <div class="hikmah-stat-card hikmah-stat-card--indigo">
            <span class="hikmah-stat-label">🌐 <?php esc_html_e( 'Unique IPs (24h)', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['unique_ips_24h'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Attempting logins', 'hikmah-login' ); ?></span>
        </div>
        <div class="hikmah-stat-card hikmah-stat-card--red">
            <span class="hikmah-stat-label">🚫 <?php esc_html_e( 'Blacklisted IPs', 'hikmah-login' ); ?></span>
            <strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['blacklisted_ips'] ); ?></strong>
            <span class="hikmah-stat-hint"><?php esc_html_e( 'Permanently blocked', 'hikmah-login' ); ?></span>
        </div>
    </div>

    <div class="hikmah-card">
        <div class="hikmah-card__head">
            <div>
                <h2 class="hikmah-card__title"><span class="hikmah-icon-chip hikmah-icon-chip--green">🛡️</span> <?php esc_html_e( 'Security Checklist', 'hikmah-login' ); ?></h2>
                <p class="hikmah-card__desc"><?php esc_html_e( 'Every layer of protection that is or can be active on your site.', 'hikmah-login' ); ?></p>
            </div>
        </div>
        <div class="hikmah-card__body">
            <table class="widefat striped">
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
                                    <span class="description" style="display:block;"><?php echo esc_html( $check['details'] ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $check['enabled'] ) : ?>
                                    <span class="hikmah-badge hikmah-badge--green">✅ <?php esc_html_e( 'Enabled', 'hikmah-login' ); ?></span>
                                <?php else : ?>
                                    <span class="hikmah-badge hikmah-badge--red">❌ <?php esc_html_e( 'Disabled', 'hikmah-login' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="hikmah-card">
        <div class="hikmah-card__head">
            <div>
                <h2 class="hikmah-card__title"><span class="hikmah-icon-chip hikmah-icon-chip--indigo">📋</span> <?php esc_html_e( 'Recent Security Activity', 'hikmah-login' ); ?></h2>
                <p class="hikmah-card__desc">
                    <?php esc_html_e( 'Latest login, lockout and block events.', 'hikmah-login' ); ?>
                    <?php echo esc_html( sprintf( __( 'Total events logged: %d', 'hikmah-login' ), $log_stats['total_events'] ) ); ?>
                </p>
            </div>
        </div>
        <div class="hikmah-card__body">
            <table class="widefat striped">
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
                            $label = $status_labels[ $log->status ] ?? [ ucfirst( (string) $log->status ), 'slate' ];
                            ?>
                            <tr>
                                <td><?php echo esc_html( Helper::format_datetime( $log->login_at ) ); ?></td>
                                <td><?php echo esc_html( $log->username ?? '—' ); ?></td>
                                <td><code><?php echo esc_html( $log->ip_address ?? '—' ); ?></code></td>
                                <td><span class="hikmah-badge hikmah-badge--<?php echo esc_attr( $label[1] ); ?>">● <?php echo esc_html( $label[0] ); ?></span></td>
                                <td class="description"><?php echo esc_html( $log->failure_reason ?? '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="hikmah-card">
        <div class="hikmah-card__head">
            <div>
                <h2 class="hikmah-card__title"><span class="hikmah-icon-chip hikmah-icon-chip--red">🚫</span> <?php esc_html_e( 'Blacklisted IP Addresses', 'hikmah-login' ); ?></h2>
            </div>
        </div>
        <div class="hikmah-card__body">
            <?php if ( empty( $blacklist ) ) : ?>
                <p class="description"><?php esc_html_e( 'No IPs are currently blacklisted.', 'hikmah-login' ); ?></p>
            <?php else : ?>
                <div style="display:flex;flex-wrap:wrap;gap:8px;margin:0;">
                    <?php foreach ( $blacklist as $entry ) : ?>
                        <span class="hikmah-chip"><code><?php echo esc_html( $entry ); ?></code></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>