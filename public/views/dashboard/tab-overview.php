<?php
/**
 * Dashboard Overview Tab
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hikmah_Login\Helpers\Helper;

$dashboard_url = Helper::get_dashboard_url();
?>
<div class="hikmah-dash-section">
	<h3><?php esc_html_e( 'Welcome back', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc">
		<?php
		printf(
			/* translators: %s: Display name */
			esc_html__( 'Here is what is happening with your account, %s.', 'hikmah-login' ),
			esc_html( $user->display_name )
		);
		?>
	</p>

	<div class="hikmah-stat-grid">
		<div class="hikmah-stat-card">
			<strong><?php echo esc_html( (int) $stats['success_logins'] ); ?></strong>
			<small><?php esc_html_e( 'Total logins', 'hikmah-login' ); ?></small>
		</div>
		<div class="hikmah-stat-card">
			<strong><?php echo esc_html( (int) $stats['logins_30d'] ); ?></strong>
			<small><?php esc_html_e( 'Logins (30 days)', 'hikmah-login' ); ?></small>
		</div>
		<div class="hikmah-stat-card">
			<strong><?php echo esc_html( (int) $stats['active_sessions'] ); ?></strong>
			<small><?php esc_html_e( 'Active sessions', 'hikmah-login' ); ?></small>
		</div>
		<div class="hikmah-stat-card">
			<strong><?php echo esc_html( (int) $stats['security_score'] ); ?><span style="font-size:14px">/100</span></strong>
			<small><?php esc_html_e( 'Security score', 'hikmah-login' ); ?></small>
		</div>
	</div>

	<div class="hikmah-dash-section" style="margin-top:28px">
		<h3 style="font-size:16px"><?php esc_html_e( 'Recent Activity', 'hikmah-login' ); ?></h3>

		<?php if ( empty( $recent_activity ) ) : ?>
			<p class="hikmah-dash-desc"><?php esc_html_e( 'No recent login activity yet.', 'hikmah-login' ); ?></p>
		<?php else : ?>
			<ul class="hikmah-activity-list">
				<?php foreach ( $recent_activity as $entry ) : ?>
					<li>
						<span>
							<?php
							$tag_classes = [
								'success' => 'hikmah-tag-success',
								'failed'  => 'hikmah-tag-failed',
								'blocked' => 'hikmah-tag-warning',
								'locked'  => 'hikmah-tag-info',
							];
							?>
							<span class="hikmah-tag <?php echo esc_attr( $tag_classes[ $entry['status'] ] ?? 'hikmah-tag-info' ); ?>">
								<?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
							</span>
						</span>
						<span><?php echo esc_html( Helper::format_datetime( $entry['time'] ) ); ?></span>
						<span style="margin-left:auto;color:var(--hikmah-text-muted,#6b7280);font-size:12px">
							<code><?php echo esc_html( $entry['ip'] ); ?></code>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p style="margin:12px 0 0">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'activity', $dashboard_url ) ); ?>" class="hikmah-btn hikmah-btn-outline">
					<?php esc_html_e( 'View full history', 'hikmah-login' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<div class="hikmah-dash-section" style="margin-top:28px">
		<h3 style="font-size:16px"><?php esc_html_e( 'Security Recommendations', 'hikmah-login' ); ?></h3>

		<ul class="hikmah-rec-list">
			<?php foreach ( $recommendations as $rec ) : ?>
				<li>
					<span>
						<?php
						$rec_icons = [
							'warning' => '⚠️',
							'info'    => 'ℹ️',
							'success' => '✅',
						];
						echo esc_html( $rec_icons[ $rec['type'] ] ?? 'ℹ️' );
						?>
					</span>
					<span><?php echo esc_html( $rec['message'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<p style="margin:14px 0 0;display:flex;gap:8px;flex-wrap:wrap">
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'security', $dashboard_url ) ); ?>" class="hikmah-btn hikmah-btn-primary">
				<?php esc_html_e( 'Go to Security', 'hikmah-login' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'profile', $dashboard_url ) ); ?>" class="hikmah-btn hikmah-btn-outline">
				<?php esc_html_e( 'Edit Profile', 'hikmah-login' ); ?>
			</a>
		</p>
	</div>
</div>