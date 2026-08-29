<?php
/**
 * Dashboard Social Tab
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Hikmah_Login\Helpers\Helper;

if ( ! class_exists( '\\Hikmah_Login\\Social\\Social_Manager' ) ) {
	?>
	<div class="hikmah-dash-section">
		<h3><?php esc_html_e( 'Connected Social Accounts', 'hikmah-login' ); ?></h3>
		<p class="hikmah-dash-desc"><?php esc_html_e( 'Social login is not available yet.', 'hikmah-login' ); ?></p>
	</div>
	<?php
	return;
}
?>
<div class="hikmah-dash-section">
	<h3><?php esc_html_e( 'Connected Social Accounts', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc"><?php esc_html_e( 'Manage your social login connections.', 'hikmah-login' ); ?></p>

	<?php if ( empty( $providers ) ) : ?>
		<p style="color:var(--hikmah-text-muted,#6b7280);text-align:center;padding:24px;">
			<?php esc_html_e( 'No social providers are enabled.', 'hikmah-login' ); ?>
		</p>
	<?php else : ?>
		<?php foreach ( $providers as $key => $provider ) : ?>
			<div class="hikmah-social-connection" style="
				display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
				padding:12px 16px;background:var(--hikmah-bg,#f9fafb);border-radius:var(--hikmah-radius-sm,8px);
				margin-bottom:8px;border:1px solid var(--hikmah-border,#e5e7eb);
			">
				<div style="display:flex;align-items:center;gap:12px;">
					<span style="font-size:24px;"><?php echo esc_html( 'google' === $key ? '🔵' : '📘' ); ?></span>
					<div>
						<strong><?php echo esc_html( is_callable( [ $provider, 'get_provider_name' ] ) ? $provider->get_provider_name() : (string) $key ); ?></strong>
						<br>
						<?php if ( isset( $linked_providers[ $key ] ) ) : ?>
							<small style="color:var(--hikmah-success,#10b981);">
								✅ <?php esc_html_e( 'Connected', 'hikmah-login' ); ?>
								<?php
								if ( ! empty( $linked_providers[ $key ]->linked_at ) ) {
									printf(
										/* translators: %s: Date */
										'(%s)',
										esc_html( Helper::format_datetime( $linked_providers[ $key ]->linked_at ) )
									);
								}
								?>
							</small>
						<?php else : ?>
							<small style="color:var(--hikmah-text-muted,#6b7280);"><?php esc_html_e( 'Not connected', 'hikmah-login' ); ?></small>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( isset( $linked_providers[ $key ] ) ) : ?>
					<button type="button" class="hikmah-btn hikmah-unlink-social"
						data-provider="<?php echo esc_attr( $key ); ?>"
						style="font-size:12px;padding:4px 12px;color:var(--hikmah-error,#dc2626);border-color:var(--hikmah-error,#dc2626);">
						<?php esc_html_e( 'Disconnect', 'hikmah-login' ); ?>
					</button>
				<?php else : ?>
					<a href="<?php echo esc_url( is_callable( [ $provider, 'get_authorization_url' ] ) ? $provider->get_authorization_url() : '#' ); ?>"
						class="hikmah-btn hikmah-btn-outline" style="font-size:12px;padding:4px 12px;">
						<?php esc_html_e( 'Connect', 'hikmah-login' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>