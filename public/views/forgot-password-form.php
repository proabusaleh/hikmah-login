<?php
/**
 * Forgot Password Form Template
 *
 * Override: yourtheme/hikmah-login/forgot-password-form.php
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
                    esc_html__( 'You are already logged in. Want to change your password? Visit your %s.', 'hikmah-login' ),
                    '<a href="' . esc_url( \Hikmah_Login\Helpers\Helper::get_dashboard_url() ) . '">' . esc_html__( 'Dashboard', 'hikmah-login' ) . '</a>'
                );
                ?>
            </p>
        </div>
    </div>
    <?php
    return;
}

$form_id    = isset( $form_id ) ? $form_id : 'hikmah-forgot-form-' . wp_rand( 1000, 9999 );
$show_title = isset( $show_title ) ? $show_title : true;
$logo_url   = get_option( 'hikmah_login_logo', '' );

// URL messages
$fp_message = '';
$fp_error   = '';

if ( isset( $_GET['checkemail'] ) && 'confirm' === $_GET['checkemail'] ) {
    $fp_message = __( 'Check your email for the confirmation link.', 'hikmah-login' );
}

if ( isset( $_GET['error'] ) ) {
    $error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
    $error_messages = [
        'invalid_email' => __( 'Please enter a valid email address.', 'hikmah-login' ),
        'invalidcombo'  => __( 'No account found with that email or username.', 'hikmah-login' ),
        'rate_limited'  => __( 'Too many requests. Please wait a few minutes and try again.', 'hikmah-login' ),
    ];
    $fp_error = $error_messages[ $error_code ] ?? __( 'An error occurred. Please try again.', 'hikmah-login' );
}

do_action( 'hikmah_forgot_password_before_form' );
?>

<div class="hikmah-login-wrapper hikmah-forgot-wrapper" id="<?php echo esc_attr( $form_id ); ?>-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="hikmah-form-header">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="hikmah-form-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </div>
            <?php endif; ?>
            <h2 class="hikmah-form-title">
                <?php echo esc_html( $atts['title'] ?? __( 'Forgot Password?', 'hikmah-login' ) ); ?>
            </h2>
            <p class="hikmah-form-subtitle">
                <?php esc_html_e( "No worries! Enter your email or username and we'll send you a reset link.", 'hikmah-login' ); ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Messages -->
    <?php if ( ! empty( $fp_message ) ) : ?>
        <div class="hikmah-notice hikmah-notice-success hikmah-dismissible">
            <span class="hikmah-notice-icon">📧</span>
            <p><?php echo esc_html( $fp_message ); ?></p>
            <button type="button" class="hikmah-notice-close">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $fp_error ) ) : ?>
        <div class="hikmah-notice hikmah-notice-error hikmah-dismissible">
            <span class="hikmah-notice-icon">⚠️</span>
            <p><?php echo esc_html( $fp_error ); ?></p>
            <button type="button" class="hikmah-notice-close">&times;</button>
        </div>
    <?php endif; ?>

    <!-- AJAX Error -->
    <div class="hikmah-notice hikmah-notice-error hikmah-dismissible hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-error">
        <span class="hikmah-notice-icon">⚠️</span>
        <p class="hikmah-error-message"></p>
        <button type="button" class="hikmah-notice-close">&times;</button>
    </div>

    <!-- AJAX Success -->
    <div class="hikmah-notice hikmah-notice-success hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-success">
        <span class="hikmah-notice-icon">📧</span>
        <p class="hikmah-success-message"></p>
    </div>

    <!-- Forgot Password Form -->
    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="hikmah-login-form hikmah-forgot-form"
          method="post"
          action=""
          novalidate>

        <?php wp_nonce_field( 'hikmah_forgot_password_action', 'hikmah_forgot_nonce' ); ?>
        <input type="hidden" name="hikmah_action" value="forgot_password">

        <!-- Honeypot -->
        <div class="hikmah-field hikmah-hp-field" aria-hidden="true"
             style="position:absolute;left:-9999px;">
            <input type="text" name="website_url" tabindex="-1" autocomplete="off">
        </div>

        <!-- Email / Username Field -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-user-login" class="hikmah-label">
                <?php esc_html_e( 'Email Address or Username', 'hikmah-login' ); ?>
                <span class="hikmah-required">*</span>
            </label>
            <div class="hikmah-input-wrapper">
                <span class="hikmah-input-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
                <input type="text"
                       id="<?php echo esc_attr( $form_id ); ?>-user-login"
                       name="user_login"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Enter your email or username', 'hikmah-login' ); ?>"
                       required
                       autocomplete="username"
                       autofocus>
            </div>
            <span class="hikmah-field-error" data-field="user_login"></span>
        </div>

        <!-- Submit -->
        <div class="hikmah-field hikmah-submit-field">
            <button type="submit"
                    class="hikmah-btn hikmah-btn-primary hikmah-btn-full"
                    id="<?php echo esc_attr( $form_id ); ?>-submit">
                <span class="hikmah-btn-text">
                    <?php esc_html_e( 'Send Reset Link', 'hikmah-login' ); ?>
                </span>
                <span class="hikmah-btn-loading hikmah-hidden">
                    <svg class="hikmah-spinner" width="18" height="18" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e( 'Sending...', 'hikmah-login' ); ?>
                </span>
            </button>
        </div>

        <?php do_action( 'hikmah_forgot_password_form_bottom', $form_id ); ?>
    </form>

    <!-- Back to Login -->
    <div class="hikmah-form-footer">
        <p class="hikmah-register-link">
            <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>">
                &larr; <?php esc_html_e( 'Back to Sign In', 'hikmah-login' ); ?>
            </a>
        </p>
    </div>

    <?php do_action( 'hikmah_forgot_password_after_form' ); ?>
</div>