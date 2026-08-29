<?php
/**
 * Dashboard Security Tab
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
	<h3><?php esc_html_e( 'Security Settings', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc"><?php esc_html_e( 'Manage your password, two-factor authentication and verification status.', 'hikmah-login' ); ?></p>

	<!-- Password Change -->
	<div class="hikmah-dash-card" style="margin-bottom:24px;padding:20px;background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);">
		<h4 style="margin:0 0 6px;color:var(--hikmah-text,#111827);">🔑 <?php esc_html_e( 'Change Password', 'hikmah-login' ); ?></h4>

		<form id="hikmah-password-form" class="hikmah-dash-form">
			<?php if ( $password_changed ) : ?>
				<p class="hikmah-field-hint">
					<?php
					printf(
						/* translators: %s: Date */
						esc_html__( 'Last changed: %s', 'hikmah-login' ),
						esc_html( Helper::format_datetime( $password_changed ) )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="hikmah-field">
				<label class="hikmah-label"><?php esc_html_e( 'Current Password', 'hikmah-login' ); ?></label>
				<input type="password" name="current_password" class="hikmah-input" required autocomplete="current-password" />
			</div>

			<div class="hikmah-field">
				<label class="hikmah-label"><?php esc_html_e( 'New Password', 'hikmah-login' ); ?></label>
				<input type="password" name="new_password" class="hikmah-input" required minlength="8" autocomplete="new-password" />
			</div>

			<div class="hikmah-field">
				<label class="hikmah-label"><?php esc_html_e( 'Confirm New Password', 'hikmah-login' ); ?></label>
				<input type="password" name="confirm_password" class="hikmah-input" required autocomplete="new-password" />
			</div>

			<button type="submit" class="hikmah-btn hikmah-btn-primary"><?php esc_html_e( 'Update Password', 'hikmah-login' ); ?></button>
			<span class="hikmah-dash-status" aria-live="polite"></span>
		</form>
	</div>

	<!-- Email verification status -->
	<div class="hikmah-dash-card" style="margin-bottom:24px;padding:20px;background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);">
		<h4 style="margin:0 0 6px;color:var(--hikmah-text,#111827);">📧 <?php esc_html_e( 'Email Verification', 'hikmah-login' ); ?></h4>
		<p style="margin:0">
			<span class="hikmah-tag hikmah-tag-<?php echo $email_verified ? 'success' : 'warning'; ?>">
				<?php echo $email_verified ? esc_html__( 'Verified', 'hikmah-login' ) : esc_html__( 'Not verified', 'hikmah-login' ); ?>
			</span>
			<?php if ( ! $email_verified ) : ?>
				<a href="<?php echo esc_url( Helper::get_dashboard_url() ); ?>#hikmah-verify-email" class="hikmah-btn hikmah-btn-outline" style="margin-left:8px;font-size:12px;padding:4px 10px;">
					<?php esc_html_e( 'Resend Verification', 'hikmah-login' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</div>

	<!-- 2FA Setup Wizard -->
	<?php
	if ( Helper::is_feature_enabled( '2fa_enabled' ) ) {
		$template = HIKMAH_LOGIN_DIR . 'public/views/2fa-setup.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	} else {
		?>
		<div class="hikmah-dash-card" style="padding:20px;background:var(--hikmah-bg,#f9fafb);border:1px solid var(--hikmah-border,#e5e7eb);border-radius:var(--hikmah-radius,12px);">
			<h4 style="margin:0 0 6px;color:var(--hikmah-text,#111827);">🔐 <?php esc_html_e( 'Two-Factor Authentication', 'hikmah-login' ); ?></h4>
			<p class="hikmah-dash-desc"><?php esc_html_e( 'Two-factor authentication is currently disabled.', 'hikmah-login' ); ?></p>
		</div>
		<?php
	}
	?>
</div>