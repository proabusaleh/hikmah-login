<?php
/**
 * Registration Form Template
 *
 * Override by copying to: yourtheme/hikmah-login/register-form.php
 *
 * @package Hikmah_Login
 * @since   1.0.0
 *
 * Available variables:
 * @var array  $atts        Shortcode attributes.
 * @var string $redirect_to Redirect URL after registration.
 * @var bool   $show_title  Whether to show form title.
 * @var string $form_id     Unique form ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if registration is enabled
if ( 'yes' !== get_option( 'hikmah_registration_enabled', 'yes' ) ) {
    ?>
    <div class="hikmah-login-wrapper">
        <div class="hikmah-notice hikmah-notice-warning">
            <span class="hikmah-notice-icon">⚠️</span>
            <p><?php esc_html_e( 'Registration is currently disabled. Please contact the site administrator.', 'hikmah-login' ); ?></p>
        </div>
    </div>
    <?php
    return;
}

// Already logged in?
if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    ?>
    <div class="hikmah-logged-in-notice">
        <div class="hikmah-notice hikmah-notice-info">
            <p>
                <?php
                printf(
                    esc_html__( 'You are already registered and logged in as %1$s. Go to your %2$s.', 'hikmah-login' ),
                    '<strong>' . esc_html( $user->display_name ) . '</strong>',
                    '<a href="' . esc_url( \Hikmah_Login\Helpers\Helper::get_dashboard_url() ) . '">' . esc_html__( 'Dashboard', 'hikmah-login' ) . '</a>'
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
$form_id     = isset( $form_id ) ? $form_id : 'hikmah-register-form-' . wp_rand( 1000, 9999 );

// Settings
$logo_url         = get_option( 'hikmah_login_logo', '' );
$google_enabled   = 'yes' === get_option( 'hikmah_google_login_enabled', 'no' );
$facebook_enabled = 'yes' === get_option( 'hikmah_facebook_login_enabled', 'no' );
$captcha_enabled  = 'yes' === get_option( 'hikmah_captcha_enabled', 'no' );
$captcha_type     = get_option( 'hikmah_captcha_type', 'recaptcha_v2' );
$site_key         = get_option( 'hikmah_recaptcha_site_key', '' );
$terms_required   = 'yes' === get_option( 'hikmah_terms_required', 'no' );
$terms_page       = get_option( 'hikmah_terms_page_url', '#' );
$privacy_page     = get_option( 'hikmah_privacy_page_url', '#' );
$email_verify     = 'yes' === get_option( 'hikmah_email_verification_required', 'yes' );

// Password strength settings
$pass_min_length   = 8;
$pass_require_upper = true;
$pass_require_lower = true;
$pass_require_number = true;
$pass_require_special = false;

// Redirect
if ( empty( $redirect_to ) && isset( $_GET['redirect_to'] ) ) {
    $redirect_to = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
}

// URL messages
$reg_message = '';
$reg_error   = '';

if ( isset( $_GET['registered'] ) && 'true' === $_GET['registered'] ) {
    if ( $email_verify ) {
        $reg_message = __( 'Registration successful! Please check your email to verify your account.', 'hikmah-login' );
    } else {
        $reg_message = __( 'Registration successful! You can now log in.', 'hikmah-login' );
    }
}

/**
 * Fires before the registration form.
 *
 * @since 1.0.0
 * @param array $atts Shortcode attributes.
 */
do_action( 'hikmah_register_before_form', $atts );
?>

<div class="hikmah-login-wrapper hikmah-register-wrapper" id="<?php echo esc_attr( $form_id ); ?>-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="hikmah-form-header">
            <?php if ( ! empty( $logo_url ) ) : ?>
                <div class="hikmah-form-logo">
                    <img src="<?php echo esc_url( $logo_url ); ?>"
                         alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                </div>
            <?php endif; ?>
            <h2 class="hikmah-form-title">
                <?php echo esc_html( $atts['title'] ?? __( 'Create Account', 'hikmah-login' ) ); ?>
            </h2>
            <?php if ( ! empty( $atts['subtitle'] ) ) : ?>
                <p class="hikmah-form-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
            <?php else : ?>
                <p class="hikmah-form-subtitle">
                    <?php esc_html_e( 'Fill in the details below to create your account.', 'hikmah-login' ); ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Success Message -->
    <?php if ( ! empty( $reg_message ) ) : ?>
        <div class="hikmah-notice hikmah-notice-success hikmah-dismissible">
            <span class="hikmah-notice-icon">✅</span>
            <p><?php echo esc_html( $reg_message ); ?></p>
            <button type="button" class="hikmah-notice-close">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Error Container (AJAX) -->
    <div class="hikmah-notice hikmah-notice-error hikmah-dismissible hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-error">
        <span class="hikmah-notice-icon">⚠️</span>
        <p class="hikmah-error-message"></p>
        <button type="button" class="hikmah-notice-close">&times;</button>
    </div>

    <!-- Multiple Errors Container -->
    <div class="hikmah-notice hikmah-notice-error hikmah-hidden"
         id="<?php echo esc_attr( $form_id ); ?>-errors-list">
        <ul class="hikmah-error-list"></ul>
    </div>

    <!-- Social Registration -->
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
                    <span><?php esc_html_e( 'Sign up with Google', 'hikmah-login' ); ?></span>
                </a>
            <?php endif; ?>
            <?php if ( $facebook_enabled ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'hikmah_social', 'facebook', site_url( 'wp-login.php' ) ) ); ?>"
                   class="hikmah-social-btn hikmah-social-facebook">
                    <svg width="18" height="18" viewBox="0 0 24 24">
                        <path fill="#1877F2" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span><?php esc_html_e( 'Sign up with Facebook', 'hikmah-login' ); ?></span>
                </a>
            <?php endif; ?>
        </div>
        <div class="hikmah-divider">
            <span><?php esc_html_e( 'or register with email', 'hikmah-login' ); ?></span>
        </div>
    <?php endif; ?>

    <!-- Registration Form -->
    <form id="<?php echo esc_attr( $form_id ); ?>"
          class="hikmah-login-form hikmah-register-form"
          method="post"
          action=""
          novalidate>

        <?php wp_nonce_field( 'hikmah_register_action', 'hikmah_register_nonce' ); ?>
        <input type="hidden" name="hikmah_action" value="register">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">

        <!-- Honeypot -->
        <div class="hikmah-field hikmah-hp-field" aria-hidden="true"
             style="position:absolute;left:-9999px;">
            <label for="<?php echo esc_attr( $form_id ); ?>-website">Website</label>
            <input type="text" id="<?php echo esc_attr( $form_id ); ?>-website"
                   name="website_url" tabindex="-1" autocomplete="off">
        </div>

        <!-- Name Row (First + Last) -->
        <div class="hikmah-field-row hikmah-name-row">
            <div class="hikmah-field hikmah-half">
                <label for="<?php echo esc_attr( $form_id ); ?>-first-name" class="hikmah-label">
                    <?php esc_html_e( 'First Name', 'hikmah-login' ); ?>
                </label>
                <input type="text"
                       id="<?php echo esc_attr( $form_id ); ?>-first-name"
                       name="first_name"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'John', 'hikmah-login' ); ?>"
                       autocomplete="given-name">
                <span class="hikmah-field-error" data-field="first_name"></span>
            </div>
            <div class="hikmah-field hikmah-half">
                <label for="<?php echo esc_attr( $form_id ); ?>-last-name" class="hikmah-label">
                    <?php esc_html_e( 'Last Name', 'hikmah-login' ); ?>
                </label>
                <input type="text"
                       id="<?php echo esc_attr( $form_id ); ?>-last-name"
                       name="last_name"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'Doe', 'hikmah-login' ); ?>"
                       autocomplete="family-name">
                <span class="hikmah-field-error" data-field="last_name"></span>
            </div>
        </div>

        <!-- Username -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-username" class="hikmah-label">
                <?php esc_html_e( 'Username', 'hikmah-login' ); ?>
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
                       placeholder="<?php esc_attr_e( 'Choose a username', 'hikmah-login' ); ?>"
                       required
                       minlength="3"
                       maxlength="60"
                       autocomplete="username"
                       data-validate="username">
            </div>
            <span class="hikmah-field-error" data-field="username"></span>
            <span class="hikmah-field-hint">
                <?php esc_html_e( '3-60 characters. Letters, numbers, underscores, hyphens.', 'hikmah-login' ); ?>
            </span>
        </div>

        <!-- Email -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-email" class="hikmah-label">
                <?php esc_html_e( 'Email Address', 'hikmah-login' ); ?>
                <span class="hikmah-required">*</span>
            </label>
            <div class="hikmah-input-wrapper">
                <span class="hikmah-input-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
                <input type="email"
                       id="<?php echo esc_attr( $form_id ); ?>-email"
                       name="email"
                       class="hikmah-input"
                       placeholder="<?php esc_attr_e( 'you@example.com', 'hikmah-login' ); ?>"
                       required
                       autocomplete="email"
                       data-validate="email">
            </div>
            <span class="hikmah-field-error" data-field="email"></span>
            <?php if ( $email_verify ) : ?>
                <span class="hikmah-field-hint">
                    <?php esc_html_e( 'A verification link will be sent to this email.', 'hikmah-login' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Password -->
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
                       placeholder="<?php esc_attr_e( 'Create a strong password', 'hikmah-login' ); ?>"
                       required
                       minlength="<?php echo esc_attr( $pass_min_length ); ?>"
                       autocomplete="new-password"
                       data-validate="password">
                <button type="button" class="hikmah-toggle-password" tabindex="-1"
                        aria-label="<?php esc_attr_e( 'Toggle password', 'hikmah-login' ); ?>">
                    <svg class="hikmah-eye-open" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="hikmah-eye-closed hikmah-hidden" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
            <span class="hikmah-field-error" data-field="password"></span>

            <!-- Password Strength Meter -->
            <div class="hikmah-password-strength" id="<?php echo esc_attr( $form_id ); ?>-strength">
                <div class="hikmah-strength-bar">
                    <div class="hikmah-strength-fill"></div>
                </div>
                <span class="hikmah-strength-text"></span>
            </div>

            <!-- Password Requirements -->
            <ul class="hikmah-password-requirements" id="<?php echo esc_attr( $form_id ); ?>-requirements">
                <li data-rule="length" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php printf( esc_html__( 'At least %d characters', 'hikmah-login' ), $pass_min_length ); ?>
                </li>
                <?php if ( $pass_require_upper ) : ?>
                <li data-rule="upper" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One uppercase letter', 'hikmah-login' ); ?>
                </li>
                <?php endif; ?>
                <?php if ( $pass_require_lower ) : ?>
                <li data-rule="lower" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One lowercase letter', 'hikmah-login' ); ?>
                </li>
                <?php endif; ?>
                <?php if ( $pass_require_number ) : ?>
                <li data-rule="number" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One number', 'hikmah-login' ); ?>
                </li>
                <?php endif; ?>
                <?php if ( $pass_require_special ) : ?>
                <li data-rule="special" class="hikmah-req-pending">
                    <span class="hikmah-req-icon">○</span>
                    <?php esc_html_e( 'One special character', 'hikmah-login' ); ?>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Confirm Password -->
        <div class="hikmah-field">
            <label for="<?php echo esc_attr( $form_id ); ?>-confirm-password" class="hikmah-label">
                <?php esc_html_e( 'Confirm Password', 'hikmah-login' ); ?>
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
                       placeholder="<?php esc_attr_e( 'Confirm your password', 'hikmah-login' ); ?>"
                       required
                       autocomplete="new-password"
                       data-validate="confirm_password">
            </div>
            <span class="hikmah-field-error" data-field="confirm_password"></span>
        </div>

        <?php
        /**
         * Fires after default registration fields.
         * Use this to add custom fields.
         *
         * @since 1.0.0
         * @param string $form_id Form ID.
         */
        do_action( 'hikmah_register_custom_fields', $form_id );
        ?>

        <!-- Terms & Conditions -->
        <?php if ( $terms_required ) : ?>
            <div class="hikmah-field hikmah-checkbox-field">
                <label class="hikmah-checkbox-label">
                    <input type="checkbox" name="terms" value="1"
                           class="hikmah-checkbox" required
                           id="<?php echo esc_attr( $form_id ); ?>-terms">
                    <span class="hikmah-checkbox-custom"></span>
                    <span>
                        <?php
                        printf(
                            /* translators: 1: Terms URL 2: Privacy URL */
                            wp_kses_post( __( 'I agree to the <a href="%1$s" target="_blank">Terms of Service</a> and <a href="%2$s" target="_blank">Privacy Policy</a>', 'hikmah-login' ) ),
                            esc_url( $terms_page ),
                            esc_url( $privacy_page )
                        );
                        ?>
                        <span class="hikmah-required">*</span>
                    </span>
                </label>
                <span class="hikmah-field-error" data-field="terms"></span>
            </div>
        <?php endif; ?>

        <!-- CAPTCHA -->
        <?php if ( $captcha_enabled && ! empty( $site_key ) ) : ?>
            <div class="hikmah-field hikmah-captcha-field">
                <?php if ( 'recaptcha_v2' === $captcha_type ) : ?>
                    <div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
                <?php endif; ?>
                <input type="hidden" name="captcha_response" id="<?php echo esc_attr( $form_id ); ?>-captcha">
                <span class="hikmah-field-error" data-field="captcha"></span>
            </div>
        <?php endif; ?>

        <!-- Submit -->
        <div class="hikmah-field hikmah-submit-field">
            <button type="submit"
                    class="hikmah-btn hikmah-btn-primary hikmah-btn-full"
                    id="<?php echo esc_attr( $form_id ); ?>-submit">
                <span class="hikmah-btn-text">
                    <?php esc_html_e( 'Create Account', 'hikmah-login' ); ?>
                </span>
                <span class="hikmah-btn-loading hikmah-hidden">
                    <svg class="hikmah-spinner" width="18" height="18" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                    </svg>
                    <?php esc_html_e( 'Creating account...', 'hikmah-login' ); ?>
                </span>
            </button>
        </div>

        <?php do_action( 'hikmah_register_form_bottom', $form_id ); ?>
    </form>

    <!-- Login Link -->
    <div class="hikmah-form-footer">
        <p class="hikmah-register-link">
            <?php esc_html_e( 'Already have an account?', 'hikmah-login' ); ?>
            <a href="<?php echo esc_url( \Hikmah_Login\Helpers\Helper::get_login_url() ); ?>">
                <?php esc_html_e( 'Sign In', 'hikmah-login' ); ?>
            </a>
        </p>
    </div>

    <?php do_action( 'hikmah_register_after_form', $atts ); ?>
</div>
