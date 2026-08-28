<?php
/**
 * Login Form Template
 *
 * This template can be overridden by copying it to:
 * yourtheme/hikmah-login/login-form.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 *
 * Available variables:
 * @var array  $atts         Shortcode attributes.
 * @var string $redirect_to  Redirect URL after login.
 * @var bool   $show_title   Whether to show form title.
 * @var string $form_id      Unique form ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Already logged in?
if ( is_user_logged_in() ) {
    $dashboard_url = \Hikmah_Login\Helpers\Helper::get_dashboard_url();
    $user = wp_get_current_user();
    ?>
    <div class="hikmah-logged-in-notice">
        <div class="hikmah-notice hikmah-notice-info">
            <p>
                <?php
                printf(
                    /* translators: 1: User display name 2: Dashboard URL 3: Logout URL */
                    esc_html__( 'Welcome back, %1$s! Go to your %2$s or %3$s.', 'hikmah-login' ),
                    '<strong>' . esc_html( $user->display_name ) . '</strong>',
                    '<a href="' . esc_url( $dashboard_url ) . '">' . esc_html__( 'Dashboard', 'hikmah-login' ) . '</a>',
                    '<a href="' . esc_url( \Hikmah_Login\Helpers\Helper::get_logout_url() ) . '">' . esc_html__( 'Logout', 'hikmah-login' ) . '</a>'
                );
                ?>
            </p>
        </div>
    </div>
    <?php
    return;
}

// Default variables
$atts        = isset( $atts ) ? $atts : [];
$redirect_to = isset( $redirect_to ) ? $redirect_to : '';
$show_title  = isset( $show_title ) ? $show_title : true;
$form_id     = isset( $form_id ) ? $form_id : 'hikmah-login-form-' . wp_rand( 1000, 9999 );

// Get settings
$logo_url         = get_option( 'hikmah_login_logo', '' );
$register_enabled = 'yes' === get_option( 'hikmah_registration_enabled', 'yes' );
$forgot_enabled   = true; // Always allow forgot password
$google_enabled   = 'yes' === get_option( 'hikmah_google_login_enabled', 'no' );
$facebook_enabled = 'yes' === get_option( 'hikmah_facebook_login_enabled', 'no' );
$captcha_enabled  = 'yes' === get_option( 'hikmah_captcha_enabled', 'no' );
$captcha_type     = get_option( 'hikmah_captcha_type', 'recaptcha_v2' );
$site_key         = get_option( 'hikmah_recaptcha_site_key', '' );

// Build redirect URL
if ( empty( $redirect_to ) ) {
    $redirect_to = isset( $_GET['redirect_to'] )
        ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) )
        : get_option( 'hikmah_login_redirect_url', '' );
}

// Check for messages in URL
$login_message = '';
$login_error   = '';

if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) {
    $login_error = __( 'Login failed. Please check your credentials.', 'hikmah-login' );
}

if ( isset( $_GET['session_expired'] ) ) {
    $login_error = __( 'Your session has expired. Please log in again.', 'hikmah-login' );
}

if ( isset( $_GET['loggedout'] ) && 'true' === $_GET['loggedout'] ) {
    $login_message = __( 'You have been logged out successfully.', 'hikmah-login' );
}

if ( isset( $_GET['registered'] ) && 'true' === $_GET['registered'] ) {
    $login_message = __( 'Registration successful! Please log in.', 'hikmah-login' );
}

if ( isset( $_GET['verified'] ) && 'true' === $_GET['verified'] ) {
    $login_message = __( 'Email verified successfully! Please log in.', 'hikmah-login' );
}

if ( isset( $_GET['password_reset'] ) && 'true' === $_GET['password_reset'] ) {
    $login_message = __( 'Password reset successful! Please log in with your new password.', 'hikmah-login' );
}

/**
 * Fires before the login form is rendered.
 *
 * @since 1.0.0
 * @param array $atts Shortcode attributes.
 */
do_action( 'hikmah_login_before_form', $atts );
?>

<div class="hikmah-login-wrapper" id="<?php echo esc_attr( $form_id ); ?>-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="hikmah-form-header">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="hikmah-form-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>"
                         alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </div>
            <?php endif; ?>
            <h2 class="hikmah-form-title">
                <?php echo esc_html( $atts['title'] ?? __( 'Sign In', 'hikmah-login' ) ); ?>
            </h2>
            <?php if ( ! empty( $atts['subtitle'] ) ) : ?>
                <p class="hikmah-form-subtitle">
                    <?php echo esc_html( $atts['subtitle'] ); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if ( ! empty( $login_message ) ) : ?>
        <div class="hikmah-notice hikmah-notice-success hikmah-dismissible">
            <span class="hikmah-notice-icon">✅</span>
            <p><?php echo esc_html( $login_message ); ?></p>
            <button type="button" class="hikmah-notice-close" aria-label="<?php esc_attr_e( 'Close', 'hikmah-login' ); ?>">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <div class="hikmah-notice hikmah-notice-error hikmah-dismissible hikmah-hidden" id="<?php echo esc_attr( $form_id ); ?>-error">
        <span class="hikmah-notice-icon">⚠️</span>
        <p class="hikmah-error-message"></p>
        <button type="button" class="hikmah-notice-close" aria-label="<?php esc_attr_e( 'Close', 'hikmah-login' ); ?>">&times;</button>
    </div>
    <?php if ( ! empty( $login_error ) ) : ?>
        <div class="hikmah-notice hikmah-notice-error hikmah-dismissible">
            <span class="hikmah-notice-icon">⚠️</span>
            <p><?php echo esc_html( $login_error ); ?></p>
            <button type="button" class="hikmah-notice-close" aria-label="<?php esc_attr_e( 'Close', 'hikmah-login' ); ?>">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Social Login Buttons (Top) -->
    <?php if ( $google_enabled || $facebook_enabled ) : ?>
        <div class="hikmah-social-login">
            <?php if ( $google_enabled ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'hikmah_social', 'google', site_url( 'wp-login.php' ) ) ); ?>"
                   class="hikmah-social-btn hikmah-social-google">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    <span><?php esc_html_e( 'Continue with Google', 'hikmah-login' ); ?></span>
                </a>
            <?php endif; ?>

            <?php if ( $facebook_enabled ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'hikmah_social', 'facebook', site_url( 'wp-login.php' ) ) ); ?>"
                   class="hikmah-social-btn hikmah-social-facebook">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span><?php esc_html_e( 'Continue with Facebook', 'hikmah-login' ); ?></span>
                </a>
            <?php endif; ?>
        </div>

        <div class="hikmah-divider">
            <span><?php esc_html_e( 'or', 'hikmah-login' ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="hikmah-login-form"
          method="post"
          action=""
          novalidate>

        <?php wp_nonce_field( 'hikmah_login_action', 'hikmah_login_nonce' ); ?>

        <input type="hidden" name="hikmah_action" value="login">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

        <!-- Honeypot Field (Anti-Spam) -->
        <div class="hikmah-field hikmah-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;">
            <label for="<?php echo esc_attr( $form_id ); ?>-website">
                <?php esc_html_e( 'Website', 'hikmah-login' ); ?>
            </label>
            <input type="text"
                   id="<?php echo esc_attr( $form_id ); ?>-website"
                   name="website_url"
                   tabindex="-1"
                   autocomplete="off">
        </div>

        <!-- Username / Email Field -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-username" class="hikmah-label">
                <?php esc_html_e( 'Username or Email', 'hikmah-login' ); ?>
                <span class="hikmah-required">*</span>
            </label>
            <div class="hikmah-input-wrapper">
                <span class="hikmah-input-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <input type="text"
                       id="<?php echo esc_attr( $form_id ); ?>-username"
                       name="username"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Enter your username or email', 'hikmah-login' ); ?>"
                       required
                       autocomplete="username"
                       autofocus>
            </div>
            <span class="hikmah-field-error" data-field="username"></span>
        </div>

        <!-- Password Field -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-password" class="hikmah-label">
                <?php esc_html_e( 'Password', 'hikmah-login' ); ?>
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
                       id="<?php echo esc_attr( $form_id ); ?>-password"
                       name="password"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Enter your password', 'hikmah-login' ); ?>"
                       required
                       autocomplete="current-password">
                <button type="button"
                        class="hikmah-toggle-password"
                        aria-label="<?php esc_attr_e( 'Toggle password visibility', 'hikmah-login' ); ?>"
                        tabindex="-1">
                    <svg class="hikmah-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="hikmah-eye-closed hikmah-hidden" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            <span class="hikmah-field-error" data-field="password"></span>
        </div>

        <!-- Remember Me & Forgot Password Row -->
        <div class="hikmah-field-row">
            <div class="hikmah-field hikmah-checkbox-field">
                <label class="hikmah-checkbox-label">
                    <input type="checkbox"
                           name="remember"
                           value="1"
                           class="hikmah-checkbox"
                           id="<?php echo esc_attr( $form_id ); ?>-remember">
                    <span class="hikmah-checkbox-custom"></span>
                    <span><?php esc_html_e( 'Remember me', 'hikmah-login' ); ?></span>
                </label>
            </div>

            <?php if ( $forgot_enabled ) : ?>
                <div class="hikmah-field hikmah-link-field">
                    <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_forgot_password_url() ); ?>"
                       class="hikmah-forgot-link">
                        <?php esc_html_e( 'Forgot Password?', 'hikmah-login' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- CAPTCHA -->
        <?php if ( $captcha_enabled && ! empty( $site_key ) ) : ?>
            <div class="hikmah-field hikmah-captcha-field">
                <?php if ( 'recaptcha_v2' === $captcha_type ) : ?>
                    <div class="g-recaptcha"
                         data-sitekey="<?php echo esc_attr( $site_key ); ?>"
                         data-callback="hikmahRecaptchaCallback"
                         data-expired-callback="hikmahRecaptchaExpired">
                    </div>
                    <input type="hidden" name="captcha_response" id="<?php echo esc_attr( $form_id ); ?>-captcha">
                <?php elseif ( 'recaptcha_v3' === $captcha_type ) : ?>
                    <input type="hidden" name="captcha_response" id="<?php echo esc_attr( $form_id ); ?>-captcha">
                <?php elseif ( 'turnstile' === $captcha_type ) : ?>
                    <div class="cf-turnstile"
                         data-sitekey="<?php echo esc_attr( $site_key ); ?>"
                         data-callback="hikmahTurnstileCallback">
                    </div>
                    <input type="hidden" name="captcha_response" id="<?php echo esc_attr( $form_id ); ?>-captcha">
                <?php endif; ?>
                <span class="hikmah-field-error" data-field="captcha"></span>
            </div>
        <?php endif; ?>

        <!-- 2FA Container (Hidden by default) -->
        <div class="hikmah-2fa-container hikmah-hidden" id="<?php echo esc_attr( $form_id ); ?>-2fa">
            <div class="hikmah-2fa-header">
                <h3><?php esc_html_e( 'Two-Factor Authentication', 'hikmah-login' ); ?></h3>
                <p><?php esc_html_e( 'Enter the verification code sent to your device.', 'hikmah-login' ); ?></p>
            </div>
            <div class="hikmah-field">
                <label for="<?php echo esc_attr( $form_id ); ?>-2fa-code" class="hikmah-label">
                    <?php esc_html_e( 'Verification Code', 'hikmah-login' ); ?>
                </label>
                <input type="text"
                       id="<?php echo esc_attr( $form_id ); ?>-2fa-code"
                       name="2fa_code"
                       class="hikmah-input hikmah-2fa-input"
                       placeholder="000000"
                       maxlength="6"
                       pattern="[0-9]{6}"
                       autocomplete="one-time-code"
                       inputmode="numeric">
            </div>
            <input type="hidden" name="2fa_user_id" id="<?php echo esc_attr( $form_id ); ?>-2fa-user-id" value="">
        </div>

        <!-- Submit Button -->
        <div class="hikmah-field hikmah-submit-field">
            <button type="submit"
                    class="hikmah-btn hikmah-btn-primary hikmah-btn-full"
                    id="<?php echo esc_attr( $form_id ); ?>-submit">
                <span class="hikmah-btn-text">
                    <?php esc_html_e( 'Sign In', 'hikmah-login' ); ?>
                </span>
                <span class="hikmah-btn-loading hikmah-hidden">
                    <svg class="hikmah-spinner" width="18" height="18" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e( 'Signing in...', 'hikmah-login' ); ?>
                </span>
            </button>
        </div>

        <?php
        /**
         * Fires inside the login form, before closing tag.
         *
         * @since 1.0.0
         * @param string $form_id Form ID.
         */
        do_action( 'hikmah_login_form_bottom', $form_id );
        ?>
    </form>

    <!-- Register Link -->
    <?php if ( $register_enabled ) : ?>
        <div class="hikmah-form-footer">
            <p class="hikmah-register-link">
                <?php esc_html_e( "Don't have an account?", 'hikmah-login' ); ?>
                <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_register_url() ); ?>">
                    <?php esc_html_e( 'Sign Up', 'hikmah-login' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php
    /**
     * Fires after the login form is rendered.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     */
    do_action( 'hikmah_login_after_form', $atts );
    ?>
</div>
