<?php
/**
 * Two-Factor Authentication Setup Wizard
 *
 * Rendered on the user profile page (show_user_profile) and via the
 * [hikmah_2fa_setup] shortcode.
 *
 * Available template variables:
 *   $user_id, $user, $two_factor (Two_Factor instance),
 *   $is_enabled, $method, $backup_count, $needs_setup, $is_admin
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="hikmah-2fa-setup" class="hikmah-2fa-setup">
	<style>
		#hikmah-2fa-setup{max-width:640px;font-family:inherit}
		#hikmah-2fa-setup .hikmah-hidden{display:none}
		#hikmah-2fa-setup .hikmah-card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px 24px;margin-bottom:16px}
		#hikmah-2fa-setup .hikmah-card h3{margin:0 0 12px;color:#111827}
		#hikmah-2fa-setup .hikmah-btn{display:inline-block;background:#f3f4f6;border:1px solid #e5e7eb;color:#111827;border-radius:6px;padding:8px 16px;font-size:14px;cursor:pointer}
		#hikmah-2fa-setup .hikmah-btn-primary{background:#2563eb;border-color:#2563eb;color:#fff}
		#hikmah-2fa-setup .hikmah-btn-danger{background:#dc2626;border-color:#dc2626;color:#fff;margin-left:8px}
		#hikmah-2fa-setup .hikmah-input{width:100%;max-width:220px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px;padding:8px 12px;font-size:14px}
		#hikmah-2fa-setup .hikmah-notice{display:block;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-size:14px;animation:none}
		#hikmah-2fa-setup .hikmah-notice-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
		#hikmah-2fa-setup .hikmah-notice-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
		#hikmah-2fa-setup .hikmah-notice-warning{background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
		#hikmah-2fa-setup .hikmah-2fa-row{display:flex;gap:12px;flex-wrap:wrap}
		#hikmah-2fa-setup .hikmah-card h3{margin:0 0 12px;color:#111827}
		#hikmah-2fa-setup .hikmah-2fa-row{display:flex;gap:12px;flex-wrap:wrap}
		#hikmah-2fa-setup .hikmah-2fa-method-card{flex:1 1 220px;border:2px solid #e5e7eb;border-radius:8px;padding:14px;cursor:pointer;transition:border-color .15s}
		#hikmah-2fa-setup .hikmah-2fa-method-card:has(input:checked){border-color:#2563eb;background:#eff6ff}
		#hikmah-2fa-setup .hikmah-2fa-method-card input{margin-right:8px}
		#hikmah-2fa-setup .hikmah-2fa-method-card .hikmah-card-title{font-weight:600;color:#111827}
		#hikmah-2fa-setup .hikmah-2fa-method-card small{display:block;color:#6b7280;margin-top:4px}
		#hikmah-2fa-setup .hikmah-2fa-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
		#hikmah-2fa-setup .hikmah-2fa-code{font-family:ui-monospace,Consolas,monospace;letter-spacing:1px}
		#hikmah-2fa-setup .hikmah-2fa-secret-box{background:#f3f4f6;border:1px dashed #d1d5db;border-radius:6px;padding:10px 12px;word-break:break-all;font-family:ui-monospace,Consolas,monospace;font-size:13px}
		#hikmah-2fa-setup .hikmah-2fa-badge{display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600}
		#hikmah-2fa-setup .hikmah-2fa-badge-on{background:#dcfce7;color:#166534}
		#hikmah-2fa-setup .hikmah-2fa-badge-off{background:#fee2e2;color:#991b1b}
		#hikmah-2fa-setup .hikmah-2fa-required-banner{background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:14px}
		#hikmah-2fa-setup .hikmah-2fa-backup-list{columns:2;-webkit-columns:2;-moz-columns:2;column-gap:24px;margin:8px 0;padding:0;list-style:none}
		#hikmah-2fa-setup .hikmah-2fa-backup-list li{padding:4px 0;font-family:ui-monospace,Consolas,monospace}
		#hikmah-2fa-setup .hikmah-2fa-note{font-size:13px;color:#6b7280;margin-top:8px}
	</style>

	<?php if ( $needs_setup ) : ?>
		<div class="hikmah-2fa-required-banner">
			<?php esc_html_e( 'Two-factor authentication is required for your account. Complete the setup below to stay secure.', 'hikmah-login' ); ?>
		</div>
	<?php endif; ?>

	<div class="hikmah-notice hikmah-hidden" id="hikmah-2fa-notice" aria-live="polite"></div>

	<?php if ( $is_enabled ) : ?>
		<div class="hikmah-card" id="hikmah-2fa-status-card">
			<h3><?php esc_html_e( 'Two-Factor Authentication', 'hikmah-login' ); ?></h3>

			<p>
				<span class="hikmah-2fa-badge hikmah-2fa-badge-on"><?php esc_html_e( 'Enabled', 'hikmah-login' ); ?></span>
				<span class="hikmah-2fa-note">
					<?php
					printf(
						/* translators: 1: Method label */
						esc_html__( 'Method: %1$s', 'hikmah-login' ),
						esc_html( 'email' === $method ? __( 'Email OTP', 'hikmah-login' ) : __( 'Authenticator App', 'hikmah-login' ) )
					);
					?>
				</span>
			</p>

			<?php if ( $backup_count > 0 ) : ?>
				<p class="hikmah-2fa-note">
					<?php
					printf(
						/* translators: %d: Backup code count */
						esc_html( _n( '%d backup code remaining.', '%d backup codes remaining.', $backup_count, 'hikmah-login' ) ),
						(int) $backup_count
					);
					?>
				</p>
			<?php else : ?>
				<p class="hikmah-2fa-note">
					<?php esc_html_e( 'No backup codes remaining. Generate new ones to avoid being locked out.', 'hikmah-login' ); ?>
				</p>
			<?php endif; ?>

			<p style="margin-top:12px;margin-bottom:0">
				<button type="button" class="hikmah-btn" id="hikmah-2fa-new-backup">
					<?php esc_html_e( 'Generate New Backup Codes', 'hikmah-login' ); ?>
				</button>
				<button type="button" class="hikmah-btn" id="hikmah-2fa-disable">
					<?php esc_html_e( 'Disable Two-Factor', 'hikmah-login' ); ?>
				</button>
			</p>

			<div class="hikmah-hidden" id="hikmah-2fa-disable-panel" style="margin-top:12px">
				<p class="hikmah-2fa-note">
					<?php esc_html_e( 'Enter your password to confirm disabling two-factor authentication.', 'hikmah-login' ); ?>
				</p>
				<input type="password" id="hikmah-2fa-disable-password"
					class="hikmah-input" autocomplete="current-password"
					placeholder="<?php esc_attr_e( 'Current password', 'hikmah-login' ); ?>" />
				<button type="button" class="hikmah-btn hikmah-btn-danger" id="hikmah-2fa-disable-confirm">
					<?php esc_html_e( 'Confirm Disable', 'hikmah-login' ); ?>
				</button>
			</div>
		</div>

		<div class="hikmah-hidden" id="hikmah-2fa-backup-container">
			<div class="hikmah-card">
				<h3><?php esc_html_e( 'Your Backup Codes', 'hikmah-login' ); ?></h3>
				<p class="hikmah-2fa-note">
					<?php esc_html_e( 'Save these codes in a secure place. Each code can only be used once.', 'hikmah-login' ); ?>
				</p>
				<ul class="hikmah-2fa-backup-list" id="hikmah-2fa-backup-codes"></ul>
				<button type="button" class="hikmah-btn" id="hikmah-2fa-copy-backup">
					<?php esc_html_e( 'Copy Codes', 'hikmah-login' ); ?>
				</button>
			</div>
		</div>
	<?php else : ?>
		<div class="hikmah-card">
			<h3><?php esc_html_e( 'Enable Two-Factor Authentication', 'hikmah-login' ); ?></h3>
			<p class="hikmah-2fa-note" style="margin-top:0">
				<?php esc_html_e( 'Add an extra layer of security to your account. Choose a verification method below.', 'hikmah-login' ); ?>
			</p>

			<div class="hikmah-2fa-row">
				<label class="hikmah-2fa-method-card">
					<input type="radio" name="hikmah-2fa-method" value="email" checked />
					<span class="hikmah-card-title"><?php esc_html_e( 'Email OTP', 'hikmah-login' ); ?></span>
					<small><?php esc_html_e( 'A 6-digit code sent to your email at each sign-in.', 'hikmah-login' ); ?></small>
				</label>

				<label class="hikmah-2fa-method-card">
					<input type="radio" name="hikmah-2fa-method" value="authenticator" />
					<span class="hikmah-card-title"><?php esc_html_e( 'Authenticator App', 'hikmah-login' ); ?></span>
					<small><?php esc_html_e( 'Scan a QR code with Google Authenticator, Authy, or similar.', 'hikmah-login' ); ?></small>
				</label>
			</div>

			<p style="margin:16px 0 0">
				<button type="button" class="hikmah-btn hikmah-btn-primary" id="hikmah-2fa-start-setup">
					<?php esc_html_e( 'Start Setup', 'hikmah-login' ); ?>
				</button>
			</p>
		</div>
	<?php endif; ?>

	<div class="hikmah-hidden" id="hikmah-2fa-email-step">
		<div class="hikmah-card">
			<h3><?php esc_html_e( 'Verify Your Email', 'hikmah-login' ); ?></h3>
			<p class="hikmah-2fa-note" style="margin-top:0">
				<?php esc_html_e( 'We sent a 6-digit code to your email address. Enter it below.', 'hikmah-login' ); ?>
			</p>
			<input type="text" id="hikmah-2fa-email-code" class="hikmah-input hikmah-2fa-code"
				maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code"
				placeholder="<?php esc_attr_e( '000000', 'hikmah-login' ); ?>" />
			<p style="margin:12px 0 0">
				<button type="button" class="hikmah-btn hikmah-btn-primary" id="hikmah-2fa-email-verify">
					<?php esc_html_e( 'Verify & Enable', 'hikmah-login' ); ?>
				</button>
				<button type="button" class="hikmah-btn" id="hikmah-2fa-email-resend">
					<?php esc_html_e( 'Resend Code', 'hikmah-login' ); ?>
				</button>
			</p>
		</div>
	</div>

	<div class="hikmah-hidden" id="hikmah-2fa-auth-step">
		<div class="hikmah-card">
			<h3><?php esc_html_e( 'Scan the QR Code', 'hikmah-login' ); ?></h3>
			<p class="hikmah-2fa-note" style="margin-top:0">
				<?php esc_html_e( 'Open your authenticator app and scan the code below, or enter the secret manually.', 'hikmah-login' ); ?>
			</p>
			<div style="text-align:center"><img id="hikmah-2fa-qr" alt="<?php esc_attr_e( 'Authenticator QR code', 'hikmah-login' ); ?>" width="200" height="200" /></div>
			<p class="hikmah-2fa-secret-box" id="hikmah-2fa-secret"></p>
			<input type="text" id="hikmah-2fa-auth-code" class="hikmah-input hikmah-2fa-code"
				maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code"
				placeholder="<?php esc_attr_e( '000000', 'hikmah-login' ); ?>" />
			<p style="margin:12px 0 0">
				<button type="button" class="hikmah-btn hikmah-btn-primary" id="hikmah-2fa-auth-verify">
					<?php esc_html_e( 'Verify & Enable', 'hikmah-login' ); ?>
				</button>
			</p>
		</div>
	</div>
</div>