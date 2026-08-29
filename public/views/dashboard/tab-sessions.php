<?php
/**
 * Dashboard Sessions Tab
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hikmah_Login\Helpers\Helper;
?>
<div class="hikmah-dash-section">
	<h3><?php esc_html_e( 'Active Sessions', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc">
		<?php
		printf(
			/* translators: %d: Number of active sessions */
			esc_html__( 'You are currently logged in on %d device(s).', 'hikmah-login' ),
			is_array( $sessions ) ? count( $sessions ) : 0
		);
		?>
	</p>

	<?php if ( is_array( $sessions ) && count( $sessions ) > 1 ) : ?>
		<button type="button" class="hikmah-btn hikmah-btn-outline" id="hikmah-logout-all"
			style="margin-bottom:16px;color:var(--hikmah-error,#dc2626);border-color:var(--hikmah-error,#dc2626);">
			🚪 <?php esc_html_e( 'Log Out All Other Devices', 'hikmah-login' ); ?>
		</button>
	<?php endif; ?>

	<?php if ( empty( $sessions ) ) : ?>
		<p style="color:var(--hikmah-text-muted,#6b7280);text-align:center;padding:24px;">
			<?php esc_html_e( 'No active sessions tracked.', 'hikmah-login' ); ?>
		</p>
	<?php else : ?>
		<div class="hikmah-sessions-list">
			<?php foreach ( $sessions as $session ) : ?>
				<div class="hikmah-session-card" style="
					display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
					padding:12px 16px;background:var(--hikmah-bg,#f9fafb);border-radius:var(--hikmah-radius-sm,8px);
					margin-bottom:8px;border:1px solid var(--hikmah-border,#e5e7eb);
					<?php echo ! empty( $session['is_current'] ) ? 'border-color:var(--hikmah-primary,#2563eb);background:var(--hikmah-primary-light,#eff6ff);' : ''; ?>
				">
					<div style="display:flex;align-items:center;gap:12px;">
						<span style="font-size:24px;">
							<?php
							$device_icons = [ 'mobile' => '📱', 'tablet' => '📟', 'desktop' => '💻' ];
							echo esc_html( $device_icons[ $session['device'] ] ?? '💻' );
							?>
						</span>
						<div>
							<strong>
								<?php echo esc_html( $session['browser'] ); ?>
								<?php if ( ! empty( $session['is_current'] ) ) : ?>
									<span style="color:var(--hikmah-primary,#2563eb);font-size:11px;font-weight:600;">(<?php esc_html_e( 'Current', 'hikmah-login' ); ?>)</span>
								<?php endif; ?>
							</strong>
							<br>
							<small style="color:var(--hikmah-text-muted,#6b7280);">
								<?php echo esc_html( $session['os'] ); ?> &bull; <?php echo esc_html( $session['ip_address'] ); ?>
							</small>
							<br>
							<small style="color:var(--hikmah-text-light,#9ca3af);">
								<?php
								printf(
									/* translators: %s: Time ago */
									esc_html__( 'Last active: %s', 'hikmah-login' ),
									esc_html( Helper::time_ago( $session['last_active'] ?: $session['login_at'] ) )
								);
								?>
							</small>
						</div>
					</div>

					<?php if ( empty( $session['is_current'] ) ) : ?>
						<button type="button" class="hikmah-btn hikmah-session-revoke"
							data-token="<?php echo esc_attr( $session['token_hash'] ); ?>"
							style="font-size:12px;padding:4px 10px;color:var(--hikmah-error,#dc2626);">
							<?php esc_html_e( 'Revoke', 'hikmah-login' ); ?>
						</button>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>