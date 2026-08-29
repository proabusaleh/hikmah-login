<?php
/**
 * Dashboard Privacy Tab (GDPR)
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="hikmah-dash-section">
	<h3><?php esc_html_e( 'Privacy', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc"><?php esc_html_e( 'Take control of your data — export a copy or permanently delete your account.', 'hikmah-login' ); ?></p>

	<!-- Data Export -->
	<div class="hikmah-dash-card" style="margin-bottom:24px;padding:20px;background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);">
		<h4 style="margin:0 0 6px;color:var(--hikmah-text,#111827);">📦 <?php esc_html_e( 'Export Your Data', 'hikmah-login' ); ?></h4>
		<p style="margin:0 0 14px;color:var(--hikmah-text-muted,#6b7280);font-size:14px;">
			<?php esc_html_e( 'Download a JSON file containing your profile, login history, sessions and connections. Exports are single-use and expire after 15 minutes.', 'hikmah-login' ); ?>
		</p>
		<button type="button" class="hikmah-btn hikmah-btn-primary" id="hikmah-export-data">
			<?php esc_html_e( 'Create Export', 'hikmah-login' ); ?>
		</button>
		<span class="hikmah-dash-status" aria-live="polite"></span>
	</div>

	<!-- Account Deletion -->
	<?php if ( ! empty( $deletion_enabled ) ) : ?>
		<div class="hikmah-dash-card" style="padding:20px;background:var(--hikmah-error-bg,#fef2f2);border:1px solid var(--hikmah-error,#dc2626);border-radius:var(--hikmah-radius,12px);">
			<h4 style="margin:0 0 6px;color:var(--hikmah-error,#dc2626);">⚠️ <?php esc_html_e( 'Delete Account', 'hikmah-login' ); ?></h4>

			<div class="hikmah-notice hikmah-notice-warning">
				<p><strong><?php esc_html_e( 'Warning:', 'hikmah-login' ); ?></strong> <?php esc_html_e( 'This action is permanent and cannot be undone. All your data will be deleted.', 'hikmah-login' ); ?></p>
			</div>

			<p><?php esc_html_e( 'Deleting your account will:', 'hikmah-login' ); ?></p>
			<ul style="color:var(--hikmah-text-muted,#6b7280);font-size:14px;line-height:2;">
				<li><?php esc_html_e( 'Permanently delete your profile and personal data', 'hikmah-login' ); ?></li>
				<li><?php esc_html_e( 'Remove all your login history and session data', 'hikmah-login' ); ?></li>
				<li><?php esc_html_e( 'Disconnect all social login connections', 'hikmah-login' ); ?></li>
				<li><?php esc_html_e( 'Delete your 2FA settings and backup codes', 'hikmah-login' ); ?></li>
			</ul>

			<form id="hikmah-delete-form" class="hikmah-dash-form" style="margin-top:20px;">
				<div class="hikmah-field">
					<label class="hikmah-label">
						<?php
						printf(
							/* translators: %s: Username */
							esc_html__( 'Type "%s" to confirm:', 'hikmah-login' ),
							'<strong>' . esc_html( $user->user_login ) . '</strong>'
						);
						?>
					</label>
					<input type="text" name="confirm_username" class="hikmah-input" required
						data-expected="<?php echo esc_attr( $user->user_login ); ?>" />
				</div>

				<div class="hikmah-field">
					<label class="hikmah-label"><?php esc_html_e( 'Enter your password:', 'hikmah-login' ); ?></label>
					<input type="password" name="password" class="hikmah-input" required autocomplete="current-password" />
				</div>

				<button type="submit" class="hikmah-btn" id="hikmah-delete-btn"
					style="background:var(--hikmah-error,#dc2626);color:#fff;" disabled>
					🗑️ <?php esc_html_e( 'Permanently Delete My Account', 'hikmah-login' ); ?>
				</button>
				<span class="hikmah-dash-status" aria-live="polite"></span>
			</form>
		</div>
	<?php else : ?>
		<div class="hikmah-dash-card" style="padding:20px;background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);">
			<h4 style="margin:0 0 6px;color:var(--hikmah-text,#111827);">⚠️ <?php esc_html_e( 'Delete Account', 'hikmah-login' ); ?></h4>
			<p style="margin:0;color:var(--hikmah-text-muted,#6b7280);font-size:14px;"><?php esc_html_e( 'Account deletion is not enabled for this site.', 'hikmah-login' ); ?></p>
		</div>
	<?php endif; ?>
</div>