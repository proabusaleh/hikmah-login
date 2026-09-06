<?php
/**
 * Admin dashboard view.
 *
 * Premium security overview: live stats, protection status, recent activity.
 *
 * @package    Hikmah_Login
 * @subpackage Hikmah_Login/admin/views
 */

use Hikmah_Login\Helpers\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$security_status = \Hikmah_Login\Security\Security_Hardening::get_security_status();
$brute_stats     = \Hikmah_Login\Security\Brute_Force::get_instance()->get_stats();
$log_stats       = \Hikmah_Login\Security\Security_Log::get_instance()->get_stats();
$recent_logs     = \Hikmah_Login\Security\Security_Log::get_instance()->get_logs( 1, 8 );

$level_badge   = [
	'strong' => [ 'Secure', 'green' ],
	'medium' => [ 'Moderate', 'amber' ],
	'weak'   => [ 'At Risk', 'red' ],
];
$level         = $security_status['overall_level'] ?? 'weak';
$badge         = $level_badge[ $level ] ?? $level_badge['weak'];
$meter_percent = 'strong' === $level ? 100 : ( 'medium' === $level ? 60 : 30 );

$settings_url = admin_url( 'admin.php?page=hikmah-login-settings' );
$security_url = admin_url( 'admin.php?page=hikmah-login-security' );
$ui_url       = admin_url( 'admin.php?page=hikmah-login-ui' );

$features = [
	[
		'icon' => '🔐',
		'name' => __( 'Two-Factor Authentication', 'hikmah-login' ),
		'desc' => __( 'Email OTP + authenticator app with backup codes', 'hikmah-login' ),
		'on'   => Helper::is_feature_enabled( '2fa_enabled' ),
		'type' => 'violet',
	],
	[
		'icon' => '🤖',
		'name' => __( 'CAPTCHA Protection', 'hikmah-login' ),
		'desc' => __( 'Blocks automated form abuse on login & register', 'hikmah-login' ),
		'on'   => Helper::is_feature_enabled( 'captcha_enabled' ),
		'type' => 'indigo',
	],
	[
		'icon' => '🛡️',
		'name' => __( 'Brute Force Protection', 'hikmah-login' ),
		'desc' => __( 'Per-IP / per-username lockouts & auto-blacklist', 'hikmah-login' ),
		'on'   => Helper::is_feature_enabled( 'brute_force_enabled' ),
		'type' => 'green',
	],
	[
		'icon' => '📋',
		'name' => __( 'Security Audit Log', 'hikmah-login' ),
		'desc' => __( 'Records logins, failures, lockouts and blocks', 'hikmah-login' ),
		'on'   => Helper::is_feature_enabled( 'login_logging_enabled' ),
		'type' => 'slate',
	],
];
?>
<div class="wrap hikmah-admin-wrap">

	<!-- Hero -->
	<div class="hikmah-hero">
		<div class="hikmah-hero__inner">
			<h1 class="hikmah-hero__title">
				<span>🔐</span>
				<?php esc_html_e( 'Hikmah Login Dashboard', 'hikmah-login' ); ?>
				<span class="hikmah-badge hikmah-badge--glass"><?php echo esc_html( $badge[0] ); ?></span>
			</h1>
			<p class="hikmah-hero__subtitle">
				<?php esc_html_e( 'Your login security command center — monitor brute force attacks, audits, and protected assets in real time.', 'hikmah-login' ); ?>
			</p>

			<div class="hikmah-hero-meter">
				<span class="hikmah-hero-meter__label"><?php esc_html_e( 'Security Level', 'hikmah-login' ); ?></span>
				<div class="hikmah-hero-meter__bar">
					<span class="hikmah-hero-meter__fill" style="width:<?php echo esc_attr( $meter_percent ); ?>%;"></span>
				</div>
				<span class="hikmah-hero-meter__label"><?php echo esc_html( $meter_percent ); ?>%</span>
			</div>

			<div class="hikmah-hero__actions">
				<a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( $settings_url ); ?>">⚙️ <?php esc_html_e( 'Security Settings', 'hikmah-login' ); ?></a>
				<a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( $security_url ); ?>">📊 <?php esc_html_e( 'Detailed Report', 'hikmah-login' ); ?></a>
				<a class="hikmah-btn hikmah-btn--light" href="<?php echo esc_url( $ui_url ); ?>">🎨 <?php esc_html_e( 'Login Customizer', 'hikmah-login' ); ?></a>
			</div>
		</div>
	</div>

	<!-- Stat grid -->
	<div class="hikmah-stat-grid" style="margin-top:24px;">
		<div class="hikmah-stat-card hikmah-stat-card--red">
			<span class="hikmah-stat-label">🛡️ <?php esc_html_e( 'Currently Locked', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['currently_locked'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Accounts / IPs', 'hikmah-login' ); ?></span>
		</div>
		<div class="hikmah-stat-card hikmah-stat-card--amber">
			<span class="hikmah-stat-label">⚠️ <?php esc_html_e( 'Failed Logins', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['failed_24h'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Failed + blocked · 24h', 'hikmah-login' ); ?></span>
		</div>
		<div class="hikmah-stat-card hikmah-stat-card--violet">
			<span class="hikmah-stat-label">🔒 <?php esc_html_e( 'Lockouts', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['locked_24h'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Applied · 24h', 'hikmah-login' ); ?></span>
		</div>
		<div class="hikmah-stat-card hikmah-stat-card--green">
			<span class="hikmah-stat-label">✅ <?php esc_html_e( 'Successful Logins', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $log_stats['success_24h'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Authenticated · 24h', 'hikmah-login' ); ?></span>
		</div>
		<div class="hikmah-stat-card hikmah-stat-card--indigo">
			<span class="hikmah-stat-label">🌐 <?php esc_html_e( 'Unique IPs', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['unique_ips_24h'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Attempting · 24h', 'hikmah-login' ); ?></span>
		</div>
		<div class="hikmah-stat-card hikmah-stat-card--red">
			<span class="hikmah-stat-label">🚫 <?php esc_html_e( 'Blacklisted', 'hikmah-login' ); ?></span>
			<strong class="hikmah-stat-value"><?php echo esc_html( $brute_stats['blacklisted_ips'] ); ?></strong>
			<span class="hikmah-stat-hint"><?php esc_html_e( 'Permanently blocked', 'hikmah-login' ); ?></span>
		</div>
	</div>

	<?php if ( 'weak' === $level ) : ?>
		<div class="hikmah-toast hikmah-toast--error" style="margin-top:24px;">
			<span>⚠️</span>
			<div>
				<?php esc_html_e( 'Your security posture is at risk.', 'hikmah-login' ); ?>
				<a href="<?php echo esc_url( $security_url ); ?>"><?php esc_html_e( 'Review the checklist →', 'hikmah-login' ); ?></a>
			</div>
		</div>
	<?php endif; ?>

	<div class="hikmah-dash-grid">

		<!-- Recent activity -->
		<div class="hikmah-card">
			<div class="hikmah-card__head">
				<div>
					<h2 class="hikmah-card__title"><span class="hikmah-icon-chip">📋</span> <?php esc_html_e( 'Recent Security Activity', 'hikmah-login' ); ?></h2>
					<p class="hikmah-card__desc">
						<?php echo esc_html( sprintf( __( 'Latest events. Total events logged: %d.', 'hikmah-login' ), $log_stats['total_events'] ) ); ?>
					</p>
				</div>
				<a class="hikmah-btn hikmah-btn--outline" href="<?php echo esc_url( $security_url ); ?>"><?php esc_html_e( 'Full log →', 'hikmah-login' ); ?></a>
			</div>
			<div class="hikmah-card__body">
				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:15%;"><?php esc_html_e( 'Time', 'hikmah-login' ); ?></th>
							<th style="width:16%;"><?php esc_html_e( 'Username', 'hikmah-login' ); ?></th>
							<th style="width:17%;"><?php esc_html_e( 'IP Address', 'hikmah-login' ); ?></th>
							<th style="width:14%;"><?php esc_html_e( 'Status', 'hikmah-login' ); ?></th>
							<th><?php esc_html_e( 'Details', 'hikmah-login' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $recent_logs['items'] ) ) : ?>
							<tr><td colspan="5" class="description"><?php esc_html_e( 'No security events recorded yet.', 'hikmah-login' ); ?></td></tr>
						<?php else : ?>
							<?php
							$status_labels = [
								'success' => [ 'Success', 'green' ],
								'failed'  => [ 'Failed', 'red' ],
								'blocked' => [ 'Blocked', 'amber' ],
								'locked'  => [ 'Locked', 'violet' ],
							];
							foreach ( $recent_logs['items'] as $log ) :
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

		<!-- Protection status -->
		<div class="hikmah-card">
			<div class="hikmah-card__head">
				<div>
					<h2 class="hikmah-card__title"><span class="hikmah-icon-chip hikmah-icon-chip--green">🛡️</span> <?php esc_html_e( 'Protection Status', 'hikmah-login' ); ?></h2>
				</div>
			</div>
			<div class="hikmah-card__body">
				<?php foreach ( $features as $feature ) : ?>
					<div class="hikmah-feature">
						<span class="hikmah-icon-chip hikmah-icon-chip--<?php echo esc_attr( $feature['type'] ); ?>"><?php echo esc_html( $feature['icon'] ); ?></span>
						<div class="hikmah-feature__info">
							<span class="hikmah-feature__name">
								<?php echo esc_html( $feature['name'] ); ?>
								<?php if ( $feature['on'] ) : ?>
									<span class="hikmah-badge hikmah-badge--green"><?php esc_html_e( 'ON', 'hikmah-login' ); ?></span>
								<?php else : ?>
									<span class="hikmah-badge hikmah-badge--slate"><?php esc_html_e( 'OFF', 'hikmah-login' ); ?></span>
								<?php endif; ?>
							</span>
							<span class="hikmah-feature__desc"><?php echo esc_html( $feature['desc'] ); ?></span>
						</div>
					</div>
				<?php endforeach; ?>

				<div style="margin-top:18px;">
					<a class="hikmah-btn hikmah-btn--primary" href="<?php echo esc_url( $settings_url ); ?>" style="width:100%;">⚙️ <?php esc_html_e( 'Manage Settings', 'hikmah-login' ); ?></a>
				</div>
			</div>
		</div>
	</div>

	<div style="margin-top:24px;">
		<h2 class="hikmah-card__title" style="margin-bottom:14px;"><span class="hikmah-icon-chip">🚀</span> <?php esc_html_e( 'Quick Actions', 'hikmah-login' ); ?></h2>
		<div class="hikmah-quick-actions">
			<a class="hikmah-quick-action" href="<?php echo esc_url( admin_url( 'admin.php?page=hikmah-backup' ) ); ?>">
				<span class="hikmah-icon-chip hikmah-icon-chip--indigo">📦</span>
				<span>
					<span class="hikmah-quick-action__label" style="display:block;"><?php esc_html_e( 'Backups', 'hikmah-login' ); ?></span>
					<span class="hikmah-quick-action__hint"><?php esc_html_e( 'Create site backups', 'hikmah-login' ); ?></span>
				</span>
			</a>
			<a class="hikmah-quick-action" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
				<span class="hikmah-icon-chip hikmah-icon-chip--violet">👥</span>
				<span>
					<span class="hikmah-quick-action__label" style="display:block;"><?php esc_html_e( 'Users', 'hikmah-login' ); ?></span>
					<span class="hikmah-quick-action__hint"><?php esc_html_e( 'Manage accounts', 'hikmah-login' ); ?></span>
				</span>
			</a>
			<a class="hikmah-quick-action" href="<?php echo esc_url( $ui_url ); ?>">
				<span class="hikmah-icon-chip hikmah-icon-chip--amber">🎨</span>
				<span>
					<span class="hikmah-quick-action__label" style="display:block;"><?php esc_html_e( 'Live Preview', 'hikmah-login' ); ?></span>
					<span class="hikmah-quick-action__hint"><?php esc_html_e( 'Customize the login page', 'hikmah-login' ); ?></span>
				</span>
			</a>
			<a class="hikmah-quick-action" href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>" target="_blank" rel="noopener">
				<span class="hikmah-icon-chip hikmah-icon-chip--green">🔗</span>
				<span>
					<span class="hikmah-quick-action__label" style="display:block;"><?php esc_html_e( 'View Login Page', 'hikmah-login' ); ?></span>
					<span class="hikmah-quick-action__hint"><?php esc_html_e( 'Opens in a new tab', 'hikmah-login' ); ?></span>
				</span>
			</a>
		</div>
	</div>
</div>