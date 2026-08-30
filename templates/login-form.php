<?php
/**
 * Template: Sleek Front-End Login Form
 *
 * Implements modern multi-factor login support, including social authentication
 * hooks and a 2FA passcode step.
 *
 * @package Hikmah_Login
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Redirect already logged-in users to home/dashboard directly
if (is_user_logged_in()) {
    wp_safe_redirect(hikmah_get_option('login_redirect', home_url()));
    exit;
}

$errors = [];
$success_message = '';

if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $success_message = __('Registration complete! Please log in to your account.', 'hikmah-login');
}
if (isset($_GET['password_reset']) && $_GET['password_reset'] === 'true') {
    $success_message = __('Your password has been successfully updated.', 'hikmah-login');
}
?>

<div class="hikmah-auth-card">
    <div class="hikmah-auth-card__header">
        <?php
        $logo_url = hikmah_get_option('login_logo', '');
        if (!empty($logo_url)) {
            printf('<img class="hikmah-auth-card__logo" src="%s" alt="%s" />', esc_url($logo_url), esc_attr(get_bloginfo('name')));
        } else {
            printf('<h2 class="hikmah-auth-card__title">%s</h2>', esc_html__('Welcome Back', 'hikmah-login'));
        }
        ?>
        <p class="hikmah-auth-card__subtitle"><?php esc_html_e('Log in to securely manage your portal', 'hikmah-login'); ?></p>
    </div>

    <div class="hikmah-auth-card__body">

        <?php if (!empty($success_message)) : ?>
            <div class="hikmah-auth-alert hikmah-auth-alert--success">
                <span class="dashicons dashicons-yes"></span>
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Outer authentication form container -->
        <form id="hikmah-login-form" class="hikmah-auth-form" method="post" action="<?php echo esc_url(wp_login_url()); ?>">
            
            <?php wp_nonce_field('hikmah_login_action', 'hikmah_login_nonce'); ?>

            <!-- Username or Email Field -->
            <div class="hikmah-form-group">
                <label for="hikmah-user-login" class="hikmah-form-label"><?php esc_html_e('Username or Email', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-admin-users"></span>
                    <input type="text" 
                           name="log" 
                           id="hikmah-user-login" 
                           class="hikmah-form-input" 
                           placeholder="<?php esc_attr_e('Enter your username or email', 'hikmah-login'); ?>" 
                           required 
                           autocomplete="username" />
                </div>
            </div>

            <!-- Password Field -->
            <div class="hikmah-form-group">
                <div class="hikmah-form-label-row">
                    <label for="hikmah-user-pass" class="hikmah-form-label"><?php esc_html_e('Password', 'hikmah-login'); ?></label>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="hikmah-form-link"><?php esc_html_e('Forgot Password?', 'hikmah-login'); ?></a>
                </div>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-lock"></span>
                    <input type="password" 
                           name="pwd" 
                           id="hikmah-user-pass" 
                           class="hikmah-form-input" 
                           placeholder="<?php esc_attr_e('Enter your password', 'hikmah-login'); ?>" 
                           required 
                           autocomplete="current-password" />
                    <button type="button" class="hikmah-password-toggle" data-target="hikmah-user-pass" aria-label="<?php esc_attr_e('Show password', 'hikmah-login'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
            </div>

            <!-- Double verification challenge area (Hidden initially, triggered via AJAX on 2FA enabled users) -->
            <div class="hikmah-form-group hikmah-form-group--2fa" style="display:none;">
                <label for="hikmah-2fa-token" class="hikmah-form-label"><?php esc_html_e('Two-Factor Passcode', 'hikmah-login'); ?></label>
                <div class="hikmah-form-input-wrapper">
                    <span class="hikmah-form-icon dashicons dashicons-shield"></span>
                    <input type="text" 
                           name="hikmah_2fa_token" 
                           id="hikmah-2fa-token" 
                           class="hikmah-form-input" 
                           placeholder="123456" 
                           maxlength="8" 
                           pattern="[0-9]*" 
                           inputmode="numeric" 
                           autocomplete="one-time-code" />
                </div>
                <p class="hikmah-form-description"><?php esc_html_e('Open your authenticator app to copy your temporary 6-digit passcode.', 'hikmah-login'); ?></p>
            </div>

            <!-- Remember Me Control -->
            <div class="hikmah-form-group">
                <label class="hikmah-form-checkbox">
                    <input type="checkbox" name="rememberme" value="forever" checked="checked" />
                    <span class="hikmah-form-checkbox__label"><?php esc_html_e('Remember me for two weeks', 'hikmah-login'); ?></span>
                </label>
            </div>

            <!-- Submit Button Trigger -->
            <button type="submit" class="hikmah-btn hikmah-btn--primary hikmah-btn--block" id="hikmah-login-submit">
                <span class="hikmah-btn__text"><?php esc_html_e('Sign In', 'hikmah-login'); ?></span>
                <span class="hikmah-btn__loading" style="display:none;">
                    <span class="hikmah-spinner"></span>
                    <?php esc_html_e('Authenticating...', 'hikmah-login'); ?>
                </span>
            </button>

        </form>

        <!-- Social Login Integrations -->
        <?php if (has_action('hikmah_social_login_buttons')) : ?>
            <div class="hikmah-auth-divider">
                <span><?php esc_html_e('Or connect with', 'hikmah-login'); ?></span>
            </div>
            <div class="hikmah-social-login-grid">
                <?php do_action('hikmah_social_login_buttons'); ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="hikmah-auth-card__footer">
        <p><?php esc_html_e("Don't have an account?", 'hikmah-login'); ?> <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Register here', 'hikmah-login'); ?></a></p>
    </div>
</div>
