<?php
/**
 * Reset Password Form Template
 *
 * Override: yourtheme/hikmah-login/reset-password-form.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Already logged in?
if ( is_user_logged_in() ) {
    ?>
    <div class="hikmah-login-wrapper">
        <div class="hikmah-notice hikmah-notice-info">
            <p>
                <?php
                printf(
                    esc_html__( 'You are already logged in. Change your password from your %s.', 'hikmah-login' ),
                    '<a href="' . esc_url( \Hikmah_Login\Helpers\Helper::get_dashboard_url() ) . '">' . esc_html__( 'Dashboard', 'hikmah-login' ) . '</a>'
                );
                ?>
            </p>
        </div>
    </div>
    <?php
    return;
}

$form_id    = isset( $form_id ) ? $form_id : 'hikmah-reset-form-' . wp_rand( 1000, 9999 );
$show_title = isset( $show_title ) ? $show_title : true;
$logo_url   = get_option( 'hikmah_login_logo', '' );

// Get token and login from URL
$reset_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$reset_login = isset( $_GET['login'] ) ? sanitize_text_field( wp_unslash( $_GET['login'] ) ) : '';

// Validate the reset key
$reset_error = '';
$user = false;

if ( empty( $reset_key ) || empty( $reset_login ) ) {
    $reset_error = __( 'Invalid password reset link. Please request a new one.', 'hikmah-login' );
} else {
    $user = check_password_reset_key( $reset_key, $reset_login );

    if ( is_wp_error( $user ) ) {
        $error_code = $user->get_error_code();

        if ( 'expired_key' === $error_code ) {
            $reset_error = __( 'This password reset link has expired. Please request a new one.', 'hikmah-login' );
        } elseif ( 'invalid_key' === $error_code ) {
            $reset_error = __( 'This password reset link is invalid. Please request a new one.', 'hikmah-login' );
        } else {
            $reset_error = __( 'An error occurred. Please request a new password reset link.', 'hikmah-login' );
        }
    }
}

// Password requirements
$pass_min_length = 8;

do_action( 'hikmah_reset_password_before_form' );
?>

<div class="hikmah-login-wrapper hikmah-reset-wrapper" id="<?php echo esc_attr( $form_id ); ?>-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="hikmah-form-header">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="hikmah-form-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </div>
            <?php endif; ?>
            <h2 class="hikmah-form-title">
                <?php esc_html_e( 'Set New Password', 'hikmah-login' ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( 'Enter your new password below.', 'hikmah-login' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Error State (invalid/expired link) -->
    <?php if ( ! empty( $reset_error ) ) : ?>
        <div class="hikmah-notice hikmah-notice-error">
            <span class="hikmah-notice-icon">⚠️</span>
            <p><?php echo esc_html( $reset_error ); ?></p>
        </div>
        <div class="hikmah-form-footer">
            <p>
                <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_forgot_password_url() ); ?>" class="hikmah-btn hikmah-btn-primary">
                    <?php esc_html_e( 'Request New Reset Link', 'hikmah-login' ); ?>
                </a>
            </p>
            <p class="hikmah-register-link" style="margin-top:12px;">
                <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>">
                    &larr; <?php esc_html_e( 'Back to Sign In', 'hikmah-login' ); ?>
                </a>
            </p>
        </div>
        <?php do_action( 'hikmah_reset_password_after_form' ); ?>
        </div>
        <?php
        return;
    endif;
    ?>

    <!-- AJAX Notices -->
    <div class="hikmah-notice hikmah-notice-error hikmah-dismissible hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-error">
        <span class="hikmah-notice-icon">⚠️</span>
        <p class="hikmah-error-message"></p>
        <button type="button" class="hikmah-notice-close">&times;</button>
    </div>

    <div class="hikmah-notice hikmah-notice-success hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-success">
        <span class="hikmah-notice-icon">✅</span>
        <p class="hikmah-success-message"></p>
    </div>

    <!-- Reset Password Form -->
    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="hikmah-login-form hikmah-reset-form"
          method="post"
          action=""
          novalidate>

        <?php wp_nonce_field( 'hikmah_reset_password_action', 'hikmah_reset_nonce' ); ?>
        <input type="hidden" name="hikmah_action" value="reset_password">
        <input type="hidden" name="reset_key" value="<?php echo esc_attr( $reset_key ); ?>">
        <input type="hidden" name="reset_login" value="<?php echo esc_attr( $reset_login ); ?>">

        <!-- New Password -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-new-password" class="hikmah-label">
                <?php esc_html_e( 'New Password', 'hikmah-login' ); ?>
                <span class="hikmah-required">*</span>
            </label>
            <div class="hikmah-input-wrapper">
                <span class="hikmah-input-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <input type="password"
                       id="<?php echo esc_attr( $form_id ); ?>-new-password"
                       name="new_password"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Enter new password', 'hikmah-login' ); ?>"
                       required
                       minlength="<?php echo esc_attr( $pass_min_length ); ?>"
                       autocomplete="new-password"
                       autofocus>
                <button type="button" class="hikmah-toggle-password" tabindex="-1">
                    <svg class="hikmah-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="hikmah-eye-closed hikmah-hidden" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            <span class="hikmah-field-error" data-field="new_password"></span>

            <!-- Strength Meter -->
            <div class="hikmah-password-strength" id="<?php echo esc_attr( $form_id ); ?>-strength">
                <div class="hikmah-strength-bar">
                    <div class="hikmah-strength-fill"></div>
                </div>
                <span class="hikmah-strength-text"></span>
            </div>

            <ul class="hikmah-password-requirements" id="<?php echo esc_attr( $form_id ); ?>-requirements">
                <li data-rule="length" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php printf( esc_html__( 'At least %d characters', 'hikmah-login' ), $pass_min_length ); ?>
                </li>
                <li data-rule="upper" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One uppercase letter', 'hikmah-login' ); ?>
                </li>
                <li data-rule="lower" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One lowercase letter', 'hikmah-login' ); ?>
                </li>
                <li data-rule="number" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One number', 'hikmah-login' ); ?>
                </li>
            </ul>
        </div>

        <!-- Confirm New Password -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-confirm-password" class="hikmah-label">
                <?php esc_html_e( 'Confirm New Password', 'hikmah-login' ); ?>
                <span class="hikmah-required">*</span>
            </label>
            <div class="hikmah-input-wrapper">
                <span class="hikmah-input-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <input type="password"
                       id="<?php echo esc_attr( $form_id ); ?>-confirm-password"
                       name="confirm_password"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Confirm new password', 'hikmah-login' ); ?>"
                       required
                       autocomplete="new-password">
            </div>
            <span class="hikmah-field-error" data-field="confirm_password"></span>
        </div>

        <!-- Submit -->
        <div class="hikmah-field hikmah-submit-field">
            <button type="submit"
                    class="hikmah-btn hikmah-btn-primary hikmah-btn-full"
                    id="<?php echo esc_attr( $form_id ); ?>-submit">
                <span class="hikmah-btn-text">
                    <?php esc_html_e( 'Reset Password', 'hikmah-login' ); ?>
                </span>
                <span class="hikmah-btn-loading hikmah-hidden">
                    <svg class="hikmah-spinner" width="18" height="18" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e( 'Resetting...', 'hikmah-login' ); ?>
                </span>
            </button>
        </div>

        <?php do_action( 'hikmah_reset_password_form_bottom', $form_id ); ?>
    </form>

    <!-- Back to Login -->
    <div class="hikmah-form-footer">
        <p class="hikmah-register-link">
            <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>">
                &larr; <?php esc_html_e( 'Back to Sign In', 'hikmah-login' ); ?>
            </a>
        </p>
    </div>

    <?php do_action( 'hikmah_reset_password_after_form' ); ?>
</div>