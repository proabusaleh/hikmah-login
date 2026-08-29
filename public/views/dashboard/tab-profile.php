<?php
/**
 * Dashboard Profile Tab
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
	<h3><?php esc_html_e( 'Profile Information', 'hikmah-login' ); ?></h3>
	<p class="hikmah-dash-desc"><?php esc_html_e( 'Update your personal information and avatar.', 'hikmah-login' ); ?></p>

	<!-- Avatar Section -->
	<div class="hikmah-avatar-section" style="display:flex;align-items:center;gap:20px;margin-bottom:24px;padding:16px;background:var(--hikmah-bg,#f9fafb);border-radius:var(--hikmah-radius,12px);">
		<div class="hikmah-avatar-preview" style="position:relative;">
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" width="80" height="80" class="hikmah-avatar-img" style="border-radius:50%;object-fit:cover;" />
			<label for="hikmah-avatar-upload" style="position:absolute;bottom:0;right:0;background:var(--hikmah-primary,#2563eb);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:12px;border:none;">✏️</label>
			<input type="file" id="hikmah-avatar-upload" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" />
		</div>
		<div>
			<strong><?php echo esc_html( $user->display_name ); ?></strong>
			<br><small style="color:var(--hikmah-text-muted,#6b7280);"><?php echo esc_html( $user->user_email ); ?></small>
			<br><small style="color:var(--hikmah-text-muted,#6b7280);">
				<?php
				printf(
					/* translators: %s: Registration date */
					esc_html__( 'Member since %s', 'hikmah-login' ),
					esc_html( Helper::format_datetime( $user->user_registered ) )
				);
				?>
			</small>
			<?php if ( $avatar_id ) : ?>
				<br><button type="button" class="hikmah-btn" id="hikmah-remove-avatar" style="margin-top:8px;font-size:12px;padding:4px 10px;color:var(--hikmah-error,#dc2626);">
					<?php esc_html_e( 'Remove Avatar', 'hikmah-login' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<!-- Profile Form -->
	<form id="hikmah-profile-form" class="hikmah-dash-form">
		<div class="hikmah-field-row hikmah-name-row" style="display:flex;gap:16px;flex-wrap:wrap">
			<div class="hikmah-field" style="flex:1;min-width:180px;">
				<label class="hikmah-label"><?php esc_html_e( 'First Name', 'hikmah-login' ); ?></label>
				<input type="text" name="first_name" class="hikmah-input" value="<?php echo esc_attr( $user->first_name ); ?>" />
			</div>
			<div class="hikmah-field" style="flex:1;min-width:180px;">
				<label class="hikmah-label"><?php esc_html_e( 'Last Name', 'hikmah-login' ); ?></label>
				<input type="text" name="last_name" class="hikmah-input" value="<?php echo esc_attr( $user->last_name ); ?>" />
			</div>
		</div>

		<div class="hikmah-field">
			<label class="hikmah-label"><?php esc_html_e( 'Display Name', 'hikmah-login' ); ?></label>
			<input type="text" name="display_name" class="hikmah-input" value="<?php echo esc_attr( $user->display_name ); ?>" />
		</div>

		<div class="hikmah-field">
			<label class="hikmah-label"><?php esc_html_e( 'Email Address', 'hikmah-login' ); ?></label>
			<input type="email" name="email" class="hikmah-input" value="<?php echo esc_attr( $user->user_email ); ?>" />
		</div>

		<div class="hikmah-field">
			<label class="hikmah-label"><?php esc_html_e( 'Website', 'hikmah-login' ); ?></label>
			<input type="url" name="url" class="hikmah-input" value="<?php echo esc_attr( $user->user_url ); ?>" placeholder="https://" />
		</div>

		<div class="hikmah-field">
			<label class="hikmah-label"><?php esc_html_e( 'Bio', 'hikmah-login' ); ?></label>
			<textarea name="description" class="hikmah-input" rows="3" style="resize:vertical;"><?php echo esc_textarea( $user->description ); ?></textarea>
		</div>

		<?php
		/**
		 * Fires inside the dashboard profile form for custom fields.
		 *
		 * @since 1.0.0
		 * @param \WP_User $user Current user.
		 */
		do_action( 'hikmah_dashboard_profile_fields', $user );
		?>

		<button type="submit" class="hikmah-btn hikmah-btn-primary"><?php esc_html_e( 'Save Profile', 'hikmah-login' ); ?></button>
		<span class="hikmah-dash-status" aria-live="polite"></span>
	</form>
</div>