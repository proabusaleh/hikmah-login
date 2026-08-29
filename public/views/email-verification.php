<?php
/**
 * Email Verification Status Template
 *
 * Displayed when user visits the verification page.
 *
 * @package Hikmah_Login
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logo_url = get_option( 'hikmah_login_logo', '' );
$status   = isset( $status ) ? $status : 'pending';
?>

<div class="hikmah-login-wrapper hikmah-verify-wrapper" style="text-align:center;">

    <?php if ( ! empty( $logo_url ) ) : ?>
        <div class="hikmah-form-logo">
            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
        </div>
    <?php endif; ?>

    <?php if ( 'success' === $status ) : ?>
        <!-- Verification Success -->
        <div class="hikmah-verify-success hikmah-fade-in">
            <div style="font-size:64px;margin-bottom:16px;">✅</div>
            <h2 class="hikmah-form-title" style="color:#10b981;">
                <?php esc_html_e( 'Email Verified!', 'hikmah-login' ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( 'Your email address has been verified successfully. You can now log in and access all features.', 'hikmah-login' ); ?>
            </p>
            <div style="margin-top:24px;">
                <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>"
                   class="hikmah-btn hikmah-btn-primary">
                    <?php esc_html_e( 'Go to Login', 'hikmah-login' ); ?>
                </a>
            </div>
        </div>

    <?php elseif ( 'expired' === $status ) : ?>
        <!-- Token Expired -->
        <div class="hikmah-verify-expired hikmah-fade-in">
            <div style="font-size:64px;margin-bottom:16px;">⏰</div>
            <h2 class="hikmah-form-title" style="color:#f59e0b;">
                <?php esc_html_e( 'Link Expired', 'hikmah-login' ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( 'This verification link has expired. Please request a new one.', 'hikmah-login' ); ?>
            </p>
            <div style="margin-top:24px;">
                <form class="hikmah-resend-form" style="max-width:360px;margin:0 auto;">
                    <?php wp_nonce_field( 'hikmah_login_nonce', 'nonce' ); ?>
                    <div class="hikmah-field">
                        <input type="email" name="email" class="hikmah-input"
                               placeholder="<?php esc_attr_e( 'Enter your email', 'hikmah-login' ); ?>"
                               required style="padding-left:12px;">
                    </div>
                    <button type="submit" class="hikmah-btn hikmah-btn-primary hikmah-btn-full">
                        <?php esc_html_e( 'Resend Verification Email', 'hikmah-login' ); ?>
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ( 'invalid' === $status ) : ?>
        <!-- Invalid Link -->
        <div class="hikmah-verify-invalid hikmah-fade-in">
            <div style="font-size:64px;margin-bottom:16px;">❌</div>
            <h2 class="hikmah-form-title" style="color:#ef4444;">
                <?php esc_html_e( 'Invalid Link', 'hikmah-login' ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( 'This verification link is invalid or has already been used. Please request a new one.', 'hikmah-login' ); ?>
            </p>
            <div style="margin-top:24px;">
                <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>"
                   class="hikmah-btn hikmah-btn-primary">
                    <?php esc_html_e( 'Back to Login', 'hikmah-login' ); ?>
                </a>
            </div>
        </div>

    <?php else : ?>
        <!-- Pending Verification -->
        <div class="hikmah-verify-pending hikmah-fade-in">
            <div style="font-size:64px;margin-bottom:16px;">📧</div>
            <h2 class="hikmah-form-title">
                <?php esc_html_e( 'Verify Your Email', 'hikmah-login' ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( 'We sent a verification link to your email address. Please check your inbox and click the link to verify your account.', 'hikmah-login' ); ?>
            </p>
            <div class="hikmah-notice hikmah-notice-info" style="text-align:left;margin-top:16px;">
                <p>
                    <strong><?php esc_html_e( "Didn't receive the email?", 'hikmah-login' ); ?></strong><br>
                    <?php esc_html_e( 'Check your spam folder or click the button below to resend.', 'hikmah-login' ); ?>
                </p>
            </div>
            <div style="margin-top:24px;">
                <button class="hikmah-btn hikmah-btn-primary hikmah-resend-btn">
                    <?php esc_html_e( 'Resend Verification Email', 'hikmah-login' ); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="hikmah-form-footer">
        <p class="hikmah-register-link">
            <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>">
                &larr; <?php esc_html_e( 'Back to Sign In', 'hikmah-login' ); ?>
            </a>
        </p>
    </div>
</div>